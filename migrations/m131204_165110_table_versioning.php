<?php

class m131204_165110_table_versioning extends OEMigration
{
	public function up()
	{
		$this->versionExistingTable('pas_assignment');
		$this->versionExistingTable('pas_patient_merged');
	}

	public function down()
	{
		$this->dropTable('pas_assignment_version');
		$this->dropTable('pas_patient_merged_version');
	}
}
