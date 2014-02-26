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

class PasServiceTest extends CDbTestCase
{
	protected $fixtures = array(
		'Address',
		'Gp',
		'Patient',
		'Practice',
	);

	private $assign;
	private $service;
	private $pas_gp, $pas_practice, $pas_patient;

	public function setUp()
	{
		$this->assign = $this->getMockBuilder('PasAssignment')->disableOriginalConstructor()->getMock();
		$this->service = new PasService($this->assign);
		$this->service->setAvailable();

		$this->pas_gp = ComponentStubGenerator::generate(
			'PAS_Gp',
			array(
				'GP_ID' => '13',
				'NAT_ID' => '12345',
				'OBJ_PROF' => '54321',
				'FN1' => 'JOHN',
				'FN2' => 'A.',
				'SN' => 'ZOIDBERG',
				'TITLE' => 'DR',
				'TEL_1' => '01234567890',
				'ADD_NAM' => ' PLANET EXPRESS HEADQUARTERS ',
				'ADD_NUM' => '123',
				'ADD_ST' => '57TH STREET',
				'ADD_DIS' => 'MANHATTAN',
				'ADD_TWN' => 'NEW NEW YORK',
				'ADD_CTY' => 'EARTH',
				'PC' => '12345',
				'PRACTICE_CODE' => '67890',
			)
		);

		$this->pas_practice = ComponentStubGenerator::generate(
			'PAS_Practice',
			array(
				'PRACTICE_CODE' => '67890',
				'OBJ_LOC' => '67890',
				'TEL_1' => '01234567890',
				'ADD_NAM' => ' PLANET EXPRESS HEADQUARTERS ',
				'ADD_NUM' => '123',
				'ADD_ST' => '57TH STREET',
				'ADD_DIS' => 'MANHATTAN',
				'ADD_TWN' => 'NEW NEW YORK',
				'ADD_CTY' => 'EARTH',
				'PC' => '12345',
			)
		);

		$addresses = array(
			ComponentStubGenerator::generate(
				'PAS_PatientAddress',
				array(
					'PROPERTY_NUMBER' => '00100100',
					'PROPERTY_NAME' => 'ROBOT ARMS APTS',
					'ADDR1' => 'MANHATTAN',
					'ADDR2' => 'NEW NEW YORK',
					'ADDR3' => 'UNITED STATES',
					'POSTCODE' => '12345',
					'TEL_NO' => '01234567890',
//					'ADDR_TYPE' => 'H',
				)
			),
			ComponentStubGenerator::generate(
				'PAS_PatientAddress',
				array(
					'PROPERTY_NAME' => ' PLANET EXPRESS HEADQUARTERS ',
					'PROPERTY_NUMBER' => ' 123 ',
					'ADDR1' => '57TH STREET',
					'ADDR2' => 'MANHATTAN',
					'ADDR3' => 'NEW NEW YORK',
					'ADDR4' => 'UNITED STATES',
					'POSTCODE' => '12345',
//					'ADDR_TYPE' => 'C',
				)
			),
		);

		$this->pas_patient = ComponentStubGenerator::generate(
			'PAS_Patient',
			array(
				'SEX' => 'M',
				'DATE_OF_BIRTH' => '1974-08-09',
				'DATE_OF_DEATH' => null,
				'ETHNIC_GRP' => 'C',
				'hos_number' => ComponentStubGenerator::generate('PAS_PatientNumber', array('NUM_ID_TYPE' => '0', 'NUMBER_ID' => '12345')),
				'nhs_number' => ComponentStubGenerator::generate('PAS_PatientNumber', array('NUM_ID_TYPE' => 'NHS', 'NUMBER_ID' => '123456789')),
				'name' => ComponentStubGenerator::generate('PAS_PatientSurname', array('SURNAME_ID' => 'FRY', 'NAME1' => 'PHILIP', 'TITLE' => 'MR')),
				'address' => $addresses[0],
				'addresses' => $addresses,
				'PatientGp' => $this->pas_gp,
			)
		);

		parent::setUp();
	}

