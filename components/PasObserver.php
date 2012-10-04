<?php
class PasObserver {

	/**
	 * Update patient from PAS
	 * @param array $params
	 */
	public function updatePatientFromPas($params) {	
		$patient = $params['patient'];
		if(!$patient->use_pas){
			return;
		}

		$pas_service = new PasService();
		if ($pas_service->available) {
			if (!$assignment = PasAssignment::model()->findByInternal('Patient', $patient->id)) {
				if (get_class(Yii::app()) == 'CConsoleApplication') {
					echo "Warning: unable to update patient $patient->hos_num from PAS (merged patient)\n";
					return;
				}

				Yii::app()->getController()->render('/error/errorPAS');
				Yii::app()->end();
			}

			if ($assignment->isStale()) {
				Yii::log('Patient details stale', 'trace');
				$pas_service->updatePatientFromPas($patient, $assignment);
			}
		} else {
			$pas_service->flashPasDown();
		}
	}

	/**
	 * Update GP from PAS
	 * @param array $params
	 */
	public function updateGpFromPas($params) {
		$pas_service = new PasService();
		if ($pas_service->available) {
			$gp = $params['gp'];
			$assignment = PasAssignment::model()->findByInternal('Gp', $gp->id);
			if(!$assignment) {
				Yii::log('Creating new Gp assignment', 'trace');
				// Assignment doesn't exist yet, try to find PAS gp using obj_prof
				$obj_prof = $gp->obj_prof;
				$pas_gp = PAS_Gp::model()->find('obj_prof = :obj_prof', array(
						':obj_prof' => $obj_prof,
				));
				if($pas_gp) {
					$assignment = new PasAssignment();
					$assignment->internal_id = $gp->id;
					$assignment->internal_type = 'Gp';
					$assignment->external_id = $pas_gp->OBJ_PROF;
					$assignment->external_type = 'PAS_Gp';
				} else {
					throw new CException('Cannot map gp');
					// @TODO Push an alert that the patient cannot be mapped
				}
			}
			if($assignment->isStale()) {
				Yii::log('Gp details stale', 'trace');
				$pas_service->updateGpFromPas($gp, $assignment);
			}
		} else {
			$pas_service->flashPasDown();
		}
	}

	/**
	 * Update Practice from PAS
	 * @param array $params
	 */
	public function updatePracticeFromPas($params) {
		$practice = $params['practice'];
		
		// Check if stale
		$assignment = PasAssignment::model()->findByInternal('Practice', $practice->id);
		if($assignment && $assignment->isStale()) {
				Yii::log('Practice details stale', 'trace');
				$pas_service = new PasService();
				if ($pas_service->isAvailable()) {
					$pas_service->updatePracticeFromPas($practice, $assignment);
				} else {
					$pas_service->flashPasDown();
				}
		} else if(!$assignment) {
			
			// Error, missing assignment
			if (get_class(Yii::app()) == 'CConsoleApplication') {
				echo "Warning: unable to update practice $practice->code from PAS\n";
			} else {
				Yii::app()->getController()->render('/error/errorPAS');
				Yii::app()->end();
			}
		}
	}
	
	/**
	 * Search PAS for patient
	 * @param array $params
	 */
	public function searchPas($params) {
		$pas_service = new PasService();
		if($pas_service->available) {
			$data = $params['params'];
			$data['hos_num'] = $params['patient']->hos_num;
			$params['criteria'] = $pas_service->search($data, $params['params']['pageSize'], $params['params']['currentPage']);
		} else {
			$pas_service->flashPasDown();
		}
	}

	/**
	 * Fetch referral from PAS
	 * @param unknown_type $params
	 * @todo This method is currently disabled until the referral code is fixed
	 */
	public function fetchReferralFromPas($params) {
		return false;
		$pas_service = new PasService();
		if($pas_service->available) {
			$pas_service->fetchReferral($params['episode']);
		} else {
			$pas_service->flashPasDown();
		}
	}
}
