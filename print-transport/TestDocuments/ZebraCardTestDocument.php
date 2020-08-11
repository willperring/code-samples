<?php
/**
 * ZebraCardTestDocument.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   09/01/2020
 */

namespace Core\Printing\TestDocuments;


use Core\Interfaces\PrintableMedia\ZebraCardPrintable;
use Core\Models\Printer\PrinterType;

class ZebraCardTestDocument implements ZebraCardPrintable {
	
	public function getMediaTypeFlag() {
		return PrinterType::MEDIA_NAMETAG_CARD;
	}
	
	public function getDocumentTitle() {
		return 'Zebra Card Test Document';
	}
	
	public function getContentType() {
		return 'text/plain';
	}
	
	public function getPrintPayload() {
		return 'Zebra Card Test';
	}
	
}
