<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

class MergedPatientService {
	public $lastMessage;

	public function findBrokenPatients() {
		$patients = Yii::app()->db->createCommand()
		->select('patient.id, external_id, patient.pas_key, patient.hos_num, contact.first_name, contact.last_name')
		->from('pas_assignment')
		->join("patient","pas_assignment.internal_id = patient.id")
		->join("contact","patient.contact_id = contact.id")
		->where("pas_assignment.internal_type = 'Patient'")
		->queryAll();

		$results = array();

		foreach ($patients as $patient) {
			$rm_patient_no = $patient['external_id'];
			$pas_patient = PAS_Patient::model()->findAll('rm_patient_no = :rm_patient_no', array(
				':rm_patient_no' => $rm_patient_no,
			));

			if (count($pas_patient) == 0) {
				$map = $this->inferMergedPatient($patient['pas_key'],$patient['first_name'],$patient['last_name'],$patient['hos_num']);

				if ($map['hos_num'] != $patient['hos_num']) {
					$results[] = array(
						'id' => $patient['id'],
						'hos_num' => $patient['hos_num'],
						'type' => 'merged',
						'new_hos_num' => $map['hos_num'],
						'new_rm_patient_no' => $map['rm_patient_no'],
						'new_first_name' => $map['first_name'],
						'new_last_name' => $map['last_name'],
						'match' => $map['match'],
					);
				} else {
					$results[] = array(
						'id' => $patient['id'],
						'hos_num' => $patient['hos_num'],
						'type' => 'dupe',
						'new_rm_patient_no' => $map['rm_patient_no'],
					);
				}
			}
		}

		return $results;
	}

	public function inferMergedPatient($pas_key, $first_name, $last_name, $hos_num) {
		foreach (array($hos_num,'1'.$hos_num,'0'.$hos_num,'00'.$hos_num) as $value) {
			if ($cn = PAS_CaseNote::model()->find('C_CN=?',array($value))) {
				if ($p = PAS_Patient::model()->find('RM_PATIENT_NO=?',array($cn['X_CN']))) {
					if ($pn = PAS_PatientNumber::model()->find('RM_PATIENT_NO=?',array($cn['X_CN']))) {
						if ($name = $p->name) {
							$result = array(
								'hos_num' => $pn['NUM_ID_TYPE'].$pn['NUMBER_ID'],
								'rm_patient_no' => $cn['X_CN'],
								'first_name' => trim($name->NAME1),
								'last_name' => trim($name->SURNAME_ID),
							);

							$result['match'] = (strtolower(trim($first_name)) == strtolower(trim($name->NAME1)) &&
								strtolower(trim($last_name)) == strtolower(trim($name->SURNAME_ID)));

							return $result;
						}
					}

					return false;
				}
			}
		}

		return false;
	}

	public function log($message) {
		if ($this->lastMessage) {
			$this->lastMessage .= ', ';
		}
		$this->lastMessage .= $message;
	}

	public function resolveDupe($patient) {
		$this->lastMessage = '';

		$patients = array();
		$theone = false;
		foreach (Patient::model()->findAll('hos_num=?',array($patient['hos_num'])) as $_patient) {
			$patients[] = $_patient;
			if ($pa = PasAssignment::model()->find('internal_type=? and internal_id=?',array('Patient',$_patient->id))) {
				if ($pa->external_id = $patient['new_rm_patient_no']) {
					$theone = $_patient;
				}
			}
		}

		if (count($patients) >1) {
			if (!$theone) {
				return false;
			} else {
				foreach ($patients as $patient) {
					if ($patient->id != $theone->id) {
						if (Episode::model()->find('patient_id=?',array($patient->id))) {
							$this->migrateEpisodes($patient,$theone);
							$this->log('migrated episodes');
						}
						$this->migratePatientAssignments($patient,$theone);
						$this->deletePatient($patient);
						$this->log('old patient deleted');
					}
				}

				return true;
			}
		}

		$this->log('no dupes found');

		return false;
	}

