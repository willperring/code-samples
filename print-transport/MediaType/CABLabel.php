<?php
/**
 * CABLabel.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   20/12/2019
 */

namespace Core\Printing\MediaType;


use Core\Exceptions\Printing\CABSquix\MediaNotConfiguredException;
use Core\Interfaces\PrintableMedia\CABSquixPrintable;
use Core\Interfaces\Printing\MediaType\CABLabel\CABLabelElement;
use Core\Models\Printer\PrinterType;
use Core\Printing\PrinterConfig\CABPrinterConfig;
use RuntimeException;

abstract class CABLabel implements CABSquixPrintable {
	
	const DIMENSION_MM   = 'm';
	const DIMENSION_INCH = 'i';
	
	protected $_unit = self::DIMENSION_MM;
	
	protected $_height = 0;
	protected $_width  = 0;
	
	protected $_offsetX = 0;
	protected $_offsetY = 0;
	
	protected $_reversed = false;
	
	protected $_heat = null;
	
	protected $_elements = array();
	protected $_commands = array();
	
	/**
	 * Stored config of target printer
	 *
	 * This shouldn't be accessed directly, use the protected method
	 * _getConfig() instead
	 *
	 * @var CABPrinterConfig
	 * @see _getConfig()
	 */
	protected $_config;
	
	public function configureMedia( CABPrinterConfig $config ) {
		$this->_config = $config;
	}
	
	/**
	 * Set the dimensions of the Print Label
	 *
	 * @param int       $height   Height of the label
	 * @param int       $width    Width of the label
	 * @param bool|null $reversed Rotate the label 180 degrees
	 *
	 * @return $this
	 * @since 05/07/2018
	 */
	public function setDimensions( $height, $width, $reversed=null ) {
		$this->_height = $height;
		$this->_width  = $width;
		
		if( $reversed !== null )
			$this->_reversed = $reversed;
		
		return $this;
	}
	
	final public function renderLabel( $count=1 ) {
		$this->_commands = array();
		
		$this->_renderHeader();
		$this->_renderBody();
		$this->_renderFooter( $count );
		
		return implode( "\n", $this->_commands ) . "\n";
	}
	
	public function setOffset( $x, $y ) {
		$this->_offsetX = $x;
		$this->_offsetY = $y;
		return $this;
	}
	
	public function getOffsetX() {
		return $this->_offsetX;
	}
	
	public function getOffsetY() {
		return $this->_offsetY;
	}
	
	final protected function _renderHeader() {
		
		$config = $this->_getConfig();
		
		// Declare units - most come first.
		$this->_commands[] = "m {$this->_unit}";
		
		// Job start - immediately after units.
		$this->_commands[] = "J";
		
		// Heat setting
		$this->_commands[] = "H " . $config->getDefaultHeat();
		
		// Label Size
		$this->_commands[] = $this->_getLabelDimensions();
		
		// Rotation - must follow 'S' (Size)
		if( $this->_reversed )
			$this->_commands[] = "O R";
		
		// Cut after each label
		$this->_commands[] = 'C 1';
	}
	
	/**
	 * Get the config of the target printer
	 *
	 * @return CABPrinterConfig
	 * @throws MediaNotConfiguredException
	 * @since 24/12/2019
	 */
	protected function _getConfig() {
		if( ! $this->_config )
			Throw new MediaNotConfiguredException('Media not configured');
		return $this->_config;
	}
	
	protected function _renderBody() {
		foreach( $this->_elements as $element ) {
			$this->_commands[] = $element->getElementCode( $this );
		}
		return $this;
	}
	
	final protected function _renderFooter( $count=1 ) {
		$this->_commands[] = "A {$count}";
		return $this;
	}
	
	protected function _addElement( CABLabelElement $element ) {
		$this->_elements[] = $element;
		return $this;
	}
	
	protected function _getLabelDimensions() {
		
		$config = $this->_getConfig();
		
		switch( $config->getLabelType() ) {
			case CABPrinterConfig::LABEL_ENDLESS:
				$cabLabelChar = 'e';
				break;
			default:
				Throw new RuntimeException('Unsupported Label Type');
		}
		
		$labelWidth  = min( $this->_width, $config->getLabelWidth() );
		$labelHeight = ( $config->getLabelType() === CABPrinterConfig::LABEL_ENDLESS )
			? $this->_height : min( $this->_height, $config->getLabelHeight() );
		
		return "S {$cabLabelChar};0,0,{$labelHeight},{$labelHeight},{$labelWidth}";
	}
	
	
	public function getMediaTypeFlag() {
		return PrinterType::MEDIA_BARCODE_LABEL;
	}
	
	public function getPrintPayload() {
		return $this->renderLabel( 1 );
	}
	
}
