<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2012
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2012, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

class PasService {

	protected $available;

	/**
	 * Is PAS enabled and up?
	 */
	public function isAvailable() {
		if(!isset($this->available)) {
			$this->available = false;
			if(isset(Yii::app()->params['mehpas_enabled']) && Yii::app()->params['mehpas_enabled'] === true) {
				try {
					Yii::log('Checking PAS is available','trace');
					$connection = Yii::app()->db_pas;
				} catch (Exception $e) {
					//Yii::log('PAS is not available: '.$e->getMessage());
				}
				$this->available = true;
			}
		}
		return $this->available;
	}

	/**
	 * Push a flash warning that the PAS is down
	 */
	public function flashPasDown() {
		Yii::log('PAS is not available, displayed data may be out of date', 'trace');
		if(Yii::app() instanceof CWebApplication) {
			Yii::app()->user->setFlash('warning.pas_unavailable', 'PAS is currently unavailable, some data may be out of date or incomplete');
		}
	}

	/**
	 * Check to see if a GP ID (obj_prof) is on our block list
	 * @param string $gp_id
	 * @return boolean
	 */
	protected function isBadGp($gp_id) {
		return (in_array($gp_id, Yii::app()->params['mehpas_bad_gps']));
	}

	/**
	 * Update Gp from PAS
	 * @param Gp $gp
	 * @param PasAssignment $assignment
	 * @return Gp
	 */
	public function updateGpFromPas($gp, $assignment) {
		if ($this->available) {
			try {
				Yii::log("Pulling data from PAS for Gp: id: {$gp->id}, PasAssignment->id: {$assignment->id}, PasAssignment->external_id: {$assignment->external_id}", 'trace');
				if(!$assignment->external_id) {
					// Without an external ID we have no way of looking up the gp in PAS
					throw new CException('GP assignment has no external ID');
				}
				if($pas_gp = $assignment->external) {
					Yii::log('Found GP in PAS', 'trace');
					$gp->nat_id = $pas_gp->NAT_ID;
					$gp->obj_prof = $pas_gp->OBJ_PROF;
	
					// Contact
					if(!$contact = $gp->contact) {
						$contact = new Contact();
						$contact->parent_class = 'Gp';
					}
					$contact->first_name = $this->fixCase(trim($pas_gp->FN1 . ' ' . $pas_gp->FN2));
					$contact->last_name = $this->fixCase($pas_gp->SN);
					$contact->title = $this->fixCase($pas_gp->TITLE);
					if(trim($pas_gp->TEL_1)) {
						$contact->primary_phone = trim($pas_gp->TEL_1);
					} else {
						$contact->primary_phone = 'Unknown';
					}
					
					// Address
					$address1 = array();
					if($pas_gp->ADD_NAM) {
						$address1[] = $this->fixCase(trim($pas_gp->ADD_NAM));
					}
					$address1[] = $this->fixCase(trim($pas_gp->ADD_NUM . ' ' . $pas_gp->ADD_ST));
					$address1 = implode("\n",$address1);
					$address2 = $this->fixCase($pas_gp->ADD_DIS);
					$city = $this->fixCase($pas_gp->ADD_TWN);
					$postcode = strtoupper($pas_gp->PC);
					if(trim(implode('',array($address1, $address2, $city, $postcode)))) {
						if(!$address = $contact->address) {
							$address = new Address();
							$address->parent_class = 'Contact';
						}
						$address->address1 = $address1;
						$address->address2 = $address2;
						$address->city = $city;
						$address->county = $this->fixCase($pas_gp->ADD_CTY);
						$address->postcode = $postcode;
						$address->country_id = 1;
					} else {
						// Address doesn't look useful, so we'll delete it
						if($address = $contact->address) {
							$address->delete();
							$address = null;
						}
					}
	
					// Save
					$gp->save();
	
					$contact->parent_id = $gp->id;
					$contact->save();
	
					if($address) {
						$address->parent_id = $contact->id;
						$address->save();
					} else {
						Yii::log("GP has no address|id: {$gp->id}, obj_prof: {$gp->obj_prof}", 'warning', 'application.action');
					}
	
					$assignment->internal_id = $gp->id;
					$assignment->save();
	
				} else {
					
					// GP not in PAS (or at least no active records), so we should remove it and it's assignment from OpenEyes
					Yii::log('GP not found in PAS', 'trace');
					if($assignment->id) {
						Yii::log('Deleting PasAssignment: id: '.$assignment->id, 'trace');
						$assignment->delete();
					}
					if($gp->id) {
						Yii::log('Removing Gp association from patients', 'trace');
						$patients = Patient::model()->noPas()->findAll('gp_id = :gp_id', array(':gp_id' => $gp->id));
						$patients_updated = 0;
						foreach($patients as $patient) {
							$patient->gp_id = null;
							$patient->save();
							$patients_updated++;
						}
						Yii::log("Updated $patients_updated patients", 'trace');
						Yii::log('Deleting Gp: id: '.$gp->id, 'trace');
						$gp->delete();
						// Set GP to null (which is returned) to let caller know that it's been deleted
						$gp = null;
					}
					
				}
			} catch (CDbException $e) {
				$this->handlePASException($e);
			}
		}
		return $gp;
	}

