<?php
/**
 * EpsonSmartPrinterTransport.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   13/12/2019
 */

namespace Core\Printing\PrinterTransport;


use Core\Exceptions\Printing\InvalidPrinterConfigException;
use Core\Exceptions\Printing\UnexpectedPrinterResponseException;
use Core\Interfaces\PrintableMedia;
use Core\Interfaces\PrintableMedia\EpsonReceiptPrintable;
use Core\Printing\PrinterTransport;
use Core\Printing\PrintingResult;
use Core\Sentry\SentryHelper;

class EpsonPrinterTransport extends PrinterTransport {
	
	/*
     * This is a value used to denote a dummy transport. It should never be applied as a string value,
     * but as a class constant, so that it can be altered in the (exceptionally unlikely) event of
     * a naming collision.
     */
	const DUMMY_TRANSPORT = 'EpsonPrintTransportDummyMode';
	
	protected static $_xmlSchema   = 'http://schemas.xmlsoap.org/soap/envelope/';
	protected static $_epsonSchema = 'http://www.epson-pos.com/schemas/2011/03/epos-print';
	
	protected static $_globalDummyMode = false;
	protected static $_hostOverride;
	
	public static function dummyMode( $mode=true ) {
		static::$_globalDummyMode = $mode;
	}
	
	public static function setHostOverride( $host ) {
		static::$_hostOverride = $host;
	}
	
	protected $_host;
	protected $_port;
	protected $_printerId;
	protected $_timeout;
	
	public function __construct( $host, $port, $printerId, $timeout ) {
		$this->_host      = $host;
		$this->_port      = $port;
		$this->_printerId = $printerId;
		$this->_timeout   = $timeout;
	}
	
	/**
	 *
	 *
	 * @param PrintableMedia $media
	 *
	 * @return PrintingResult
	 * @throws InvalidPrinterConfigException
	 * @since 13/12/2019
	 */
	protected function _transport( PrintableMedia $media ) {
		/** @var EpsonReceiptPrintable $media */
		
		if( static::$_globalDummyMode || $this->_printerId === self::DUMMY_TRANSPORT )
			return new PrintingResult(true);
		
		$result = new PrintingResult();
		
		$envelope = $this->_getEnvelope( $media->getPrintPayload() );
		$url      = $this->_getTransmitURL();
		$ch       = curl_init();
		
		$result->addData( 'url',      $url      );
		$result->addData( 'envelope', $envelope );
		
		$options = array(
			CURLOPT_URL            => $url,
			CURLOPT_HEADER         => false,
			CURLOPT_POST           => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POSTFIELDS     => $envelope,
			CURLOPT_TIMEOUT        => $this->_timeout,
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: text/xml; charset=utf-8',
				'If-Modified-Since: Thu, 01 Jan 1970 00:00:00 GMT',
				'Content-Length: '.strlen($envelope)
			)
		);
		
		curl_setopt_array($ch, $options);
		$result->addData( 'options', print_r($options, true) );
		
		SentryHelper::breadcrumb(
			'EpsonSmartPrinterTransport',
			'A print request was generated',
			array(
				'printer'      => $this->_printerId,
				'transmitUrl'  => $url,
				'timeout'      => $this->_timeout,
			)
		);
		
		// For reporting later if something goes wrong...
		$response = 'Unsent request';
		
		try {
			
			// Submit print job and get result into variable
			$response = curl_exec($ch);
			
			$result->addData( 'response', $response );
			if( ! $response )
				$result->addData( 'curl_error', curl_error($ch) );
				
			curl_close($ch);
			
			if( ! $response )
				Throw new UnexpectedPrinterResponseException( 'EpsonSmartPrinterTransport: result is falsey' );
			
			// Get result into XML object and extract info to return via JSON
			$pr     = simplexml_load_string($response);
			$child1 = $pr->children( static::$_xmlSchema );
			if( ! $child1 )
				Throw new UnexpectedPrinterResponseException('EpsonSmartPrinterTransport: child1 is falsey');
			
			$child2 = $child1->children();
			if( ! $child2 )
				Throw new UnexpectedPrinterResponseException('EpsonSmartPrinterTransport: child2 is falsey');
			
			$res = $child2->response;

			if( (string) $res['success'] === 'true' )
				$result->setSuccessful(true);
			
		} catch( \Exception $e ) {
			SentryHelper::capture( $e, array(
				'result' => $response
			));
			
			$result->addData( 'exception', $e );
			return $result;
		}
		
		SentryHelper::breadcrumb(
			'EpsonSmartPrinterTransport',
			'A print response was received',
			array(
				'resultBody'   => $result
			)
		);
		
		return $result;
	}
	
	protected function _getEnvelope( $content ) {
		
		$xmlSchema   = static::$_xmlSchema;
		$epsonSchema = static::$_epsonSchema;
		
		return
			"<soapenv:Envelope xmlns:soapenv=\"{$xmlSchema}\">
				<soapenv:Body>
					<epos-print xmlns=\"{$epsonSchema}\">
						{$content}
					</epos-print>
				</soapenv:Body>
			</soapenv:Envelope>";
	}
	
	protected function _getTransmitURL() {
		if( !is_string($this->_printerId) || !strlen($this->_printerId) )
			Throw new InvalidPrinterConfigException('No printer device id specified');
		
		if( ! filter_var($this->_host, FILTER_VALIDATE_IP) )
			Throw new InvalidPrinterConfigException("Invalid remote IP address: '{$this->_host}'");
		
		$timeout = $this->_timeout * 1000;
		return "http://{$this->_host}:{$this->_port}/cgi-bin/epos/service.cgi?devid={$this->_printerId}&timeout={$timeout}";
	}
	
}
