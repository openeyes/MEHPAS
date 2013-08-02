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

class MergedPatientService
{
	public $lastMessage;

	public function findBrokenPatients()
	{
		$external_ids = array();
		$patients = array();
		$batch = array();

		foreach (Yii::app()->db->createCommand()
			->select('patient.id, external_id, patient.pas_key, patient.hos_num, contact.first_name, contact.last_name')
			->from('pas_assignment')
			->join("patient","pas_assignment.internal_id = patient.id")
			->join("contact","patient.contact_id = contact.id")
			->where("pas_assignment.internal_type = 'Patient'")
			->queryAll() as $row) {

			$patients[] = $row;
			$external_ids[] = $row['external_id'];

			if (count($external_ids) >= 1000) {
				$batch[] = $external_ids;
				$external_ids = array();
			}
		}

		if (!empty($external_ids)) {
			$batch[] = $external_ids;
		}

		$external_id_count = array();

		foreach ($batch as $external_ids) {
			foreach (Yii::app()->db_pas->createCommand()
				->select("RM_PATIENT_NO")
				->from("SILVER.PATIENTS")
				->where("RM_PATIENT_NO in (".implode(',',$external_ids).")")
				->queryAll() as $row) {

				if (!isset($external_id_count[$row['RM_PATIENT_NO']])) {
					$external_id_count[$row['RM_PATIENT_NO']] = 1;
				} else {
					$external_id_count[$row['RM_PATIENT_NO']]++;
				}
			}
		}

		$results = array();

		foreach ($patients as $patient) {
			$count = isset($external_id_count[$patient['external_id']]) ? $external_id_count[$patient['external_id']] : 0;

			if ($count == 0) {
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

	public function inferMergedPatient($pas_key, $first_name, $last_name, $hos_num)
	{
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

	public function log($message)
	{
		if ($this->lastMessage) {
			$this->lastMessage .= ', ';
		}
		$this->lastMessage .= $message;
	}

	public function resolveDupe($patient)
	{
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
						if (Episode::model()->disableDefaultScope()->find('patient_id=?',array($patient->id))) {
							$this->migrateEpisodes($patient,$theone);
							$this->log('migrated episodes');
						}
						$this->migratePatientAssignments($patient,$theone);
						if (!$patient->delete()) {
							throw new Exception("Unable to delete patient: ".print_r($patient->getErrors(),true));
						}
						$this->log('old patient deleted');
					}
				}

				return true;
			}
		}

		$this->log('no dupes found');

		return false;
	}

	public function resolveMerged($patient)
	{
		$this->lastMessage = '';

		if (!$_patient = Patient::model()->find('hos_num=?',array($patient['hos_num']))) {
			throw new Exception("Patient not found with hos_num={$patient['hos_num']}");
		}

		if ($new_patient = Patient::model()->find('hos_num=?',array($patient['new_hos_num']))) {
			if ($new_patient->id != $_patient->id) {
				if (Episode::model()->disableDefaultScope()->find('patient_id=?',array($_patient->id))) {
					$this->migrateEpisodes($_patient,$new_patient);
					$this->log('migrated episodes');
				}
				$this->migratePatientAssignments($_patient,$new_patient);

				if ($ppm = PAS_Patient_Merged::model()->find('patient_id=?',array($_patient->id))) {
					if (!$ppm->delete()) {
						throw new Exception("Unable to remove pas_patient_merged: ".print_r($ppm->getErrors(),true));
					}
				}

				if (!$_patient->delete()) {
					throw new Exception("Unable to delete patient: ".print_r($_patient->getErrors(),true));
				}
				$this->log('old patient deleted');
			} else {
				$this->log('same patient');
			}
		} else {
			$_patient->hos_num = $patient['new_hos_num'];
			$_patient->pas_key = $patient['new_hos_num'];

			if (!$_patient->save(true,null,true)) {
				throw new Exception("Unable to update patient: ".print_r($_patient->getErrors(),true));
			}

			if (!$pa = PasAssignment::model()->find('internal_type=? and internal_id=?',array('Patient',$_patient->id))) {
				throw new Exception("pas_assignment not found for internal_type=Patient internal_id=$_patient->id");
			}

			$pa->external_id = $patient['new_rm_patient_no'];
			if (!$pa->save(true,null,true)) {
				throw new Exception("Unable to save pas_assignment: ".print_r($pa->getErrors(),true));
			}

			if ($ppm = PAS_Patient_Merged::model()->find('patient_id=?',array($_patient->id))) {
				if (!$ppm->delete()) {
					throw new Exception("Unable to remove pas_patient_merged: ".print_r($ppm->getErrors(),true));
				}
			}

			$this->log('migrated patient');
		}

		return true;
	}