	/**
	 * Update Practice from PAS
	 * @param Practice $practice
	 */
	public function updatePracticeFromPas($practice, $assignment) {
		if ($this->isAvailable()) {
			try {
				Yii::log("Pulling data from PAS for Practice: Practice->id: {$practice->id}, PasAssignment->id: {$assignment->id}, PasAssignment->external_id: {$assignment->external_id}", 'trace');
				if(!$assignment->external_id) {
					// Without an external ID we have no way of looking up the practice in PAS
					throw new CException('Practice assignment has no external ID');
				}
				if($pas_practice = $assignment->external) {
					Yii::log('Found Pracice in PAS', 'trace');
					$practice->code = $pas_practice->OBJ_LOC;
					if(trim($pas_practice->TEL_1)) {
						$practice->phone = trim($pas_practice->TEL_1);
					} else {
						$practice->phone = 'Unknown';
					}
	
					// Address
					$address1 = array();
					if($pas_practice->ADD_NAM) {
						$address1[] = $this->fixCase(trim($pas_practice->ADD_NAM));
					}
					$address1[] = $this->fixCase(trim($pas_practice->ADD_NUM . ' ' . $pas_practice->ADD_ST));
					$address1 = implode("\n",$address1);
					$address2 = $this->fixCase($pas_practice->ADD_DIS);
					$city = $this->fixCase($pas_practice->ADD_TWN);
					$postcode = strtoupper($pas_practice->PC);
					if(trim(implode('',array($address1, $address2, $city, $postcode)))) {
						if(!$address = $practice->address) {
							$address = new Address();
							$address->parent_class = 'Practice';
						}
						$address->address1 = $address1;
						$address->address2 = $address2;
						$address->city = $city;
						$address->county = $this->fixCase($pas_practice->ADD_CTY);
						$address->postcode = $postcode;
						$address->country_id = 1;
					} else {
						// Address doesn't look useful, so we'll delete it
						if($address = $practice->address) {
							$address->delete();
							$address = null;
						}
					}
	
					// Save
					$practice->save();
	
					if($address) {
						$address->parent_id = $practice->id;
						$address->save();
					} else {
						Yii::log("Practice has no address|id: {$practice->id}, code: {$practice->code}", 'warning', 'application.action');
					}
	
					$assignment->internal_id = $practice->id;
					$assignment->save();
	
				} else {
					// Practice not in PAS (or at least no active records), so we should remove it and it's assignment from OpenEyes
					Yii::log('Practice not found in PAS', 'trace');
					if($assignment->id) {
						Yii::log('Deleting PasAssignment: id: '.$assignment->id, 'trace');
						$assignment->delete();
					}
					if($practice->id) {
						Yii::log('Removing Practice association from patients', 'trace');
						$patients = Patient::model()->noPas()->findAll('practice_id = :practice_id', array(':practice_id' => $practice->id));
						$patients_updated = 0;
						foreach($patients as $patient) {
							$patient->practice_id = null;
							$patient->save();
							$patients_updated++;
						}
						Yii::log("Updated $patients_updated patients", 'trace');
						Yii::log('Deleting Practice: id: '.$practice->id, 'trace');
						$practice->delete();
						// Set Practice to null (which is returned) to let caller know that it's been deleted
						$practice = null;
					}
								
				}
			} catch (CDbException $e) {
				$this->handlePASException($e);
			}
		}
		return $practice;
	}
	
