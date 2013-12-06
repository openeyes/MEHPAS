<?php

class m131206_150631_soft_deletion extends CDbMigration
{
	public function up()
	{
		$this->addColumn('pas_assignment','deleted','tinyint(1) unsigned not null');
		$this->addColumn('pas_assignment_version','deleted','tinyint(1) unsigned not null');
		$this->addColumn('pas_patient_merged','deleted','tinyint(1) unsigned not null');
		$this->addColumn('pas_patient_merged_version','deleted','tinyint(1) unsigned not null');
	}

	public function down()
	{
		$this->dropColumn('pas_assignment','deleted');
		$this->dropColumn('pas_assignment_version','deleted');
		$this->dropColumn('pas_patient_merged','deleted');
		$this->dropColumn('pas_patient_merged_version','deleted');
	}
}
