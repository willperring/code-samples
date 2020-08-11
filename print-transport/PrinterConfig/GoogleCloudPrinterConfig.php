<?php
/**
 * GoogleCloudPrinterConfig.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   13/12/2019
 */

namespace Core\Printing\PrinterConfig;


use Core\Command;
use Core\Interfaces\PrintableMedia;
use Core\Interfaces\PrintableMedia\GoogleCloudPrintable;
use Core\Printing\PrinterConfig;
use Core\Printing\PrinterTransport\GoogleCloudPrinterTransport;

/**
 * @see https://github.com/googleapis/google-api-php-client
 */
abstract class GoogleCloudPrinterConfig extends PrinterConfig {
	
	protected $_address;
	protected $_timeout = 15;
	
	public function configure( Command $command ) {
		do {
			$this->_address = $command->ask('Enter address', $this->_address);
			$this->_timeout = $command->ask('Enter timeout', $this->_timeout);
		} while( ! is_string($this->_address) && strlen($this->_address) );
	}
	
	public function isValid() {
		return is_string( $this->_address );
	}
	
	public function getTransport() {
		$transport = new GoogleCloudPrinterTransport();
		$transport->setAddress( $this->_address );
		$transport->setTimeout( $this->_timeout );
		return $transport;
	}
	
	protected function _inflateFromDatabase( $params ) {
		$this->_address = $params->address;
		
		if( property_exists($params, 'timeout') )
			$this->_timeout = $params->timeout;
	}
	
	protected function _deflateToDatabase() {
		return [
			'address' => $this->_address,
			'timeout' => $this->_timeout
		];
	}
	
}
