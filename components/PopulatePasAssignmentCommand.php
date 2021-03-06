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
 * @todo This command is currently disabled until the referral code is fixed
 */

class PopulatePasAssignmentCommand extends CConsoleCommand
{
	public function getName()
	{
		return 'PopulatePasAssignment';
	}

	public function getHelp()
	{
		return "Adds an assignment record for every patient currently in OpenEyes\n";
	}

	public function run($args)
	{
		$pas_service = PasService::load();
		if ($pas_service->isAvailable()) {
			$this->populatePatientPasAssignment();
			$this->populateGpPasAssignment();
			$this->populatePracticePasAssignment();
		} else {
			echo "PAS is unavailable or module is disabled";
			return false;
		}
	}

	protected function populateGpPasAssignment()
	{
		// Find all gps that don't have an assignment
		$gps = Yii::app()->db->createCommand()
		->select('gp.id, gp.obj_prof')
		->from('gp')
		->leftJoin('pas_assignment', "pas_assignment.internal_id = gp.id AND pas_assignment.internal_type = :internal_type and pas_assignment.deleted = :notdeleted")
		->where('pas_assignment.id IS NULL and gp.deleted = :notdeleted', array(
			':internal_type' => 'Gp',
			':notdeleted' => 0,
		))
		->order('gp.last_modified_date DESC')
		->queryAll();

		echo "There are ".count($gps)." gps without an assignment, processing...\n";

		$results = array(
				'updated' => 0,
				'removed' => 0,
				'duplicates' => 0,
				'skipped' => 0,
				'conflicted' => 0,
		);

		foreach ($gps as $gp) {

			$obj_prof = $gp['obj_prof'];
			$gp_id = $gp['id'];

			// Check to see if GP is associated with a patient
			$patient = Yii::app()->db->createCommand()
			->select('count(id)')
			->from('patient')
			->where('gp_id = :gp_id and patient.deleted = :notdeleted', array(':gp_id' => $gp_id, ':notdeleted' => 0))
			->queryScalar();
			if (!$patient) {
				// GP is not being used, let's delete it!
				echo "Deleting unused GP (obj_prof $obj_prof, id $gp_id)\n";
				$results['removed']++;
				Gp::model()->deleteByPk($gp_id);
				continue;
			}

			// Check to see if there is more than one GP with the same obj_prof (duplicates)
			$duplicate_gps = Yii::app()->db->createCommand()
			->select('id')
			->from('gp')
			->where('obj_prof = :obj_prof AND id != :gp_id and gp.deleted = :notdeleted', array(':obj_prof' => $obj_prof, ':gp_id' => $gp_id, ':notdeleted' => 0))
			->queryColumn();
			if (count($duplicate_gps)) {
				echo "There are one or more other GPs with obj_prof $obj_prof, attempting to merge\n";
				$merged = 0;
				foreach ($duplicate_gps as $duplicate_gp_id) {
					$gp_patients = Yii::app()->db->createCommand()
					->update('patient', array('gp_id' => $gp_id), 'gp_id = :duplicate_gp_id and deleted = :notdeleted', array(
						':duplicate_gp_id' => $duplicate_gp_id,
						':notdeleted' => 0,
					));
					$results['duplicates']++;
					$results['removed']++;
					Gp::model()->deleteByPk($duplicate_gp_id);
				}
				echo "Removed ".count($duplicate_gps)." duplicate GP(s) and merged their patients\n";
			}

			// Find a matching gp
			$pas_gps = PAS_Gp::model()->findAll(array(
					'condition' => 'OBJ_PROF = :obj_prof AND (DATE_TO IS NULL OR DATE_TO >= SYSDATE) AND (DATE_FR IS NULL OR DATE_FR <= SYSDATE)',
					'order' => 'DATE_FR DESC',
					'params' => array(
							':obj_prof' => $obj_prof,
					),
			));

			if (count($pas_gps) > 0) {
				// Found a match
				Yii::log("Found match in PAS for obj_prof $obj_prof, creating assignment", 'trace');

				if ($assignment = PasAssignment::model()->find('internal_id=? and internal_type=?',array($gp_id,'Gp'))) {
					if ($assignment->external_id != $obj_prof || $assignment->external_type != 'PAS_Gp') {
						echo "Conflist in pas_assignment:\n\n";
						echo "Wanted to insert:\n\n";
						echo "external_id : $obj_prof\n";
						echo "external_type : PAS_Gp\n";
						echo "internal_id : $gp_id\n";
						echo "internal_type : Gp\n\n";
						echo "But this already exists:\n\n";
						echo "external_id : $assignment->external_id\n";
						echo "external_type : $assignment->external_type\n";
						echo "internal_id : $assignment->internal_id\n";
						echo "internal_type : $assignment->internal_type\n\n";

						$results['conflicted']++;
					} else {
						$results['skipped']++;
					}
				} else {
					$assignment = new PasAssignment();
					$assignment->external_id = $obj_prof;
					$assignment->external_type = 'PAS_Gp';
					$assignment->internal_id = $gp_id;
					$assignment->internal_type = 'Gp';
					$assignment->save();
					$results['updated']++;
				}
			} else {
				// GP is not in PAS, let's remove GP and update associated patients
				$gp_patients = Patient::model()->findAllByAttributes(array('gp_id' => $gp_id));
				foreach ($gp_patients as $patient) {
					if ($patient->gp_id == $gp_id) {
						$patient->gp_id = null;
						$patient->save();
					}
				}
				echo "Deleting invalid GP\n";
				if ($assignment = PasAssignment::model()->find('internal_id=? and internal_type=?',array($gp_id,'Gp'))) {
					$assignment->delete();
				}
				Gp::model()->deleteByPk($gp_id);
				$results['removed']++;
			}

		}

		echo "GP Results:\n";
		echo " - Updated: ".$results['updated']."\n";
		echo " - Removed: ".$results['removed']."\n";
		echo " - Duplicates: ".$results['duplicates']."\n";
		echo " - Conflicts: ".$results['conflicted']."\n";
		echo " - Skipped: ".$results['skipped']."\n";
		echo "Done.\n";
	}

