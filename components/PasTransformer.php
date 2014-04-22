<?php
/**
 * (C) OpenEyes Foundation, 2014
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2014, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

/**
 * Utilities for transforming data from PAS
 */
class PasTransformer
{
	/**
	 * @param string $string
	 * @return string
	 */
	static public function fixCase($string)
	{
		// Basic Title Case to start with
		$string = ucwords(strtolower($string));

		// Fix delimited words
		foreach (array('-', '\'', '.') as $delimiter) {
			if (strpos($string, $delimiter) !== false) {
				$string = implode($delimiter, array_map('ucfirst', explode($delimiter, $string)));
			}
		}

		// Exception is possessive (i.e. Paul's should not be Paul'S)
		$string = str_replace('\'S ', '\'s ', $string);

		return $string;
	}

	/**
	 * Parse a patient address from PAS and populate an Address object with the result
	 *
	 * @param PAS_PatientAddress $pas_address
	 * @param Address $address
	 */
	static public function parseAddress(PAS_PatientAddress $pas_address, Address $address)
	{
		$propertyName = trim($pas_address->PROPERTY_NAME);
		$propertyNumber = trim($pas_address->PROPERTY_NO);

		// Make sure they are not the same!
		if (strcasecmp($propertyName, $propertyNumber) == 0) {
			$propertyNumber = '';
		}

		$addr1 = trim($pas_address->ADDR1);
		if ($addr1) {
			// Remove any duplicate property name or number from ADDR1
			if (strlen($propertyName) > 0) {
				// Search plain, with comma, and with full stop
				$needles = array("{$propertyName},","{$propertyName}.",$propertyName);
				$addr1 = trim(str_replace($needles, '', $addr1));
			}
			if (strlen($propertyNumber) > 0) {
				// Search plain, with comma, and with full stop
				$needles = array("{$propertyNumber},","{$propertyNumber}.",$propertyNumber);
				$addr1 = trim(str_replace($needles, '', $addr1));
			}
		}

		// Combine property name, number and first line
		$address1 = array();
		if ($propertyName) {
			$address1[] = $propertyName;
		}
		if ($propertyNumber || $addr1) {
			$address1[] = trim($propertyNumber . ' ' . $addr1);
		}
		$address1 = implode("\n", $address1);

		$pcu = new PostCodeUtility();
		$lines = array();
		$city = null;
		$county = null;
		$postcode = null;
		$country = null;
		foreach (array('POSTCODE','ADDR5','ADDR4','ADDR3','ADDR2') as $name) {
			$line = trim($pas_address->{$name});
			if (!$line) continue;

			if (!$country && ($country = Country::model()->find('name like ?', array($line)))) continue;

			if (($pc_array = $pcu->parsePostCode($line))) {
				if (!$postcode) $postcode = $pc_array['full'];
				continue;
			}

			if (!$county && $pcu->isCounty($line)) {
				$county = $line;
				continue;
			}

			if (!$city && $pcu->isTown($line)) {
				$city = $line;
				continue;
			}

			$lines[] = $line;
		}
		$lines = array_unique($lines);

		// Cannot find country, so we assume it is UK
		if (!$country) $country = Country::model()->findByAttributes(array('name' => 'United Kingdom'));

		// If we didn't recognise a postcode (eg foreign country), trust the PAS postcode entry
		if (!$postcode && trim($pas_address->POSTCODE)) {
			$postcode = array_shift($lines);
		}

		// Now fill in anything else we're missing from the array
		if (!$address1) $address1 = array_pop($lines);
		if (!$county) $county = array_shift($lines);
		if (!$city) $city = array_shift($lines);
		$address2 = implode("\n", array_reverse($lines)) ?: null;

		// Store data
		$address->address1 = self::fixCase($address1);
		$address->address2 = self::fixCase($address2);
		$address->city = self::fixCase($city);
		$address->county = self::fixCase($county);
		$address->country_id = $country->id;
		$address->postcode = strtoupper($postcode);
		$address->address_type_id = self::getAddressType($pas_address->ADDR_TYPE);
		$address->date_start = $pas_address->DATE_START;
		$address->date_end = $pas_address->DATE_END;
	}

	static private function getAddressType($addr_type)
	{
		switch ($addr_type) {
			case 'H': return AddressType::model()->find('name=?',array('Home'))->id;
			case 'C': return AddressType::model()->find('name=?',array('Correspondence'))->id;
			case 'T': return AddressType::model()->find('name=?',array('Transport'))->id;
		}

		return null;
	}
}
