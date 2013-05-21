<?php

class m130521_101040_pas_patient_merged extends CDbMigration
{
	public function up()
	{
		$this->createTable('pas_patient_merged',array(
				'id' => 'int(10) unsigned NOT NULL AUTO_INCREMENT',
				'patient_id' => 'int(10) unsigned NOT NULL',
				'new_hos_num' => 'varchar(40) COLLATE utf8_bin DEFAULT NULL',
				'new_rm_patient_no' => 'varchar(40) COLLATE utf8_bin NOT NULL',
				'new_first_name' => 'varchar(255) COLLATE utf8_bin NOT NULL',
				'new_last_name' => 'varchar(255) COLLATE utf8_bin NOT NULL',
				'last_modified_date' => 'datetime NOT NULL DEFAULT \'1900-01-01 00:00:00\'',
				'last_modified_user_id' => 'int(10) unsigned NOT NULL DEFAULT \'1\'',
				'created_user_id' => 'int(10) unsigned NOT NULL DEFAULT \'1\'',
				'created_date' => 'datetime NOT NULL DEFAULT \'1900-01-01 00:00:00\'',
				'PRIMARY KEY (`id`)',
				'KEY `pas_patient_merged_last_modified_user_id_fk` (`last_modified_user_id`)',
				'KEY `pas_patient_merged_created_user_id_fk` (`created_user_id`)',
				'KEY `pas_patient_merged_patient_id_fk` (`patient_id`)',
				'CONSTRAINT `pas_patient_merged_created_user_id_fk` FOREIGN KEY (`created_user_id`) REFERENCES `user` (`id`)',
				'CONSTRAINT `pas_patient_merged_last_modified_user_id_fk` FOREIGN KEY (`last_modified_user_id`) REFERENCES `user` (`id`)',
				'CONSTRAINT `pas_patient_merged_patient_id_fk` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`id`)',
			), 'ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin'
		);
	}

	public function down()
	{
		$this->dropTable('pas_patient_merged');
	}
}
