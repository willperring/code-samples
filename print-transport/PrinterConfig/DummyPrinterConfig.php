<?php
/**
 * DummyPrinterConfig.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   24/06/2020
 */

namespace Core\Printing\PrinterConfig;


use Core\Command;
use Core\Interfaces\PrintableMedia;
use Core\Printing\PrinterConfig;
use Core\Printing\PrinterTransport\DummyPrintTransport;
use Core\Printing\TestDocuments\DummyTestDocument;

class DummyPrinterConfig extends PrinterConfig {
	
	protected $_delaySeconds = 0;
	
	public function canPrint( PrintableMedia $media ) {
		return true; // it's not a real printer
	}
	
	public function configure( Command $command ) {
		do {
			$this->_delaySeconds = $command->ask('How many seconds to wait before returning?', 0);
		} while( ! is_numeric($this->_delaySeconds) );
	}
	
	public function isValid() {
		return true;
	}
	
	public function getTestDocument() {
		return new DummyTestDocument();
	}
	
	public function getTransport() {
		return new DummyPrintTransport( $this->_delaySeconds );
	}
	
	protected function _inflateFromDatabase( $params ) {
		$this->_delaySeconds = $params->delay;
	}
	
	protected function _deflateToDatabase() {
		return [ 'delay' => (int) $this->_delaySeconds ];
	}
	
}
