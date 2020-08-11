<?php
/**
 * Axis.php
 *
 * @author  Will Perring <will@supernatural.ninja>
 * @since   2019-07-12
 */

namespace App\KPI\DataType\TimeSeries;


use app\KPI\DataType\Interfaces\AjaxData;
use App\KPI\DataType\TimeSeries;
use App\KPI\ValueType;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class Axis
 *
 * An Axis represents a changing value over time on a TimeSeries.
 * Internally, the axis keeps track of the types of values being stored within it. It's
 * not set explicitly, but rather than through the methods used to affect the values inside.
 * For example, calling updateObject() requires that the axis is in object mode, which is
 * set by calling setObject(). The default value is null, which passes all assertion tests
 * unless specifically constrained otherwise.
 *
 * Generally, the methods here are designed to be called by the TimeSeries rather than
 * called directly. Consult the TimeSeries documentation for further information.
 *
 * @see TimeSeries
 *
 * @package App\KPI\DataType\TimeSeries
 * @author  Will Perring <will@supernatural.ninja>
 * @since   14/01/2020
 * @version 1.0
 */
class Axis implements AjaxData {

    // Internal flag, designed to keep track of what kind of data we're storing
    const AXIS_VALUE  = 1;
    const AXIS_ARRAY  = 2;
    const AXIS_OBJECT = 3;

    /** @var string Axis Name */
    protected $_name;
    /** @var int Axis type (see class constants) */
    protected $_type;
    /** @var mixed Default value for axis series entries */
    protected $_defaultValue;

    protected $_data = array();
    protected $_meta = array();

    /** @var Closure|null */
    protected $_axisValueTransformer;

    /**
     * Axis constructor.
     *
     * @param string $name         Display name for axis
     * @param mixed  $defaultValue Default value
     * @param array  $meta         (optional) axis metadata
     */
    public function __construct( $name, $defaultValue, $meta=array() ) {
        $this->_name         = $name;
        $this->_defaultValue = is_object($defaultValue) ? clone $defaultValue : $defaultValue ;
        $this->_meta         = $meta;
    }

    /**
     * Get the display name of the axis
     *
     * @return string
     * @since 14/01/2020
     */
    public function getName() {
        return $this->_name;
    }

    /**
     * Set a value transformer
     *
     * See the documentation on the TimerSeries class for more details.
     *
     * @param Closure $transformer
     *
     * @return $this
     * @see TimeSeries::setValueTransformer()
     * @since 14/01/2020
     */
    public function setValueTransformer( Closure $transformer ) {
        $this->_axisValueTransformer = $transformer;
        return $this;
    }

    /**
     * Clear the value transformer
     *
     * @return $this
     * @see Axis::setValueTransformer()
     * @see TimeSeries::setValueTransformer()
     * @since 14/01/2020
     */
    public function removeValueTransformer() {
        $this->_axisValueTransformer = null;
        return $this;
    }

    /**
     * Sets a value within the axis
     *
     * Requires and sets the internal datatype to 'value'.
     *
     * @param string $key   Axis series key
     * @param mixed  $value Value to set
     *
     * @since 14/01/2020
     */
    public function setValue( $key, $value ) {

        if( ! is_scalar($value) )
            Throw new InvalidArgumentException( 'setValue expects scalar value' );

        $this->_assertType( static::AXIS_VALUE );
        $this->_data[ $key ] = $value;
        $this->_type         = static::AXIS_VALUE;
    }

    /**
     * Increment a value for a key
     *
     * Requires and sets the internal datatype to 'value'.
     *
     * @param string $key    Axis series key
     * @param int    $amount Amount to increment by
     *
     * @since 14/01/2020
     */
    public function incrementValue( $key, $amount=1 ) {

        $this->_assertType( static::AXIS_VALUE );

        if( ! array_key_exists($key, $this->_data) )
            $this->_data[ $key ] = 0;

        $this->_data[ $key ] += $amount;
        $this->_type          = static::AXIS_VALUE;
    }

    /**
     * Add a value to an array for a key
     *
     * Requires and sets the internal datatype to 'array'.
     *
     * @param string $key   Axis series key
     * @param mixed  $value The value to add
     *
     * @since 14/01/2020
     */
    public function addArrayValue( $key, $value ) {

        $this->_assertType( static::AXIS_ARRAY );

        if( ! array_key_exists($key, $this->_data) )
            $this->_data[ $key ] = array();

        $this->_data[ $key ][] = $value;
        $this->_type           = static::AXIS_ARRAY;
    }

    /**
     * Set an object for a key
     *
     * Requires and sets the internal datatype to 'object'.
     *
     * @param string $key    Axis series key
     * @param mixed  $object Value to set
     *
     * @since 14/01/2020
     */
    public function setObject( $key, $object ) {

        if( ! is_object($object) && ! is_array($object) )
            Throw new InvalidArgumentException( 'setObject expects object' );

        $this->_assertType( static::AXIS_OBJECT );
        $this->_data[ $key ] = $object;
        $this->_type         = static::AXIS_OBJECT;
    }

