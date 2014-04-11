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
 * This is the model class for table "SILVER.OUT040_REFDETS".
 *
 * The followings are the available columns in table 'SILVER.OUT040_REFDETS':
 * @property integer $REFNO
 * @property integer $X_CN
 * @property string $DATEX
 * @property string $TIMEX
 * @property string $METHOD
 * @property string $DT_REC
 * @property string $REC_LOC
 * @property string $SRCE_REF
 * @property string $S_TYPE
 * @property string $SOURCE
 * @property string $P_TYPE
 * @property string $REF_PERS
 * @property string $DISCIP
 * @property string $REASON
 * @property string $PRIORITY
 * @property string $CANCER
 * @property string $CAN_TYPE
 * @property string $REF
 * @property string $REF_TO
 * @property string $REF_SPEC
 * @property string $REF_TEXT
 * @property string $CUR_LOC
 * @property string $DIAGTYPE
 * @property string $DIAGCODE
 * @property string $DIAGTEXT
 * @property string $DT_CLOSE
 * @property string $TM_CLOSE
 * @property string $PC_TYPE
 * @property string $CLS_PERS
 * @property string $CLS_REAS
 * @property string $HDDR_GROUP
 * @property integer $USED_COUNT
 * @property string $USRCODET
 * @property string $USRCODEC_1
 * @property string $USRCODEC_2
 * @property string $USRCODEC_3
 * @property string $USRCODEC_4
 * @property string $USRCODEC_5
 * @property string $USRCODEC_6
 * @property string $USRCODEC_7
 * @property string $USRCODEC_8
 * @property string $USRCODEC_9
 * @property string $USRCODEC_10
 * @property string $REF_ORG
 * @property string $RTT
 */
class PAS_Referral extends PasAssignedEntity
{
	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'SILVER.OUT040_REFDETS';
	}

	public function primaryKey()
	{
		return 'REFNO';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
				array('REFNO, X_CN, USED_COUNT', 'numerical', 'integerOnly'=>true),
				array('TIMEX, TM_CLOSE', 'length', 'max'=>5),
				array('METHOD, SRCE_REF, S_TYPE, P_TYPE, REASON, PRIORITY, DIAGTYPE, PC_TYPE, USRCODET', 'length', 'max'=>4),
				array('REC_LOC, SOURCE, REF_PERS, DISCIP, REF_TO, REF_SPEC, DIAGCODE, CLS_PERS, REF_ORG', 'length', 'max'=>10),
				array('CANCER, RTT', 'length', 'max'=>1),
				array('CAN_TYPE', 'length', 'max'=>2),
				array('REF', 'length', 'max'=>17),
				array('REF_TEXT, DIAGTEXT', 'length', 'max'=>4000),
				array('CUR_LOC, CLS_REAS', 'length', 'max'=>8),
				array('HDDR_GROUP', 'length', 'max'=>48),
				array('USRCODEC_1, USRCODEC_2, USRCODEC_3, USRCODEC_4, USRCODEC_5, USRCODEC_6, USRCODEC_7, USRCODEC_8, USRCODEC_9, USRCODEC_10', 'length', 'max'=>12),
				array('DATEX, DT_REC, DT_CLOSE', 'safe'),
				// The following rule is used by search().
				// Please remove those attributes that should not be searched.
				array('REFNO, X_CN, DATEX, TIMEX, METHOD, DT_REC, REC_LOC, SRCE_REF, S_TYPE, SOURCE, P_TYPE, REF_PERS, DISCIP, REASON, PRIORITY, CANCER, CAN_TYPE, REF, REF_TO, REF_SPEC, REF_TEXT, CUR_LOC, DIAGTYPE, DIAGCODE, DIAGTEXT, DT_CLOSE, TM_CLOSE, PC_TYPE, CLS_PERS, CLS_REAS, HDDR_GROUP, USED_COUNT, USRCODET, USRCODEC_1, USRCODEC_2, USRCODEC_3, USRCODEC_4, USRCODEC_5, USRCODEC_6, USRCODEC_7, USRCODEC_8, USRCODEC_9, USRCODEC_10, REF_ORG, RTT', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
			'pas_ref_type' => array(self::BELONGS_TO, 'PAS_ReferralType', array('SRCE_REF' => 'CODE'), 'on' => '"pas_ref_type"."ULNKEY" = \'SREF\''),
			'patient' => array(self::BELONGS_TO, 'PAS_Patient', 'X_CN'),
			'pas_rtts' => array(self::HAS_MANY, 'PAS_RTT', 'REF_NO'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
				'REFNO' => 'Refno',
				'X_CN' => 'X Cn',
				'DATEX' => 'Datex',
				'TIMEX' => 'Timex',
				'METHOD' => 'Method',
				'DT_REC' => 'Dt Rec',
				'REC_LOC' => 'Rec Loc',
				'SRCE_REF' => 'Srce Ref',
				'S_TYPE' => 'S Type',
				'SOURCE' => 'Source',
				'P_TYPE' => 'P Type',
				'REF_PERS' => 'Ref Pers',
				'DISCIP' => 'Discip',
				'REASON' => 'Reason',
				'PRIORITY' => 'Priority',
				'CANCER' => 'Cancer',
				'CAN_TYPE' => 'Can Type',
				'REF' => 'Ref',
				'REF_TO' => 'Ref To',
				'REF_SPEC' => 'Ref Spec',
				'REF_TEXT' => 'Ref Text',
				'CUR_LOC' => 'Cur Loc',
				'DIAGTYPE' => 'Diagtype',
				'DIAGCODE' => 'Diagcode',
				'DIAGTEXT' => 'Diagtext',
				'DT_CLOSE' => 'Dt Close',
				'TM_CLOSE' => 'Tm Close',
				'PC_TYPE' => 'Pc Type',
				'CLS_PERS' => 'Cls Pers',
				'CLS_REAS' => 'Cls Reas',
				'HDDR_GROUP' => 'Hddr Group',
				'USED_COUNT' => 'Used Count',
				'USRCODET' => 'Usrcodet',
				'USRCODEC_1' => 'Usrcodec 1',
				'USRCODEC_2' => 'Usrcodec 2',
				'USRCODEC_3' => 'Usrcodec 3',
				'USRCODEC_4' => 'Usrcodec 4',
				'USRCODEC_5' => 'Usrcodec 5',
				'USRCODEC_6' => 'Usrcodec 6',
				'USRCODEC_7' => 'Usrcodec 7',
				'USRCODEC_8' => 'Usrcodec 8',
				'USRCODEC_9' => 'Usrcodec 9',
				'USRCODEC_10' => 'Usrcodec 10',
				'REF_ORG' => 'Ref Org',
				'RTT' => 'Rtt',
		);
	}

	/**
	 * Wrapper function for searching for the referral from the PasAssignment object.
	 */
	public function findByExternalId($id)
	{
		return $this->findByPk($id);
	}
}
