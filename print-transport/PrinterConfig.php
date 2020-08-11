<?php
/**
 * PrinterConfig.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   13/12/2019
 */

namespace Core\Printing;


use Core\Command;
use Core\Exceptions\Printing\InvalidPrinterConfigException;
use Core\Interfaces\PrintableMedia;

/**
 * Printer Configuration
 *
 * These classes are taken from a larger library that allows for multiple types of printer devices
 * to be saved to a database. They are generally created from two pieces of information - the class
 * that represents the configuration for any given printer, and a JSON string that contains the required
 * parameters to instantate that specific type of PrinterConfig instance. You can either call the class type
 * directly, or provide the class type as a parameter on the base class, so:
 * Either : PrinterConfig::fromDatabase( $string, ConfigSubType::class );
 * Or     : ConfigSubType::fromDatabase( $string );
 */
abstract class PrinterConfig {
	
	/**
	 * Get an inflated printer config from JSON
	 *
	 * @static
	 *
	 * @param string $string JSON config options
	 * @param string $class  Optional class name to inflate
	 *
	 * @return PrinterConfig
	 * @throws InvalidPrinterConfigException
	 * @since 13/12/2019
	 */
	public static function fromDatabase( $string, $class=null ) {
		$instance = $class ? new $class() : new static();
		$params   = json_decode( $string );
		$instance->_inflateFromDatabase( $params );
		return $instance;
	}
	
	/** 
	 * Return a configured transport for this configuration
	 * 
	 * @return PrinterTransport 
	 */
	abstract public function getTransport();
	
	/** 
	 * Test the configuration to see if it is valid
	 *
	 * @return bool 
	 */
	abstract public function isValid();
	
	/**
	 * Interactively configure the printer via a running Laravel Command
     * 
	 * In order to set up the printer, we need details. To do this through the shell, we can
	 * provide the running command, allowing us access to prompts, etc. This method should
	 * configure the class instance by asking the user for any details required - things like
	 * remote hostnames, printer IDs, etc. These values should just be set within the class 
	 * instance as normal so that they can be saved/validated.
     *
     * @param Command $command Command that is running
     *
     * @return void
 	 */
	abstract public function configure( Command $command );
	
	/**
	 * Get a printer-specific test document
	 *
	 * Obviously this will vary between printertypes, but it should return an appropriate instance
	 * that implements PrintableMedia
	 *
	 * @return PrintableMedia 
	 */
	abstract public function getTestDocument();
	
	/** @return bool */
	abstract public function canPrint( PrintableMedia $media );
	
	public function __toString() {
		return json_encode( $this->_deflateToDatabase() );
	}
	
	/**
	 * @param string JSON-encoded string of properties
	 * @throws InvalidPrinterConfigException
	 * @return void
	 */
	abstract protected function _inflateFromDatabase( $params );
	
	/** @return array */
	abstract protected function _deflateToDatabase();
}
