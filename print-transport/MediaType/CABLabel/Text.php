<?php
/**
 * Text.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   20/12/2019
 */

namespace Core\Printing\MediaType\CABLabel;


use Core\Interfaces\Printing\MediaType\CABLabel\CABLabelElement;
use Core\Printing\MediaType\CABLabel;
use OutOfRangeException;
use UnexpectedValueException;

class Text implements CABLabelElement {
	
	/* Font Faces */
	const FONT_BITMAP12X12  =  -1;
	const FONT_BITMAP16X16  =  -2;
	const FONT_BITMAP16X32  =  -3;
	const FONT_OCRA         =  -4;
	const FONT_OCRB         =  -5;
	const FONT_SWISS721     =   3;
	const FONT_SWISS721BOLD =   5;
	const FONT_MONOSPACE821 = 596;
	
	/* Text Effects */
	const FX_BOLD      = 'b';
	const FX_SLANT     = 's';
	const FX_LEFTSLANT = 'z';
	const FX_ITALIC    = 'i';
	const FX_NEGATIVE  = 'n';
	const FX_OUTLINE   = 'o';
	const FX_GREY      = 'g';
	const FX_UNDERLINE = 'u';
	const FX_LIGHT     = 'l';
	const FX_KERNING   = 'k';
	const FX_VERTICAL  = 'v';
	
	const UNIT_POINT = 'pt';
	const UNIT_LABEL = '';
	
	protected $_content = "";
	protected $_name    = null;
	
	protected $_x        = 0;
	protected $_y        = 0;
	protected $_rotation = 0;
	
	protected $_fontFace = self::FONT_SWISS721;
	protected $_fontSize = self::UNIT_POINT . '5';
	protected $_effects  = array();
	
	/**
	 * CabLabelText constructor.
	 *
	 * @param int    $x       X position
	 * @param int    $y       Y position
	 * @param string $content Text to display
	 * @param array  $options Formatting options
	 */
	public function __construct( $x, $y, $content, $options=array() ) {
		$this->_x = $x;
		$this->_y = $y;
		$this->_content = $content;
		
		if( isset($options['rotation']) )
			$this->setRotation( $options['rotation'] );
		
		if( isset($options['fontFace']) )
			$this->setFontFace( $options['fontFace'] );
		if( isset($options['fontSize']) )
			$this->setFontSize( $options['fontSize'] );
		
		if( isset($options['effects']) )
			$this->setEffects( $options['effects'] );
		if( isset($options['squeeze']) )
			$this->setSqueeze( $options['squeeze'] );
		if( isset($options['hCharWidth']) )
			$this->setHCharacterWidth( $options['hCharWidth'] );
		if( isset($options['squeeze']) )
			$this->setSqueeze( $options['squeeze'] );
		if( isset($options['charSpacing']) )
			$this->setCharacterSpacing( $options['charSpacing'] );
	}
	
	public function getElementCode( CABLabel $label ) {
		
		$xPos = $this->_x + $label->getOffsetX();
		$yPos = $this->_y + $label->getOffsetY();
		
		// Text Element Code
		$code  = 'T ';
		
		// Named Elements
		if( $this->_name )
			$code .= ":{$this->_name};";
		
		// Positional Information
		$code .= "{$xPos},{$yPos},{$this->_rotation},";
		
		// Typeface
		$code .= "{$this->_fontFace},{$this->_fontSize}";
		
		// Effects - are just a string of the values together - the blank array ensures
		// We have at least one thing to join.
		$code .= (count($this->_effects))
			? ',' . implode( '', array_values($this->_effects) )
			: '';
		
		// Text Content
		return $code . ";{$this->_content}";
	}
	
	public function setRotation( $degrees ) {
		if( $degrees < 0 || $degrees > 360 )
			Throw new OutOfRangeException("Degrees should be 0-360");
		$this->_rotation = $degrees;
		return $this;
	}
	
	public function setFontFace( $font ) {
		$this->_fontFace = $font;
		return $this;
	}
	
	public function setFontSize( $size, $unit=self::UNIT_POINT ) {
		if( !is_numeric($size) )
			Throw new UnexpectedValueException('Font size must be numeric');
		$this->_fontSize = $unit . strval($size);
		return $this;
	}
	
	public function setEffects( array $effects ) {
		foreach( $effects as $effect ) {
			$this->_effects[ $effect ] = $effect;
		}
		return $this;
	}
	
	public function setSqueeze( $amount ) {
		
		$amount = intval( $amount );
		
		if( $amount < 10 || $amount > 1000 )
			Throw new OutOfRangeException("CabLabelText squeeze must be between 10-1000");
		
		$this->_effects['q'] = "q{$amount}";
		return $this;
	}
	
	public function setHCharacterWidth( $width ) {
		$this->_effects['h'] = "h{$width}";
		return $this;
	}
	
	public function setCharacterSpacing( $spacing ) {
		$this->_effects['m'] = "m{$spacing}";
		return $this;
	}
	
}
