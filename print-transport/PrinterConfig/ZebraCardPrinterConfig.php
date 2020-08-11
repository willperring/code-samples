<?php
/**
 * ZebraCardPrinterConfig.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   09/01/2020
 */

namespace Core\Printing\PrinterConfig;


use Core\Interfaces\PrintableMedia;
use Core\Models\Printer\PrinterType;
use Core\Printing\TestDocuments\ZebraCardTestDocument;

class ZebraCardPrinterConfig extends GoogleCloudPrinterConfig {
	
	public function canPrint( PrintableMedia $media ) {
		return ( ($media->getMediaTypeFlag() & PrinterType::MEDIA_NAMETAG_CARD) > 0 );
	}
	
	public function getTestDocument() {
		return new ZebraCardTestDocument();
	}
	
}