	public function handlePASException($e) {
		$logmsg = "PAS DB exception: ".$e->getMessage()."\n";

		foreach ($e->getTrace() as $i => $item) {
			if ($i <10) $i = ' '.$i;
			$logmsg .= $i.'. '.@$item['class'].@$item['type'].$item['function'].'()';
			if (isset($item['file']) && isset($item['line'])) {
				$logmsg .= ' '.$item['file'].':'.$item['line'];
			}
			$logmsg .= "\n";
		}

		Yii::log($logmsg);

		$this->available = false;
		$this->flashPasDown();
	}

	/**
	 * Update patient from PAS
	 * @param Patient $patient
	 * @param PasPatientAssignment $assignment
	 */
	public function updatePatientFromPas($patient, $assignment) {
		if (!$this->isAvailable()) return;

		try {
			Yii::log("Pulling data from PAS for Patient: Patient->id: {$patient->id}, PasAssignment->id: {$assignment->id}, PasAssignment->external_id: {$assignment->external_id}", 'trace');
			if(!$assignment->external_id) {
				// Without an external ID we have no way of looking up the patient in PAS
				throw new CException("Patient assignment has no external ID: PasAssignment->id: {$assignment->id}");
			}
			if($pas_patient = $assignment->external) {
				Yii::log("Found patient in PAS", 'trace');
				$patient_attrs = array(
						'gender' =>$pas_patient->SEX,
						'dob' => $pas_patient->DATE_OF_BIRTH,
						'date_of_death' => $pas_patient->DATE_OF_DEATH,
				);
				if($hos_num = $pas_patient->hos_number) {
					$hos_num = $hos_num->NUM_ID_TYPE . $hos_num->NUMBER_ID;
					$patient_attrs['pas_key'] = $hos_num;
					$patient_attrs['hos_num'] = $hos_num;
				}
				if($nhs_number = $pas_patient->nhs_number) {
					$patient_attrs['nhs_num'] = $nhs_number->NUMBER_ID;
				}

				$patient->attributes = $patient_attrs;

				// Get latest GP mapping from PAS
				$pas_patient_gp = $pas_patient->PatientGp;
				if($pas_patient_gp) {
					Yii::log("Patient has GP record: PAS_PatientGps->GP_ID: {$pas_patient_gp->GP_ID}", 'trace');
					
					// Check if GP is not on our block list
					if($this->isBadGp($pas_patient_gp->GP_ID)) {
						Yii::log("GP on blocklist, ignoring", 'trace');
						$patient->gp_id = null;
					} else {
						// Check if the GP is in openeyes
						Yii::log("Checking if GP is in openeyes", 'trace');
						$gp = Gp::model()->findByAttributes(array('obj_prof' => $pas_patient_gp->GP_ID));
						if(!$gp) {
							// GP not in openeyes, pulling from PAS
							Yii::log("GP not in openeyes, importing", 'trace');
							$gp = new Gp();
							$gp_assignment = new PasAssignment();
							$gp_assignment->internal_type = 'Gp';
							$gp_assignment->external_id = $pas_patient_gp->GP_ID;
							$gp_assignment->external_type = 'PAS_Gp';
							$gp = $this->updateGpFromPas($gp, $gp_assignment);
						} else if(!$gp->refresh()) {
							// GP has been deleted (probably by an observer)
							// FIXME: There must be a better way of dealing with this
							Yii::log("GP was deleted after find", 'trace');
							$gp = null;
						}

						// Update/set patient's GP
						$gp_id = ($gp) ? $gp->id : null;
						if($patient->gp_id != $gp_id) {
							Yii::log("Patient's GP changed", 'trace');
							$patient->gp_id = $gp_id;
						} else {
							Yii::log("Patient's GP has not changed", 'trace');
						}

					}
					if(!$patient->gp_id && $pas_patient_gp->GP_ID) {
						Yii::log("Patient GP invalid or not found in PAS|id: {$patient->id}, hos_num: {$patient->hos_num}, gp_id: {$pas_patient_gp->GP_ID}", 'warning', 'application.action');
					} else if(!$patient->gp_id) {
						Yii::log("Patient has no GP|id: {$patient->id}, hos_num: {$patient->hos_num}", 'warning', 'application.action');
					}
					
					// Check if the Practice is in openeyes
					Yii::log("Checking if Practice is in openeyes: PAS_PatientGps->PRACTICE_CODE: {$pas_patient_gp->PRACTICE_CODE}", 'trace');
					$practice = Practice::model()->findByAttributes(array('code' => $pas_patient_gp->PRACTICE_CODE));
					if(!$practice) {
						// Practice not in openeyes, pulling from PAS
						Yii::log("Practice not in openeyes", 'trace');
						$practice = new Practice();
						$practice_assignment = new PasAssignment();
						$practice_assignment->internal_type = 'Practice';
						$practice_assignment->external_id = $pas_patient_gp->PRACTICE_CODE;
						$practice_assignment->external_type = 'PAS_Practice';
						$practice = $this->updatePracticeFromPas($practice, $practice_assignment);
					} else if(!$practice->refresh()) {
						// Practice has been deleted (probably by an observer)
						// FIXME: There must be a better way of dealing with this
						Yii::log("Practice was deleted after find", 'trace');
						$practice = null;
					}

					// Update/set patient's practice
					$practice_id = ($practice) ? $practice->id : null;
					if($patient->practice_id != $practice_id) {
						Yii::log("Patient's practice changed", 'trace');
						$patient->practice_id = $practice_id;
					} else {
						Yii::log("Patient's practice has not changed", 'trace');
					}
					
					if(!$patient->practice_id && $pas_patient_gp->PRACTICE_CODE) {
						Yii::log("Patient Practice invalid or not found in PAS|id: {$patient->id}, hos_num: {$patient->hos_num}, practice_code: {$pas_patient_gp->PRACTICE_CODE}", 'warning', 'application.action');
					} else if(!$patient->practice_id) {
						Yii::log("Patient has no Practice|id: {$patient->id}, hos_num: {$patient->hos_num}", 'warning', 'application.action');
					}
					
				} else {
					Yii::log("Patient has no GP/practice in PAS", 'trace');
					Yii::log("Patient has no GP or Practice|id: {$patient->id}, hos_num: {$patient->hos_num}", 'warning', 'application.action');
				}

				if (!$contact = $patient->contact) {
					$contact = new Contact;
					$contact->parent_class = 'Patient';
				}

				// Save
				$patient->save();

				$contact->parent_id = $patient->id;
				$contact->title = $this->fixCase($pas_patient->name->TITLE);
				$contact->first_name = ($pas_patient->name->NAME1) ? $this->fixCase($pas_patient->name->NAME1) : '(UNKNOWN)';
				$contact->last_name = $this->fixCase($pas_patient->name->SURNAME_ID);
				if($pas_patient->address) {
					// Get primary phone from patient's main address
					$contact->primary_phone = $pas_patient->address->TEL_NO;
				}
				$contact->save();

				$assignment->internal_id = $patient->id;
				$assignment->save();

				// Addresses
				if($pas_patient->addresses) {

					// Matching addresses for update is tricky cos we don't have a primary key on the pas address table,
					// so we need to keep track of patient address ids as we go
					$matched_address_ids = array();
					foreach($pas_patient->addresses as $pas_address) {

						// Match an address
						Yii::log("Looking for patient address: PAS_PatientAddress->POSTCODE: ".$pas_address->POSTCODE, 'trace');
						$matched_clause = ($matched_address_ids) ? ' AND id NOT IN ('.implode(',',$matched_address_ids).')' : '';
						$address = Address::model()->find(array(
								'condition' => "parent_id = :patient_id AND parent_class = 'Patient' AND REPLACE(postcode,' ','') = :postcode" . $matched_clause,
								'params' => array(':patient_id' => $patient->id, ':postcode' => str_replace(' ','',$pas_address->POSTCODE)),
						));

						// Check if we have an address (that we haven't already matched)
						if(!$address) {
							Yii::log("Patient address not found, creating", 'trace');
							$address = new Address;
							$address->parent_id = $patient->id;
							$address->parent_class = 'Patient';
						}

						$this->updateAddress($address, $pas_address);
						$address->save();
						$matched_address_ids[] = $address->id;
					}

					// Remove any orphaned addresses (expired?)
					$matched_string = implode(',',$matched_address_ids);
					$orphaned_addresses = Address::model()->deleteAll(array(
							'condition' => "parent_id = :patient_id AND parent_class = 'Patient' AND id NOT IN($matched_string)",
							'params' => array(':patient_id' => $patient->id),
					));
					$matched_addresses = count($matched_address_ids);
					if($orphaned_addresses) {
						Yii::log("$orphaned_addresses orphaned patient addresses were deleted", 'trace');
					}
					Yii::log("Patient has $matched_addresses valid addresses", 'trace');

				}

			} else {
				Yii::log('Patient not found in PAS', 'trace');
			}
		} catch (CDbException $e) {
			$this->handlePASException($e);
		}
	}

