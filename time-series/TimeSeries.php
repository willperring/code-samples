<?php
/**
 * TimeSeries.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   2019-06-20
 */

namespace App\KPI\DataType;


use App\Http\Resources\KPI\DataType\TimeSeriesResource;
use App\Http\Resources\KPI\DataTypeResource;
use App\KPI\DataType;
use App\KPI\DataType\Interfaces\AjaxData;
use App\KPI\DataType\Interfaces\Chartable;
use App\KPI\DataType\Interfaces\ConsoleTable;
use App\KPI\DataType\TimeSeries\Axis;
use App\KPI\DateContext;
use App\Sentry\BreadcrumbFactory;
use Closure;
use DateInterval;
use DateTime;
use Illuminate\Http\Resources\Json\JsonResource;
use Sentry\Laravel\Facade as Sentry;
use Symfony\Component\Console\Helper\TableSeparator;
use UnexpectedValueException;

/**
 * Class TimeSeries
 *
 * The TimeSeries represents a data plot over a certain time period. When instantiating, a DateContext
 * is provided to indicate the range required and how values should be grouped (i.e, daily, weekly,
 * monthly, etc). Internally, a series is then created containing a slot for each grouping in the range.
 *
 * Data is then added to the series in the form of Axes. This represents a single value changing over time.
 * The TimeSeries itself can be given a default value, as can each axis. Also, there is the concept of a
 * 'Value Transformer', which is a closure that can be defined either on the TimeSeries or on an individual
 * axis which is applied to each value before it is returned for display.
 *
 * A lot of the methods in here require a '$time' parameter - this is either an instance of DateTime or
 * a strtotime() compatible string which will be converted to a DateTime automatically.
 *
 * @package App\KPI\DataType
 * @author  Will Perring <will@supernatural.ninja>
 * @version 1.0
 */
class TimeSeries extends DataType implements ConsoleTable, Chartable, AjaxData {

    // Orientation for console table display
    const TABLE_COLUMN_AXES = 'column-axes'; // Columns represent the Axes
    const TABLE_COLUMN_TIME = 'column-time'; // Columns represent the series

    /**
     * The time series
     *
     * This is represented as an array of arrays, with each child array containing:
     * key    => This is key used to identify the series group
     * label  => How this group should be labelled
     * stamp  => Where this value should be represented on a time graph
     * period => (optional) for continuous periods (ie, ones with no overlap and no break - not
     *           by weekday, for example) then a Period object containing the start and end point.
     *
     * @var array[]
     */
    protected $_series = array();

    /** @var Axis[] */
    protected $_axes = array();

    /** @var mixed|null Default value for each axis group value  */
    protected $_axisDefaultValue;

    /** @var Closure Optional function to transform values */
    protected $_valueTransformer;

    /** @var string Orientation for console table */
    protected $_consoleTableOrientation = self::TABLE_COLUMN_AXES;

    /**
     * TimeSeries constructor.
     *
     * @param string      $title   Title of the data being displayed
     * @param DateContext $context Time period and grouping configuration
     */
    public function __construct( $title, DateContext $context ) {
        parent::__construct( $title, $context );
        $this->_fillSeries();
    }

    /**
     * Add an Axis to the TimeSeries
     *
     * PHP doesn't let us overload functions, but this has as close as you'll get - essentially,
     * it has two signatures. You can call this with the key, a display name, an optional default
     * values and metadata - this will create an Axis object for you. Alternatively, you can create
     * the Axis yourself and pass it as the second argument, after the key. In the second instance,
     * the default value and metadata will be discarded (if supplied).
     *
     * @param string      $key          The array key for the new axis
     * @param string|Axis $name         The axis title, or an axis object.
     * @param mixed       $defaultValue (optional) Axis default value. Ignored when providing an Axis object.
     * @param array       $metadata     (optional) Axis metadata. Ignored when providing an Axis object.
     *
     * @return Axis
     * @since 14/01/2020
     */
    public function addAxis( $key, $name, $defaultValue=null, $metadata=array() ) {
        if( array_key_exists($key, $this->_axes) )
            Throw new \UnexpectedValueException("Duplicate Axis Key: {$key}");

        if( is_a($name, Axis::class) ) {
            $this->_axes[ $key ] = $name;
            return $name;
        }

        /** @see https://www.php.net/manual/en/migration70.new-features.php#migration70.new-features.null-coalesce-op */
        $defaultValue = $defaultValue ?? ( is_object($this->_axisDefaultValue)
            ? clone $this->_axisDefaultValue
            : $this->_axisDefaultValue
        );

        $axis = new Axis( $name, $defaultValue, $metadata );
        $this->_axes[ $key ] = $axis;

        return $axis;
    }

