<?php
/**
 * GoogleCloudPrinterTransport.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   13/12/2019
 */

namespace Core\Printing\PrinterTransport;

use Core\Exceptions\Printing\GoogleCloud\NoAccessTokenException;
use Core\Exceptions\Printing\GoogleCloud\NoJsonCredentialsException;
use Core\Exceptions\Printing\GoogleCloud\NoPrinterAddressException;
use Core\Exceptions\Printing\InvalidMediaTypeException;
use Core\Interfaces\PrintableMedia;
use Core\Printing\PrinterTransport;
use Core\Printing\PrintingResult;
use Google_Client;
use Google_Exception;

class GoogleCloudPrinterTransport extends PrinterTransport {
	
	protected static $_keyFile;
	protected static $_token;
	
	public static function init( $jsonKeyFile ) {
		static::$_keyFile = $jsonKeyFile;
	}
	
	/**
	 * Request an access token from Google.
	 *
	 * @static
	 * @return mixed
	 * @throws NoAccessTokenException
	 * @throws NoJsonCredentialsException
	 * @throws \Google_Exception
	 * @since 09/01/2020
	 */
	protected static function _getToken() {
		
		if( ! static::$_keyFile )
			Throw new NoJsonCredentialsException('GoogleCloudPrinterTransport has not been initialised');
		
		$scopes = [ 'https://www.googleapis.com/auth/cloudprint' ];
		$client = new Google_Client();
		
		/** @var \Google_Auth_AssertionCredentials $creds */
		$creds = $client->loadServiceAccountJson( static::$_keyFile, $scopes );
		$client->setAssertionCredentials( $creds );
		
		$auth = $client->getAuth();
		if( $auth->isAccessTokenExpired() )
			$auth->refreshTokenWithAssertion();
		
		$res = json_decode( $client->getAccessToken() );
		if( ! property_exists($res, 'access_token') )
			Throw new NoAccessTokenException('No access token in client response');
			
		return $res->access_token;
		
	}
	
	protected $_address;
	protected $_timeout = 15;
	
	public function setAddress( $address ) {
		$this->_address = $address;
	}
	
	public function setTimeout( $timeout ) {
		$this->_timout = $timeout;
	}
	
	/**
	 * Transport the payload to the printer
	 *
	 * @param PrintableMedia $media
	 *
	 * @return PrintingResult
	 * @throws InvalidMediaTypeException
	 * @throws NoAccessTokenException
	 * @throws NoJsonCredentialsException
	 * @throws NoPrinterAddressException
	 * @throws Google_Exception
	 * @since 09/01/2020
	 */
	protected function _transport( PrintableMedia $media ) {
		
		if( static::$_developmentMode )
			return new PrintingResult(true);
		
		/** @var PrintableMedia\ZebraCardPrintable $media */
		if( ! is_a($media, PrintableMedia\GoogleCloudPrintable::class) )
			Throw new InvalidMediaTypeException('Media is not Google Cloud printable');
		
		if( ! static::$_token )
			static::$_token = static::_getToken();
		
		if( ! $this->_address )
			Throw new NoPrinterAddressException('Address for printer not specified');
		
		$result = new PrintingResult();
		
		$requestUrl = 'https://www.google.com/cloudprint/interface/submit';
		$postFields = [
			'printerid'               => $this->_address,
			'title'                   => $media->getDocumentTitle(),
			'contentTransferEncoding' => 'base64',
			'content'                 => base64_encode( $media->getPrintPayload() ),
			'contentType'             => $media->getContentType(), // TODO confirm
		];
		
		$authHeaders = [
			'Authorization: OAuth ' . static::$_token
		];
		
		$ch = curl_init( $requestUrl );
		/** @noinspection CurlSslServerSpoofingInspection */
		curl_setopt_array( $ch, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $postFields,
			CURLOPT_HTTPHEADER     => $authHeaders,
			CURLOPT_HTTPAUTH       => CURLAUTH_ANY,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => $this->_timeout
		]);
		
		$response = curl_exec( $ch );
		$result->addData( 'response', $response );
		
		$response = json_decode( $response );
		
		if( property_exists($response, 'success') && $response->success ) {
			$result->setSuccessful(true);
		}
		
		return $result;
	}
	
}