	public function resolveMerged($patient) {
		$this->lastMessage = '';

		$patient = Patient::model()->find('hos_num=?',array($patient['hos_num']));

		if ($new_patient = Patient::model()->find('hos_num=?',array($patient['new_hos_num']))) {
			if ($new_patient->id != $patient->id) {
				if (Episode::model()->find('patient_id=?',array($patient->id))) {
					$this->migrateEpisodes($patient,$new_patient);
					$this->log('migrated episodes');
				}
				$this->migratePatientAssignments($patient,$new_patient);
				$this->deletePatient($patient);
				$this->log('old patient deleted');
			} else {
				$this->log('same patient');
			}
		} else {
			Yii::app()->db->createCommand("update patient set hos_num = '{$patient['new_hos_num']}', pas_key = '{$patient['new_hos_num']}' where hos_num = '$patient->hos_num'")->query();
			Yii::app()->db->createCommand("update pas_assignment set external_id = '{$patient['new_rm_patient_no']}' where internal_type = 'Patient' and internal_id = $patient->id")->query();
			$this->log('migrated patient');
		}

		return true;
	}

	public function markMerged($patient) {
		if (!$ppm = PAS_Patient_Merged::model()->find('patient_id=? and new_hos_num=? and new_rm_patient_no=?',array($patient['id'],$patient['new_hos_num'],$patient['new_rm_patient_no']))) {
			$ppm = new PAS_Patient_Merged;
			$ppm->patient_id = $patient['id'];
			$ppm->new_hos_num = $patient['new_hos_num'];
			$ppm->new_rm_patient_no = $patient['new_rm_patient_no'];
		}

		$ppm->new_first_name = $patient['new_first_name'];
		$ppm->new_last_name = $patient['new_last_name'];

		if (!$ppm->save()) {
			throw new Exception("Unable to save PAS_Patient_Merged: ".print_r($ppm->getErrors(),true));
		}

		return true;
	}

	public function deletePatient($patient) {
		Yii::app()->db->createCommand("delete from audit where patient_id = $patient->id")->query();
		Yii::app()->db->createCommand("delete from patient where id = $patient->id")->query();
		Yii::app()->db->createCommand("delete from pas_assignment where internal_type = 'Patient' and internal_id = $patient->id")->query();
	}

	public function migrateEpisodes($old_patient, $new_patient) {
		foreach (Episode::model()->findAll('patient_id=?',array($old_patient->id)) as $episode) {
			if ($new_episode = $this->findMatchingEpisode($episode, $new_patient)) {
				$this->migrateEvents($episode, $new_episode);
				Yii::app()->db->createCommand("delete from audit where episode_id = $episode->id")->query();
				Yii::app()->db->createCommand("delete from episode where id = $episode->id")->query();
			} else {
				$this->reassignEpisode($episode, $new_patient);
			}
		}
	}

	public function migrateEvents($episode, $new_episode) {
		Yii::app()->db->createCommand("update event set episode_id = $new_episode->id where episode_id = $episode->id")->query();
		Yii::app()->db->createCommand("update audit set episode_id = $new_episode->id where episode_id = $episode->id")->query();
	}

	public function reassignEpisode($episode, $new_patient) {
		Yii::app()->db->createCommand("update episode set patient_id = $new_patient->id where id = $episode->id")->query();
		Yii::app()->db->createCommand("update audit set patient_id = $new_patient->id where episode_id = $episode->id")->query();
	}

	public function findMatchingEpisode($episode, $patient) {
		if ($episode->legacy) {
			return Episode::model()->find('patient_id=? and legacy=?',array($patient->id,1));
		}
		return $this->findEpisodeWithSSA($patient, $episode->firm->serviceSubspecialtyAssignment);
	}

	public function findEpisodeWithSSA($patient, $ssa) {
		$firm_ids = array();

		foreach (Firm::model()->findAll('service_subspecialty_assignment_id=?',array($ssa->id)) as $firm) {
			$firm_ids[] = $firm->id;
		}

		return Episode::model()->find('patient_id=? and firm_id in ('.implode(',',$firm_ids).')',array($patient->id));
	}