	/**
	 * Perform a search based on form $_POST data from the patient search page
	 * Search against PAS data and then import the data into OpenEyes database
	 * @param array $data
	 * @param integer $num_results
	 * @param integer $page
	 */
	public function search($data, $num_results = 20, $page = 1) {
		try {
			Yii::log('Searching PAS', 'trace');

			// oracle apparently doesn't do case-insensitivity, so everything is uppercase
			foreach ($data as $key => &$value) {
				$value = strtoupper($value);
			}

			$whereSql = '';
			$whereParams = array();

			// Hospital number
			if (!empty($data['hos_num'])) {
				$hosNum = preg_replace('/[^\d]/', '0', $data['hos_num']);
				$whereSql .= " AND n.num_id_type = substr(:hos_num,1,1) and n.number_id = substr(:hos_num,2,6)";
				$whereParams[':hos_num'] = $hosNum;
			}

			if (!empty($data['nhs_num'])) {
				$whereSql .= " AND n.num_id_type = 'NHS' and n.number_id = :nhs_num";
				$whereParams[':nhs_num'] = $data['nhs_num'];
			}

			// Name
			if (!empty($data['first_name']) && !empty($data['last_name'])) {
				$whereSql .= " AND p.RM_PATIENT_NO IN (SELECT RM_PATIENT_NO FROM SILVER.SURNAME_IDS WHERE Surname_Type = 'NO' AND ((Name1 = :first_name
				OR Name2 = :first_name) AND Surname_ID = :last_name))";
				$whereParams[':first_name'] = $data['first_name'];
				$whereParams[':last_name'] = $data['last_name'];
			}

			if (empty($data['nhs_num'])) {
				$whereSql .= " AND LENGTH(TRIM(TRANSLATE(n.num_id_type, '0123456789', ' '))) is null";
			}

			$command = Yii::app()->db_pas->createCommand()
			->select('COUNT(*) as count')
			->from('SILVER.PATIENTS P')
			->join('SILVER.SURNAME_IDS S', 'S.rm_patient_no = P.rm_patient_no')
			->join('SILVER.NUMBER_IDS N', 'N.rm_patient_no = P.rm_patient_no')
			->where("surname_type = 'NO' $whereSql", $whereParams);
			foreach ($command->queryAll() as $results) $this->num_results = $results['COUNT'];

			$offset = ($page * $num_results) + 1;
			$limit = $offset + $num_results - 1;
			switch ($data['sortBy']) {
				case 'HOS_NUM*1':
					// hos_num
					$sort_by = "n.NUM_ID_TYPE||n.NUMBER_ID";
					break;
				case 'TITLE':
					// title
					$sort_by = "s.TITLE";
					break;
				case 'FIRST_NAME':
					// first_name
					$sort_by = "s.NAME1";
					break;
				case 'LAST_NAME':
					// last_name
					$sort_by = "s.SURNAME_ID";
					break;
				case 'DOB':
					// date of birth
					$sort_by = "p.DATE_OF_BIRTH";
					break;
				case 'GENDER':
					// gender
					$sort_by = "p.SEX";
					break;
				case 'NHS_NUM*1':
					// nhs_num
					$sort_by = "n.NUM_ID_TYPE||n.NUMBER_ID";
					break;
			}

			$sort_dir = ($data['sortDir'] == 'asc' ? 'ASC' : 'DESC');
			$sort_rev = ($data['sortDir'] == 'asc' ? 'DESC' : 'ASC');

			$sql = "
			SELECT * from
			( select a.*, rownum rnum from (
			SELECT p.RM_PATIENT_NO, n.NUM_ID_TYPE, n.NUMBER_ID
			FROM SILVER.PATIENTS p
			JOIN SILVER.NUMBER_IDS n ON n.rm_patient_no = p.rm_patient_no
			JOIN SILVER.SURNAME_IDS s ON s.rm_patient_no = p.rm_patient_no
			LEFT OUTER JOIN SILVER.NUMBER_IDS n2 ON n2.rm_patient_no = p.rm_patient_no
			AND n2.NUM_ID_TYPE = 'NHS'
			WHERE ( s.surname_type = 'NO' $whereSql )
			ORDER BY $sort_by $sort_dir
			) a
			where rownum <= $limit
			order by rownum $sort_rev
			)
			where rnum >= $offset
			order by rnum $sort_rev
			";

			$command = Yii::app()->db_pas->createCommand($sql);
			$command->bindValues($whereParams);
			$results = $command->queryAll();

			$ids = array();
			$patients_with_no_address = 0;

			foreach ($results as $result) {

				// See if the patient is in openeyes, if not then fetch from PAS
				//Yii::log("Fetching assignment for patient: rm_patient_no:" . $result['RM_PATIENT_NO'], 'trace');
				$patient_assignment = $this->findPatientAssignment($result['RM_PATIENT_NO'], $result['NUM_ID_TYPE'] . $result['NUMBER_ID']);
				if($patient_assignment) {
					//Yii::log("Got assignment",'trace');
					$patient = $patient_assignment->internal;

					// Check that patient has an address
					if($patient->address) {
						$ids[] = $patient->id;
					} else {
						$patients_with_no_address++;
					}
				} else {
					// Something went wrong with the assignment, probably a DB error
					Yii::log("Patient assignment failed for RM_PATIENT_NO " . $result['RM_PATIENT_NO']);
				}

			}

			switch (@$_GET['sort_by']) {
				case 0:
					// hos_num
					$sort_by = "hos_num";
					break;
				case 1:
					// title
					$sort_by = "title";
					break;
				case 2:
					// first_name
					$sort_by = "first_name";
					break;
				case 3:
					// last_name
					$sort_by = "last_name";
					break;
				case 4:
					// date of birth
					$sort_by = "dob";
					break;
				case 5:
					// gender
					$sort_by = "gender";
					break;
				case 6:
					// nhs_num
					$sort_by = "nhs_num";
					break;
				default:
					$sort_by = 'hos_num';
					
			}

			// Collect all the patients we just created
			$criteria = new CDbCriteria;
			$criteria->addInCondition('id', $ids);
			$criteria->order = "$sort_by $sort_dir";

			if ($patients_with_no_address > 0) {
				$this->num_results -= $patients_with_no_address;
				$this->no_address = true;
			}

			return $criteria;
		} catch (CDbException $e) {
			$this->handlePASException($e);
		}
	}

	/**
	 * Try to find patient assignment in OpenEyes and if necessary create a new one and populate it from PAS
	 * @param string $rm_patient_no
	 * @param string $hos_num
	 * @return PasAssignment
	 */
	protected function findPatientAssignment($rm_patient_no, $hos_num) {
		//Yii::log('Getting assignment','trace');
		$assignment = PasAssignment::model()->findByExternal('PAS_Patient', $rm_patient_no);
		if($assignment && $assignment->isStale()) {
			//Yii::log('Got assignment','trace');
			// Patient is in OpenEyes and has an existing assignment
			$patient_id = $assignment->internal_id;
			$patient = Patient::model()->noPas()->findByPk($patient_id);
			$this->updatePatientFromPas($patient, $assignment);
			//Yii::log('Updated from PAS','trace');
		} else if(!$assignment) {
			//Yii::log('No assignment','trace');
			// Patient is not in OpenEyes
			$patient = new Patient();
			$assignment = new PasAssignment();
			$assignment->external_id = $rm_patient_no;
			$assignment->external_type = 'PAS_Patient';
			$assignment->internal_type = 'Patient';
			$this->updatePatientFromPas($patient, $assignment);
			//Yii::log('Updated from PAS','trace');

			if (Yii::app()->params['mehpas_legacy_letters']) {
				Yii::import('application.modules.OphLeEpatientletter.models.*');
				$this->associateLegacyEvents($patient);
			}
		}
		// Only return assignment if it's been fully updated
		if($assignment->internal) {
			return $assignment;
		} else {
			return null;
		}
	}

	/**
	 * Update address info with the latest info from PAS
	 * @param Address $address The patient address model to be updated
	 * @param PAS_PatientAddress $data Data from PAS to store in the patient address model
	 */
	protected function updateAddress($address, $data) {

		$address1 = '';
		$address2 = '';
		$city = '';
		$county = '';
		$postcode = '';
		$town = '';

		$propertyName = trim($data->PROPERTY_NAME);
		$propertyNumber = trim($data->PROPERTY_NO);

		// Make sure they are not the same!
		if (strcasecmp($propertyName, $propertyNumber) == 0) {
			$propertyNumber = '';
		}

		// Address1
		$addr1 = trim($data->ADDR1);
		if ($addr1) {

			// Remove any duplicate property name or number from ADDR1
			if (strlen($propertyName) > 0) {
				// Search plain, with comma, and with full stop
				$needles = array("{$propertyName},","{$propertyName}.",$propertyName);
				$addr1 = trim(str_replace($needles, '', $addr1));
			}
			if (strlen($propertyNumber) > 0) {
				// Search plain, with comma, and with full stop
				$needles = array("{$propertyNumber},","{$propertyNumber}.",$propertyNumber);
				$addr1 = trim(str_replace($needles, '', $addr1));
			}

			// Make sure street number has a comma and space after it
			$addr1 = preg_replace('/([0-9]) /', '\1, ', $addr1);

			// Replace any full stops after street numbers with commas
			$addr1 = preg_replace('/([0-9])\./', '\1,', $addr1);

		}

		// Combine property name, number and first line
		$address1 = array();
		if($propertyName) {
			$address1[] = $propertyName;
		}
		if($propertyNumber || $addr1) {
			$address1[] = trim($propertyNumber . ' ' . $addr1);
		}

		$address1 = implode("\n", $address1);

		// Create array of remaining address lines, from last to first
		$addressLines = array();
		foreach(array('POSTCODE','ADDR5','ADDR4','ADDR3','ADDR2') as $address_line) {
			if($address_line_content = trim($data->{$address_line})) {
				$addressLines[] = $address_line_content;
			}
		} 

		// Instantiate a postcode utility object
		$postCodeUtility = new PostCodeUtility();

		// Set flags and default values
		$postCodeFound = false;
		$postCodeOuter = '';
		$townFound = false;
		$countyFound = false;
		$address2 = '';

		// Go through array looking for likely candidates for postcode, town/city and county
		for ($index = 0; $index < count($addressLines); $index++) {
			// Is element a postcode? (Postcodes may exist in other address lines)
			if ($postCodeArray = $postCodeUtility->parsePostCode($addressLines[$index])) {
				if (!$postCodeFound) {
					$postCodeFound = true;
					$postcode = $postCodeArray['full'];
					$postCodeOuter = $postCodeArray['outer'];
				}
			} else { // Otherwise a string
				// Last in (inverted array) is a non-postcode, non-city second address line
				if ($townFound) {
					$address2 = trim($addressLines[$index]);
				}

				// County?
				if (!$countyFound) {
					if ($postCodeUtility->isCounty($addressLines[$index])) {
						$countyFound = true;
						$county = trim($addressLines[$index]);
					}
				}

				// Town?
				if (!$townFound) {
					if ($postCodeUtility->isTown($addressLines[$index])) {
						$townFound = true;
						$town = trim($addressLines[$index]);
					}
				}
			}
		}

		// If no town or county found, get them from postcode data if available, otherwise fall back to best guess
		if ($postCodeFound) {
			if (!$countyFound) $county = $postCodeUtility->countyForOuterPostCode($postCodeOuter);
			if (!$townFound) $town = $postCodeUtility->townForOuterPostCode($postCodeOuter);
		} else {
			// Number of additional address lines
			$extraLines = count($addressLines) - 1;
			if ($extraLines > 1) {
				$county = trim($addressLines[0]);
				$town = trim($addressLines[1]);
			} elseif ($extraLines > 0) {
				$town = trim($addressLines[0]);
			}
		}

		// Dedupe
		if (isset($county) && isset($town) && $town == $county) {
			$county = '';
		}

		// Store data
		$address->address1 = $this->fixCase($address1);
		$address->address2 = $this->fixCase($address2);
		$address->city = $this->fixCase($town);
		$address->county = $this->fixCase($county);
		$unitedKingdom = Country::model()->findByAttributes(array('name' => 'United Kingdom'));
		$address->country_id = $unitedKingdom->id;
		$address->postcode = strtoupper($postcode);
		$address->type = $data->ADDR_TYPE;
		$address->date_start = $data->DATE_START;
		$address->date_end = $data->DATE_END;

	}

	public function associateLegacyEvents($patient) {
		if (Element_OphLeEpatientletter_EpatientLetter::model()->find('epatient_hosnum=?',array($patient->hos_num))) {
			$episode = new Episode;
			$episode->patient_id = $patient->id;
			$episode->firm_id = null;
			$episode->start_date = date('Y-m-d H:i:s');
			$episode->end_date = null;
			$episode->episode_status_id = 1;
			$episode->legacy = 1;
			if (!$episode->save()) {
				throw new Exception('Unable to save new legacy episode: '.print_r($episode->getErrors(),true));
			}

			$earliest = time();

			foreach (Element_OphLeEpatientletter_EpatientLetter::model()->findAll('epatient_hosnum=?',array($patient->hos_num)) as $letter) {
				$event = Event::model()->findByPk($letter->event_id);
				$event->episode_id = $episode->id;
				if (!$event->save()) {
					throw new Exception('Unable to associate legacy event with episode: '.print_r($event->getErrors(),true));
				}

				if (strtotime($event->datetime) < $earliest) {
					$earliest = strtotime($event->datetime);
				}
			}

			$episode->start_date = date('Y-m-d H:i:s',$earliest);
		}
	}
	
	protected function fixCase($string) {

		// Basic Title Case to start with
		$string = ucwords(strtolower($string));

		// Fix delimited words
		foreach (array('-', '\'', '.') as $delimiter) {
			if (strpos($string, $delimiter) !== false) {
				$string = implode($delimiter, array_map('ucfirst', explode($delimiter, $string)));
			}
		}

		// Exception is possessive (i.e. Paul's should not be Paul'S)
		$string = str_replace('\'S ', '\'s ', $string);

		return $string;

	}

}
