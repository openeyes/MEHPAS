<?php

class m140416_151630_missing_from_pas_version extends CDbMigration
{
	public function up()
	{
		$this->addColumn("pas_assignment_version", "missing_from_pas", "boolean not null default 0 after external_type");
	}

	public function down()
	{
		$this->dropColumn("pas_assignment_version", "missing_from_pas");
	}
}