	public function migratePatientAssignments($patient,$new_patient) {
		foreach ($patient->contactAssignments as $ca) {
			if (!PatientContactAssignment::model()->find('patient_id=? and location_id=?',array($new_patient->id,$ca->location_id))) {
				Yii::app()->db->createCommand("insert into patient_contact_assignment (patient_id,location_id,last_modified_user_id,last_modified_date,created_user_id,created_date) values ($new_patient->id,$ca->location_id,$ca->last_modified_user_id,'$ca->last_modified_date',$ca->created_user_id,'$ca->created_date')")->query();
			}

			Yii::app()->db->createCommand("delete from patient_contact_assignment where id = $ca->id")->query();
		}

		foreach (PatientAllergyAssignment::model()->findAll('patient_id=?',array($patient->id)) as $paa) {
			if (!PatientAllergyAssignment::model()->find('patient_id=? and allergy_id=?',array($new_patient->id,$paa->allergy_id))) {
				Yii::app()->db->createCommand("insert into patient_allergy_assignment (patient_id,allergy_id,last_modified_user_id,last_modified_date,created_user_id,created_date) values ($new_patient->id,$paa->allergy_id,$paa->last_modified_user_id,'$paa->last_modified_date',$paa->created_user_id,'$paa->created_date')")->query();
			}
			Yii::app()->db->createCommand("delete from patient_allergy_assignment where id = $paa->id")->query();
		}

		foreach ($patient->previousOperations as $po) {
			if (!PreviousOperation::model()->find('patient_id=? and side_id=? and operation=? and date=?',array($new_patient->id,$po->side_id,$po->operation,$po->date))) {
				Yii::app()->db->createCommand("insert into previous_operation (patient_id,side_id,operation,date,last_modified_user_id,last_modified_date,created_user_id,created_date) values ($new_patient->id,$po->side_id,'".mysql_escape_string($po->operation)."','$po->date',$po->last_modified_user_id,'$po->last_modified_date',$po->created_user_id,'$po->created_date'")->query();
			}
			Yii::app()->db->createCommand("delete from previous_operation where id = $po->id")->query();
		}

		foreach ($patient->familyHistory as $fh) {
			if (!FamilyHistory::model()->find('patient_id=? and relative_id=? and side_id=? and condition_id=? and comments=?',array($new_patient->id,$fh->relative_id,$fh->side_id,$fh->condition_id,$fh->comments))) {
				Yii::app()->db->createCommand("insert into family_history (patient_id,relative_id,side_id,condition_id,comments,last_modified_user_id,last_modified_date,created_user_id,created_date) values ($new_patient->id,$fh->relative_id,$fh->side_id,$fh->condition_id,'".mysql_escape_string($fh->comments)."',$fh->last_modified_user_id,'$fh->last_modified_date',$fh->created_user_id,'$fh->created_date')")->query();
			}
			Yii::app()->db->createCommand("delete from family_history where id = $fh->id")->query();
		}

		foreach ($patient->medications as $m) {
			if (!Medication::model()->find('patient_id=? and drug_id=? and route_id=? and option_id=? and frequency_id=? and start_date=? and end_date=?',array($new_patient->id,$m->drug_id,$m->route_id,$m->route_id,$m->option_id,$m->frequency_id,$m->start_date,$m->end_date))) {
				Yii::app()->db->createCommand("insert into medication (patient_id,drug_id,route_id,option_id,frequency_id,start_date,end_date,last_modified_user_id,last_modified_date,created_user_id,created_date) values ($new_patient->id,$m->drug_id,$m->route_id,$m->option_id,$m->frequency_id,'$m->start_date','$m->end_date',$m->last_modified_user_id,'$m->last_modified_date',$m->created_user_id,'$m->created_date'")->query();
			}
			Yii::app()->db->createCommand("delete from medication where id = $m->id")->query();
		}
	}
}
