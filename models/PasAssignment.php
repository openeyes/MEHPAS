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

/**
 * This is the model class for table "pas_assignment".
 *
 * The followings are the available columns in table 'pas_assignment':
 * @property string $id
 * @property string $external_id
 * @property string $external_type
 * @property integer $internal_id
 * @property string $internal_type
 * @property string $created_date
 * @property string $last_modified_date
 * @property string $created_user_id
 * @property string $last_modified_user_id
 *
 * The followings are the available model relations:
 * @property Patient $patient
 * @property PAS_Patient $pas_patient
 */
class PasAssignment extends BaseActiveRecordVersioned
{
	/**
	 * Default time (in seconds) before cached PAS details are considered stale
	 */
	const PAS_CACHE_TIME = 300;

	/**
	 * Returns the static model of the specified AR class.
	 * @return PasAssignment the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'pas_assignment';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
				array('external_id, external_type, internal_id, internal_type', 'required'),
				array('id, external_id, external_type internal_id, internal_type, created_date, last_modified_date, created_user_id, last_modified_user_id', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * Find or create associated internal record
	 *
	 * @return CActiveRecord
	 */
	public function getInternal()
	{
		if ($this->internal_id) {
			return self::model($this->internal_type)->noPas()->findByPk($this->internal_id);
		} else {
			return new $this->internal_type;;
		}
	}

	/**
	 * Get associated external record
	 * @return CActiveRecord
	 */
	public function getExternal()
	{
		return self::model($this->external_type)->findByExternalId($this->external_id);
	}

	/**
	 * Find association using internal details and lock if found
	 *
	 * @param string $internal_type
	 * @param integer $internal_id
	 * @return PasAssignment|null
	 */
	public function findByInternal($internal_type, $internal_id)
	{
		$record = $this->findByAttributes(array('internal_type' => $internal_type, 'internal_id' => $internal_id));
		if (!$record) return null;

		$this->lock($record->external_type, $record->external_id);
		if (!$record->refresh()) {
			$record->unlock();
			return null;
		}

		return $record;
	}

	/**
	 * Find or create association using external details and lock
	 *
	 * @param string $external_type
	 * @param string $external_id
	 * @return PasAssignment
	 */
	public function findByExternal($external_type, $external_id)
	{
		$this->lock($external_type, $external_id);

		$record = $this->findByAttributes(array('external_type' => $external_type, 'external_id' => $external_id));
		if (!$record) {
			$record = new PasAssignment;
			$record->external_type = $external_type;
			$record->external_id = $external_id;
			$record->internal_type = str_replace('PAS_', '', $external_type);
		}

		return $record;
	}

	/**
	 * Does this assignment need refreshing from PAS?
	 *
	 * @return boolean
	 */
	public function isStale()
	{
		if ($this->isNewRecord || $this->missing_from_pas) return true;

		$cache_time = (isset(Yii::app()->params['mehpas_cache_time'])) ? Yii::app()->params['mehpas_cache_time'] : self::PAS_CACHE_TIME;
		return strtotime($this->last_modified_date) < (time() - $cache_time);
	}

	/**
	 * Unlock assignment
	 */
	public function unlock()
	{
		$this->dbConnection->createCommand('SELECT RELEASE_LOCK(?)')->execute(array($this->getLockKey($this->external_type, $this->external_id)));
	}

	protected function lock($external_type, $external_id)
	{
		$cmd = $this->dbConnection->createCommand('SELECT GET_LOCK(?, 1)');
		$key = $this->getLockKey($external_type, $external_id);

		while (!$cmd->queryScalar(array($key)));
	}

	protected function getLockKey($external_type, $external_id)
	{
		return "openeyes.mehpas.{external_type}:{external_id}";
	}
}
