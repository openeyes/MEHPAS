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

class PasService
{
	/**
	 * @return PasService
	 */
	static public function load()
	{
		return new self(PasAssignment::model());
	}

	protected $assign;
	protected $available;

	/**
	 * @param PasAssignment $assign PasAssignment static model
	 */
	public function __construct(PasAssignment $assign)
	{
		$this->assign = $assign;
	}

	/**
	 * Is PAS enabled and up?
	 */
	public function isAvailable()
	{
		if (!isset($this->available)) {
			$this->available = false;
			if (isset(Yii::app()->params['mehpas_enabled']) && Yii::app()->params['mehpas_enabled'] === true) {
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
	 * @param boolean $available
	 */
	public function setAvailable($available = true)
	{
		$this->available = $available;
	}

	/**
	 * Push a flash warning that the PAS is down
	 */
	public function flashPasDown()
	{
		Yii::log('PAS is not available, displayed data may be out of date', 'trace');
		if (Yii::app() instanceof CWebApplication) {
			Yii::app()->user->setFlash('warning.pas_unavailable', 'PAS is currently unavailable, some data may be out of date or incomplete');
		}
	}

	/**
	 * Check to see if a GP ID (obj_prof) is on our block list
	 * @param string $gp_id
	 * @return boolean
	 */
	protected function isBadGp($gp_id)
	{
		return (in_array($gp_id, Yii::app()->params['mehpas_bad_gps']));
	}

	/**
	 * Update Gp from PAS
	 * @param Gp $gp
	 * @param PasAssignment $assignment
	 * @return Gp
	 */
	public function updateGpFromPas($gp, $assignment)
	{
		if ($this->available) {
			try {
				Yii::log("Pulling data from PAS for Gp: id: {$gp->id}, PasAssignment->id: {$assignment->id}, PasAssignment->external_id: {$assignment->external_id}", 'trace');
				if (!$assignment->external_id) {
					// Without an external ID we have no way of looking up the gp in PAS
					throw new CException('GP assignment has no external ID');
				}
				if ($pas_gp = $assignment->external) {
					Yii::log('Found GP in PAS', 'trace');
					$gp->nat_id = $pas_gp->NAT_ID;
					$gp->obj_prof = $pas_gp->OBJ_PROF;

					// Contact
					if (!$contact = $gp->contact) {
						$contact = new Contact();
					}
					$contact->first_name = $this->fixCase(trim($pas_gp->FN1 . ' ' . $pas_gp->FN2));
					$contact->last_name = $this->fixCase($pas_gp->SN);
					$contact->title = $this->fixCase($pas_gp->TITLE);
					if (trim($pas_gp->TEL_1)) {
						$contact->primary_phone = trim($pas_gp->TEL_1);
					} else {
						$contact->primary_phone = 'Unknown';
					}

					// Address
					$address1 = array();
					if ($pas_gp->ADD_NAM) {
						$address1[] = $this->fixCase(trim($pas_gp->ADD_NAM));
					}
					$address1[] = $this->fixCase(trim($pas_gp->ADD_NUM . ' ' . $pas_gp->ADD_ST));
					$address1 = implode("\n",$address1);
					$address2 = $this->fixCase($pas_gp->ADD_DIS);
					$city = $this->fixCase($pas_gp->ADD_TWN);
					$postcode = strtoupper($pas_gp->PC);
					if (trim(implode('',array($address1, $address2, $city, $postcode)))) {
						if (!$address = $contact->address) {
							$address = new Address();
						}
						$address->address1 = $address1;
						$address->address2 = $address2;
						$address->city = $city;
						$address->county = $this->fixCase($pas_gp->ADD_CTY);
						$address->postcode = $postcode;
						$address->country_id = 1;
					} else {
						// Address doesn't look useful, so we'll delete it
						if ($address = $contact->address) {
							$address->delete();
							$address = null;
						}
					}

					// Save
					if (!$gp->save()) {
						throw new CException('Cannot save gp: '.print_r($gp->getErrors(),true));
					}

					if (!$contact->save()) {
						throw new CException('Cannot save gp contact: '.print_r($contact->getErrors(),true));
					}

					$gp->contact_id = $contact->id;
					if (!$gp->save()) {
						throw new Exception("Unable to save gp: ".print_r($gp->getErrors(),true));
					}

					if ($address) {
						$address->contact_id = $contact->id;
						if (!$address->save()) {
							throw new CException('Cannot save gp contact address: '.print_r($address->getErrors(),true));
						}
					} else {
						Yii::log("GP has no address|id: {$gp->id}, obj_prof: {$gp->obj_prof}", 'warning', 'application.action');
					}

					$assignment->internal_id = $gp->id;
					if (!$assignment->save()) {
						throw new CException('Cannot save gp assignment: '.print_r($assignment->getErrors(),true));
					}

				} else {

					// GP not in PAS (or at least no active records), so we should remove it and it's assignment from OpenEyes
					Yii::log('GP not found in PAS', 'trace');
					if ($assignment->id) {
						Yii::log('Deleting PasAssignment: id: '.$assignment->id, 'trace');
						$assignment->delete();
					}
					if ($gp->id) {
						Yii::log('Removing Gp association from patients', 'trace');
						$patients = Patient::model()->noPas()->findAll('gp_id = :gp_id', array(':gp_id' => $gp->id));
						$patients_updated = 0;
						foreach ($patients as $patient) {
							$patient->gp_id = null;
							if (!$patient->save()) {
								throw new CException('Cannot save patient: '.print_r($patient->getErrors(),true));
							}
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
	 * Update CCG CommissioningBody from PAS
	 * @param string $code
	 * @return CommissioningBody
	 */
	public function updateCcgFromPas($code)
	{

		// There must be a CommissioningBodyType for CCG
		if(!$ccg_type = CommissioningBodyType::model()->find('shortname = ?', array('CCG'))) {
			throw new CException('Cannot find CommissioningBodyType');
		}

		// Get the CCG data from PAS
		if(!$pas_ccg = PAS_CCG::model()->findByExternalId($code)) {
			return null;
		}

		// Commissioning body
		if (!$commissioning_body = CommissioningBody::model()->find('commissioning_body_type_id = :type_id AND code = :code',
		array(':type_id' => $ccg_type->id, ':code' => $code))) {
			$commissioning_body = new CommissioningBody;
			$commissioning_body->commissioning_body_type_id = $ccg_type->id;
			$commissioning_body->code = $code;
		}
		$commissioning_body->name = $pas_ccg->OBJ_DESC;

		// Contact
		if (!$contact = $commissioning_body->contact) {
			$contact = new Contact;
		}
		if (!$contact->save()) {
			throw new CException("Unable to save CommissioningBody contact: ".print_r($contact->getErrors(),true));
		}
		$commissioning_body->contact_id = $contact->id;

		// Address
		$address1 = array();
		if ($pas_ccg->ADD_NAM) {
			$address1[] = $this->fixCase(trim($pas_ccg->ADD_NAM));
		}
		$address1[] = $this->fixCase(trim($pas_ccg->ADD_NUM . ' ' . $pas_ccg->ADD_ST));
		$address1 = implode("\n",$address1);
		$address2 = $this->fixCase($pas_ccg->ADD_DIS);
		$city = $this->fixCase($pas_ccg->ADD_TWN);
		$postcode = strtoupper($pas_ccg->PC);
		if (trim(implode('',array($address1, $address2, $city, $postcode)))) {
			if (!$address = $contact->address) {
				$address = new Address();
				$address->contact_id = $contact->id;
			}
			$address->address1 = $address1;
			$address->address2 = $address2;
			$address->city = $city;
			$address->county = $this->fixCase($pas_ccg->ADD_CTY);
			$address->postcode = $postcode;
			$address->country_id = 1;
			if (!$address->save()) {
				throw new CException('Cannot save CommissioningBody address: '.print_r($address->getErrors(),true));
			}
		} else {
			// Address doesn't look useful, so we'll delete it
			if ($address = $contact->address) {
				$address->delete();
				$address = null;
			}
			Yii::log("CommissioningBody has no address|id: {$commissioning_body->id}, code: {$commissioning_body->code}", 'warning', 'application.action');
		}

		// Save
		if (!$commissioning_body->save()) {
			throw new CException('Cannot save CommissioningBody: '.print_r($commissioning_body->getErrors(),true));
		}

		// Associate service with the new commissioning body, if there is one
		if ($cbs = CommissioningBodyService::model()->find('code=?',array($commissioning_body->code))) {
			$cbs->commissioning_body_id = $commissioning_body->id;
			if (!$cbs->save()) {
				throw new Exception("Unable to save CommissioningBodyService: ".print_r($cbs->getErrors(),true));
			}
		}

		return $commissioning_body;

	}

	/**
	 * Update Practice from PAS
	 * @param Practice $practice
	 */
	public function updatePracticeFromPas($practice, $assignment)
	{
		if ($this->isAvailable()) {
			try {
				Yii::log("Pulling data from PAS for Practice: Practice->id: {$practice->id}, PasAssignment->id: {$assignment->id}, PasAssignment->external_id: {$assignment->external_id}", 'trace');
				if (!$assignment->external_id) {
					// Without an external ID we have no way of looking up the practice in PAS
					throw new CException('Practice assignment has no external ID');
				}
				if ($pas_practice = $assignment->external) {
					Yii::log('Found Pracice in PAS', 'trace');
					$practice->code = $pas_practice->OBJ_LOC;
					if (trim($pas_practice->TEL_1)) {
						$practice->phone = trim($pas_practice->TEL_1);
					} else {
						$practice->phone = 'Unknown';
					}

					// Contact
					if (!$contact = $practice->contact) {
						$contact = new Contact;
					}
					$contact->primary_phone = $practice->phone;

					if (!$contact->save()) {
						throw new Exception("Unable to save practice contact: ".print_r($contact->getErrors(),true));
					}
					$practice->contact_id = $contact->id;

					// Address
					$address1 = array();
					if ($pas_practice->ADD_NAM) {
						$address1[] = $this->fixCase(trim($pas_practice->ADD_NAM));
					}
					$address1[] = $this->fixCase(trim($pas_practice->ADD_NUM . ' ' . $pas_practice->ADD_ST));
					$address1 = implode("\n",$address1);
					$address2 = $this->fixCase($pas_practice->ADD_DIS);
					$city = $this->fixCase($pas_practice->ADD_TWN);
					$postcode = strtoupper($pas_practice->PC);
					if (trim(implode('',array($address1, $address2, $city, $postcode)))) {
						if (!$address = $contact->address) {
							$address = new Address();
							$address->contact_id = $contact->id;
						}
						$address->address1 = $address1;
						$address->address2 = $address2;
						$address->city = $city;
						$address->county = $this->fixCase($pas_practice->ADD_CTY);
						$address->postcode = $postcode;
						$address->country_id = 1;
					} else {
						// Address doesn't look useful, so we'll delete it
						if ($address = $contact->address) {
							$address->delete();
							$address = null;
						}
					}

					// Update assigned CCG
					if($pas_practice->OWN_OBJ && $commissioning_body = $this->updateCcgFromPas($pas_practice->OWN_OBJ)) {
						if(!$ccg_assignment = CommissioningBodyPracticeAssignment::model()
							->find('commissioning_body_id = :commissioning_body_id AND practice_id = :practice_id',
								array(':commissioning_body_id' => $commissioning_body->id, ':practice_id' => $practice->id))) {
							$ccg_assignment = new CommissioningBodyPracticeAssignment;
							$ccg_assignment->practice_id = $practice->id;
							$ccg_assignment->commissioning_body_id = $commissioning_body->id;
							$ccg_assignment->save();
						}
					}

					// Remove any other CCG assignments
					$criteria = new CDbCriteria();
					$criteria->condition = 'practice_id = :practice_id AND commissioning_body_type.shortname = :commissioning_body_type';
					$criteria->join = 'JOIN commissioning_body ON commissioning_body.id = t.commissioning_body_id JOIN commissioning_body_type ON commissioning_body_type.id = commissioning_body.commissioning_body_type_id';
					$criteria->params = array(':practice_id' => $practice->id, ':commissioning_body_type' => 'CCG');
					if(isset($commissioning_body)) {
						$criteria->condition .= ' AND commissioning_body_id != :id';
						$criteria->params[':id'] = $commissioning_body->id;
					}
					$other_ccgs = array();
					foreach(CommissioningBodyPracticeAssignment::model()->findAll($criteria) as $other_ccg) {
						$other_ccgs[] = $other_ccg->id;
					}
					CommissioningBodyPracticeAssignment::model()->deleteByPk($other_ccgs);

					// Save
					if (!$practice->save()) {
						throw new CException('Cannot save practice: '.print_r($practice->getErrors(),true));
					}

					if ($address) {
						if (!$address->save()) {
							throw new CException('Cannot save practice address: '.print_r($address->getErrors(),true));
						}
					} else {
						Yii::log("Practice has no address|id: {$practice->id}, code: {$practice->code}", 'warning', 'application.action');
					}

					$assignment->internal_id = $practice->id;
					if (!$assignment->save()) {
						throw new CException('Cannot save practice assignment: '.print_r($assignment->getErrors(),true));
					}

				} else {
					// Practice not in PAS (or at least no active records), so we should remove it and it's assignment from OpenEyes
					Yii::log('Practice not found in PAS', 'trace');
					if ($assignment->id) {
						Yii::log('Deleting PasAssignment: id: '.$assignment->id, 'trace');
						$assignment->delete();
					}
					if ($practice->id) {
						Yii::log('Removing Practice association from patients', 'trace');
						$patients = Patient::model()->noPas()->findAll('practice_id = :practice_id', array(':practice_id' => $practice->id));
						$patients_updated = 0;
						foreach ($patients as $patient) {
							$patient->practice_id = null;
							if (!$patient->save()) {
								throw new CException('Cannot save patient: '.print_r($patient->getErrors(),true));
							}
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

	public function handlePASException($e)
	{
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

	public function updatePatientsFromPas($patients)
	{
		$patient_ids = array();
		foreach ($patients as $patient) {
			$patient_ids[$patient->id] = $patient->id;
		}
		$criteria = new CDbCriteria();
		$criteria->addInCondition('internal_id', $patient_ids);
		$criteria->compare('internal_type', 'Patient');
		$assignments = $this->assign->findAll($criteria);
		$rm_patient_numbers = array();
		foreach ($assignments as $assignment) {
			$rm_patient_numbers[] = $assignment->external_id;
		}
		$criteria = new CDbCriteria();
		$criteria->addInCondition('RM_PATIENT_NO', $rm_patient_numbers);
		$pas_patients = PAS_Patient::model()->findAll($criteria);
		// TODO: Finish!
	}

	/**
	 * Update patient from PAS
	 * @param Patient $patient
	 * @param PasPatientAssignment $assignment
	 */
	public function updatePatientFromPas($patient, $assignment)
	{
		if (!$this->isAvailable()) return;

		try {
			Yii::log("Pulling data from PAS for Patient: Patient->id: {$patient->id}, PasAssignment->id: {$assignment->id}, PasAssignment->external_id: {$assignment->external_id}", 'trace');
			if (!$assignment->external_id) {
				// Without an external ID we have no way of looking up the patient in PAS
				throw new CException("Patient assignment has no external ID: PasAssignment->id: {$assignment->id}");
			}
			if ($pas_patient = $assignment->external) {
				Yii::log("Found patient in PAS", 'trace');
				$patient_attrs = array(
						'gender' => $pas_patient->SEX,
						'dob' => $pas_patient->DATE_OF_BIRTH,
						'date_of_death' => $pas_patient->DATE_OF_DEATH,
				);
				if ($ethnic_group = EthnicGroup::model()->findByAttributes(array('code' => $pas_patient->ETHNIC_GRP))) {
					$patient_attrs['ethnic_group_id'] = $ethnic_group->id;
				} else {
					$patient_attrs['ethnic_group_id'] = null;
				}
				if ($hos_num = $pas_patient->hos_number) {
					$hos_num = $hos_num->NUM_ID_TYPE . $hos_num->NUMBER_ID;
					$patient_attrs['pas_key'] = $hos_num;
					$patient_attrs['hos_num'] = $hos_num;
				}
				if ($nhs_number = $pas_patient->nhs_number) {
					$patient_attrs['nhs_num'] = $nhs_number->NUMBER_ID;
				} else {
					$patient_attrs['nhs_num'] = '';
				}

				$patient->attributes = $patient_attrs;

				// Save
				if (!$patient->save()) {
					throw new CException('Cannot save patient: '.print_r($patient->getErrors(),true));
				}

				$contact = $patient->contact;
				$contact->title = $this->fixCase($pas_patient->name->TITLE);
				$contact->first_name = ($pas_patient->name->NAME1) ? $this->fixCase($pas_patient->name->NAME1) : '(UNKNOWN)';
				$contact->last_name = $this->fixCase($pas_patient->name->SURNAME_ID);
				if ($pas_patient->address) {
					// Get primary phone from patient's main address
					$contact->primary_phone = $pas_patient->address->TEL_NO;
				}
				if (!$contact->save()) {
					throw new CException('Cannot save patient contact: '.print_r($contact->getErrors(),true));
				}

				$assignment->internal_id = $patient->id;
				if (!$assignment->save()) {
					throw new CException('Cannot save patient assignment: '.print_r($assignment->getErrors(),true));
				}

				// Addresses
				if ($pas_patient->addresses) {
					// Matching addresses for update is tricky cos we don't have a primary key on the pas address table,
					// so we need to keep track of patient address ids as we go
					$matched_address_ids = array();
					foreach ($pas_patient->addresses as $pas_address) {

						// Match an address
						Yii::log("Looking for patient address: PAS_PatientAddress->POSTCODE: ".$pas_address->POSTCODE, 'trace');
						$matched_clause = ($matched_address_ids) ? ' AND id NOT IN ('.implode(',',$matched_address_ids).')' : '';
						$address = Address::model()->find(array(
								'condition' => "contact_id = :contact_id AND REPLACE(postcode,' ','') = :postcode" . $matched_clause,
								'params' => array(':contact_id' => $contact->id, ':postcode' => str_replace(' ','',$pas_address->POSTCODE)),
						));

						// Check if we have an address (that we haven't already matched)
						if (!$address) {
							Yii::log("Patient address not found, creating", 'trace');
							$address = new Address;
							$address->contact_id = $contact->id;
						}

						$this->updateAddress($address, $pas_address);
						if (!$address->save()) {
							throw new CException('Cannot save patient address: '.print_r($address->getErrors(),true));
						}
						$matched_address_ids[] = $address->id;
					}

					// Remove any orphaned addresses (expired?)
					$matched_string = implode(',',$matched_address_ids);
					$orphaned_addresses = Address::model()->deleteAll(array(
							'condition' => "contact_id = :contact_id AND id NOT IN($matched_string)",
							'params' => array(':contact_id' => $contact->id),
					));
					$matched_addresses = count($matched_address_ids);
					if ($orphaned_addresses) {
						Yii::log("$orphaned_addresses orphaned patient addresses were deleted", 'trace');
					}
					Yii::log("Patient has $matched_addresses valid addresses", 'trace');
				}

				// CCG assignment
				$commissioning_body = null;
				if($pas_patient->address && $ha_code = $pas_patient->address->HA_CODE) {
					if($commissioning_body = $this->updateCcgFromPas($ha_code)) {
						if(!$ccg_assignment = CommissioningBodyPatientAssignment::model()
							->find('commissioning_body_id = :commissioning_body_id AND patient_id = :patient_id',
								array(':commissioning_body_id' => $commissioning_body->id, ':patient_id' => $patient->id))) {
							$ccg_assignment = new CommissioningBodyPatientAssignment;
							$ccg_assignment->patient_id = $patient->id;
							$ccg_assignment->commissioning_body_id = $commissioning_body->id;
							$ccg_assignment->save();
						}
					}
				}

				// Remove any other CCG assignments
				$criteria = new CDbCriteria();
				$criteria->condition = 'patient_id = :patient_id AND commissioning_body_type.shortname = :commissioning_body_type';
				$criteria->params = array(':patient_id' => $patient->id, ':commissioning_body_type' => 'CCG');
				$criteria->join = 'JOIN commissioning_body ON commissioning_body.id = t.commissioning_body_id JOIN commissioning_body_type ON commissioning_body_type.id = commissioning_body.commissioning_body_type_id';
				if($commissioning_body) {
					$criteria->condition .= ' AND commissioning_body_id != :id';
					$criteria->params[':id'] = $commissioning_body->id;
				}
				$other_ccgs = array();
				foreach(CommissioningBodyPatientAssignment::model()->findAll($criteria) as $other_ccg) {
					$other_ccgs[] = $other_ccg->id;
				}
				CommissioningBodyPatientAssignment::model()->deleteByPk($other_ccgs);

				/* FIXME - Disabling due to errors and currently unused
				$pas_referrals = PAS_Referral::model()->findAll('X_CN=?',array($pas_patient['RM_PATIENT_NO']));

				if ($pas_referrals) {
					foreach ($pas_referrals as $pasReferral) {
						$this->updateReferralFromPAS($patient,$pasReferral);
					}
				}
				*/

				// Advisory locks cannot be nested so release patient lock here
				$assignment->unlock();

				// Get latest GP mapping from PAS
				$pas_patient_gp = $pas_patient->PatientGp;
				if ($pas_patient_gp) {
					Yii::log("Patient has GP record: PAS_PatientGps->GP_ID: {$pas_patient_gp->GP_ID}", 'trace');

					// Check if GP is not on our block list
					if ($this->isBadGp($pas_patient_gp->GP_ID)) {
						Yii::log("GP on blocklist, ignoring", 'trace');
						$patient->gp_id = null;
					} else {
						$gp_assignment = $this->assign->findByExternal('PAS_Gp', $pas_patient_gp->GP_ID);
						$gp = $gp_assignment->internal;
						if ($gp_assignment->isStale()) {
							$gp = $this->updateGpFromPas($gp, $gp_assignment);
						}
						$gp_assignment->unlock();

						// Update/set patient's GP
						$gp_id = ($gp) ? $gp->id : null;
						if ($patient->gp_id != $gp_id) {
							Yii::log("Patient's GP changed", 'trace');
							$patient->gp_id = $gp_id;
						} else {
							Yii::log("Patient's GP has not changed", 'trace');
						}

					}
					if (!$patient->gp_id && $pas_patient_gp->GP_ID) {
						Yii::log("Patient GP invalid or not found in PAS|id: {$patient->id}, hos_num: {$patient->hos_num}, gp_id: {$pas_patient_gp->GP_ID}", 'warning', 'application.action');
					} elseif (!$patient->gp_id) {
						Yii::log("Patient has no GP|id: {$patient->id}, hos_num: {$patient->hos_num}", 'warning', 'application.action');
					}

					// Check if the Practice is in openeyes
					Yii::log("Checking if Practice is in openeyes: PAS_PatientGps->PRACTICE_CODE: {$pas_patient_gp->PRACTICE_CODE}", 'trace');
					$practice_assignment = $this->assign->findByExternal('PAS_Practice', $pas_patient_gp->PRACTICE_CODE);
					$practice = $practice_assignment->internal;
					if ($practice_assignment->isStale()) {
						$practice = $this->updatePracticeFromPas($practice, $practice_assignment);
					}
					$practice_assignment->unlock();

					// Update/set patient's practice
					$practice_id = ($practice) ? $practice->id : null;
					if ($patient->practice_id != $practice_id) {
						Yii::log("Patient's practice changed", 'trace');
						$patient->practice_id = $practice_id;
					} else {
						Yii::log("Patient's practice has not changed", 'trace');
					}

					if (!$patient->practice_id && $pas_patient_gp->PRACTICE_CODE) {
						Yii::log("Patient Practice invalid or not found in PAS|id: {$patient->id}, hos_num: {$patient->hos_num}, practice_code: {$pas_patient_gp->PRACTICE_CODE}", 'warning', 'application.action');
					} elseif (!$patient->practice_id) {
						Yii::log("Patient has no Practice|id: {$patient->id}, hos_num: {$patient->hos_num}", 'warning', 'application.action');
					}

					if (!$patient->save()) {
						throw new CException('Cannot save patient: '.print_r($patient->getErrors(),true));
					}
				} else {
					Yii::log("Patient has no GP/practice in PAS", 'trace');
					Yii::log("Patient has no GP or Practice|id: {$patient->id}, hos_num: {$patient->hos_num}", 'warning', 'application.action');
				}
			} else {
				Yii::log("Patient with external ID '{$assignment->external_id}' not found in PAS", 'warning');

				$assignment->missing_from_pas = 1;
				$assignment->save();

				if (Yii::app() instanceof CWebApplication) {
					Yii::app()->user->setFlash('warning.pas_record_missing', 'Patient not found in PAS, some data may be out of date or incomplete');
				}
			}
		} catch (CDbException $e) {
			$this->handlePASException($e);
		}
	}

	public function updateReferralFromPAS($patient,$pasReferral) {
		if (!$referralType = ReferralType::model()->notDeleted()->find('code=?',array($pasReferral['SRCE_REF']))) {
			if (!$pasReferralType = PAS_ReferralType::model()->find('ULNKEY=? and code=?',array('SREF',$pasReferral['SRCE_REF']))) {
				throw new Exception("PAS referral apparently created with a non-existant code: {$pasReferral['REFNO']} / {$pasReferral['SRCE_REF']}");
			}
			$referralType = new ReferralType;
			$referralType->code = $pasReferral['SRCE_REF'];
			$referralType->name = $pasReferralType['DESCRIPT'];

			if (!$referralType->save()) {
				throw new Exception("Unable to save referral_type: ".print_r($referralType->getErrors(),true));
			}
		}

		if (!$ref_assignment = $this->assign->find('external_type=? and external_id=?',array('PAS_Referral',$pasReferral->REFNO))) {
			$ref_assignment = new PasAssignment;
			$ref_assignment->internal_type = 'Referral';
			$ref_assignment->external_type = 'PAS_Referral';
			$ref_assignment->external_id = $pasReferral->REFNO;

			$referral = new Referral;
			$referral->patient_id = $patient->id;
			$referral->refno = $pasReferral->REFNO;
		} elseif (!$referral = Referral::model()->findByPk($ref_assignment->internal_id)) {
			throw new Exception("Broken pas_assignment id {$ref_assignment->id} references non-existant Referral {$ref_assignment->internal_id}");
		}

		$referral->referral_type_id = $referralType->id;
		$referral->received_date = $pasReferral['DT_REC'];
		$referral->closed_date = $pasReferral['DT_CLOSE'] ? $pasReferral['DT_CLOSE'] : null;
		$referral->referrer = $pasReferral['REF_PERS'];

		if ($referral->referrer) {
			if (strlen($referral->referrer) == 4) {
				$criteria = new CDbCriteria;

				if ($subspecialty = Subspecialty::model()->with('serviceSubspecialtyAssignment')->find('ref_spec=?',array($pasReferral['REF_SPEC']))) {
					$criteria->addCondition('service_subspecialty_assignment_id = :ssa_id');
					$criteria->params[':ssa_id'] = $subspecialty->serviceSubspecialtyAssignment->id;

					$referral->service_subspecialty_assignment_id = $subspecialty->serviceSubspecialtyAssignment->id;
				}

				$criteria->addCondition('pas_code = :pas_code');
				$criteria->params[':pas_code'] = $referral->referrer;

				$firm_ids = array();
				foreach (Firm::model()->findAll($criteria) as $firm) {
					$firm_ids[] = $firm->id;
				}

				if (count($firm_ids) == 1) {
					$referral->firm_id = $firm_ids[0];
				}
			}

			if (!$referral->firm_id) {
				if (!$gp = Gp::model()->find('obj_prof=?',array($referral->referrer))) {
					// See if this is a GP in PAS
					if ($pas_gp = PAS_Gp::model()->find('obj_prof=?',array($referral->referrer))) {
						$gp = new Gp;
						$gp_assignment = new PasAssignment;
						$gp_assignment->internal_type = 'Gp';
						$gp_assignment->external_id = $pas_gp->OBJ_PROF;
						$gp_assignment->external_type = 'PAS_Gp';
						$gp = $this->updateGpFromPas($gp, $gp_assignment);
						$referral->gp_id = $gp->id;
					}
				} else {
					$referral->gp_id = $gp->id;
				}
			}
		}

		if (!$referral->save()) {
			throw new Exception("Unable to save referral: ".print_r($referral->getErrors(),true));
		}

		if (!$ref_assignment->id) {
			$ref_assignment->internal_id = $referral->id;
			if (!$ref_assignment->save()) {
				throw new Exception("Unable to save pas_assignment: ".print_r($ref_assignment->getErrors(),true));
			}
		}
	}

	/**
	 * Perform a search based on form $_POST data from the patient search page
	 * Search against PAS data and then import the data into OpenEyes database
	 * @param array $data
	 * @param integer $num_results
	 * @param integer $page
	 */
	public function search($data, $num_results = 20, $page = 1)
	{
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

			foreach ($results as $result) {
				// See if the patient is in openeyes, if not then fetch from PAS
				//Yii::log("Fetching assignment for patient: rm_patient_no:" . $result['RM_PATIENT_NO'], 'trace');
				$this->createOrUpdatePatient($result['RM_PATIENT_NO']);
			}
		} catch (CDbException $e) {
			$this->handlePASException($e);
		}
	}

	/**
	 * Try to find patient assignment in OpenEyes and if necessary create a new one and populate it from PAS
	 * @param string $rm_patient_no
	 * @param string $hos_num
	 */
	public function createOrUpdatePatient($rm_patient_no)
	{
		//Yii::log('Getting assignment','trace');
		$assignment = $this->assign->findByExternal('PAS_Patient', $rm_patient_no);

		if ($assignment->isNewRecord && ($patient = $this->checkForMergedPatient($assignment->external))) {
			$assignment->unlock();

			$old_assignment = $this->assign->findByInternal('Patient', $patient->id);
			if ($old_assignment) {  // Not a problem if missing, another process might have deleted it
				$this->updatePatientFromPas($patient, $old_assignment);

				if (!$old_assignment->missing_from_pas) {
					throw new Exception("Duplicate patient in PAS?  Patient ID: {$patient->id}, rm_patient_nos: {$old_assignment->external_id}, {$assignment->external_id}");
				}

				Yii::app()->user->setFlash('warning.pas_record_missing', null);

				$old_assignment->delete();
				$old_assignment->unlock();
			}

			// Re-obtain lock
			$assignment = $this->assign->findByExternal('PAS_Patient', $rm_patient_no);
			if ($assignment->isNewRecord) {  // ok, nobody else has written to it in the meantime
				$assignment->internal_id = $patient->id;
			}
		}

		if ($assignment->isStale()) {
			$patient = $assignment->internal;
			$new = $patient->isNewRecord;
			$this->updatePatientFromPas($patient, $assignment);
			if ($new && Yii::app()->params['mehpas_legacy_letters']) {
				Yii::import('application.modules.OphLeEpatientletter.models.*');
				$this->associateLegacyEvents($patient);
			}
		}
		$assignment->unlock();
	}

	protected function checkForMergedPatient(PAS_Patient $pas_patient)
	{
		// Look for existing patients with a matching hos_num
		$crit = new CDbCriteria;
		$crit->addInCondition('hos_num', $pas_patient->getAllHosNums());
		$crit->order = 'last_modified_date desc';
		$patient = Patient::model()->noPas()->find($crit);
		if (!$patient) return null;

		// Sanity checks
		if (strtotime($patient->dob) != strtotime($pas_patient->DATE_OF_BIRTH) ||
			$patient->contact->last_name != $this->fixCase($pas_patient->name->SURNAME_ID)) {
			Yii::log("Rejected patient ID {$patient->id} for merge with rm_patient_no {$pas_patient->RM_PATIENT_NO} after sanity checks", "trace");
			return null;
		}

		Yii::log("Selected patient ID {$patient->id} for merge with rm_patient_no {$pas_patient->RM_PATIENT_NO}", "trace");

		return $patient;
	}

	/**
	 * Update address info with the latest info from PAS
	 * @param Address $address The patient address model to be updated
	 * @param PAS_PatientAddress $data Data from PAS to store in the patient address model
	 */
	protected function updateAddress($address, $data)
	{
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
		if ($propertyName) {
			$address1[] = $propertyName;
		}
		if ($propertyNumber || $addr1) {
			$address1[] = trim($propertyNumber . ' ' . $addr1);
		}

		$address1 = implode("\n", $address1);

		// Create array of remaining address lines, from last to first
		$addressLines = array();
		foreach (array('POSTCODE','ADDR5','ADDR4','ADDR3','ADDR2') as $address_line) {
			if ($address_line_content = trim($data->{$address_line})) {
				$addressLines[] = $address_line_content;
			}
		}

		// See if we can find a country
		$country = null;
		$index = 0;
		while (!$country && $index < count($addressLines)) {
			$country = Country::model()->find('LOWER(name) = :name', array(':name' => strtolower($addressLines[$index])));
			$index++;
		}
		if ($country) {
			// Found a country, so we will remove the line from the address
			unset($addressLines[$index-1]);
		} else {
			// Cannot find country, so we assume it is UK
			$country = Country::model()->findByAttributes(array('name' => 'United Kingdom'));
		}

		$address2 = '';
		$town = '';
		$county = '';
		$postcode = '';
		if ($country->name == 'United Kingdom') {
			// We've got a UK address, so we'll see if we can parse the remaining tokens,

			// Instantiate a postcode utility object
			$postCodeUtility = new PostCodeUtility();

			// Set flags and default values
			$postCodeFound = false;
			$postCodeOuter = '';
			$townFound = false;
			$countyFound = false;

			// Go through array looking for likely candidates for postcode, town/city and county
			for ($index = 0; $index < count($addressLines); $index++) {
				if (!isset($addressLines[$index])) continue;

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
		} else {
			// We've got a non UK address, so we'll just try to store things whereever they fit
			if (trim($data->POSTCODE)) {
				$postcode = array_shift($addressLines);
			} else {
				$postcode = '';
			}
			if (count($addressLines)) {
				$address2 = array_pop($addressLines);
			}
			if (count($addressLines)) {
				$town = array_pop($addressLines);
			}
			if (count($addressLines)) {
				$county = implode(', ', $addressLines);
			}
		}


		// Store data
		$address->address1 = $this->fixCase($address1);
		$address->address2 = $this->fixCase($address2);
		$address->city = $this->fixCase($town);
		$address->county = $this->fixCase($county);
		$address->country_id = $country->id;
		$address->postcode = strtoupper($postcode);
		$address->address_type_id = $this->getAddressType($data->ADDR_TYPE);
		$address->date_start = $data->DATE_START;
		$address->date_end = $data->DATE_END;
	}

	public function getAddressType($addr_type)
	{
		switch ($addr_type) {
			case 'H': return AddressType::model()->find('name=?',array('Home'))->id;
			case 'C': return AddressType::model()->find('name=?',array('Correspondence'))->id;
			case 'T': return AddressType::model()->find('name=?',array('Transport'))->id;
		}

		return null;
	}

	public function associateLegacyEvents($patient)
	{
		if (Element_OphLeEpatientletter_EpatientLetter::model()->find('epatient_hosnum=?',array($patient->hos_num))) {
			if (!Episode::model()->find('patient_id=? and legacy=?',array($patient->id,1))) {
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

					if (strtotime($event->created_date) < $earliest) {
						$earliest = strtotime($event->created_date);
					}
				}

				$episode->start_date = date('Y-m-d H:i:s',$earliest);
			}
		}
	}

	protected function fixCase($string)
	{
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