	public function testUpdateGpFromPas_New()
	{
		$gp = new Gp;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_gp));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$this->assertSame($gp, $this->service->updateGpFromPas($gp, $assignment));

		$gp = Gp::model()->noPas()->findByPk($assignment->internal_id);
		$this->assertEquals('12345', $gp->nat_id);
		$this->assertEquals('54321', $gp->obj_prof);
		$this->assertEquals('John A.', $gp->contact->first_name);
		$this->assertEquals('Zoidberg', $gp->contact->last_name);
		$this->assertEquals('Dr', $gp->contact->title);
		$this->assertEquals('01234567890', $gp->contact->primary_phone);
		$this->assertEquals("Planet Express Headquarters\n123 57th Street", $gp->contact->address->address1);
		$this->assertEquals("Manhattan", $gp->contact->address->address2);
		$this->assertEquals("New New York", $gp->contact->address->city);
		$this->assertEquals("Earth", $gp->contact->address->county);
		$this->assertEquals("12345", $gp->contact->address->postcode);
	}

	public function testUpdateGpFromPas_Existing()
	{
		$gp = new Gp;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_gp));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));
		$this->service->updateGpFromPas($gp, $assignment);

		$this->pas_gp->TITLE = 'VISCOUNT';
		$this->assertSame($gp, $this->service->updateGpFromPas($gp, $assignment));

		$gp = Gp::model()->noPas()->findByPk($assignment->internal_id);
		$this->assertEquals('Viscount', $gp->contact->title);
	}

	public function testUpdateGpFromPas_Existing_AddressGone()
	{
		$gp = new Gp;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_gp));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));
		$this->service->updateGpFromPas($gp, $assignment);

		$this->pas_gp->ADD_NAM = $this->pas_gp->ADD_NUM = $this->pas_gp->ADD_ST = $this->pas_gp->ADD_DIS = $this->pas_gp->ADD_TWN = $this->pas_gp->ADD_CTY = $this->pas_gp->PC = '';
		$this->service->updateGpFromPas($gp, $assignment);

		$gp = Gp::model()->noPas()->findByPk($assignment->internal_id);
		$this->assertNull($gp->contact->address);
	}

	public function testUpdateGpFromPas_Existing_Removed()
	{
		$gp = new Gp;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_gp));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));
		$this->service->updateGpFromPas($gp, $assignment);

		$assignment->external = null;
		$assignment->expects($this->once())->method('delete');
		$this->assertNull($this->service->updateGpFromPas($gp, $assignment));
	}

	public function testUpdatePracticeFromPas_New()
	{
		$practice = new Practice;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_practice));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$this->assertSame($practice, $this->service->updatePracticeFromPas($practice, $assignment));

		$practice = Practice::model()->noPas()->findByPk($assignment->internal_id);
		$this->assertEquals('67890', $practice->code);
		$this->assertEquals('01234567890', $practice->phone);
		$this->assertEquals('01234567890', $practice->contact->primary_phone);
		$this->assertEquals("Planet Express Headquarters\n123 57th Street", $practice->contact->address->address1);
		$this->assertEquals("Manhattan", $practice->contact->address->address2);
		$this->assertEquals("New New York", $practice->contact->address->city);
		$this->assertEquals("Earth", $practice->contact->address->county);
		$this->assertEquals("12345", $practice->contact->address->postcode);
	}

	public function testUpdatePracticeFromPas_Existing()
	{
		$practice = new Practice;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_practice));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));
		$this->service->updatePracticeFromPas($practice, $assignment);

		$this->pas_practice->TEL_1 = '09876543210';
		$this->assertSame($practice, $this->service->updatePracticeFromPas($practice, $assignment));

		$practice = Practice::model()->noPas()->findByPk($assignment->internal_id);
		$this->assertEquals('09876543210', $practice->phone);
		$this->assertEquals('09876543210', $practice->contact->primary_phone);
	}

	public function testUpdatePracticeFromPas_Existing_AddressGone()
	{
		$practice = new Practice;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_practice));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));
		$this->service->updatePracticeFromPas($practice, $assignment);

		$this->pas_practice->ADD_NAM = $this->pas_practice->ADD_NUM = $this->pas_practice->ADD_ST = $this->pas_practice->ADD_DIS = $this->pas_practice->ADD_TWN = $this->pas_practice->ADD_CTY = $this->pas_practice->PC = '';
		$this->service->updatePracticeFromPas($practice, $assignment);

		$practice = Practice::model()->noPas()->findByPk($assignment->internal_id);
		$this->assertNull($practice->contact->address);
	}

	public function testUpdatePracticeFromPas_Existing_Removed()
	{
		$practice = new Practice;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_practice));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));
		$this->service->updatePracticeFromPas($practice, $assignment);

		$assignment->external = null;
		$assignment->expects($this->once())->method('delete');
		$this->assertNull($this->service->updatePracticeFromPas($practice, $assignment));
	}

	public function testUpdatePatientFromPas_New()
	{
		$patient = new Patient;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_patient));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$gp_assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_gp, 'internal' => new Gp));
		$gp_assignment->expects($this->any())->method('isStale')->will($this->returnValue(true));
		$gp_assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$practice_assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_practice, 'internal' => new Practice));
		$practice_assignment->expects($this->any())->method('isStale')->will($this->returnValue(true));
		$practice_assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$this->assign->expects($this->any())->method('findByExternal')->will($this->returnValueMap(
				array(array('PAS_Gp', '13', $gp_assignment), array('PAS_Practice', '67890', $practice_assignment))
		));

		$this->service->updatePatientFromPas($patient, $assignment);

		$patient = Patient::model()->noPas()->findByPk($assignment->internal_id);
		$this->assertEquals('012345', $patient->pas_key);
		$this->assertEquals('012345', $patient->hos_num);
		$this->assertEquals('123456789', $patient->nhs_num);
		$this->assertEquals('1974-08-09', $patient->dob);
		$this->assertNull($patient->date_of_death);
		$this->assertEquals($gp_assignment->internal_id, $patient->gp_id);
		$this->assertEquals($practice_assignment->internal_id, $patient->practice_id);
		$this->assertEquals(3, $patient->ethnic_group_id);
	}

	public function testUpdatePatientFromPas_Existing_Removed()
	{
		$patient = new Patient;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_patient));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$gp_assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_gp, 'internal' => new Gp));
		$gp_assignment->expects($this->any())->method('isStale')->will($this->returnValue(true));
		$gp_assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$practice_assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_practice, 'internal' => new Practice));
		$practice_assignment->expects($this->any())->method('isStale')->will($this->returnValue(true));
		$practice_assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$this->assign->expects($this->any())->method('findByExternal')->will($this->returnValueMap(
				array(array('PAS_Gp', '13', $gp_assignment), array('PAS_Practice', '67890', $practice_assignment))
		));

		$this->service->updatePatientFromPas($patient, $assignment);

		$assignment->external = null;
		$this->service->updatePatientFromPas($patient, $assignment);
		$this->assertEquals(1, $assignment->missing_from_pas);
	}

	public function testUpdatePatientFromPas_Existing_GpRemoved()
	{
		$patient = new Patient;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_patient));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$gp_assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_gp, 'internal' => new Gp));
		$gp_assignment->expects($this->any())->method('isStale')->will($this->returnValue(true));
		$gp_assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$practice_assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_practice, 'internal' => new Practice));
		$practice_assignment->expects($this->any())->method('isStale')->will($this->returnValue(true));
		$practice_assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$this->assign->expects($this->any())->method('findByExternal')->will($this->returnValueMap(
				array(array('PAS_Gp', '13', $gp_assignment), array('PAS_Practice', '67890', $practice_assignment))
		));

		$this->service->updatePatientFromPas($patient, $assignment);

		$gp_assignment->external = null;
		$this->service->updatePatientFromPas($patient, $assignment);

		$patient = Patient::model()->noPas()->findByPk($assignment->internal_id);
		$this->assertNull($patient->gp);
	}

	public function testUpdatePatientFromPas_Existing_PracticeRemoved()
	{
		$patient = new Patient;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_patient));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$gp_assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_gp, 'internal' => new Gp));
		$gp_assignment->expects($this->any())->method('isStale')->will($this->returnValue(true));
		$gp_assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$practice_assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_practice, 'internal' => new Practice));
		$practice_assignment->expects($this->any())->method('isStale')->will($this->returnValue(true));
		$practice_assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$this->assign->expects($this->any())->method('findByExternal')->will($this->returnValueMap(
				array(array('PAS_Gp', '13', $gp_assignment), array('PAS_Practice', '67890', $practice_assignment))
		));

		$this->service->updatePatientFromPas($patient, $assignment);

		$practice_assignment->external = null;
		$this->service->updatePatientFromPas($patient, $assignment);

		$patient = Patient::model()->noPas()->findByPk($assignment->internal_id);
		$this->assertNull($patient->practice);
	}

	public function testUpdatePatientFromPas_Existing_GpAndPracticeRemoved()
	{
		$patient = new Patient;
		$assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_patient));
		$assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$gp_assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_gp, 'internal' => new Gp));
		$gp_assignment->expects($this->any())->method('isStale')->will($this->returnValue(true));
		$gp_assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$practice_assignment = ComponentStubGenerator::generate('PasAssignment', array('id' => 42, 'external_id' => 43, 'external' => $this->pas_practice, 'internal' => new Practice));
		$practice_assignment->expects($this->any())->method('isStale')->will($this->returnValue(true));
		$practice_assignment->expects($this->any())->method('save')->will($this->returnValue(true));

		$this->assign->expects($this->any())->method('findByExternal')->will($this->returnValueMap(
				array(array('PAS_Gp', '13', $gp_assignment), array('PAS_Practice', '67890', $practice_assignment))
		));

		$this->service->updatePatientFromPas($patient, $assignment);

		$gp_assignment->external = null;
		$practice_assignment->external = null;
		$this->service->updatePatientFromPas($patient, $assignment);

		$patient = Patient::model()->noPas()->findByPk($assignment->internal_id);
		$this->assertNull($patient->gp);
		$this->assertNull($patient->practice);
	}
}
