<?php
/**
 * File.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   08/11/2019
 */

namespace Core;


use InvalidArgumentException;
use RuntimeException;

abstract class File {
	
	public function __construct( $filepath ) {
		if( ! is_string($filepath) )
			Throw new InvalidArgumentException('File() expects param 1 to be string');
		if( ! file_exists($filepath) )
			Throw new RuntimeException("File {$filepath} does not exist");
		if( ! is_readable($filepath) )
			Throw new RuntimeException("File {$filepath} is not readable");
	}
}