    /**
     * Check to see if an axis exists by key
     *
     * @param string $key Key to check
     *
     * @return bool
     * @since 14/01/2020
     */
    public function axisExists( $key ) {
        return array_key_exists( $key, $this->_axes );
    }

    /**
     * Return all axes as an array
     *
     * @return Axis[]
     * @since 14/01/2020
     */
    public function getAxes() {
        return $this->_axes;
    }

    /**
     * Get the axis for a specific key
     *
     * @param string $key
     *
     * @return Axis
     * @since 14/01/2020
     */
    public function getAxis( $key ) {
        return $this->_getAxisForKey( $key );
    }

    /**
     * Get the number of groupings in the series
     *
     * For example, if you were grouping by day over three weeks it would return 21,
     * if it were hours over 3 days it would be 72.
     *
     * @return int
     * @since 14/01/2020
     */
    public function getSeriesLength() {
        return count( $this->_series );
    }

    /**
     * Get the number of axes currently in the series
     *
     * @return int
     * @since 14/01/2020
     */
    public function getAxesCount() {
        return count( $this->_axes );
    }

    /**
     * Add a reference line to the graph display
     *
     * This is really just a helper method - it sets a value within the metadata for the
     * time series which is then used in the front end to show a reference line. Because
     * more than one might be required, it also checks to see if there are existing lines
     * and adds them to the array rather than overwriting.
     *
     * @param string    $name  Name of the line, for display purposes
     * @param int|float $value The Y value at which the line should appear (where X is time)
     *
     * @return $this
     * @since 14/01/2020
     */
    public function addRefLine( $name, $value ) {
        if( ! array_key_exists('ref-lines', $this->_meta) || ! is_array($this->_meta['ref-lines']) )
            $this->_meta['ref-lines'] = array();
        $this->_meta['ref-lines'][] = [ 'name' => $name, 'value' => $value ];

        return $this;
    }

    /**
     * Get a value for a given time and axis
     *
     * This retrieves a value by taking a time (either a DateTime or string which can be converted
     * to DateTime), identifies the correct grouping on the series and then fetches the value from the
     * axis, with the value transformer optionally applied.
     *
     * @param string          $axisKey   The axis identifier
     * @param DateTime|string $time      The time to get a value for
     * @param bool            $transform Whether to apply the value transformer
     *
     * @return mixed
     * @since 14/01/2020
     */
    public function getValue( $axisKey, $time, $transform=true ) {
        $key = $this->_context->getGroupForTime( $time );
        $this->_validateSeriesKey( $key );

        $axis = $this->_getAxisForKey( $axisKey );

        return $transform
            ? $this->_getTransformedAxisValue( $axis, $key )
            : $axis->getValue( $key );
    }

    /**
     * Set a default value for each axis
     *
     * Note: as it stands currently, this must be set *before* adding axes to the series.
     * If set afterwards, it will not be applied.
     *
     * @param mixed $default Default value for axes
     *
     * @return $this
     * @since 14/01/2020
     */
    public function setAxisDefaultValue( $default ) {
        $this->_axisDefaultValue = $default;
        return $this;
    }

    /**
     * Set a value for a specific axis and time
     *
     * There are numerous ways to alter a value on the series - this is the most basic.
     * You simply set the value that you want at the time and axis you want it.
     *
     * @param string          $axisKey Axis key to set the value for
     * @param DateTime|string $time    The time to set the value on the axis
     * @param mixed           $value   The value to set
     *
     * @return $this
     * @since 14/01/2020
     */
    public function setValue( $axisKey, $time, $value ) {
        $key = $this->_context->getGroupForTime( $time );
        $this->_validateSeriesKey( $key );

        $this->_getAxisForKey( $axisKey )->setValue( $key, $value );
        return $this;
    }

    /**
     * Set a value transformer for the series
     *
     * A value transformer is a Closure that accepts and returns a single value. It will
     * be given the value is it is provided from the axis and apply the callback, displaying
     * the returned value
     *
     * @param Closure $transformer
     *
     * @return $this
     * @since 14/01/2020
     */
    public function setValueTransformer( Closure $transformer ) {
        $this->_valueTransformer = $transformer;
        return $this;
    }

