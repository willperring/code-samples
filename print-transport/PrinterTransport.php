<?php
/**
 * PrinterTransport.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   13/12/2019
 */

namespace Core\Printing;


use Core\Exceptions\Printing\InvalidMediaTypeException;
use Core\Interfaces\PrintableMedia;

abstract class PrinterTransport {
	
	protected static $_developmentMode = false;
	
	/**
	 * Set printing development mode
	 *
	 * @static
	 *
	 * @param bool $mode
	 *
	 * @since 13/12/2019
	 */
	public static function setDevelopmentMode( $mode ) {
		static::$_developmentMode = $mode;
	}
	
	/**
	 * @param PrintableMedia $media
	 * @return PrintingResult
	 */
	abstract protected function _transport( PrintableMedia $media );
	
	/**
	 * Print an Item
	 *
	 * @param PrintableMedia $media
	 *
	 * @throws InvalidMediaTypeException
	 * @return PrintingResult
	 * @since 13/12/2019
	 */
	public function printMedia( PrintableMedia $media ) {
		// TODO - some kind of check maybe?
		return $this->_transport($media);
	}
	
}
