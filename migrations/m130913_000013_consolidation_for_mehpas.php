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

class m130913_000013_consolidation_for_mehpas extends OEMigration
{
	private  $element_types ;

	public function setData(){
		$this->element_types = array(

		);
	}

	public function up()
	{
		if (!$this->consolidate(
			array(
				"m120327_154617_pas_assignment",
				"m130521_101040_pas_patient_merged",
			)
		)
		) {
			$this->createTables();
		}
	}

	public function createTables()
	{
		$this->setData();
		//disable foreign keys check
		$this->execute("SET foreign_key_checks = 0");

		Yii::app()->cache->flush();

		$this->execute("CREATE TABLE `pas_assignment` (
			  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `internal_id` int(10) unsigned NOT NULL,
			  `external_id` varchar(40) COLLATE utf8_bin NOT NULL,
			  `internal_type` varchar(40) COLLATE utf8_bin NOT NULL,
			  `external_type` varchar(40) COLLATE utf8_bin NOT NULL,
			  `created_user_id` int(10) unsigned NOT NULL DEFAULT '1',
			  `created_date` datetime NOT NULL,
			  `last_modified_user_id` int(10) unsigned NOT NULL DEFAULT '1',
			  `last_modified_date` datetime NOT NULL,
			  PRIMARY KEY (`id`),
			  UNIQUE KEY `internal_key` (`internal_id`,`internal_type`),
			  UNIQUE KEY `external_key` (`external_id`,`external_type`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin
		");

		$this->execute("CREATE TABLE `pas_patient_merged` (
			  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `patient_id` int(10) unsigned NOT NULL,
			  `new_hos_num` varchar(40) COLLATE utf8_bin DEFAULT NULL,
			  `new_rm_patient_no` varchar(40) COLLATE utf8_bin NOT NULL,
			  `new_first_name` varchar(255) COLLATE utf8_bin NOT NULL,
			  `new_last_name` varchar(255) COLLATE utf8_bin NOT NULL,
			  `last_modified_date` datetime NOT NULL DEFAULT '1900-01-01 00:00:00',
			  `last_modified_user_id` int(10) unsigned NOT NULL DEFAULT '1',
			  `created_user_id` int(10) unsigned NOT NULL DEFAULT '1',
			  `created_date` datetime NOT NULL DEFAULT '1900-01-01 00:00:00',
			  PRIMARY KEY (`id`),
			  KEY `pas_patient_merged_last_modified_user_id_fk` (`last_modified_user_id`),
			  KEY `pas_patient_merged_created_user_id_fk` (`created_user_id`),
			  KEY `pas_patient_merged_patient_id_fk` (`patient_id`),
			  CONSTRAINT `pas_patient_merged_created_user_id_fk` FOREIGN KEY (`created_user_id`) REFERENCES `user` (`id`),
			  CONSTRAINT `pas_patient_merged_last_modified_user_id_fk` FOREIGN KEY (`last_modified_user_id`) REFERENCES `user` (`id`),
			  CONSTRAINT `pas_patient_merged_patient_id_fk` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin
		");

		$migrations_path = dirname(__FILE__);
		$this->initialiseData($migrations_path);

		//enable foreign keys check
		$this->execute("SET foreign_key_checks = 1");

	}

	public function down()
	{
		echo "Down method not supported on consolidation";
	}
}
