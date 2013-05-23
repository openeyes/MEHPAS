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
 * @todo This command is currently disabled until the referral code is fixed
 */

class MergedPatientsCommand extends CConsoleCommand {
	public function run($args) {
		$mps = new MergedPatientService;

		echo "Scanning patient table for broken assignments ... ";

		$patients = $mps->findBrokenPatients();

		echo "done\n";

		$newMerged = 0;

		foreach ($patients as $patient) {
			echo "Processing {$patient['hos_num']} ({$patient['type']}): ";

			switch ($patient['type']) {
				case 'dupe':
					if ($mps->resolveDupe($patient)) {
						echo "OK ($mps->lastMessage)\n";
					} else {
						echo "FAILED ($mps->lastMessage)\n";
					}
					break;
				case 'merged':
					if ($patient['match']) {
						if ($mps->resolveMerged($patient)) {
							echo "OK ($mps->lastMessage)\n";
						} else {
							echo "FAILED ($mps->lastMessage)\n";
						}
					} else {
						$newMerged += $mps->markMerged($patient);
						echo "FAILED (patient names don't match)\n";
					}
					break;
			}
		}

		$message = Yii::app()->mailer->newMessage();
		$message->setFrom(array("devteam@openeyes.org.uk" => "Dev team"));
		$message->setTo(array(Yii::app()->params['helpdesk_email']));
		$message->setSubject("New merged patients detected");
		$message->setBody("New merged patients were detected, please see: http://openeyes.moorfields.nhs.uk/mehpas/admin/mergedPatients");
		Yii::app()->mailer->sendMessage($message);
	}
}
