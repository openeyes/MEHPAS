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

class PasObserver
{
	/**
	 * Update patient from PAS
	 * @param array $params
	 */
	public function updatePatientFromPas($params)
	{
		// Check to see if patient is in "offline" mode
		$patient = $params['patient'];
		if (!$patient->use_pas) {
			return;
		}

		if (PHP_SAPI != 'cli') {
			// Check to see if we are buffering updates
			if (Yii::app()->mehpas_buffer->getBuffering()) {
				Yii::app()->mehpas_buffer->addPatient($patient);
				return;
			}
		}

		$pas_service = PasService::load();
		if ($pas_service->isAvailable()) {
			$assignment = PasAssignment::model()->findByInternal('Patient', $patient->id);
			if ($assignment) {
				if ($assignment->isStale()) {
					Yii::log('Patient details stale', 'trace');
					$pas_service->updatePatientFromPas($patient, $assignment);
				}
				$assignment->unlock();
			} elseif (!$assignment) {
				// Error, missing assignment
				Yii::log("Cannot find Patient assignment|id: {$patient->id}, hos_num: {$patient->hos_num}", 'warning', 'application.action');
				if (get_class(Yii::app()) == 'CConsoleApplication') {
					echo "Warning: unable to update patient $patient->hos_num from PAS (merged patient)\n";
				} else {
					Yii::app()->getController()->render('//error/errorPAS');
					Yii::app()->end();
				}
			}
		}
		else {
			$pas_service->flashPasDown();
		}


	}

	/**
	 * Update GP from PAS
	 * @param array $params
	 */
	public function updateGpFromPas($params)
	{
		$gp = $params['gp'];
		if (!$gp->use_pas) return;

		// Check to see if we are buffering updates
		if (PHP_SAPI != 'cli') {
			if (Yii::app()->mehpas_buffer->getBuffering()) {
				Yii::app()->mehpas_buffer->addGp($gp);
				return;
			}
		}

		// Check if stale
		$assignment = PasAssignment::model()->findByInternal('Gp', $gp->id);
		if ($assignment) {
			if ($assignment->isStale()) {
				Yii::log('GP details stale', 'trace');
				$pas_service = PasService::load();
				if ($pas_service->isAvailable()) {
					$pas_service->updateGpFromPas($gp, $assignment);
				} else {
					$pas_service->flashPasDown();
				}
			}
			$assignment->unlock();

		} elseif (!$assignment) {
			// Error, missing assignment
			Yii::log("Cannot find Gp assignment|id: {$gp->id}, obj_prof: {$gp->obj_prof}", 'warning', 'application.action');
			if (get_class(Yii::app()) == 'CConsoleApplication') {
				echo "Warning: unable to update gp $gp->obj_prof from PAS\n";
			} else {
				Yii::app()->getController()->render('//error/errorPAS');
				Yii::app()->end();
			}

		}

	}

	/**
	 * Update Practice from PAS
	 * @param array $params
	 */
	public function updatePracticeFromPas($params)
	{
		$practice = $params['practice'];
		if (!$practice->use_pas) return;

		// Check to see if we are buffering updates
		if (PHP_SAPI != 'cli') {
			if (Yii::app()->mehpas_buffer->getBuffering()) {
				Yii::app()->mehpas_buffer->addPractice($practice);
				return;
			}
		}

		// Check if stale
		$assignment = PasAssignment::model()->findByInternal('Practice', $practice->id);
		if ($assignment) {

			if ($assignment->isStale()) {
				Yii::log('Practice details stale', 'trace');
				$pas_service = PasService::load();
				if ($pas_service->isAvailable()) {
					$pas_service->updatePracticeFromPas($practice, $assignment);
				} else {
					$pas_service->flashPasDown();
				}
			}
			$assignment->unlock();

		} elseif (!$assignment) {

			// Error, missing assignment
			Yii::log("Cannot find Practice assignment|id: {$practice->id}, code: {$practice->code}", 'warning', 'application.action');
			if (get_class(Yii::app()) == 'CConsoleApplication') {
				echo "Warning: unable to update practice $practice->code from PAS\n";
			} else {
				Yii::app()->getController()->render('//error/errorPAS');
				Yii::app()->end();
			}
		}
	}

	/**
	 * Search PAS for patient
	 * @param array $params
	 */
	public function searchPas($params)
	{
		$pas_service = PasService::load();
		if ($pas_service->isAvailable()) {
			$data = $params['params'];
			if ($params['patient']->hos_num) {
				$data['hos_num'] = $params['patient']->hos_num;
			} else {
				$data['nhs_num'] = $params['patient']->nhs_num;
			}
			$pas_service->search($data, $params['params']['pageSize'], $params['params']['currentPage']);
		} else {
			$pas_service->flashPasDown();
		}
	}

	/**
	 * Fetch referral from PAS
	 * @param unknown_type $params
	 * @todo This method is currently disabled until the referral code is fixed
	 */
	public function fetchReferralFromPas($params)
	{
		return false;
		$pas_service = PasService::load();
		if ($pas_service->available) {
			$pas_service->fetchReferral($params['episode']);
		} else {
			$pas_service->flashPasDown();
		}
	}

	/**
	 * Start buffering PAS events so they can be processed as a batch job
	 * which should hopefully be more efficient
	 */
	public function bufferUpdates()
	{
		Yii::log('Starting PAS buffer','trace');
		Yii::app()->mehpas_buffer->setBuffering(true);
	}

	/**
	 * Process buffered PAS events
	 * @todo Currently this does nothing, emulating previous "no_pas" mode. We may want to improve on this in the future.
	 */
	public function processBuffer()
	{
		Yii::log('Processing PAS buffer','trace');
		Yii::app()->mehpas_buffer->setBuffering(false);
		/*
		$pas_service = PasService::load();
		if ($pas_service->isAvailable()) {
			$pas_service->updatePatientsFromPas(Yii::app()->mehpas_buffer->getPatients());
			$pas_service->updatePatientsFromPas(Yii::app()->mehpas_buffer->getPractices());
			$pas_service->updatePatientsFromPas(Yii::app()->mehpas_buffer->getGps());
		} else {
			$pas_service->flashPasDown();
		}
		*/
	}

}
