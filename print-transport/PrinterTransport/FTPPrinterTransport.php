<?php
/**
 * FTPPrinterTransport.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   13/12/2019
 */

namespace Core\Printing\PrinterTransport;


use Core\Interfaces\PrintableMedia;
use Core\Printing\PrinterTransport;
use Core\Printing\PrintingResult;
use Exception;
use RuntimeException;

class FTPPrinterTransport extends PrinterTransport {
	
	protected $_host;
	protected $_port;
	
	protected $_username;
	protected $_password;
	
	protected $_transferMode = FTP_ASCII;
	protected $_timeout = 15;
	
	public function setDestination( $host, $port=22 ) {
		$this->_host = $host;
		$this->_port = $port;
		return $this;
	}
	
	public function setCredentials( $username, $password ) {
		$this->_username = $username;
		$this->_password = $password;
		return $this;
	}
	
	public function setTransferMode( $mode ) {
		$this->_transferMode = $mode;
		return $this;
	}
	
	public function setTimeout( $timeout ) {
		$this->_timeout = $timeout;
		return $this;
	}
	
	protected function _transport( PrintableMedia $media ) {
		
		if( static::$_developmentMode )
			return new PrintingResult(true);
		
		$result = new PrintingResult();
		
		try {
			$payloadResource = fopen('php://temp', 'w+');
			$payloadContent  = $media->getPrintPayload();
			
			if( ! fwrite( $payloadResource, $payloadContent ) )
				Throw new RuntimeException('Unable to write the payload to a temporary resource');
			
			$result->addData( 'payload', $payloadContent );
			
			// Essential, otherwise the pointer sits at the end and there's no 'content' for the FTP
			// PUT command to read in.
			rewind( $payloadResource );
			
			if( ! $ftpConnection = ftp_connect( $this->_host, $this->_port, $this->_timeout ) )
				Throw new RuntimeException('Unable to connect to the FTP server');
			
			if( ! $loginResult = ftp_login( $ftpConnection, $this->_username, $this->_password ) )
				Throw new RuntimeException('Unable to authenticate against FTP server');
			
			// This will require both a PASSIVE connection, as well as the override to
			// prevent the returned IP/port combination from being used. We need to
			// keep communication on the main host/port because it's the only one being
			// port forwarded.
			ftp_set_option( $ftpConnection, FTP_USEPASVADDRESS, false );
			if( ! ftp_pasv( $ftpConnection, true ) )
				Throw new RuntimeException('Unable to set passive flag on FTP connection');
			
			$remoteFile = 'remote-' . microtime(true) . '.txt';
			$result->addData( 'remoteFile', $remoteFile );
			
			if( ! ftp_fput( $ftpConnection, $remoteFile, $payloadResource, $this->_transferMode ) )
				Throw new RuntimeException('Unable to put the payload via FTP');
			
			$result->setSuccessful(true);
			
		} catch( Exception $e ) {
			$result->addData( 'exception', $e->getMessage() );
			
			$trace = "";
			foreach( $e->getTrace() as $step ) {
				$trace .= "{$step['file']} ( {$step['line']})\n{$step['class']}::{$step['function']}\n\n";
				//var_dump( $step );
			}
			
			$result->addData( 'backtrace', $trace );
		}
		
		return $result;
		
	}
	
}
