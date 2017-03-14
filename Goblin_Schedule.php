<?php

/**
 * Goblin Framework Scheduler Class
 *
 * This class allows for times and dates to be converted into 'schedule numbers', which can be used
 * for things like content release dates - rather than hardcoding dates and times into a database,
 * which can be awkward for testing, this class allows you to set start times and interval lengths,
 * which will then be converted into numbers relative to the start time
 * 
 * @author Will Perring <@willperring>
 */
Class Goblin_Schedule {

	const FORMAT_INT   = 1;
	const FORMAT_FLOAT = 2;
	
	const UNIT_SECOND = 1;
	const UNIT_MINUTE = 2;
	const UNIT_HOUR   = 3;
	const UNIT_DAY    = 4;
	const UNIT_WEEK	  = 5;
	const UNIT_MONTH  = 6;
	const UNIT_YEAR   = 7;
	
	/** @var Array(int) multipliers indexed by UNIT_ constant: null (zero-index), second, minute, hour, day, week */
	protected $multipliers = array(0, 1, 60, 3600, 86400, 604800); 
	
	/** @var array Default Initialisation Options */
	protected $defaults = array(
		'start'	    => false,
		'interval'  => 3600, 	// one hour
		'subunits'  => 60,		// minutes
		'format'    => self::FORMAT_INT,
		'zeroIndex' => false,
		'zeroSubIndex' => true,
	);
	
	/** @var int|string Valid time to be processed by strtotime() */
	protected $startTime;
	/** @var int Number of 'units' per interval, e.g. 36 seconds would be 36 */
	protected $intervalCount;
	/** @var int Unit of interval in seconds, eg seconds =1, minutes = 60 */
	protected $intervalUnit;
	/** @var int Number of subunits per interval */
	protected $subUnits;
	/** @var int Format required as output @see FORMAT_INT @see FORMAT_FLOAT */
	protected $format;
	
	/**
	 * Constructor
	 *
	 * @param Array $options Override default instatiation options
	 */
	public function __construct( $options = array() ) {
		
		// merge the options parameters
		$options = array_merge(
			$this->defaults, 
			array_intersect_key(
				$options, 
				$this->defaults
			)
		);
		
		// set defaults
		$this->setStart(   $options['start']    )
			->setInterval( $options['interval'] )
			->setSubUnits( $options['subunits'] )
			->setFormat(   $options['format']   );
	}
	
	/**
	 * Set the start time for the first interval
	 *
	 * @param string|int $startTime Time to be parsed by strtotime()
	 *
	 * @throws Goblin_Exception
	 * @return $this For Method Chaining
	 */
	public function setStart( $startTime ) {
		
		if( is_string($startTime) ) {
			$time = strtotime( $startTime );
			if( $time === false ) {
				Throw new Goblin_Exception("Can't interpret string '{$startTime}' as time");
			}
			$this->startTime = $time;
		
		} else if( is_numeric($startTime) ) {
			$this->startTime = $startTime;
		
		} else if( $startTime === false ) {
			$this->startTime = false;
		
		}
		
		return $this;
	}
	
	/**
	 * Set Interval Length
	 *
	 * Sets the length of an interval. For readability, constants are provided at the top of this class
	 *
	 * @param int|string $interval Interval Length in seconds, or string e.g '2 hours'
	 *
	 * @throws Goblin_Exception
	 * @return $this For Method chaining
	 */
	public function setInterval( $interval ) {
		
		if( is_numeric($interval) ) {
			$this->intervalCount = $interval;
			$this->intervalUnit  = constant('self::UNIT_SECOND');
			
		} else if( preg_match('/^([0-9]*) (second|minute|hour|day|week|month|year)(|s)$/i', $interval, $matches) ) {
			$count = $matches[1];
			$unit  = $matches[2];
			
			$constantName = "UNIT_".strtoupper($unit);
			if( !defined('self::'.$constantName) ) {
				Throw new Goblin_Exception("Can't get identifier for unit '{$unit}'");
			}
			
			$this->intervalCount = $count;
			$this->intervalUnit  = constant('self::'.$constantName);
		
		} else {
			Throw new Goblin_Exception("Couldn't interpret interval '{$interval}'");
		}
		
		return $this;
	}
	
	/**
	 * Set Number of Subunits
	 *
	 * Use false to denote no subunits, rather than zero
	 *
	 * @param int|bool $subUnits Number of subunits, or false.
	 *
	 * @throws Goblin_Exception
	 * @return $this For Method chaining
	 */	
	public function setSubUnits( $subUnits ) {
		
		if( !is_numeric($subUnits) && $subUnits !== false ) {
			Throw new Goblin_Exception("Sub Units must be numeric or false");
		}
		
		$this->subUnits = $subUnits;
		
		return $this;
	}
	
	/**
	 * Set Return format
	 *
	 * Current interval can be returned as a string, or a float. This is primarily for 
	 * intervals with subunits - for example, 
	 * 
	 * Interval as float -> Interval as int
	 * 3.01                 301
	 * 3.10                 310
	 * 6.001                6001
	 * 6.125                6125
	 *
	 * @see FORMAT_FLOAT
	 * @see FORMAT_INT
	 *
	 * @param int $format Interval Format
	 *
	 * @throws Goblin_Exception
	 * @return $this For Method chaining
	 */
	public function setFormat( $format ) {
		
		if( !is_numeric($format) ) {
			Throw new Goblin_Exception('Format must be numeric - see class Constants FORMAT_*');
		}
		
		$this->format = $format;
		
		return $this;
	}
	
	/**
	 * Return the current interval, based on start time and configured options
	 *
	 * @throws Goblin_Exception
	 * @return int|float Current Interval
	 */
	public function getIndex( $timestamp=false ) {
		
		if( !is_numeric($this->startTime) ) {
			Throw new Goblin_Exception('No start time has been defined');
		}
		
		// seconds that have elapsed since the start time
		$nowTime = time();
		if( $timestamp ) {
			if( !is_numeric($timestamp) )
				Throw new Goblin_Exception('Invalid Timestamp');
			$nowTime = $timestamp;
		}

		$secondsSinceStart = $nowTime - $this->startTime;

		// calculate how many of the interval unit has passed since then
		switch( $this->intervalUnit ) {
			// implementation incomplete - will always work from 00:00. needs hours timing.
			case self::UNIT_MONTH:
				
				$start = array(
					'date'  => date('d', $this->startTime),
					'month' => date('m', $this->startTime),
					'year'  => date('Y', $this->startTime)
				);
				
				$end = array(
					'date'  => date('d'),
					'month' => date('m'),
					'year'  => date('Y')
				);
				
				$subUnitsPassed = false;
				
				// get the difference
				$unitsPassed = $end['month'] - $start['month'];
				
				// if negative, add 12
				$unitsPassed = ($unitsPassed < 0) ? $unitsPassed+12 : $unitsPassed;
				
				// add 1 if the dates overlap
				$unitsPassed = ($end['date'] < $start['date']) ? $unitsPassed : $unitsPassed+1;
				
				break;
				
			case self::UNIT_YEAR:
				// @TODO: - add in logic for these intervals - leap years mess things up
				$subUnitsPassed = false; // no sub-units on these measurements
				Throw new Goblin_Exception('Code not completed - no implementation for years');
				break;
			// for standard, second-based intervals: second, minute, hour, day, week
			default:
				$multiplier 	= $this->multipliers[ $this->intervalUnit ];
				$intervalFactor = $multiplier * $this->intervalCount;
				$unitsPassed	= floor( $secondsSinceStart / $intervalFactor ) + (($secondsSinceStart > -1) ? 1 : 0);
				// get the leftover (sub-units) and express them as a fraction of the multiplier, then multiply by the subunit count
				$subUnitsPassed = ($this->subUnits) ? floor((($secondsSinceStart % $intervalFactor) / $intervalFactor) * $this->subUnits) : false;
		}
		
		// round down the logarithm and add 1: log(10) = 1 + 1, so 2 spaces req'd.
		// log(9) = 0.9.., gets rounded to 0, +1 = 1. perfect.
		$charsRequired = ($this->subUnits !== false) ? floor(log10($this->subUnits))+1 : false;

		// make sure we have enough leading zeros on the subUnits.
		if( $subUnitsPassed !== false ) {
			$subUnitsPassed = str_pad( (string) $subUnitsPassed, $charsRequired, '0', STR_PAD_LEFT );
		}
		
		switch( $this->format ) {
			
			case self::FORMAT_INT: 
				return ( $subUnitsPassed !== false )
					? intval( $unitsPassed.$subUnitsPassed )
					: intval( $unitsPassed ) ;					
				break;
				
			case self::FORMAT_FLOAT:
				$number = ( $subUnitsPassed !== false )
					? floatval( $unitsPassed.".".$subUnitsPassed )
					: intval( $unitsPassed ) ;
				
				return ($charsRequired !== false) ? number_format($number, $charsRequired) : $number ;				
				break;
			
			default:
				Throw new Goblin_Exception('Unrecognised format code');
				break;
		}
		
		return 1;
	}


}