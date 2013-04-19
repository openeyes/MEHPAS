<?php

class PasUpdateBuffer extends CApplicationComponent {
	
	protected $_patients;
	protected $_gps;
	protected $practices;
	
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
	
	public function addPractice($practice) {
		$this->_practices[] = $$practice;
	}
	
	public function getPractices() {
		return $this->_practices;
	}
	
	public function addGp($gp) {
		$this->_gps[] = $gp;
	}
	
	public function getGps() {
		return $this->_gps;
	}
	
}