    /**
     * Returns the configured value transformer
     *
     * @return Closure
     * @see TimeSeries::setValueTransformer()
     * @since 14/01/2020
     */
    public function getValueTransformer() {
        return $this->_valueTransformer;
    }

    /**
     * Transform a value
     *
     * Applies the value transformer (if configured) to a given value. Returns the value
     * unchanged if no transformer has been et
     *
     * @param mixed $value
     *
     * @return mixed
     * @see TimeSeries::setValueTransformer()
     * @since 14/01/2020
     */
    public function transformValue( $value ) {
        $valueTransformer = $this->_valueTransformer;
        return $valueTransformer ? $valueTransformer($value) : $value ;
    }

    /**
     * Increment a value by a certain amount
     *
     * This is the second way to interact with values in axes. Rather than setting
     * a value, you can increment (or decrement with a negative amount) a value
     * by providing the axis key and a time
     *
     * @param string          $axisKey Axis key to adjust value for
     * @param DateTime|string $time    Time to alter the value for
     * @param int             $amount  Amount to alter by
     *
     * @return $this
     * @since 14/01/2020
     */
    public function incrementValue( $axisKey, $time, $amount=1 ) {
        $key = $this->_context->getGroupForTime( $time );
        $this->_validateSeriesKey( $key );

        $this->_getAxisForKey( $axisKey )->incrementValue( $key, $amount );
        return $this;
    }

    /**
     * Add a value to an array
     *
     * Another method of interacting with values, this adds a value to an array on a
     * given axis at a given time.
     *
     * @param string          $axisKey Axis key to adjust value for
     * @param DateTime|string $time    Time to alter the value for
     * @param mixed           $value   Value to add to the array
     *
     * @return $this
     * @since 14/01/2020
     */
    public function addArrayValue( $axisKey, $time, $value ) {
        $key = $this->_context->getGroupForTime( $time );
        $this->_validateSeriesKey( $key );

        $this->_getAxisForKey( $axisKey )->addArrayValue( $key, $value );
        return $this;
    }

    /**
     * Set an object as a value in an array
     *
     * This requires a slightly more detailed understanding of the internals of the Axis
     * class to comprehend, but it acts similarly to setValue(), but setting the internal
     * mode of the axis to 'object' rather than 'value'. This allows the use of the updateObject()
     * method on the TimeSeries.
     *
     * @param string          $axisKey Axis key to adjust value for
     * @param DateTime|string $time    Time to alter the value for
     * @param mixed           $object  The object to set
     *
     * @return $this
     * @since 14/01/2020
     */
    public function setObject( $axisKey, $time, $object ) {
        $key = $this->_context->getGroupForTime( $time );
        $this->_validateSeriesKey( $key );

        $this->_getAxisForKey( $axisKey )->setObject( $key, $object );
        return $this;
    }

    /**
     * Update an object for an axis
     *
     * If a value is more complex than a simple array or scalar value, we can update an object for an axis.
     * The first two parameters are the same as all the other value interactions here, but the third is a
     * callback which accepts a single value (the current value for the axis at that point, or the default)
     * and returns an updated object which is then set in its place.
     *
     * @param string          $axisKey        Axis key to adjust value for
     * @param DateTime|string $time           Time to alter the value for
     * @param Closure         $updateFunction Callback to apply to value
     *
     * @return $this
     * @since 14/01/2020
     */
    public function updateObject( $axisKey, $time, Closure $updateFunction ) {
        $key = $this->_context->getGroupForTime( $time );
        $this->_validateSeriesKey( $key );

        $this->_getAxisForKey( $axisKey )->updateObject( $key, $updateFunction );
        return $this;
    }

    /**
     * Sort the axes by their display name
     *
     * @return $this
     * @since 14/01/2020
     */
    public function sortAxesByName() {
        usort( $this->_axes, function( Axis $a, Axis $b ) {
            return $a->getName() <=> $b->getName();
        });

        return $this;
    }

    /**
     * Sort the axes by a provided callback
     *
     * @param Closure $callback Sorting function. Receives two instances of Axis
     *
     * @return $this
     * @since 14/01/2020
     */
    public function sortAxesBy( Closure $callback ) {
        usort( $this->_axes, $callback );
        return $this;
    }

