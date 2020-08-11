<?php
/**
 * EpsonTestDocument.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   19/12/2019
 */

namespace Core\Printing\TestDocuments;


use Core\Interfaces\PrintableMedia\EpsonReceiptPrintable;
use Core\Models\Printer\PrinterType;
use Core\Printing\PrinterConfig\EpsonPrinterConfig;

class EpsonTestDocument implements EpsonReceiptPrintable {
	
	protected $_config;
	
	public function __construct( EpsonPrinterConfig $config ) {
		$this->_config = $config;
	}
	
	public function getMediaTypeFlag() {
		return PrinterType::MEDIA_SERVICE_TICKET;
	}
	
	public function getPrintPayload() {
		return
 			"<text font=\"font_a\" />
			 <text smooth=\"true\" />
			 <feed />
			 <text>EPSON TEST DOCUMENT</text>
			 <feed />
			 <text>Host: {$this->_config->getHost()}</text>
			 <feed />
			 <text>Device ID: {$this->_config->getPrinterId()}</text>
			 <feed />
			 <text>Timeout: {$this->_config->getTimeout()}</text>
			 <feed />
			 <feed />
			 <feed />
			 <feed />
			 <cut type=\"feed\"/>";
	}
	
}
