<?php
/**
 * DefinitionList.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   2019-10-29
 */

namespace Core\Utility\Units;


use MongoDB\Driver\Exception\InvalidArgumentException;
use UnexpectedValueException;

class DefinitionList {
	
	public static function bytes() {
		return new static([
			new Definition(    1, 'B',  'bytes' ),
			new Definition( 1024, 'KB', 'kilobytes' ),
			new Definition( 1024, 'MB', 'megabytes' ),
			new Definition( 1024, 'GB', 'gigabytes' ),
			new Definition( 1024, 'TB', 'terabytes' ),
		]);
	}
	
	public static function time() {
		return new static([
			new Definition(  1, 's', 'seconds' ),
			new Definition( 60, 'm', 'minutes' ),
			new Definition( 60, 'h', 'hours'   ),
			new Definition( 24, 'd', 'days'    ),
			new Definition(  7, 'w', 'weeks'   )
		]);
	}
	
	/** @var Definiton[] */
	protected $_definitions;
	
	/**
	 * DefinitionList constructor
	 *
	 * @param Definition[] $definitions Array of Definitions, smallest to largest
	 *
	 * @see Units
	 */
	public function __construct( array $definitions ) {
		
		if( ! is_array($definitions) || ! count($definitions) )
			Throw new InvalidArgumentException('DefinitionList is not array or empty');
		
		foreach( $definitions as $definition )
			if( ! is_a( $definition, Definition::class ) )
				Throw new UnexpectedValueException('Items in DefinitionList must be instances of Definition');
			
		$this->_definitions = $definitions;
	}
	
	public function definitions() {
		return $this->_definitions;
	}
	
}
