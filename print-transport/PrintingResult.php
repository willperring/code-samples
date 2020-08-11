<?php
/**
 * PrintingResult.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   13/12/2019
 */

namespace Core\Printing;


class PrintingResult {
	
	protected $_successful;
	protected $_data;
	
	public function __construct( $successful=false, $data=array() ) {
		$this->_successful = $successful;
		$this->_data       = $data;
	}
	
	public function setSuccessful( $state ) {
		$this->_successful = $state;
	}
	
	public function wasSuccessful() {
		return (bool) $this->_successful;
	}
	
	public function addData( $key, $value ) {
		$this->_data[ $key ] = $value;
		return $this;
	}
	
	public function getData() {
		return $this->_data;
	}
	
}
