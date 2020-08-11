<?php
/**
 * CABPrinterConfig.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   19/12/2019
 */

namespace Core\Printing\PrinterConfig;


use Core\Command;
use Core\Exceptions\Printing\InvalidPrinterTransportException;
use Core\Interfaces\PrintableMedia;
use Core\Models\Printer\PrinterType;
use Core\Printing\PrinterTransport\CABPrinterTransport;
use Core\Printing\PrinterTransport\FTPPrinterTransport;
use Core\Printing\TestDocuments\CABTestDocument;

/**
 * CAB Printer Configuration
 * 
 * CAB Printers print onto thermal adhesive labels. They use a specific language to 
 * communicate their content to the printer
 * @see Core\Printing\MediaType\CABLabael
 */
class CABPrinterConfig extends FTPPrinterConfig {
	
	const LABEL_ENDLESS = 'e';
	const LABEL_FIXED   = 'f';
	
	const HEIGHT_ENDLESS = -1;
	
	protected $_labelType   = self::LABEL_ENDLESS;
	protected $_labelHeight = self::HEIGHT_ENDLESS;
	protected $_labelWidth  = 20;
	
	protected $_defaultHeat  = 75;
	protected $_overrideHeat = null;
	
	public function configure( Command $command ) {
		parent::configure( $command );
		
		$this->_labelType = $command->choice( 'Label Type', [
			static::LABEL_ENDLESS => 'Endless',
			static::LABEL_FIXED   => 'Fixed'
		], $this->_labelType );
		
		$this->_labelHeight = ( $this->_labelType === self::LABEL_FIXED )
			? $command->ask('Label Height')
			: static::HEIGHT_ENDLESS ;
		
		$this->_labelWidth  = $command->ask( 'Label Width',  $this->_labelWidth );
		$this->_defaultHeat = $command->ask( 'Default Heat', $this->_defaultHeat );
	}
	
	public function getTransport() {
		
		/** @var FTPPrinterTransport $transport */
		$transport = $this->_getEmptyTransportClass();
		if( ! is_a($transport, CABPrinterTransport::class) )
			Throw new InvalidPrinterTransportException('Empty transport does not inherit from CABPrinterTransport');
		
		$transport
			->setDestination( $this->_host, $this->_port )
			->setCredentials( $this->_username, $this->_password )
			->setConfig( $this )
		;
		
		return $transport;
	}
	
	
	public function canPrint( PrintableMedia $media ) {
		return $media->getMediaTypeFlag() === PrinterType::MEDIA_BARCODE_LABEL;
	}
	
	public function getTestDocument() {
		return new CABTestDocument( $this );
	}
	
	public function getDefaultHeat() {
		return $this->_defaultHeat;
	}
	
	public function getLabelType() {
		return $this->_labelType;
	}
	
	public function getLabelWidth() {
		return $this->_labelWidth;
	}
	
	public function getLabelHeight() {
		return $this->_labelHeight;
	}
	
	protected function _inflateFromDatabase( $params ) {
		parent::_inflateFromDatabase( $params );
		$this->_labelType   = @$params->type   ?: self::LABEL_ENDLESS;
		$this->_labelHeight = @$params->height ?: self::HEIGHT_ENDLESS;
		$this->_labelWidth  = @$params->width  ?: $this->_labelWidth;
		$this->_defaultHeat = @$params->heat   ?: $this->_defaultHeat;
	}
	
	protected function _deflateToDatabase() {
		return array_merge( parent::_deflateToDatabase(), [
			'type'   => $this->_labelType,
			'height' => $this->_labelHeight,
			'width'  => $this->_labelWidth,
			'heat'   => $this->_defaultHeat
		]);
	}
	
	
	protected function _getEmptyTransportClass() {
		return new CABPrinterTransport();
	}
}
