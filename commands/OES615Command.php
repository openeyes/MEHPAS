<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2012
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2012, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

class OES615Command extends CConsoleCommand {
	public $map = array(
		'1940107,0533070' => array(
			'episode_ids' => array('372847'),
		),
		'1185056,1598682' => array(
			'episode_ids' => array('331634'),
			'episode_status_ids' => array(4),
			'eye_ids' => array(1),
			'disorder_ids' => array('232009009'),
		),
		'1791712,1617060' => array(
			'episode_ids' => array('98902','86286'),
			'eye_ids' => array(1),
			'disorder_ids' => array('193570009'),
		),
		'1991982,1626508' => array(
			'episode_ids' => array('496894'),
		),
		'1690407,1689801' => array(
			'episode_ids' => array('267143'),
		),
		'1517425,1718255' => array(
			'episode_ids' => array('11873','75205'),
		),
		'1925808,1719041' => array(
			'episode_ids' => array('347183'),
		),
		'1959103,1720101' => array(
			'episode_ids' => array('413021'),
		),
		'1872811,1725461' => array(
			'episode_ids' => array('266404'),
			'episode_status_ids' => array(5),
		),
		'1917324,1817620' => array(
			'episode_ids' => array('326196'),
		),
		'1878441,1829722' => array(
			'episode_ids' => array('280575'),
			'episode_status_ids' => array(4),
			'eye_ids' => array(1),
			'disorder_ids' => array('193570009'),
		),
		'1795398,1845697' => array(
			'episode_ids' => array('162378'),
		),
		'1843147,1847982' => array(
			'episode_ids' => array('154068'),
		),
		'1875148,1874918' => array(
			'episode_ids' => array('225382'),
		),
		'1883691,1881437' => array(
			'episode_ids' => array('245142'),
			'episode_status_ids' => array(4),
			'eye_ids' => array(3),
			'disorder_ids' => array('193570009'),
		),
		'1980228,1883892' => array(
			'episode_ids' => array('455149'),
		),
		'1886072,1892170' => array(
			'episode_ids' => array('265548'),
		),
		'1940225,1935480' => array(
			'episode_ids' => array('410417'),
		),
		'1943874,1946829' => array(
			'episode_ids' => array('380838'),
		),
		'1952025,1950156' => array(
			'episode_ids' => array('397567'),
			'episode_status_ids' => array(5),
		),
		'1979826,1980980' => array(
			'episode_ids' => array('454084'),
			'episode_status_ids' => array(4),
		),
		'1885813,0256908' => array(
			'episode_ids' => array('262614'),
		),
		'1292435,0512002' => array(
			'episode_ids' => array('353624'),
		),
		'1517371,0532036' => array(
			'episode_ids' => array('239719'),
			'episode_status_ids' => array(6),
		),
		'1847084,0642999' => array(
			'episode_ids' => array('169821'),
		),
		'1961573,0876710' => array(
			'episode_ids' => array('223428'),
		),
		'1051857,0929712' => array(
			'episode_ids' => array('147384'),
		),
		'1900769,1030629' => array(
			'episode_ids' => array('284763'),
		),
		'1833889,1061072' => array(
			'episode_ids' => array('183002'),
			'episode_status_ids' => array(5),
		),
		'1907582,1062699' => array(
			'episode_ids' => array('302073'),
		),
		'1918923,1118235' => array(
			'episode_ids' => array('331663'),
		),
		'1921599,1185950' => array(
			'episode_ids' => array('335030'),
		),
		'1889134,1303755' => array(
			'episode_ids' => array('379583','301812'),
		),
		'1596962,1516605' => array(
			'episode_ids' => array('167151'),
			'episode_status_ids' => array(5),
			'eye_ids' => array(2),
			'disorder_ids' => array('314493000'),
		),
		'1762044,1763226' => array(
			'episode_ids' => array('6866'),
		),
		'1823670,1182367' => array(
			'episode_ids' => array('113879'),
		),
		'1433737,1446099' => array(
			'episode_ids' => array('456419'),
			'episode_status_ids' => array(4),
		),
		'1908469,1555535' => array(
			'episode_ids' => array('153543'),
		),
		'1839821,1702330' => array(
			'episode_ids' => array('133521'),
			'episode_status_ids' => array(4),
		),
		'1935939,1753746' => array(
			'episode_ids' => array('364856'),
			'episode_status_ids' => array(4),
		),
		'1815871,1804764' => array(
			'episode_ids' => array('17937'),
			'episode_status_ids' => array(4),
		),
		'1324649,1067938' => array(
			'episode_ids' => array('193687'),
		),
		'0927971,0489581' => array(
			'episode_ids' => array('132736'),
		),
		'0800930,1866559' => array(
			'episode_ids' => array('237973'),
		),
		'1551528,1294040' => array(
			'episode_ids' => array('267983','374962','459645'),
		),
	);