	protected function populatePatientPasAssignment()
	{
		// Find all patients that don't have an assignment
		$patients = Yii::app()->db->createCommand()
		->select('patient.id, patient.hos_num')
		->from('patient')
		->leftJoin('pas_assignment', "pas_assignment.internal_id = patient.id AND pas_assignment.internal_type = :internal_type and pas_assignment.deleted = :notdeleted")
		->where('pas_assignment.id IS NULL and patient.deleted = :notdeleted', array(
			':internal_type' => 'Patient',
			':notdeleted' => 0,
		))
		->queryAll();

		echo "There are ".count($patients)." patients without an assignment, processing...\n";

		$results = array(
				'updated' => 0,
				'removed' => 0,
				'duplicates' => 0,
				'skipped' => 0,
		);
		foreach ($patients as $patient) {

			// Find rm_patient_no
			$hos_num = sprintf('%07d',$patient['hos_num']);
			$number_id = substr($hos_num, -6);
			$num_id_type = substr($hos_num, 0, 1);
			$patient_no = PAS_PatientNumber::model()->findAll('num_id_type = :num_id_type AND number_id = :number_id', array(
					':num_id_type' => $num_id_type,
					':number_id' => $number_id,
			));

			if (count($patient_no) == 1) {
				// Found a single match
				Yii::log("Found match in PAS for hos_num $hos_num, creating assignment", 'trace');
				if ($assignment = PasAssignment::model()->find('internal_type=? and internal_id=?',array('Patient',$patient['id']))) {
					if ($assignment->external_type != 'PAS_Patient' || $assignment->external_id != $patient_no[0]->RM_PATIENT_NO) {
						throw new CException("Conflicting pas_assignment for internal_type=Patient internal_id={$patient['id']}: wanted to insert external_type=PAS_Patient external_id={$patient_no[0]->RM_PATIENT_NO} but already have external_type=PAS_Patient external_id={$assignment->external_id}");
					}
				} elseif ($assignment = PasAssignment::model()->find('external_type=? and external_id=?',array('PAS_Patient',$patient_no[0]->RM_PATIENT_NO))) {
					if ($assignment->internal_type != 'Patient' || $assignment->internal_id != $patient['id']) {
						throw new CException("Conflicting pas_assignment for external_type=PAS_Patient external_id={$patient_no[0]->RM_PATIENT_NO}: wanted to insert internal_type=Patient internal_id={$patient['id']} but already have internal_type=Patient internal_id={$assignment->internal_id}");
					}
				} else {
					$assignment = new PasAssignment();
					$assignment->external_id = $patient_no[0]->RM_PATIENT_NO;
					$assignment->external_type = 'PAS_Patient';
					$assignment->internal_id = $patient['id'];
					$assignment->internal_type = 'Patient';
					$assignment->save();
					$results['updated']++;
				}
			} elseif (count($patient_no) > 1) {
				// Found more than one match
				echo "Found more than one match in PAS for hos_num $hos_num, cannot create assignment\n";
				$results['skipped']++;
			} else {
				// No match
				echo "Cannot find match in PAS for hos_num $hos_num, cannot create assignment\n";
				$results['skipped']++;
			}

		}

		echo "Patient Results:\n";
		echo " - Updated: ".$results['updated']."\n";
		echo " - Skipped: ".$results['skipped']."\n";
		echo "Done.\n";
	}

