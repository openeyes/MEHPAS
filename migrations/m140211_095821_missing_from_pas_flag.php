<?php

class m140211_095821_missing_from_pas_flag extends CDbMigration
{
	public function up()
	{
		$this->addColumn("pas_assignment", "missing_from_pas", "boolean not null default 0 after external_type");
	}

	public function down()
	{
		$this->dropColumn("pas_assignment", "missing_from_pas");
	}
}
