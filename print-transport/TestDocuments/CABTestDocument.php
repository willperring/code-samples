<?php
/**
 * CSBTestDocument.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   19/12/2019
 */

namespace Core\Printing\TestDocuments;


use Core\Interfaces\PrintableMedia\CABSquixPrintable;
use Core\Models\Printer\PrinterType;
use Core\Printing\MediaType\CABLabel;
use Core\Printing\MediaType\CABLabel\Text;
use Core\Printing\PrinterConfig\CABPrinterConfig;

class CABTestDocument extends CABLabel {
	
	public function __construct( CABPrinterConfig $config ) {
		
		$phrase = 'TEST';
		
		$this->setDimensions( 50, 20 );
		
		$fontOptionsA = array(
			'fontSize' => 9,
			'rotation' => 270,
			'effects'  => array( Text::FX_BOLD )
		);
		
		$fontOptionsB = array(
			'fontSize' => 9,
			'rotation' => 270,
			'effects'  => array( Text::FX_BOLD, Text::FX_NEGATIVE )
		);
		
		$xPos = 0;
		$loop = str_repeat(" > {$phrase}", 5);
		
		for( $i=0; $i<10; $i++ ) {
			
			$fontOptions = ( $i % 2 === 0 ) ? $fontOptionsA : $fontOptionsB ;
			
			$this->_addElement(
				new Text($xPos, -1, $loop, $fontOptions)
			);
			
			$xPos += 3;
			
			$end  = substr( $loop, -2 );
			$loop = $end . substr( $loop, 0, -2 );
		}
		
		
	}
	
	
}