    /**
     * Apply a callback to each value in each axis
     *
     * This functions as per most standard language map() functions.
     * See the internals of Axis for more understanding
     *
     * @param Closure $callback Map callback to apply
     *
     * @return $this
     * @see Axis::map()
     * @since 14/01/2020
     */
    public function mapAxesValues( Closure $callback ) {
        foreach( $this->_axes as $axis ) {
            $axis->map( $callback );
        }
        return $this;
    }

    /**
     * Filter axes by a specific callback
     *
     * The callback receives an instance of Axis and should return a
     * truthy/falsy value
     *
     * @param Closure $callback Callback function
     *
     * @return $this
     * @since 14/01/2020
     */
    public function filterAxes( Closure $callback ) {
        $filtered = array();
        foreach( $this->_axes as $key => $axis ) {
            if( $callback($axis) )
                $filtered[ $key ] = $axis;
        }

        $this->_axes = $filtered;
        return $this;
    }

    /**
     * Remove an axis by key
     *
     * @param string $key Axis to remove
     *
     * @return $this
     * @since 14/01/2020
     */
    public function removeAxis( $key ) {
        unset( $this->_axes[$key] );
        return $this;
    }

    /**
     * Removes axes that contain no data
     *
     * @return $this
     * @since 14/01/2020
     */
    public function removeEmptyAxes() {
        $this->_axes = array_filter( $this->_axes, function( Axis $axis ) {
            return ! $axis->isEmpty();
        });

        return $this;
    }

    /**
     * Set the orientation of the table for console view
     *
     * Use the class constants, plz! :)
     *
     * @param string $orientation
     *
     * @return $this
     * @since 14/01/2020
     */
    public function setTableOrientation( $orientation ) {
        $this->_consoleTableOrientation = $orientation;
        return $this;
    }

    /**
     * Return data for a Laravel Console table
     *
     * Required by interface ConsoleTable.
     * This returns a stdClass instance with two keys,
     * headers -> array of strings for table headers
     * rows    -> array of arrays, for table rows
     *
     * @return \stdClass
     * @since 14/01/2020
     */
    public function toConsoleTable(): \stdClass {
        switch( $this->_consoleTableOrientation ) {
            case self::TABLE_COLUMN_AXES:
                return $this->_toTableColumnAxes();
            case self::TABLE_COLUMN_TIME:
                return $this->_toTableColumnTime();
            default:
                throw new UnexpectedValueException( 'Unknown console table orientation: ' . $this->_consoleTableOrientation );
        }
    }

    /**
     * Get chart data for the time series
     *
     * Required by interface Chartable.
     * Returns [
     *   axis-key => [
     *     series-key => series-value
     *   ]
     * ]
     *
     * @return array
     * @since 14/01/2020
     */
    public function toChart() {

        $axes = array();
        foreach( $this->_axes as $key => $axis ) {
            $axes[ $key ] = array();
            foreach( $this->_series as $series ) {
                $axes[ $key ][ $series['key'] ] = $this->_getTransformedAxisValue( $axis, $series['key'] );
            }
        }

        return $axes;
    }

    /**
     * Returns the data to be sent over Ajax.
     *
     * This is called by the parent class when returning data over ajax. This means that
     * DataTypes have a standardized structure. Check the front-end rendering code before
     * making any changes to what is returned here.
     *
     * @param $request
     *
     * @return array
     * @see DataType::toResource()
     * @see Axis::toAjaxData()
     * @since 14/01/2020
     */
    public function toAjaxData( $request ): array {

        $axes = array();

        /**
         * @var string $key
         * @var Axis   $axis
         */
        foreach( $this->_axes as $key => $axis ) {
            $axes[] = $axis->toAjaxData( $request, $this->_valueTransformer );
        }

        return array(
            'series' => array_values($this->_series),
            'axes'   => $axes,
        );
    }

    /**
     * Get as a Laravel Resource
     *
     * @return DataTypeResource
     * @since 14/01/2020
     */
    public function toResource(): DataTypeResource {
        return new TimeSeriesResource( $this );
    }

