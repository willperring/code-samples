<?php
/**
 * Definition.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   2019-10-29
 */

namespace Core\Utility\Units;


class Definition {
	
	protected $_factor;
	protected $_symbol;
	protected $_name;
	
	/**
	 * Definition Constructor
	 *
	 * @param int    $factor How many times smaller the previous definition in a DefnitonList is
	 * @param string $symbol The symbol of this unit, e.g. Kilometers = 'km'
	 * @param string $name   The name of the unit
	 *
	 * @see DefinitionList
	 */
	public function __construct( int $factor, string $symbol, string $name ) {
		$this->_factor = $factor;
		$this->_symbol = $symbol;
		$this->_name   = $name;
	}
	
	public function factor() {
		return $this->_factor;
	}
	
	public function symbol() {
		return $this->_symbol;
	}
	
	public function name() {
		return $this->_name;
	}
}
