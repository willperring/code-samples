<?php
/**
 * EpsonPrinterConfig.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   13/12/2019
 */

namespace Core\Printing\PrinterConfig;


use Core\Command;
use Core\Interfaces\PrintableMedia;
use Core\Models\Printer\PrinterType;
use Core\Printing\PrinterConfig;
use Core\Printing\PrinterTransport\EpsonPrinterTransport;
use Core\Printing\TestDocuments\EpsonTestDocument;

class EpsonPrinterConfig extends PrinterConfig {
	
	protected $_host;
	protected $_port = 80;
	protected $_printerId;
	protected $_timeout = 30;
	
	public function getTransport() {
		return new EpsonPrinterTransport( $this->_host, $this->_port, $this->_printerId, $this->_timeout ) ;
	}
	
	public function getHost() {
		return $this->_host;
	}
	
	public function getPort() {
		return $this->_port;
	}
	
	public function getPrinterId() {
		return $this->_printerId;
	}
	
	public function getTimeout() {
		return $this->_timeout;
	}
	
	public function isValid() {
		return is_string( $this->_host )
			&& is_string( $this->_printerId )
			&& is_numeric( $this->_port )
			&& is_numeric( $this->_timeout );
	}
	
	public function configure( Command $command ) {
		
		do {
			$this->_host = $command->ask( 'Enter printer host', $this->_host );
		} while( $this->_host !== null && ! filter_var($this->_host, FILTER_VALIDATE_IP) );
		
		do {
			$this->_port = $command->ask( 'Enter printer port', $this->_port );
		} while( ! is_numeric($this->_port) );
		
		do {
			$this->_printerId = $command->ask( 'Enter printer ID', $this->_printerId );
		} while( ! $this->_printerId );
		
		do {
			$this->_timeout = $command->ask( 'Enter timeout', $this->_timeout );
		} while( ! is_numeric($this->_timeout) );
		
		$this->_port    = (int) $this->_port;
		$this->_timeout = (int) $this->_timeout ;
	}
	
	public function canPrint( PrintableMedia $media ) {
		return $media->getMediaTypeFlag() === PrinterType::MEDIA_SERVICE_TICKET;
	}
	
	public function getTestDocument() {
		return new EpsonTestDocument( $this );
	}
	
	protected function _inflateFromDatabase( $params ) {
		$this->_host      = $params->host;
		$this->_printerId = $params->printer_id;
		$this->_timeout   = $params->timeout;
		
		// Port was added later, need to account for config settings without a value
		$this->_port      = @$params->port ?: 80 ;
	}
	
	protected function _deflateToDatabase() {
		return [
			'host'       => $this->_host,
			'port'       => $this->_port,
			'printer_id' => $this->_printerId,
			'timeout'    => $this->_timeout
		];
	}
	
}