	public function markMerged($patient)
	{
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

	public function migrateEpisodes($old_patient, $new_patient)
	{
		foreach (Episode::model()->disableDefaultScope()->findAll('patient_id=?',array($old_patient->id)) as $episode) {
			if ($new_episode = $this->findMatchingEpisode($episode, $new_patient)) {
				foreach (Event::model()->disableDefaultScope()->findAll('episode_id=?',array($episode->id)) as $event) {
					$event->episode_id = $new_episode->id;
					if (!$event->save(true,null,true)) {
						throw new Exception("Unable to save event: ".print_r($event->getErrors(),true));
					}
				}

				foreach (Audit::model()->findAll('episode_id=?',array($episode->id)) as $audit) {
					$audit->episode_id = $new_episode->id;

					if (!$audit->save(true,null,true)) {
						throw new Exception("Unable to save audit: ".print_r($audit->getErrors(),true));
					}
				}

				if (!$episode->delete()) {
					throw new Exception("Unable to delete episode: ".print_r($episode->getErrors(),true));
				}
			} else {
				$episode->patient_id = $new_patient->id;

				if (!$episode->save(true,null,true)) {
					throw new Exception("Unable to save episode: ".print_r($episode->getErrors(),true));
				}

				foreach (Audit::model()->findAll('episode_id=?',array($episode->id)) as $audit) {
					$audit->patient_id = $new_patient->id;

					if (!$audit->save(true,null,true)) {
						throw new Exception("Unable to save audit: ".print_r($audit->getErrors(),true));
					}
				}
			}
		}
	}

	public function findMatchingEpisode($episode, $patient)
	{
		if ($episode->legacy) {
			return Episode::model()->disableDefaultScope()->find('patient_id=? and legacy=?',array($patient->id,1));
		}
		return $this->findEpisodeWithSSA($patient, $episode->firm->serviceSubspecialtyAssignment);
	}

	public function findEpisodeWithSSA($patient, $ssa)
	{
		$firm_ids = array();

		foreach (Firm::model()->findAll('service_subspecialty_assignment_id=?',array($ssa->id)) as $firm) {
			$firm_ids[] = $firm->id;
		}

		return Episode::model()->disableDefaultScope()->find('patient_id=? and firm_id in ('.implode(',',$firm_ids).')',array($patient->id));
	}

	public function migratePatientAssignments($patient,$new_patient)
	{
		foreach ($patient->contactAssignments as $ca) {
			if (!PatientContactAssignment::model()->find('patient_id=? and location_id=?',array($new_patient->id,$ca->location_id))) {
				$_ca = BaseActiveRecord::cloneObject($ca,array('patient_id'=>$new_patient->id));

				if (!$_ca->save(true,null,true)) {
					throw new Exception("Failed to save patient contact assignment: ".print_r($_ca->getErrors(),true));
				}
			}

			if (!$ca->delete()) {
				throw new Exception("Unable to delete patient contact assignment: ".print_r($ca->getErrors(),true));
			}
		}

		foreach (PatientAllergyAssignment::model()->findAll('patient_id=?',array($patient->id)) as $paa) {
			if (!PatientAllergyAssignment::model()->find('patient_id=? and allergy_id=?',array($new_patient->id,$paa->allergy_id))) {
				$_paa = BaseActiveRecord::cloneObject($paa,array('patient_id'=>$new_patient->id));

				if (!$_paa->save(true,null,true)) {
					throw new Exception("Unable to save patient allergy assignment: ".print_r($_paa->getErrors(),true));
				}
			}

			if (!$paa->delete()) {
				throw new Exception("Unable to delete patient allergy assignment: ".print_r($paa->getErrors(),true));
			}
		}

		foreach ($patient->previousOperations as $po) {
			if (!PreviousOperation::model()->find($this->getCriteria(array(
					'patient_id' => $new_patient->id,
					'site_id' => $po->site_id,
					'operation' => $po->operation,
					'date' => $po->date,
				)))) {
				$_po = BaseActiveRecord::cloneObject($po,array('patient_id'=>$new_patient->id));

				if (!$_po->save(true,null,true)) {
					throw new Exception("Unable to save previous operation: ".print_r($_po->getErrors(),true));
				}
			}

			if (!$po->delete()) {
				throw new Exception("Unable to delete previous operation: ".print_r($po->getErrors(),true));
			}
		}

		foreach ($patient->familyHistory as $fh) {
			if (!FamilyHistory::model()->find($this->getCriteria(array(
					'patient_id' => $new_patient->id,
					'relative_id' => $fh->relative_id,
					'side_id' => $fh->side_id,
					'condition_id' => $fh->condition_id,
					'comments' => $fh->comments,
				)))) {
				$_fh = BaseActiveRecord::cloneObject($fh,array('patient_id'=>$new_patient->id));

				if (!$_fh->save(true,null,true)) {
					throw new Exception("Unable to save family history: ".print_r($_fh->getErrors(),true));
				}
			}

			if (!$fh->delete()) {
				throw new Exception("Unable to delete family history: ".print_r($fh->getErrors(),true));
			}
		}

		foreach ($patient->medications as $m) {
			if (!Medication::model()->find($this->getCriteria(array(
					'patient_id' => $new_patient->id,
					'drug_id' => $m->drug_id,
					'route_id' => $m->route_id,
					'option_id' => $m->option_id,
					'frequency_id' => $m->frequency_id,
					'start_date' => $m->start_date,
					'end_date' => $m->end_date,
				)))) {
				$_m = BaseActiveRecord::cloneObject($m,array('patient_id'=>$new_patient->id));

				if (!$_m->save(true,null,true)) {
					throw new Exception("Unable to save medication: ".print_r($_m->getErrors(),true));
				}
			}

			if (!$m->delete()) {
				throw new Exception("Unable to delete medication: ".print_r($m->getErrors(),true));
			}
		}

		foreach ($patient->secondarydiagnoses as $sd) {
			if (!SecondaryDiagnosis::model()->find($this->getCriteria(array(
					'patient_id' => $new_patient->id,
					'disorder_id' => $sd->disorder_id,
					'eye_id' => $sd->eye_id,
					'date' => $sd->date,
				)))) {
				$_sd = BaseActiveRecord::cloneObject($sd,array('patient_id'=>$new_patient->id));

				if (!$_sd->save(true,null,true)) {
					throw new Exception("Unable to save secondary diagnosis: ".print_r($_sd->getErrors(),true));
				}
			}

			if (!$sd->delete()) {
				throw new Exception("Unable to delete secondary diagnosis: ".print_r($sd->getErrors(),true));
			}
		}

		foreach (CommissioningBodyPatientAssignment::model()->findAll('patient_id=?',array($patient->id)) as $pa) {
			if (!CommissioningBodyPatientAssignment::model()->find('patient_id=? and commissioning_body_id=?',array($new_patient->id,$pa->commissioning_body_id))) {
				$_pa = BaseActiveRecord::cloneObject($pa,array('patient_id'=>$new_patient->id));

				if (!$_pa->save(true,null,true)) {
					throw new Exception("Unable to save commissioning body patient assignment: ".print_r($_pa->getErrors(),true));
				}
			}

			if (!$pa->delete()) {
				throw new Exception("Unable to delete commissioning body patient assignment: ".print_r($pa->getErrors(),true));
			}
		}

		foreach (PatientOphInfo::model()->findAll('patient_id=?',array($patient->id)) as $oi) {
			if (!PatientOphInfo::model()->find('patient_id=? and cvi_status_date=? and cvi_status_id=?',array($new_patient->id,$oi->cvi_status_date,$oi->cvi_status_id))) {
				$_oi = BaseActiveRecord::cloneObject($oi,array('patient_id'=>$new_patient->id));

				if (!$_oi->save(true,null,true)) {
					throw new Exception("Unable to save patient oph info: ".print_r($_oi->getErrors(),true));
				}
			}

			if (!$oi->delete()) {
				throw new Exception("Unable to delete patient oph info: ".print_r($oi->getErrors(),true));
			}
		}

		foreach ($patient->referrals as $referral) {
			if (!Referral::model()->find($this->getCriteria(array(
					'patient_id' => $new_patient->id,
					'refno' => $referral->refno,
					'referral_type_id' => $referral->referral_type_id,
					'received_date' => $referral->received_date,
					'closed_date' => $referral->closed_date,
					'referrer' => $referral->referrer,
					'firm_id' => $referral->firm_id,
					'service_subspecialty_assignment_id' => $referral->service_subspecialty_assignment_id,
					'gp_id' => $referral->gp_id,
				)))) {
				$_referral = BaseActiveRecord::cloneObject($referral,array('patient_id'=>$new_patient->id));

				if (!$_referral->save(true,null,true)) {
					throw new Exception("Unable to save referral: ".print_r($_referral->getErrors(),true));
				}
			}

			if (!$referral->delete()) {
				throw new Exception("Unable to delete referral: ".print_r($referral->getErrors(),true));
			}
		}

		foreach (Audit::model()->findAll('patient_id=?',array($patient->id)) as $audit) {
			$audit->patient_id = $new_patient->id;

			if (!$audit->save(true,null,true)) {
				throw new Exception("Unable to save audit: ".print_r($audit->getErrors(),true));
			}
		}
	}

	public function getCriteria($params) {
		$criteria = new CDbCriteria;

		foreach ($params as $key => $value) {
			if ($value === null) {
				$criteria->addCondition("$key is null");
			} else {
				$criteria->addCondition("$key = :$key");
				$criteria->params[":$key"] = $value;
			}
		}

		return $criteria;
	}
}