    /**
     * Return the axis for a given key
     *
     * This includes a test, and throws an exception if not found
     *
     * @param string $key Axis key
     *
     * @return Axis
     * @throws UnexpectedValueException
     * @since 14/01/2020
     */
    protected function _getAxisForKey( $key ): Axis {
        if( ! array_key_exists($key, $this->_axes) )
            Throw new \UnexpectedValueException("No Axis for Key: {$key}");
        return $this->_axes[ $key ];
    }

    /**
     * Fill the series
     *
     * One of the first tasks the TimeSeries needs to undertake upon construction is the
     * generation of the series data (this is so that the series is complete, rather than
     * only being the groupings that have data). This function delegates that task out,
     * depending on the type of grouping required.
     *
     * @return bool|void
     * @since 14/01/2020
     */
    protected function _fillSeries() {
        switch( $this->_context->getGroupByType() ) {
            case DateContext::GROUP_HOUR:
            case DateContext::GROUP_DAY:
            case DateContext::GROUP_WEEK:
            case DateContext::GROUP_MONTH:
            case DateContext::GROUP_QUARTER:
            case DateContext::GROUP_YEAR:
                return $this->_fillSeriesIncremental();
            case DateContext::GROUP_WEEKDAY:
                return $this->_fillSeriesWeekdays();
            case DateContext::GROUP_TIME:
                return $this->_fillSeriesTime();
            case DateContext::GROUP_ALL:
                return $this->_fillSeriesSingle();
        }

        Throw new \UnexpectedValueException('Unable to fill TimeSeries for groupBy type ' . $this->_context->getGroupByType() );
    }

    /**
     * Fill the series for any incremental groupings
     *
     * Incremental groupings are groupings that follow each other sequentially with
     * no overlap - so for example, by day, week, month, etc. It does not cover
     * groupings like time of day, day of week.
     *
     * The process is that it started from the start date, moving forward by the grouping
     * period, until is has exceeded the end date.
     *
     * @return bool
     * @throws \Exception
     * @since 14/01/2020
     */
    protected function _fillSeriesIncremental() {

        $groupByType = $this->_context->getGroupByType();

        /** @var string $period A DatePeriod compatible string */
        $period = null;

        switch( $groupByType ) {
            case DateContext::GROUP_HOUR:
                $period = 'PT1H';
                break;
            case DateContext::GROUP_DAY:
                $period = 'P1D';
                break;
            case DateContext::GROUP_WEEK:
                $period = 'P1W';
                break;
            case DateContext::GROUP_MONTH:
                $period = 'P1M';
                break;
            case DateContext::GROUP_QUARTER:
                $period = 'P3M';
                break;
            case DateContext::GROUP_YEAR:
                $period = 'P1Y';
                break;
            default:
                Throw new \UnexpectedValueException("Invalid group by type: {$groupByType}");
        }

        $increment  = new DateInterval( $period );
        $seriesTime = clone $this->_context->getFromDate();
        $endDate    = $this->_context->getToDate();
        $breaker    = 9999;

        // If we're incrementing in months then we need to make sure that we don't use a date
        // higher than 28 (because the increment gets confused if we try and jump one month forward
        // from something like the 31st to a month with only 30 days).
        if( in_array($groupByType, [DateContext::GROUP_MONTH, DateContext::GROUP_QUARTER], false) ) {
            $date = (int) $seriesTime->format('d');
            if( $date > 28 ) {
                $seriesTime->setDate( $seriesTime->format('Y'), $seriesTime->format('m'), 28 );
            }
        }

        while( $seriesTime <= $endDate && --$breaker ) {

            $group  = $this->_context->getGroupForTime( $seriesTime );
            $label  = $this->_context->getLabelForTime( $seriesTime );
            $period = $this->_context->getPeriodForTime( $seriesTime );

            $this->_series[ $group ] = array(
                'key'    => $group,
                'label'  => $label,
                'stamp'  => $seriesTime->getTimestamp(),
                'period' => $period
            );

            $seriesTime->add( $increment );
        }

        $group = $this->_context->getGroupForTime( $endDate );
        if( ! array_key_exists($group, $this->_series) ) {
            $label = $this->_context->getLabelForTime( $endDate );
            $this->_series[ $group ] = array(
                'key'   => $group,
                'label' => $label,
                'stamp' => $endDate->getTimestamp()
            );
        }

        return true;

    }

