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

return array(
	'import' => array(
		'application.modules.mehpas.*',
		'application.modules.mehpas.components.*',
		'application.modules.mehpas.models.*',
	),
	'components' => array(
		'mehpas_buffer' => array(
			'class' => 'PasUpdateBuffer',
		),
		'event' => array(
			'observers' => array(
				'patient_search_criteria' => array(
					'search_pas' => array(
						'class' => 'PasObserver',
						'method' => 'searchPas',
					),
				),
				'patient_after_find' => array(
					'update_from_pas' => array(
						'class' => 'PasObserver',
						'method' => 'updatePatientFromPas',
					),
				),
				'gp_after_find' => array(
					'update_from_pas' => array(
						'class' => 'PasObserver',
						'method' => 'updateGpFromPas',
					),
				),
				'practice_after_find' => array(
					'update_from_pas' => array(
						'class' => 'PasObserver',
						'method' => 'updatePracticeFromPas',
					),
				),
				'start_batch_mode' => array(
					'mehpas_buffer_updates' => array(
						'class' => 'PasObserver',
						'method' => 'bufferUpdates',
					),
				),
				'end_batch_mode' => array(
					'mehpas_process_buffer' => array(
						'class' => 'PasObserver',
						'method' => 'processBuffer',
					),
				),
				/* Referral code is currently broken
				 'episode_after_create' => array(
					'fetch_pas_referral' => array(
						'class' => 'PasObserver',
						'method' => 'fetchReferralFromPas',
					),
				 ),
				*/
			),
		),
		'db_pas' => array(
			'class' => 'CDbConnection',
			//'connectionString' => 'oci:dbname=remotename:1521/database',
			//'username' => 'root',
			//'password' => '',
			'schemaCachingDuration' => 86400,
			// Make oracle default date format the same as MySQL (default is DD-MMM-YY)
			'initSQLs' => array(
				'ALTER SESSION SET NLS_DATE_FORMAT = \'YYYY-MM-DD\'',
			),
			// Don't autoconnect, as many pages don't need access to PAS
			'autoConnect' => false,
		),
	),
	'params'=>array(
		'mehpas_enabled' => false, // Disabled by default
		'mehpas_importreferrals' => true, // whether referrals should be imported for patients.
		'mehpas_cache_time' => 300,
		'mehpas_bad_gps' => array(),
		'admin_menu' => array(
			//'Merged patients' => '/mehpas/admin/mergedPatients',
		),
	),
);
