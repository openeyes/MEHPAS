<?php

class m140710_161316_unversion_pas_assignment extends OEMigration
{
	public function up()
	{
		$this->dropTable('pas_assignment_version');
	}

	public function down()
	{
		echo "m140710_161316_unversion_pas_assignment does not support migration down.\n";
		return false;
	}
}

