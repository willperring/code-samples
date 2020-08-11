<?php
/**
 * LogFile.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   2019-07-03
 */

namespace Core\Logging;


use Exception;

/**
 * Class LogFile
 *
 * This class represents a log file on the server, and provides a much-needed way to quickly
 * and easily set up log streams across the project. Constructed with a single parameter (the
 * extension-free filename) you can easily write to the file using write(), or for more
 * convenience, one of the alias methods that passes the log level for you. Like in the
 * Google Chrome console, these methods are called info(), warn(), and error().
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   20/09/2018
 * @version 1.0
 */
class LogFile extends WriteableLocation {
	
	/*
	 * These constants are used for code readability, but rather
	 * than containing numbers, these are the prefixes for the
	 * log line - hence why they are all the same length. The
	 * exclamation marks are positioned for quick scanning down a log
	 * file.
	 */
	const LEVEL_INFO  = '   info';
	const LEVEL_WARN  = '!  warn';
	const LEVEL_ERROR = '! ERROR';
	
	/**
	 * LogFile constructor.
	 *
	 * When constructing LogFile instances, the only parameter that needs to be passed is the filename,
	 * WITHOUT the extension. ".log" will automatically be appended when obtaining the resource handle.
	 *
	 * @param string $filename The extension-free filename for the log
	 *
	 * @throws Exception
	 */
	public function __construct( $filename ) {
		$this->_getResourceHandle( $filename . '.log' );
	}
	
	/**
	 * Write a line to the log file
	 *
	 * The parameter passed here merely need to contain the content for the log. The header
	 * will be generated for you.
	 *
	 * @TODO: handle new lines within the log entry?
	 *
	 * @param string $logValue The log entry to record
	 * @param string $level    The error level - see the class constants.
	 * @return mixed
	 *
	 * @throws Exception
	 * @since 20/09/2018
	 */
	public function write( $logValue, $level=self::LEVEL_INFO ) {
		$logLine = $this->_getLogPrefix( $level ) . $logValue . "\n";
		return $this->_writeToResource( $logLine );
	}
	
	/**
	 * Alias to write a LEVEL_INFO log entry
	 *
	 * @param string $logValue The log entry to record
	 *
	 * @see LogFile::write()
	 * @throws Exception
	 * @since 20/09/2018
	 */
	public function info( $logValue ) {
		/** @noinspection ArgumentEqualsDefaultValueInspection */
		$this->write( $logValue, self::LEVEL_INFO );
	}
	
	/**
	 * Alias to write a LEVEL_WARN log entry
	 *
	 * @param string $logValue The log entry to record
	 *
	 * @see LogFile::write()
	 * @throws Exception
	 * @since 20/09/2018
	 */
	public function warn( $logValue ) {
		$this->write( $logValue, self::LEVEL_WARN );
	}
	
	/**
	 * Alias to write a LEVEL_ERROR log entry
	 *
	 * @param string $logValue The log entry to record
	 *
	 * @see LogFile::write()
	 * @throws Exception
	 * @since 20/09/2018
	 */
	public function error( $logValue ) {
		$this->write( $logValue, self::LEVEL_ERROR );
	}
	
	/**
	 * Generates the prefix to the log entry
	 *
	 * In order to be as readable as possible, this function aims to have distinct 'columns' of spaces in the
	 * start of the log entry. It's for this reason that all the class constants contain strings of equal
	 * length, with aligned exclamation marks on the LEVEL_WARN and LEVEL_ERROR log levels. The goal is that
	 * it would be easy to scan downwards through a list of entries to easily identify the significant data.
	 *
	 * @param string $level The string to display denoting the seriousness of the information recorded
	 *
	 * @return string Log Entry prefix
	 * @since 20/09/2018
	 */
	protected function _getLogPrefix( $level ) {
		return '[ ' . date('Y-m-d H:i:s O') . " : {$level} ]  ";
	}
	
}