    /**
     * Update an object for a key
     *
     * Requires and sets the internal datatype to 'object'.
     *
     * @param string  $key            Axis series key
     * @param Closure $updateFunction Callback to apply
     *
     * @since 14/01/2020
     * @see TimeSeries::updateObject()
     */
    public function updateObject( $key, Closure $updateFunction ) {
        $currentValue = $this->getValue( $key, false );
        $this->setObject( $key, $updateFunction($currentValue) );
    }

    /**
     * Get the default value for the axis, with optional transformation
     *
     * @param bool $transform Whether to apply transformation
     *
     * @return mixed
     * @since 14/01/2020
     */
    public function getDefaultValue( $transform=true ) {
        $valueTransformer = $this->_axisValueTransformer;
        return ( $transform && $valueTransformer )
            ? $valueTransformer($this->_defaultValue)
            : $this->_defaultValue ;
    }

    /**
     * Get the value for a key, with optional transformation
     *
     * @param string $key       Axis series key
     * @param bool   $transform Whether to apply transformation
     *
     * @return mixed
     * @since 14/01/2020
     */
    public function getValue( $key, $transform=true ) {

        /** @noinspection NestedTernaryOperatorInspection */
        $value = array_key_exists($key, $this->_data)
            ? $this->_data[ $key ]
            : ( is_object($this->_defaultValue) ? clone $this->_defaultValue : $this->_defaultValue );

        $valueTransformer = $this->_axisValueTransformer;

        return ( $transform && $valueTransformer ) ? $valueTransformer($value) : $value ;
    }

    /**
     * Return all axis data
     *
     * @return array
     * @since 14/01/2020
     */
    public function getData() {
        return $this->_data;
    }

    /**
     * Return axis meta
     *
     * Returns a specific value if a key is provided, or the whole array if not
     *
     * @param null|string $key
     *
     * @return array|mixed
     * @since 14/01/2020
     */
    public function getMeta( $key=null ) {
        return ( $key === null ) ? $this->_meta : @$this->_meta[ $key ];
    }

    /**
     * Add Axis Metadata
     *
     * @param string $key   Metadata key
     * @param mixed  $value Metadata value
     *
     * @return $this
     * @since 14/01/2020
     */
    public function addMeta( $key, $value ) {
        $this->_meta[ $key ] = $value;
        return $this;
    }

    /**
     * Set the format for axis data
     *
     * @see DataType
     */
    public function setFormat( $format ) {
        $this->addMeta( 'format', $format );
        return $this;
    }

    /**
     * Test if the axis contains data
     *
     * @return bool
     * @since 14/01/2020
     */
    public function isEmpty() {
        return ! count( $this->_data );
    }

    /**
     * Apply a callback to each item in the axis data
     *
     * @param Closure $callback Callback to apply
     *
     * @return $this
     * @since 14/01/2020
     */
    public function map( Closure $callback ) {

        $newType = static::AXIS_ARRAY;
        $newData = array();

        foreach( $this->_data as $key => $value ) {
            $mapped  = $callback( $value, $key );
            $newType = !is_array($mapped) ? self::AXIS_VALUE : $newType ;

            $newData[ $key ] = $mapped;
        }

        $this->_data = $newData;
        $this->_type = $newType;

        return $this;
    }

    /**
     * Return ajax data for the axis
     *
     * The second parameter here provides the opportunity for further transformation,
     * which is usually provided from the TimeSeries (where set)
     *
     * @param Request      $request           Laravel HTTP Request
     * @param Closure|null $seriesTransformer Optional callback to apply to each value
     *
     * @return array
     * @see TimeSeries::toAjaxData()
     * @since 14/01/2020
     */
    public function toAjaxData( $request, Closure $seriesTransformer=null ): array {

        ksort( $this->_data );

        $seriesTransformer = $seriesTransformer ?: static function( $object ) {
            return $object;
        };

        $data = array();
        foreach( $this->_data as $key => $value ) {
            $type  = 'default';
            $value = $seriesTransformer( $this->getValue( $key, true ) );
            if( is_a($value, ValueType::class) ) {
                $type  = $value->getType();
                $value = $value->toValue();
            }

            $data[] = array(
                'key'   => (string) $key,
                'value' => $value,
                'type'  => $type
            );
        }

        $defaultValue = $seriesTransformer( $this->getDefaultValue(true) );
        $defaultValue = is_a($defaultValue, ValueType::class)
            ? [ 'value' => $defaultValue->toValue(), 'type' => $defaultValue->getType() ]
            : [ 'value' => $defaultValue,            'type' => 'default' ];

        return array(
            'name'    => $this->_name,
            'default' => $defaultValue,
            'data'    => $data,
            'meta'    => $this->_meta,
        );
    }

    /**
     * Ensure the axis is of a certain type, or null
     *
     * @param int  $type
     * @param bool $allowNull
     *
     * @throws RuntimeException
     * @return bool
     * @since 2019-09-06
     */
    protected function _assertType( int $type, bool $allowNull=true ) {
        if( $allowNull && $this->_type === null )
            return true;
        if( $this->_type !== $type )
            Throw new RuntimeException("Axis '{$this->_name}' is not type '{$type}', actual '{$this->_type}''");
        return true;
    }

}
