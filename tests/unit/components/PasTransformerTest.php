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

class PasTransformerTest extends CDbTestCase
{
	public $fixtures = array(
		'AddressType',
		'Country',
	);

	public function fixCaseDataProvider()
	{
		return array(
			array("FOO BAR", "Foo Bar"),
			array("FOO-BAR", "Foo-Bar"),
			array("FOO'BAR", "Foo'Bar"),
			array("FOO.BAR", "Foo.Bar"),
			array("FOO'S BAR", "Foo's Bar"),
		);
	}

	/**
	 * @dataProvider fixCaseDataProvider
	 */
	public function testFixCase($input, $expected)
	{
		$this->assertEquals($expected, PasTransformer::fixCase($input));
	}

	public function parseAddressProvider()
	{
		return array(
			array(  // Basic empty address
				array(
				),
				array(
				),
			),
			array(  // Duplicate property name and number
				array(
					'PROPERTY_NAME' => '10',
					'PROPERTY_NO' => '10',
				),
				array(
					'address1' => '10',
				),
			),
			array(  // Property no appears in addr1
				array(
					'PROPERTY_NO' => '10',
					'ADDR1' => '10, TEST STREET',
				),
				array(
					'address1' => '10 Test Street',
				),
			),
			array(  // Property name appears in addr1
				array(
					'PROPERTY_NAME' => 'FRED',
					'ADDR1' => 'FRED, TEST STREET',
				),
				array(
					'address1' => "Fred\nTest Street",
				),
			),
			array(  // Comma added after number in addr1 (even though this doesn't happen when they're separate...)
				array(
					'ADDR1' => '10 TEST STREET',
				),
				array(
					'address1' => '10, Test Street',
				),
			),
			array(  // Combine property name, number and addr1
				array(
					'PROPERTY_NAME' => 'FRED',
					'PROPERTY_NO' => '10',
					'ADDR1' => 'TEST STREET',
				),
				array(
					'address1' => "Fred\n10 Test Street",
				),
			),
			array(  // UK specified
				array(
					'ADDR2' => 'UNITED KINGDOM',
				),
				array(
				),
			),
			array(  // Postcode in correct field
				array(
					'POSTCODE' => 'EC1V 2PD',
				),
				array(
					'city' => 'London',
					'postcode' => 'EC1V 2PD',
				),
			),
			array(  // Postcode in another field
				array(
					'ADDR3' => 'EC1V 2PD',
				),
				array(
					'city' => 'London',
					'postcode' => 'EC1V 2PD',
				),
			),
			array(  // Non-uk country specified
				array(
					'ADDR2' => 'CANADA',
				),
				array(
					'country_id' => Country::model()->findByAttributes(array('name' => 'Canada'))->id,
				),
			),
			array(  // Non-uk country line extraction
				array(
					'ADDR1' => 'ADDR1',
					'ADDR2' => 'ADDR2',
					'ADDR3' => 'ADDR3',
					'ADDR4' => 'ADDR4',
					'ADDR5' => 'CANADA',
				),
				array(
					'address1' => 'Addr1',
					'address2' => 'Addr2',
					'city' => 'Addr3',
					'county' => 'Addr4',
					'country_id' => Country::model()->findByAttributes(array('name' => 'Canada'))->id,
				),
			),
			array(  // Address type lookup
				array(
					'ADDR_TYPE' => 'H',
				),
				array(
					'address_type_id' => AddressType::model()->findByAttributes(array('name' => 'Home'))->id,
				),
			),
		);
	}

	/**
	 * @dataProvider parseAddressProvider
	 */
	public function testParseAddress($input, $output)
	{
		$input += array(
			'ADDR_TYPE' => '',
			'PROPERTY_NAME' => '',
			'PROPERTY_NO' => '',
			'ADDR1' => '',
			'ADDR2' => '',
			'ADDR3' => '',
			'ADDR4' => '',
			'ADDR5' => '',
			'POSTCODE' => '',
			'DATE_START' => '',
			'DATE_END' => null,
		);

		$output += array(
			'address1' => '',
			'address2' => '',
			'city' => '',
			'county' => '',
			'postcode' => '',
			'country_id' => Country::model()->findByAttributes(array('name' => 'United Kingdom'))->id,
			'address_type_id' => null,
			'date_start' => '',
			'date_end' => null,
			'id' => null,
			'contact_id' => null,
			'email' => null,
			'last_modified_user_id' => '1',
			'last_modified_date' => '1900-01-01 00:00:00',
			'created_user_id' => '1',
			'created_date' => '1900-01-01 00:00:00',
		);

		$pas_address = ComponentStubGenerator::generate('PAS_PatientAddress', $input);
		$address = new Address;

		PasTransformer::parseAddress($pas_address, $address);

		$this->assertEquals($output, $address->attributes);
	}
}
