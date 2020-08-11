<?php
/**
 * FTPPrinterConfig.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   13/12/2019
 */

namespace Core\Printing\PrinterConfig;


use Core\Command;
use Core\Exceptions\Printing\InvalidPrinterTransportException;
use Core\Printing\PrinterConfig;
use Core\Printing\PrinterTransport\FTPPrinterTransport;

abstract class FTPPrinterConfig extends PrinterConfig {
	
	protected $_host;
	protected $_port = 22;
	
	protected $_username;
	protected $_password;
	
	protected $_transferMode = FTP_ASCII;
	
	abstract protected function _getEmptyTransportClass();
	
	public function getTransport() {
		
		/** @var FTPPrinterTransport $transport */
		$transport = $this->_getEmptyTransportClass();
		if( ! is_a($transport, FTPPrinterTransport::class) )
			Throw new InvalidPrinterTransportException('Empty transport does not inherit from FTPPrinterTransport');
		
		$transport->setDestination( $this->_host, $this->_port );
		$transport->setCredentials( $this->_username, $this->_password );
		
		return $transport;
	}
	
	public function isValid() {
		return filter_var( $this->_host, FILTER_VALIDATE_IP )
			&& is_int( $this->_port ) && ( $this->_port > 0 )
			&& is_string( $this->_username ) && strlen( $this->_username )
			&& is_string( $this->_password ) && strlen( $this->_password );
	}
	
	public function configure( Command $command ) {
		$this->_host     = $command->ask( 'Enter host', $this->_host );
		$this->_port     = (int) $command->ask( 'Enter port', $this->_port );
		$this->_username = $command->ask( 'Enter username', $this->_username );
		$this->_password = $command->ask( 'Enter password', $this->_password );
	}
	
	protected function _inflateFromDatabase( $params ) {
		$this->_host     = $params->host;
		$this->_port     = $params->port;
		$this->_username = $params->username;
		$this->_password = $params->password;
	}
	
	protected function _deflateToDatabase() {
		return [
			'host'      => $this->_host,
			'port'      => $this->_port,
			'username'  => $this->_username,
			'password'  => $this->_password
		];
	}
	
}
