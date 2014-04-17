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

		// Address1
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

			// Make sure street number has a comma and space after it
			$addr1 = preg_replace('/([0-9]) /', '\1, ', $addr1);

			// Replace any full stops after street numbers with commas
			$addr1 = preg_replace('/([0-9])\./', '\1,', $addr1);

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

		// Create array of remaining address lines, from last to first
		$addressLines = array();
		foreach (array('POSTCODE','ADDR5','ADDR4','ADDR3','ADDR2') as $address_line) {
			if ($address_line_content = trim($pas_address->{$address_line})) {
				$addressLines[] = $address_line_content;
			}
		}

		// See if we can find a country
		$country = null;
		$index = 0;
		while (!$country && $index < count($addressLines)) {
			$country = Country::model()->find('LOWER(name) = :name', array(':name' => strtolower($addressLines[$index])));
			$index++;
		}
		if ($country) {
			// Found a country, so we will remove the line from the address
			unset($addressLines[$index-1]);
		} else {
			// Cannot find country, so we assume it is UK
			$country = Country::model()->findByAttributes(array('name' => 'United Kingdom'));
		}

		$address2 = '';
		$town = '';
		$county = '';
		$postcode = '';
		if ($country->name == 'United Kingdom') {
			// We've got a UK address, so we'll see if we can parse the remaining tokens,

			// Instantiate a postcode utility object
			$postCodeUtility = new PostCodeUtility();

			// Set flags and default values
			$postCodeFound = false;
			$postCodeOuter = '';
			$townFound = false;
			$countyFound = false;

			// Go through array looking for likely candidates for postcode, town/city and county
			for ($index = 0; $index < count($addressLines); $index++) {
				if (!isset($addressLines[$index])) continue;

				// Is element a postcode? (Postcodes may exist in other address lines)
				if ($postCodeArray = $postCodeUtility->parsePostCode($addressLines[$index])) {
					if (!$postCodeFound) {
						$postCodeFound = true;
						$postcode = $postCodeArray['full'];
						$postCodeOuter = $postCodeArray['outer'];
					}
				} else { // Otherwise a string
					// Last in (inverted array) is a non-postcode, non-city second address line
					if ($townFound) {
						$address2 = trim($addressLines[$index]);
					}

					// County?
					if (!$countyFound) {
						if ($postCodeUtility->isCounty($addressLines[$index])) {
							$countyFound = true;
							$county = trim($addressLines[$index]);
						}
					}

					// Town?
					if (!$townFound) {
						if ($postCodeUtility->isTown($addressLines[$index])) {
							$townFound = true;
							$town = trim($addressLines[$index]);
						}
					}
				}
			}

			// If no town or county found, get them from postcode data if available, otherwise fall back to best guess
			if ($postCodeFound) {
				if (!$countyFound) $county = $postCodeUtility->countyForOuterPostCode($postCodeOuter);
				if (!$townFound) $town = $postCodeUtility->townForOuterPostCode($postCodeOuter);
			} else {
				// Number of additional address lines
				$extraLines = count($addressLines) - 1;
				if ($extraLines > 1) {
					$county = trim($addressLines[0]);
					$town = trim($addressLines[1]);
				} elseif ($extraLines > 0) {
					$town = trim($addressLines[0]);
				}
			}

			// Dedupe
			if (isset($county) && isset($town) && $town == $county) {
				$county = '';
			}
		} else {
			// We've got a non UK address, so we'll just try to store things whereever they fit
			if (trim($pas_address->POSTCODE)) {
				$postcode = array_shift($addressLines);
			} else {
				$postcode = '';
			}
			if (count($addressLines)) {
				$address2 = array_pop($addressLines);
			}
			if (count($addressLines)) {
				$town = array_pop($addressLines);
			}
			if (count($addressLines)) {
				$county = implode(', ', $addressLines);
			}
		}

		// Store data
		$address->address1 = self::fixCase($address1);
		$address->address2 = self::fixCase($address2);
		$address->city = self::fixCase($town);
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
