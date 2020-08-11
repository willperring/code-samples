<?php
/**
 * Units.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   2019-10-29
 */

namespace Core\Utility;


use Core\Utility\Units\Definition;
use Core\Utility\Units\DefinitionList;
use RuntimeException;

/**
 * Number-to-Unit Formatter
 *
 * This class is designed to be able to convert numbers - for example, seconds - into a list of units
 * such as "X days, Y hours, Z minutes" or simply just "X days", where it automatically uses the most
 * significant unit.
 *
 * When instantiating, the constructor requires an instance of DefinitionList, which is in itself a 
 * collection of instances of Definition, where each definition has a name, a unit, and a factor the the
 * unit that comes before it in the list. See the DefinitionList class for two examples, for time and data
 * size.
 */
class Units {
	
	/**
	 * Get a predefined 'bytes' instance
	 *
	 * @return self
	 */
	public static function bytes() {
		return new self( DefinitionList::bytes(), self::TYPE_SINGLE );
	}
	
	/**
	 * Get a predefined 'time' instance
	 *
	 * @return self
	 */
	public static function time() {
		return new self( DefinitionList::time(), self::TYPE_SUBUNITS );
	}
	
	// Return only the most significant unit
	const TYPE_SINGLE   = 1;
	// Return all subunits
	const TYPE_SUBUNITS = 2;
	
	const USE_NONE   = 0;
	const USE_SYMBOL = 1;
	const USE_NAME   = 2;
	
	/** @var DefinitionList */
	protected $_unitsList;
	protected $_unitsType;
	protected $_subunitValue; // Yet to be implemented; for configuring things like float subunits
	
	protected $_separator = ', ';
	
	public function __construct( DefinitionList $unitsList, int $unitsType, $_subunitValue=0 ) {
		$this->_unitsList    = $unitsList;
		$this->_unitsType    = $unitsType;
		$this->_subunitValue = $_subunitValue;
	}
	
	/**
	 * Get a formatted string for a given numeric value
	 *
	 * @param int|float $value     The number to convert
	 * @param int       $use       Whether to use the full unit name, the symbol, or no unit identifier
	 * @param string    $separator An optional parameter to override the default separator
	 *
	 * @throws RuntimeException
	 * @return string
	 */
	public function getStringForValue( $value, $use=self::USE_SYMBOL, string $separator=null ) {
		
		if( ! is_numeric($value) )
			Throw new RuntimeException( 'Units::getStringForValue() expects numeric value');
		
		$parts = $this->_getUnitParts( $value, $use );
		return $this->_formatResult( $parts, $separator ?? $this->_separator );
	}
	
	/**
	 * Get an array containing the description for each unit
	 *
	 * To use the seconds example again, this would take a number and return something like
	 * an array containing [ 'X hours', 'Y minutes', 'Z seconds' ]
	 *
	 * @param int|float $value       The number to convert
	 * @param int       $use         Whether to use the full unit name, the symbol, or no unit identifier
	 * @param bool      $includeZero If true, any units with a value of zero are omitted
	 *
	 * @return array
	 */
	protected function _getUnitParts( $value, $use, bool $includeZero=false ) {
		
		$parts        = array();
		$definitions  = array_values( $this->_unitsList->definitions() );
		$rollingValue = $value;
		
		/** @var Definition $definition */
		foreach( $definitions as $index => $definition ) {
			
			$unitDescriptor = @[
				self::USE_SYMBOL => $definition->symbol(),
				self::USE_NAME   => ' ' . $definition->name()
			][ $use ] ?? '' ;
			
			/** @var Definition $nextDefinition */
			$nextDefinition = @$definitions[ $index+1 ];

			if( ! $nextDefinition || ($rollingValue < $nextDefinition->factor()) ) {
				$parts[] = $rollingValue . $unitDescriptor;
				break;
			}
			
			$remainderValue = $rollingValue % $nextDefinition->factor();
			if( $remainderValue || $includeZero )
				$parts[] = $remainderValue . $unitDescriptor;
			
			$rollingValue = floor( $rollingValue / $nextDefinition->factor() );
		}
		
		return $parts;
	}
	
	/**
	 * Convert an array of units to a string
	 *
	 * This essentially trims an array if required and applies the correct separator, returning a string
	 * 
	 * @param array  $parts     Array of parts, as provided by self::_getUnitParts()
	 * @param string $separator Separator to use
	 *
	 * @return string
	 */
	protected function _formatResult( array $parts, $separator ) {
		
		if( $this->_unitsType === self::TYPE_SINGLE ) {
			return array_pop( $parts );
		}
		
		return implode( $separator, array_reverse($parts) );
	}
	
}
