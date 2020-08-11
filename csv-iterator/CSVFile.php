<?php
/**
 * CSVFile.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   08/11/2019
 */

namespace Core;


use Iterator;

/**
 * Class CSVFile
 *
 * This class iterates a CSV file and can normalise the row keys to an associative
 * array based on the column headings contained in the first row (where appropriate)
 *
 * @package Core\File
 * @author  Will Perring <will@supernatural.ninja>
 * @since   11/08/2020
 * @version 1.0
 */
class CSVFile extends File implements Iterator {
	
	// For constructor header mode
	const HEADERS_NONE       = 0; // No headers
	const HEADERS_ROW        = 1; // Use headers as exact keys
	const HEADERS_NORMALISED = 2; // Use headers as normalised keys
	
	/** @var resource CSV file handle */
	protected $_resource;
	protected $_pointer = 0;
	
	protected $_lastValue;
	protected $_lastPointer;
	
	/** @var int Type of headers to use (see constants) */
	protected $_headerMode;
	
	/**
	 * Associate array of header mappings
	 *
	 * If using headers, we need to know what each column in our CSV should be
	 * renamed before being passed back out. This array will contain the key names
	 * indexed by the column.
	 *
	 * @var string[]
	 */
	protected $_headerNames;
	
	/**
	 * CSVFile constructor.
	 *
	 * @param string $filepath   Path to file
	 * @param int    $headerMode Number representing the type of headers (see constants)
	 */
	public function __construct( $filepath, $headerMode=0 ) {
		parent::__construct( $filepath );
		$this->_headerMode = $headerMode;
		$this->_resource   = fopen( $filepath, 'r' );
		$this->_resetPointer();
	}
	
	// As per definition of 'Iterator'
	public function next() {
		$this->_pointer++;
		$this->_getCsvLine();
	}
	
	// As per definition of 'Iterator'
	public function current() {
		return $this->_headerMode
			? $this->_mapHeadersToValue( $this->_lastValue )
			: $this->_lastValue
			;
	}
	
	// As per definition of 'Iterator'
	public function rewind() {
		$this->_resetPointer();
	}
	
	// As per definition of 'Iterator'
	public function valid() {
		return is_array($this->_lastValue)
			&& $this->_lastValue !== array(null)
			&& ! feof( $this->_resource );
	}
	
	// As per definition of 'Iterator'
	public function key() {
		return $this->_pointer;
	}
	
	/**
	 * Get a line from the CSV file
	 *
	 * We need to keep track of the last pointer, so by abstracting this procedure
	 * into a mini-function we can make sure it stays in sync.
	 *
	 * @since 11/08/2020
	 */
	protected function _getCsvLine() {
		$this->_lastValue   = fgetcsv( $this->_resource );
		$this->_lastPointer = $this->_pointer;
	}
	
	/**
	 * Reset the pointer on the CSV file.
	 *
	 * Because we're iterating a file with each call to next, we need to
	 * reset both our internal pointer and the one on the resource handle. As a result of this,
	 * we'll also need to reprocess headers before we can start another iteration.
	 *
	 * @since 11/08/2020
	 */
	protected function _resetPointer() {
		$this->_pointer     = 0;
		$this->_lastPointer = null;
		rewind( $this->_resource );
		
		if( $this->_headerMode ) {
			$this->_headerNames = fgetcsv( $this->_resource );
			if( $this->_headerMode === self::HEADERS_NORMALISED )
				$this->_normaliseHeaders();
		}
		
		$this->_getCsvLine();
	}
	
	/**
	 * Convert a numeric index array from the CSV to an associative array
	 *
	 * @param $values
	 *
	 * @see self::$_headerNames
	 *
	 * @return array
	 * @since 11/08/2020
	 */
	protected function _mapHeadersToValue( $values ) {
		$result = array();
		foreach( $values as $index => $value ) {
			$result[ $this->_headerNames[$index] ] = $value;
		}
		
		return $result;
	}
	
	/**
	 * Normalise a header
	 *
	 * This simply provides some safety by removing non-alphanumeric characters
	 * (except hyphens), converting spaces to underscores, and converting to lowercase
	 *
	 * @since 11/08/2020
	 */
	protected function _normaliseHeaders() {
		$find    = array( '/[ _]+/', '/[^a-z0-9_-]/' );
		$replace = array( '_',       ''              );
		
		$this->_headerNames = array_map( static function( $header ) use( $find, $replace ) {
			return trim( preg_replace( $find, $replace, strtolower($header) ), '_ ');
		}, $this->_headerNames );
	}
	
}
