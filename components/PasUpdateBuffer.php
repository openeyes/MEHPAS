<?php

class PasUpdateBuffer extends CApplicationComponent {
	
	protected $_patients;
	
	protected $_buffer = false;
	
	public function setBuffering($state) {
		$this->_buffer = (bool) $state;
	}
	
	public function getBuffering() {
		return $this->_buffer;
	}
	
	public function addPatient($patient) {
		$this->_patients[] = $patient;
	}
	
	public function getPatients() {
		return $this->_patients;
	}
	
}