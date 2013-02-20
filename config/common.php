<?php
return array(
		'import' => array(
				'application.modules.mehpas.*',
				'application.modules.mehpas.components.*',
				'application.modules.mehpas.models.*',
		),
		'components' => array(
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
                                                'schemaCachingDuration' => 300,
                                                // Make oracle default date format the same as MySQL (default is DD-MMM-YY)
                                                'initSQLs' => array(
                                                                'ALTER SESSION SET NLS_DATE_FORMAT = \'YYYY-MM-DD\'',
                                                ),
                                                // Don't autoconnect, as many pages don't need access to PAS
                                                'autoConnect' => false,
                                ),
		),
		'params'=>array(
				'mehpas_cache_time' => 300,
		),
);