	protected function populatePracticePasAssignment()
	{
		// Find all practices that don't have an assignment
		$practices = Yii::app()->db->createCommand()
		->select('practice.id, practice.code')
		->from('practice')
		->leftJoin('pas_assignment', "pas_assignment.internal_id = practice.id AND pas_assignment.internal_type = :internal_type and pas_assignment.deleted = :notdeleted")
		->where('pas_assignment.id IS NULL and practice.deleted = :notdeleted', array(
			':internal_type' => 'Practice',
			':notdeleted' => 0,
		))
		->order('practice.last_modified_date DESC')
		->queryAll();

		echo "There are ".count($practices)." practices without an assignment, processing...\n";

		$results = array(
				'updated' => 0,
				'removed' => 0,
				'duplicates' => 0,
				'skipped' => 0,
				'conflicted' => 0,
		);

		foreach ($practices as $practice) {

			$code = $practice['code'];
			$practice_id = $practice['id'];

			// Check to see if practice is associated with a patient
			$patient = Yii::app()->db->createCommand()
			->select('count(id)')
			->from('patient')
			->where('practice_id = :practice_id and deleted = :notdeleted', array(
				':practice_id' => $practice_id,
				':notdeleted' => 0,
			))
			->queryScalar();
			if (!$patient) {
				// Practice is not being used, let's delete it!
				echo "Deleting unused practice (code $code, id $practice_id)\n";
				$results['removed']++;
				Practice::model()->deleteByPk($practice_id);
				continue;
			}

			// Check to see if there is more than one practice with the same code (duplicates)
			$duplicate_practices = Yii::app()->db->createCommand()
			->select('id')
			->from('practice')
			->where('code = :code AND id != :practice_id AND deleted = :notdeleted', array(
				':code' => $code,
				':practice_id' => $practice_id,
				':notdeleted' => 0,
			))
			->queryColumn();
			if (count($duplicate_practices)) {
				echo "There are one or more other practices with code $code, attempting to merge\n";
				$merged = 0;
				foreach ($duplicate_practices as $duplicate_practice_id) {
					$practice_patients = Yii::app()->db->createCommand()
					->update('patient', array('practice_id' => $practice_id), 'practice_id = :duplicate_practice_id', array(':duplicate_practice_id' => $duplicate_practice_id));
					$results['duplicates']++;
					$results['removed']++;
					Practice::model()->deleteByPk($duplicate_practice_id);
				}
				echo "Removed ".count($duplicate_practices)." duplicate practice(s) and merged their patients\n";
			}

			// Find a matching practice
			$pas_practices = PAS_Practice::model()->findAll(array(
					'condition' => 'OBJ_LOC = :code AND (DATE_TO IS NULL OR DATE_TO >= SYSDATE) AND (DATE_FR IS NULL OR DATE_FR <= SYSDATE)',
					'order' => 'DATE_FR DESC',
					'params' => array(
							':code' => $code,
					),
			));

			if (count($pas_practices) > 0) {
				// Found a match
				Yii::log("Found match in PAS for code $code, creating assignment", 'trace');

				if ($assignment = PasAssignment::model()->find('internal_id=? and internal_type=?',array($practice_id,'Practice'))) {
					if ($assignment->external_id != $obj_prof || $assignment->external_type != 'PAS_Practice') {
						echo "Conflict in pas_assignment:\n\n";
						echo "Wanted to insert:\n\n";
						echo "external_id : $code\n";
						echo "external_type : PAS_Practice\n";
						echo "internal_id : $practice_id\n";
						echo "internal_type : Practice\n\n";
						echo "But this already exists:\n\n";
						echo "external_id : $assignment->external_id\n";
						echo "external_type : $assignment->external_type\n";
						echo "internal_id : $assignment->internal_id\n";
						echo "internal_type : $assignment->internal_type\n\n";

						$results['conflicted']++;
					} else {
						$results['skipped']++;
					}
				} else {
					$assignment = new PasAssignment();
					$assignment->external_id = $code;
					$assignment->external_type = 'PAS_Practice';
					$assignment->internal_id = $practice_id;
					$assignment->internal_type = 'Practice';
					$assignment->save();
					$results['updated']++;
				}
			} else {
                                // Practice is not in PAS, let's remove Practice and update associated patients
                                $practice_patients = Patient::model()->findAllByAttributes(array('practice_id' => $practice_id));
                                foreach ($practice_patients as $patient) {
                                        if ($patient->practice_id == $practice_id) {
                                                $patient->practice_id = null;
                                                $patient->save();
                                        }
                                }
                                echo "Deleting invalid Practice\n";
                                if ($assignment = PasAssignment::model()->find('internal_id=? and internal_type=?',array($practice_id,'Practice'))) {
                                        $assignment->delete();
                                }
                                Practice::model()->deleteByPk($practice_id);
                                $results['removed']++;
			}

		}

		echo "Practice Results:\n";
		echo " - Updated: ".$results['updated']."\n";
		echo " - Removed: ".$results['removed']."\n";
		echo " - Duplicates: ".$results['duplicates']."\n";
		echo " - Conflicts: ".$results['conflicted']."\n";
		echo " - Skipped: ".$results['skipped']."\n";
		echo "Done.\n";
	}

}