	public $ignore = array(
		'1614795,0162771',
		'1969672,0878450',
		'0999702,1302780',
	);

	public function run($args) {
		//1969672,0878450

		Yii::app()->db->createCommand("update audit set patient_id = 2214341 where patient_id = 2214312;
		update commissioning_body_patient_assignment set patient_id = 2214341 where patient_id = 2214312;
		update episode set patient_id = 2214341 where patient_id = 2214312;
		delete from patient where id = 2214312;
		delete from pas_assignment where id = 362199;")->query();

		//1614795,0162771
		Yii::app()->db->createCommand("update audit set patient_id = 2285291 where patient_id = 1996305;
		update commissioning_body_patient_assignment set patient_id = 2285291 where patient_id = 1996305;
		update episode set patient_id = 2285291 where patient_id = 1996305;
		delete from patient where id = 1996305;
		delete from pas_assignment where id = 131016;
		delete from audit where episode_id = 534509;
		delete from episode where id = 534509;")->query();

		$fp = fopen("/home/mark/merge.csv","r");

		fgetcsv($fp);

		while ($data = fgetcsv($fp)) {
			$data[0] = str_pad($data[0],7,'0',STR_PAD_LEFT);
			$data[1] = str_pad($data[1],7,'0',STR_PAD_LEFT);

			if ($patient = Yii::app()->db->createCommand()->select("*")->from("patient")->where("hos_num=:hos_num",array(":hos_num" => $data[0]))->queryRow()) {
				$new_patient = new Patient;
				$new_patient->hos_num = $data[1];

				$dataProvider = $new_patient->search(array(
					'currentPage' => 0,
					'pageSize' => 20,
					'sortBy' => 'hos_num*1',
					'sortDir'=> 'asc',
					'first_name' => '',
					'last_name' => '',
				));
				$dataProvider->getData();

				if (!$new_patient = Yii::app()->db->createCommand()->select("*")->from("patient")->where("hos_num=:hos_num",array(":hos_num" => $data[1]))->queryRow()) {
					if (!in_array($data[0].",".$data[1],$this->ignore)) {
						echo $data[0].",".$data[1]." (3)\n";
					}
					continue;
				}

				if ($patient['id'] == $new_patient['id']) {
					// mehpas did the merge for us
					continue;
				}

				$episodes = Yii::app()->db->createCommand("select * from episode where patient_id = {$patient['id']}")->queryAll();

				if (empty($episodes) || $this->remapEpisodes($episodes,$patient,$new_patient)) {
					Yii::app()->db->createCommand("delete from pas_patient_merged where patient_id = {$patient['id']}")->query();
					$this->mergeUser($patient['id'],$new_patient['id']);

					if (Yii::app()->db->createCommand()->select("*")->from("patient")->where("hos_num=:hos_num",array(":hos_num" => $data[0]))->queryRow()) {
						echo $data[0].",".$data[1]." (1)\n";
					}
				} else {
					echo $data[0].",".$data[1]." (2)\n";
				}
			}
		}

		echo "\n";
	}

	public function mergeUser($old_patient_id, $new_patient_id)
	{
		$max_adherence = Yii::app()->db->createCommand("select @maxMedAdherenceTimestamp := MAX(last_modified_date) from medication_adherence where patient_id in ($old_patient_id , $new_patient_id);")->queryScalar();
		Yii::app()->db->createCommand("delete from medication_adherence where patient_id in ($old_patient_id , $new_patient_id) and last_modified_date < '$max_adherence'; ")->query();
		Yii::app()->db->createCommand("update episode set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update secondary_diagnosis set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update audit set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update family_history set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update medication_adherence set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update pas_patient_merged set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update patient_allergy_assignment set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update patient_contact_assignment set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update patient_measurement set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update patient_oph_info set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update previous_operation set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update referral set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update secondary_diagnosis set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("update socialhistory set patient_id = $new_patient_id where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("delete from commissioning_body_patient_assignment where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("delete from pas_assignment where internal_id = $old_patient_id and internal_type = 'Patient';")->query();
		Yii::app()->db->createCommand("delete from pas_patient_merged where patient_id = $old_patient_id;")->query();
		Yii::app()->db->createCommand("delete from patient where id = $old_patient_id;")->query();
	}

	public function remapEpisodes($episodes, $patient, $new_patient)
	{
		foreach ($episodes as $episode) {
			if (!$other_episode = $this->patientHasEpisodeOfSameSubspecialty($new_patient,$episode)) {
				Yii::app()->db->createCommand("update episode set patient_id = {$new_patient['id']} where id = {$episode['id']}")->query();
			} else {
				if (isset($this->map[$patient['hos_num'].','.$new_patient['hos_num']])) {
					$map = $this->map[$patient['hos_num'].','.$new_patient['hos_num']];

					$to_id = null;

					foreach ($map['episode_ids'] as $i => $episode_id) {
						if (in_array($episode_id,array($episode['id'],$other_episode['id']))) {
							$to_id = $episode_id;
							break;
						}
					}

					if (!$to_id) {
						throw new Exception("Failed to decide between episode {$episode['id']} and {$other_episode['id']}");
					}

					if ($episode['id'] == $to_id) {
						$from_id = $other_episode['id'];
						$to = $episode;
					} else {
						$from_id = $episode['id'];
						$to = $other_episode;
					}

					if (isset($map['disorder_ids'][$i])) {
						$disorder_id = $map['disorder_ids'][$i];
					} else {
						$disorder_id = $to['disorder_id'];
					}

					if (isset($map['eye_ids'][$i])) {
						$eye_id = $map['eye_ids'][$i];
					} else {
						$eye_id = $to['eye_id'];
					}

					if (isset($map['episode_status_ids'])) {
						$episode_status_id = $map['episode_status_ids'][$i];
					} else {
						$episode_status_id = $to['episode_status_id'];
					}

	 			} else {
					if ($episode['eye_id'] && $episode['disorder_id'] && !$other_episode['eye_id'] && !$other_episode['disorder_id']) {
						$eye_id = $episode['eye_id'];
						$disorder_id = $episode['disorder_id'];
					} else if (!$episode['eye_id'] && !$episode['disorder_id'] && $other_episode['eye_id'] && $other_episode['disorder_id']) {
						$eye_id = $other_episode['eye_id'];
						$disorder_id = $other_episode['disorder_id'];
					} else if (!$episode['eye_id'] && !$episode['disorder_id'] && !$other_episode['eye_id'] && !$other_episode['disorder_id']) {
						$eye_id = $disorder_id = false;
					} else if ($episode['eye_id'] == $other_episode['eye_id'] && $episode['disorder_id'] == $other_episode['disorder_id']) {
						$eye_id = $episode['eye_id'];
						$disorder_id = $episode['disorder_id'];
					} else {
						return false;
					}

					if ($episode['episode_status_id'] != $other_episode['episode_status_id']) {
						return false;
					}

					$episode_status_id = $episode['episode_status_id'];

					if (strtotime($episode['created_date']) < strtotime($other_episode['created_date'])) {
						// Nuke other episode and retain first patients episode
						$from_id = $other_episode['id'];
						$to_id = $episode['id'];
					} else {
						// Opposite
						$from_id = $episode['id'];
						$to_id = $other_episode['id'];
					}
				}

				if ($from_id == $to_id) {
					throw new Exception("from episode is the same as to episode.");
				}

				if ($eye_id) {
					Yii::app()->db->createCommand("update episode set eye_id = $eye_id where id = $to_id")->query();
				}
				if ($disorder_id) {
					Yii::app()->db->createCommand("update episode set disorder_id = $disorder_id where id = $to_id")->query();
				}

				Yii::app()->db->createCommand("update episode set episode_status_id = $episode_status_id where id = $to_id")->query();

				//echo "update audit set episode_id = $to_id where episode_id = $from_id\n";
				Yii::app()->db->createCommand("update audit set episode_id = $to_id where episode_id = $from_id")->query();
				//echo "update event set episode_id = $to_id where episode_id = $from_id\n";
				Yii::app()->db->createCommand("update event set episode_id = $to_id where episode_id = $from_id")->query();
				//echo "delete from episode where id = $from_id\n";
				Yii::app()->db->createCommand("delete from episode where id = $from_id")->query();
			}
		}

		return true;
	}

	public function patientHasEpisodeOfSameSubspecialty($patient,$episode)
	{
		if ($episode['legacy']) {
			return Yii::app()->db->createCommand("select * from episode where patient_id = {$patient['id']} and legacy=1 and id != {$episode['id']}")->queryRow();
		}

		if ($episode['support_services']) {
			return Yii::app()->db->createCommand("select * from episode where patient_id = {$patient['id']} and support_services=1 and id != {$episode['id']}")->queryRow();
		}

		$firm = Yii::app()->db->createCommand("select * from firm where id = {$episode['firm_id']}")->queryRow();

		$firm_ids = array();

		foreach (Firm::model()->findAll('service_subspecialty_assignment_id=?',array($firm['service_subspecialty_assignment_id'])) as $firm) {
			$firm_ids[] = $firm['id'];
		}

		return Yii::app()->db->createCommand("select * from episode where patient_id = {$patient['id']} and firm_id in (".implode(',',$firm_ids).") and id != {$episode['id']}")->queryRow();
	}
}
