<?php

namespace Core\Logging;

use Core\Sentry\SentryHelper;
use Exception;
use RuntimeException;

/**
 * class.WriteableLocation.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   2019-02-05
 */

abstract class WriteableLocation {
	
	protected static $_outputLocation;
	
	/**
	 * Set the location for log files on the system
	 *
	 * @static
	 *
	 * @param string $location Path to log storage location on the system
	 *
	 * @throws Exception
	 * @since 20/09/2018
	 */
	public static function setOutputLocation( $location ) {
		if( !self::_isLocationWriteable($location) )
			Throw new RuntimeException( 'WriteableLocation is not writeable' );
		static::$_outputLocation = $location;
	}
	
	public static function getOutputLocation() {
		return static::$_outputLocation;
	}
	
	/**
	 * Test to see if a log location is suitable
	 *
	 * @static
	 *
	 * @param $location
	 *
	 * @return bool
	 * @since 20/09/2018
	 */
	protected static function _isLocationWriteable( $location ) {
		if( !is_dir($location) )
			return false;
		if( !is_writable($location) )
			return false;
		return true;
	}
	
	/** @var string The extension-free filename */
	protected $_filename           = '';
	/** @var Resource|null The file handle, once opened */
	protected $_resourceHandle;
	/** @var bool If true, exceptions will be thrown when errors happen at the write stage */
	protected $_throwOnWriteErrors = false;
	protected $_disabled = false;
	
	protected function _writeToResource( $value ) {
		
		if( !$this->_resourceHandle )
			/** @noinspection PhpUnhandledExceptionInspection */
			return $this->_handleWriteError( 'No resource handle exists' );
		
		if( $this->_disabled )
			return false;
		
		try {
			fwrite( $this->_resourceHandle, $value );
			return true;
			
		} catch( Exception $e ) {
			/** @noinspection PhpUnhandledExceptionInspection */
			$this->_handleWriteError( $e );
		}
		
		return false;
	}
	
	/**
	 * Generate, record, and possibly throw an exception
	 *
	 * As mentioned in the write() method docblock, throwing exceptions at write
	 * time can be very disruptive, or mean a lot of try{}catch blocks. This method
	 * generates and records the exception in sentry so that we don't miss out on
	 * vital error reporting, and, if configured, throws it up to the parent code.
	 *
	 * @param string $explanation The message for the exception
	 * @return boolean Returns a false value, mainly so the IDE doesn't get annoyed about returning a void.
	 *
	 * @see LogFile::write()
	 * @throws Exception
	 * @since 20/09/2018
	 */
	protected function _handleWriteError( $explanation ) {
		
		$exception = ( $explanation instanceof Exception )
			? $explanation : new Exception( $explanation );
		
		try {
			SentryHelper::capture( $exception );
			
		} catch( Exception $e ) {
			// what would we even do here?
		}
		
		if( $this->_throwOnWriteErrors )
			Throw $exception;
		
		return false;
	}
	
	/**
	 * Obtain a resouce handle on the log file
	 *
	 * At this stage, ".log" should have been appended to the filename already
	 * (This happens in the constructor)
	 *
	 * @param string $filename Filename of the log file (WITH extension)
	 *
	 * @see LogFile::__construct()
	 * @throws Exception
	 * @since 20/09/2018
	 */
	protected function _getResourceHandle( $filename ) {
		
		if( empty(static::$_outputLocation) )
			Throw new RuntimeException( 'LogLocation has not been set' );
		
		$fullPath  = static::$_outputLocation . DIRECTORY_SEPARATOR . $filename;
		
		/** @noinspection FopenBinaryUnsafeUsageInspection */
		$this->_resourceHandle = fopen( $fullPath, 'a' );
		$this->_filename       = $filename;
	}
	
	public function disable() {
		return $this->setDisabled( true );
	}
	
	public function enable() {
		return $this->setDisabled( false );
	}
	
	public function setDisabled( $state ) {
		$this->_disabled = (bool) $state;
		return $this;
	}
	
	/**
	 * Enable/Disable write-time exceptions
	 *
	 * This function is provided so that decisions can be made between ensuring valid data is
	 * obtained versus interuptting the main code flow. When enabled, exceptions will be thrown
	 * if the file cannot be written to - meaning that in order to ensure that the request is not
	 * abandoned the write statements need to be placed inside a try{}catch block. When disabled,
	 * you potentially run the risk of missing some valid data (although, errors are never
	 * discarded - they are always reported to sentry), this option merely dictates whether
	 * they should be re-thrown once logged.
	 *
	 * @param boolean $state True to throw exceptions, else false (default)
	 *
	 * @since 20/09/2018
	 */
	public function throwOnWriteErrors( $state ) {
		$this->_throwOnWriteErrors = $state;
	}
	
	/**
	 * Close the file handle
	 *
	 * This is just good housekeeping - if we're destroying the object, it's safe to say
	 * that we won't be writing anything else to the file.
	 */
	public function __destruct() {
		if( $this->_resourceHandle )
			fclose( $this->_resourceHandle );
	}
	
	
}

