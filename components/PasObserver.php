<?php
class PasObserver {

	/**
	 * Update patient from PAS
	 * @param array $params
	 */
	public function updatePatientFromPas($params) {

		// Check to see if patient is in "offline" mode
		$patient = $params['patient'];
		if(!$patient->use_pas){
			return;
		}

		// Check if stale
		$assignment = PasAssignment::model()->findByInternal('Patient', $patient->id);
		if($assignment && $assignment->isStale()) {

			// Assignment is stale (and locked ready for update)
			Yii::log('Patient details stale', 'trace');
			$pas_service = new PasService();
			if ($pas_service->isAvailable()) {
				$pas_service->updatePatientFromPas($patient, $assignment);
			} else {
				$pas_service->flashPasDown();
				$assignment->unlock();
			}

		} else if(!$assignment) {

			// Error, missing assignment
			Yii::log("Cannot find Patient assignment|id: {$patient->id}, hos_num: {$patient->hos_num}", 'warning', 'application.action');
			if (get_class(Yii::app()) == 'CConsoleApplication') {
				echo "Warning: unable to update patient $patient->hos_num from PAS (merged patient)\n";
			} else {
				Yii::app()->getController()->render('/error/errorPAS');
				Yii::app()->end();
			}

		}

	}

	/**
	 * Update GP from PAS
	 * @param array $params
	 */
	public function updateGpFromPas($params) {
		$gp = $params['gp'];

		// Check if stale
		$assignment = PasAssignment::model()->findByInternal('Gp', $gp->id);
		if($assignment && $assignment->isStale()) {

			// Assignment is stale (and locked ready for update)
			Yii::log('GP details stale', 'trace');
			$pas_service = new PasService();
			if ($pas_service->isAvailable()) {
				$pas_service->updateGpFromPas($gp, $assignment);
			} else {
				$pas_service->flashPasDown();
				$assignment->unlock();
			}

		} else if(!$assignment) {

			// Error, missing assignment
			Yii::log("Cannot find Gp assignment|id: {$gp->id}, obj_prof: {$gp->obj_prof}", 'warning', 'application.action');
			if (get_class(Yii::app()) == 'CConsoleApplication') {
				echo "Warning: unable to update gp $gp->obj_prof from PAS\n";
			} else {
				Yii::app()->getController()->render('/error/errorPAS');
				Yii::app()->end();
			}

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
				
			// Assignment is stale (and locked ready for update)
			Yii::log('Practice details stale', 'trace');
			$pas_service = new PasService();
			if ($pas_service->isAvailable()) {
				$pas_service->updatePracticeFromPas($practice, $assignment);
			} else {
				$pas_service->flashPasDown();
				$assignment->unlock();
			}
		} else if(!$assignment) {
				
			// Error, missing assignment
			Yii::log("Cannot find Practice assignment|id: {$practice->id}, code: {$practice->code}", 'warning', 'application.action');
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
		if($pas_service->isAvailable()) {
			$data = $params['params'];
			if ($params['patient']->hos_num) {
				$data['hos_num'] = $params['patient']->hos_num;
			} else {
				$data['nhs_num'] = $params['patient']->nhs_num;
			}
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
