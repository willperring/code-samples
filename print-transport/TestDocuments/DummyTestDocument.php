<?php
/**
 * DummyTestDocument.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   24/06/2020
 */

namespace Core\Printing\TestDocuments;


use Core\Interfaces\PrintableMedia;

class DummyTestDocument implements PrintableMedia {
	
	public function getMediaTypeFlag() {
		return 0;
	}
	
	public function getPrintPayload() {
		return null;
	}
	
}