    /**
     * Fill series with Days of the week
     *
     * @return bool
     * @since 14/01/2020
     */
    protected function _fillSeriesWeekdays() {

        $days = array('', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');

        foreach( $days as $key => $day ) {
            if( $key == 0 )
                continue;

            $this->_series[ $key ] = array(
                'key'   => $key,
                'label' => $day,
                'stamp' => null,
            );
        }

        return true;
    }

    /**
     * Fill series with hours of the day
     *
     * @return bool
     */
    protected function _fillSeriesTime() {
        for( $i=0; $i<24; $i++ ) {
            $hourPadded                   = str_pad( $i, 2, '0', STR_PAD_LEFT );
            $this->_series[ $hourPadded ] = array(
                'key'   => $hourPadded,
                'label' => "{$hourPadded}:00",
                'stamp' => null,
            );
        }

        return true;
    }

    /**
     * Fill series with a single grouping
     *
     * This is only used for any DateContexts that have the 'all' grouping
     *
     * @since 14/01/2020
     */
    protected function _fillSeriesSingle() {
        $key   = $this->_context->getGroupForTime( $this->_context->getFromDate() );
        $label = $this->_context->getLabelForTime( $this->_context->getFromDate() );
        $this->_series[ $key ] = array(
            'key'   => $key,
            'label' => $label,
            'stamp' => null,
        );
    }

    /**
     * Get a transformed value from an axis at a certain time
     *
     * @param Axis            $axis Axis to get value from
     * @param DateTime|string $key  Time to get value for
     *
     * @return mixed
     * @since 14/01/2020
     */
    protected function _getTransformedAxisValue( Axis $axis, $key ) {
        $valueTransformer = $this->_valueTransformer;
        $axisValue        = $axis->getValue( $key );

        return $valueTransformer ? $valueTransformer($axisValue) : $axisValue ;
    }

    /**
     * Tests to see if a series key is valid
     *
     * Checks the keys of the series, reports to the error reporting platform if it doesn't exist
     *
     * @param string $key
     *
     * @since 14/01/2020
     */
    protected function _validateSeriesKey( $key ) {
        if( ! array_key_exists($key, $this->_series) ) {
            $bc = BreadcrumbFactory::debug('TimeSeries', 'Series Configuration', [
                'key' => $key
                // TODO: add axis details?
            ]);

            Sentry::addBreadcrumb( $bc );
            Throw new \UnexpectedValueException( "No Axis series for Key: {$key}" );
        }
    }

    /**
     * Get data for a table where the columns represent axes
     *
     * @return \stdClass
     * @see TimeSeries::toConsoleTable()
     * @since 14/01/2020
     */
    protected function _toTableColumnAxes() {

        $tableData = new \stdClass();
        $tableData->headers = array('Key', 'Label');
        $tableData->rows    = array();

        ksort( $this->_series );

        if( count($this->_axes) > 6 ) {
            $tableData->headers = array('Error');
            $tableData->rows    = [['Too many columns to display']];
            return $tableData;
        }

        foreach( $this->_axes as $axis ) {
            $tableData->headers[] = $axis->getName();
        }

        foreach( $this->_series as $series ) {
            $tableRow = [ $series['key'], $series['label'] ];

            /** @var Axis $axis */
            foreach( $this->_axes as $axis ) {
                $tableRow[] = $this->_getTransformedAxisValue( $axis,  $series['key'] );
            }

            $tableData->rows[] = $tableRow;
        }

        return $tableData;
    }

    /**
     * Get data for a table where the columns represent time
     *
     * @return \stdClass
     * @see TimeSeries::toConsoleTable()
     * @since 14/01/2020
     */
    protected function _toTableColumnTime() {

        $tableData = new \stdClass();
        $tableData->headers = array('Axis');
        $tableData->rows    = array();

        if( count( $this->_series) > 6 ) {
            $tableData->headers = array('Error');
            $tableData->rows    = [['Too many columns to display']];
            return $tableData;
        }

        foreach( $this->_series as $series ) {
            $tableData->headers[] = $series['label'];
        }

        /** @var Axis $axis */
        foreach( $this->_axes as $axis ) {
            $tableRow = [ $axis->getName() ];

            foreach( $this->_series as $series ) {
                $tableRow[] = $this->_getTransformedAxisValue( $axis, $series['key'] );
            }

            if( $axis->getMeta(DataType::TABLE_BREAK_ABOVE) )
                $tableData->rows[] = new TableSeparator();

            $tableData->rows[] = $tableRow;
        }

        return $tableData;
    }

}
