<?php
/**
 * class.Colour.php
 *
 * @noinspection PowerOperatorCanBeUsedInspection
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   2019-01-07
 */

namespace Core;


use InvalidArgumentException;
use UnexpectedValueException;


class Colour {
	
	//
	// Static Methods
	//
	
	public static function fromHex( $hexColour ) {
		
		if( !preg_match('/^\#?([0-9A-F]{6}(?:[0-9A-F]{2})?)$/i', $hexColour, $matches) )
			Throw new UnexpectedValueException("Invalid Hex colour '{$hexColour}'");
		
		$hexColour = $matches[1];
		$hexParts  = str_split( $hexColour, 2 );
		$colour    = array();
		
		$parts = array( 'red', 'green', 'blue', 'alpha' );
		foreach( $hexParts as $index => $part ) {
			$partValue = !empty($part) ? hexdec($part) : 255 ;
			$colour[ $parts[$index] ] = $partValue;
		}
		
		return new Colour( $colour['red'], $colour['green'], $colour['blue'], $colour['alpha'] ?: 255 );
	}
	
	public static function fromHSL( $hue, $saturation, $luminosity ) {
		// @TODO: find algorithm...
	}
	
	public static function fromRGB( $red, $green, $blue ) {
		return new Colour( $red, $green, $blue );
	}
	
	public static function fromRGBA( $red, $green, $blue, $alpha ) {
		return new Colour( $red, $green, $blue, $alpha );
	}
	
	public static function getBestContrastMatch( Colour $colour, Colour ...$matches ) {
		
		$bestMatchContrast = 0;
		$bestMatchColour   = null;
		
		foreach( $matches as $match ) {
			$matchContrast = self::_calculateContrast( $colour, $match );
			if( $matchContrast > $bestMatchContrast ) {
				$bestMatchContrast = $matchContrast;
				$bestMatchColour   = $match;
			}
		}
		
		return $bestMatchColour;
	}
	
	protected static function _calculateContrast( Colour $colour1, Colour $colour2 ) {
		$luminosity1 = $colour1->_getLuminosity();
		$luminosity2 = $colour2->_getLuminosity();
		
		/**
		 * Luminosity Contrast Algorithm
		 * @see https://stackoverflow.com/questions/1331591/given-a-background-color-black-or-white-text
		 */
		return ( $luminosity1 > $luminosity2 )
			? (int) ( ($luminosity1 + 0.05) / ($luminosity2 + 0.05) )
			: (int) ( ($luminosity2 + 0.05) / ($luminosity1 + 0.05) );
	}
	
	//
	// Instance Properties and Methods
	//
	
	protected $_allowTransparency = true;
	
	protected $_red;
	protected $_green;
	protected $_blue;
	protected $_alpha;
	
	protected function __construct( $red, $green, $blue, $alpha=255 ) {
		$this->_validateComponents( $red, $green, $blue, $alpha );
		$this->_red   = $red;
		$this->_blue  = $blue;
		$this->_green = $green;
		$this->_alpha = $alpha;
	}
	
	public function allowTransparency( $state ) {
		$this->_allowTransparency = (bool) $state;
	}
	
	public function asRGB() {
		return "rgb( {$this->_red}, {$this->_green}, {$this->_blue} )";
	}
	
	public function asHex( $omitHash=false ) {
		
		$colour = $omitHash ? '' : '#' ;
		foreach( array($this->_red, $this->_green, $this->_blue) as $part ) {
			$colour .= str_pad( dechex( $part ), 2, '0', STR_PAD_LEFT );
		}
		
		if( $this->_allowTransparency && $this->_alpha != 255 ) {
			$colour .= str_pad( dechex( $this->_alpha ), 2, '0', STR_PAD_LEFT );
		}
		
		return $colour;
	}
	
	protected function _validateComponents( ...$components ) {
		foreach( $components as $component ) {
			if( !is_int($component) || $component < 0 || $component > 255 )
				Throw new InvalidArgumentException("Invalid Colour Component Value: {$component}");
		}
	}
	
	// @TODO: cache result?
	protected function _getLuminosity() {
		
		/**
		 * Luminosity Contrast Algorithm
		 * @see https://stackoverflow.com/questions/1331591/given-a-background-color-black-or-white-text
		 */
		
		/** @noinspection PowerOperatorCanBeUsedInspection */
		return 0.2126 * pow( $this->_red   / 255, 2.2 )
			 + 0.7152 * pow( $this->_green / 255, 2.2 )
			 + 0.0722 * pow( $this->_blue  / 255, 2.2 );
	}
	
	
}
