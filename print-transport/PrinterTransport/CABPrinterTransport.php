<?php
/**
 * CABPrinterTransport.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   08/01/2020
 */

namespace Core\Printing\PrinterTransport;


use Core\Interfaces\PrintableMedia;
use Core\Printing\PrinterConfig\CABPrinterConfig;

/**
 * Class CABPrinterTransport
 *
 * Transport class for the CAB SQUIX printer series. These printers need to configure
 * the media for the specific printer configuration, so this class builds upon the native
 * FTP transport layer in order to do so.
 *
 * @package Core\Printing\PrinterTransport
 * @author  Will Perring <will@supernatural.ninja>
 * @since   09/01/2020
 * @version 1.0
 */
class CABPrinterTransport extends FTPPrinterTransport {
	
	/** @var CABPrinterConfig */
	protected $_printerConfig;
	
	public function setConfig( CABPrinterConfig $config ) {
		$this->_printerConfig = $config;
		return $this;
	}
	
	protected function _transport( PrintableMedia $media ) {
		/** @var PrintableMedia\CABSquixPrintable $media */
		$media->configureMedia( $this->_printerConfig );
		return parent::_transport( $media );
	}
	
}
