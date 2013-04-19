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

		if(Yii::app()->mehpas_buffer->getBuffering()) {
			Yii::log('Buffering', 'trace');
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
	
	public function bufferUpdates() {
		Yii::app()->mehpas_buffer->setBuffering(true);
	}

	public function processBuffer() {
		Yii::app()->mehpas_buffer->setBuffering(true);
	}

}
