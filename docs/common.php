<?php
return array(
		'components' => array(
				'db_pas' => array(
						'connectionString' => 'oci:dbname=remotename:1521/database',
						'username' => 'root',
						'password' => '',
				),
		),
		'params'=>array(
				'mehpas_enabled' => true,
				//'mehpas_cache_time' => 300,
				//'mehpas_bad_gps' => array(),
		),
);

