<?php

class m131204_165110_table_versioning extends CDbMigration
{
	public function up()
	{
		$this->execute("
CREATE TABLE `pas_assignment_version` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`internal_id` int(10) unsigned NOT NULL,
	`external_id` varchar(40) NOT NULL,
	`internal_type` varchar(40) NOT NULL,
	`external_type` varchar(40) NOT NULL,
	`created_user_id` int(10) unsigned NOT NULL DEFAULT '1',
	`created_date` datetime NOT NULL,
	`last_modified_user_id` int(10) unsigned NOT NULL DEFAULT '1',
	`last_modified_date` datetime NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `internal_key` (`internal_id`,`internal_type`),
	UNIQUE KEY `external_key` (`external_id`,`external_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
		");

		$this->alterColumn('pas_assignment_version','id','int(10) unsigned NOT NULL');
		$this->dropPrimaryKey('id','pas_assignment_version');

		$this->createIndex('pas_assignment_aid_fk','pas_assignment_version','id');
		$this->addForeignKey('pas_assignment_aid_fk','pas_assignment_version','id','pas_assignment','id');

		$this->addColumn('pas_assignment_version','version_date',"datetime not null default '1900-01-01 00:00:00'");

		$this->addColumn('pas_assignment_version','version_id','int(10) unsigned NOT NULL');
		$this->addPrimaryKey('version_id','pas_assignment_version','version_id');
		$this->alterColumn('pas_assignment_version','version_id','int(10) unsigned NOT NULL AUTO_INCREMENT');

		$this->execute("
CREATE TABLE `pas_patient_merged_version` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`patient_id` int(10) unsigned NOT NULL,
	`new_hos_num` varchar(40) DEFAULT NULL,
	`new_rm_patient_no` varchar(40) NOT NULL,
	`new_first_name` varchar(255) NOT NULL,
	`new_last_name` varchar(255) NOT NULL,
	`last_modified_date` datetime NOT NULL DEFAULT '1900-01-01 00:00:00',
	`last_modified_user_id` int(10) unsigned NOT NULL DEFAULT '1',
	`created_user_id` int(10) unsigned NOT NULL DEFAULT '1',
	`created_date` datetime NOT NULL DEFAULT '1900-01-01 00:00:00',
	PRIMARY KEY (`id`),
	KEY `acv_pas_patient_merged_last_modified_user_id_fk` (`last_modified_user_id`),
	KEY `acv_pas_patient_merged_created_user_id_fk` (`created_user_id`),
	KEY `acv_pas_patient_merged_patient_id_fk` (`patient_id`),
	CONSTRAINT `acv_pas_patient_merged_created_user_id_fk` FOREIGN KEY (`created_user_id`) REFERENCES `user` (`id`),
	CONSTRAINT `acv_pas_patient_merged_last_modified_user_id_fk` FOREIGN KEY (`last_modified_user_id`) REFERENCES `user` (`id`),
	CONSTRAINT `acv_pas_patient_merged_patient_id_fk` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
		");

		$this->alterColumn('pas_patient_merged_version','id','int(10) unsigned NOT NULL');
		$this->dropPrimaryKey('id','pas_patient_merged_version');

		$this->createIndex('pas_patient_merged_aid_fk','pas_patient_merged_version','id');
		$this->addForeignKey('pas_patient_merged_aid_fk','pas_patient_merged_version','id','pas_patient_merged','id');

		$this->addColumn('pas_patient_merged_version','version_date',"datetime not null default '1900-01-01 00:00:00'");

		$this->addColumn('pas_patient_merged_version','version_id','int(10) unsigned NOT NULL');
		$this->addPrimaryKey('version_id','pas_patient_merged_version','version_id');
		$this->alterColumn('pas_patient_merged_version','version_id','int(10) unsigned NOT NULL AUTO_INCREMENT');

		$this->addColumn('pas_assignment','deleted','tinyint(1) unsigned not null');
		$this->addColumn('pas_assignment_version','deleted','tinyint(1) unsigned not null');
		$this->addColumn('pas_patient_merged','deleted','tinyint(1) unsigned not null');
		$this->addColumn('pas_patient_merged_version','deleted','tinyint(1) unsigned not null');
	}

	public function down()
	{
		$this->dropColumn('pas_assignment','deleted');
		$this->dropColumn('pas_patient_merged','deleted');

		$this->dropTable('pas_assignment_version');
		$this->dropTable('pas_patient_merged_version');
	}
}
