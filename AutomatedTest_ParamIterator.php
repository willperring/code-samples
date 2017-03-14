<?php

/**
 * Class AutomatedTest_ParamIterator
 *
 * This class is a multi-dimensional iterator to cover every possible permutation across multiple
 * independent sets of values. Parameters are added using the addParameter() method, giving each
 * specified parameter a set of possible values. These are incrementally advanced one single parameter
 * value at a time to ensure that every single possible combination of parameter values is covered.
 *
 * @author Will Perring <@willperring>
 * @since  2015-01-21
 */
class AutomatedTest_ParamIterator implements Iterator, Countable {

    /** @var array Storage of all possible values by parameter name */
    private $_parameters = array();
    /** @var bool  True when the class has reached the end of its iterations @see next() */
    private $_finished   = false;
    /** @var int   Internal count of current iteration number @see key() */
    private $_counter    = 0;

    /**
     * Add a parameter and value set to the Iterator
     *
     * When adding parameter sets to the iterator, we need an identifier to store the values under. This
     * will also be used to pass back data upon iteration, ready to be passed to the test. The key needs to be
     * a string, rather than numeric. Any single (i.e. non-array) values passed into the second parameter will
     * automatically be converted into a single-value array.
     *
     * @param string $key    The name of the parameter
     * @param array  $values Array of all possible values for iterating
     */
    public function addParameter( $key, $values ) {

        // We need a non-empty string key to both store the data under, and pass back in the resulting array
        if( !is_string($key) || empty($key) ) {
            Throw new UnexpectedValueException
                ("AutomatedTest_ParamIterator::addParameter expects param 1 to be valid string key");
        }

        // If we've started iterating, adding parameters will have to reset the array
        if( $this->_counter > 0 ) {
            trigger_error("Adding parameters to AutomatedTest_ParamIterator during iteration resets counter", E_USER_WARNING);
            $this->rewind();
        }

        // If we've only got a single value for some reason, array it up
        if( !is_array($values) )
            $values = array($values);

        // Warn the user if we're overwriting (dev only, will be squashed on production)
        if( isset($this->_parameters[$key]) )
            trigger_error("AutomatedTest_ParamIterator already contains parameter '{$key}', overwriting will occur", E_USER_WARNING);

        // Strip any specific indexing - we want numeric
        $this->_parameters[ $key ] = array_values($values);
    }

    /**
     * Return the total count of possible iterations
     *
     * Required by the Countable interface
     *
     * @return int
     */
    public function count() {

        if( !count($this->_parameters) )
            return 0;

        $count = 1;

        foreach( $this->_parameters as $valueSet )
            $count *= count($valueSet);

        return $count;
    }

    /**
     * Rewind all value sets to their initial values
     *
     * This rewinds the iterator, so as well as resetting all internal value arrays, this needs
     * to reset any instance values. Note the addition of the pass-by-reference symbol in the
     * foreach loop. This is essential to prevent infinite loops.
     *
     * Required by the Iterable interface
     *
     * @see $_finished
     * @see $_counter
     *
     * @returns AutomatedTest_ParamIterator Reference to self for method chaining
     */
    public function rewind() {

        // Reset array pointers
        foreach( $this->_parameters as &$valueSet )
            reset( $valueSet );

        // Reset instance values
        $this->_counter  = 0;
        $this->_finished = false;

        return $this;
    }

    /**
     * Get an array of the values currently under each pointer
     *
     * Note the addition of the pass-by-reference symbol in the foreach loop. This is essential
     * to prevent infinite loops
     *
     * Required by the Iterable interface
     *
     * @return array
     */
    public function current() {

        $currentParams = array();

        foreach( $this->_parameters as $key => &$valueSet )
            $currentParams[ $key ] = current($valueSet);

        return $currentParams;
    }

    /**
     * Return the current 'key' during iteration
     *
     * This function is demanded by the Iterable interface, although its purpose in this instance
     * is unclear. To satisfy the code dependency, it simply returns the current index in the
     * iteration cycle.
     *
     * Required by the Iterable interface
     *
     * @see rewind()
     *
     * @return int
     */
    public function key() {
        return $this->_counter;
    }

    /**
     * Advance the collective pointer to the next set of iterated variables
     *
     * Required by the Iterable interface
     *
     * @return AutomatedTest_ParamIterator Reference to self for method chaining
     */
    public function next() {

        foreach( $this->_parameters as &$valueSet ) {

            // strict typing test is important, although no null values should be passed in
            // next will return false if the array has no further possible iterations...
            if( next($valueSet) !== false ) {
                // so if we're here, it iterated successfully...
                $this->_counter++;
                return $this;
            }

            // and if we're here, it didn't. rewind this one and attempt to iterate the next array
            reset( $valueSet );
        }

        // if we hit this point, there are no more arrays to iterate
        $this->_finished = true;
        return $this;
    }

    /**
     * Test whether the current pointer location is valid
     *
     * Because we don't maintain pointer information for the value sets within this class, we check this
     * against the finished flag that we set inside the next() method. Also, because we cannot iterate with
     * no saved parameter sets this also returns false if no parameters have been added.
     *
     * Required by the Iterable interface
     *
     * @see next()
     * @see $_finished
     *
     * @return bool
     */
    public function valid() {
        return ( !$this->_finished && count($this->_parameters) );
    }

}
