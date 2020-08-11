<?php
/**
 * QRCode.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   20/12/2019
 */

namespace Core\Printing\MediaType\CABLabel;


use Core\Interfaces\Printing\MediaType\CABLabel\CABLabelElement;
use Core\Printing\MediaType\CABLabel;
use OutOfRangeException;

class QRCode implements CABLabelElement {
	
	const ERROR_LOW  = 'L';
	const ERROR_MED  = 'M';
	const ERROR_HIGH = 'Q';
	const ERROR_MAX  = 'H';
	
	protected $_content  = '';
	
	protected $_x        = 0;
	protected $_y        = 0;
	protected $_size     = 0;
	protected $_rotation = 0;
	
	protected $_whitespace = 0;
	protected $_model      = 2;
	protected $_errorLevel = null; // Printer Default
	
	public function __construct( $x, $y, $size, $content, $options=array() ) {
		$this->_x       = $x;
		$this->_y       = $y;
		$this->_size    = $size;
		$this->_content = $content;
		
		if( isset($options['rotation']) )
			$this->setRotation( $options['rotation'] );
		
		if( isset($options['errorLevel']) )
			$this->setErrorLevel( $options['errorLevel'] );
	}
	
	public function getElementCode( CABLabel $label ) {
		
		$xPos = $this->_x + $label->getOffsetX();
		$yPos = $this->_y + $label->getOffsetY();
		
		$code = 'B ';
		
		$code .= "{$xPos},{$yPos},{$this->_rotation},";
		
		$code .= 'QRCODE';
		
		if( $this->_model !== null )
			$code .= "+MODEL{$this->_model}";
		
		if( $this->_whitespace !== null )
			$code .= "+WS{$this->_whitespace}";
		
		if( $this->_errorLevel !== null )
			$code .= "+EL{$this->_errorLevel}";
		
		return $code . ",{$this->_size};{$this->_content}";
	}
	
	public function setRotation( $degrees ) {
		if( $degrees < 0 || $degrees > 360 )
			Throw new OutOfRangeException('Degrees should be 0-360');
		$this->_rotation = $degrees;
		return $this;
	}
	
	public function setErrorLevel( $level ) {
		$this->_errorLevel = $level;
		return $this;
	}
	
	
}
