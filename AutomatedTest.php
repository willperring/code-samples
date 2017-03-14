<?php

/**
 * Base Class for all Automated Test Operations
 *
 * Test CLASSES refer to the type of a test. Currently, in the spec THREE types of test are described.
 * BASIC: The most elementary type of class, has a few configurable parameters, but is otherwise a 'fire
 *      and forget' type of test providing only the most basic type of reporting (pass/fail).
 * EXTENDED: An extended test provides more detail than a basic test, and this information is collected
 *      and displayed to the user in a useful manner. Extended tests may also be scheduled (NOT YET IMPLEMENTED).
 * SELENIUM: Selenium tests are run from a file created using the selenium browser extension. The user can
 *      upload a file, the actions are replayed, and the results are displayed in the manner of the extended tests.
 *
 * INHERITANCE: There are two stages of inheritance, test class types, and actual instantiable tests. So that
 * the loading from the filesystem can occur properly, test class types should be abstract and implement
 * AutomatedTest_TestClassInterface, and instantiable tests should extend a test class type and NOT be
 * abstract.
 *
 * @author Will Perring <@willperring>
 * @since  2015-01-19
 */
abstract class AutomatedTest {

    /** @var array Registry of available tests */
    private static $_registry = array();

    /** @var null|RemoteWebDriver Storage of WebDriver */
    protected static $_driver = null;

    /** @var string Display name of the test instances. Designed to be overridden in test instance classes */
    protected static $_displayName = "Undefined Test Name";

    /** @var array Configurable Test Options */
    protected $_configurableParams = array();

    /** @var null @var AutomatedTest_Reporter Reporter instance */
    protected $_reporter = null;

    /**
     * Load all dependencies for an individual test instance
     *
     * @return void
     */
    abstract protected function loadTestDependencies();

    /**
     * Define an abstract method to run the test
     *
     * @param array $testParams Array of test data
     *
     * @return mixed
     */
    abstract public function run( array $testParams );

    /**
     * Get an array of configurable parameters for this test class
     *
     * @return array
     */
    final public function getConfigurableParams() {
        return $this->_configurableParams;
    }

    /**
     * Translate parameters into human-readable variation for output report
     *
     * This method only returns the array as is, but is designed to be overriden
     * in any test instance class where applicable.
     *
     * @param array $testParams
     * @return array
     */
    public function translateDisplayParams( array $testParams ) {
        return $testParams;
    }

    /**
     * Process parameter sets and validate the presence of required data
     *
     * @param array $testParams Array of test parameter data, e.g. POST data from test control panel
     *
     * @return StdClass Object with two properties, 'testParams' (filtered data) and 'missingParams' (missing req'd fields)
     */
    final public function validateSuppliedParams( $testParams ) {

        $configParams   = $this->getConfigurableParams();
        $requiredParams = $this->getParamNames( $configParams, true );
        $validParams    = $this->getParamNames( $configParams );

        // Filter out params not part of test
        $testParams    = array_intersect_key( $testParams, array_flip($validParams) );
        // Calculate missing parameter keys
        $missingParams = array_keys(array_diff_key(array_flip($requiredParams), $testParams));

        $result = new StdClass();
        $result->testParams    = $testParams;
        $result->missingParams = $missingParams;

        return $result;
    }

    /**
     * Get the Test Reporter instance for this class
     *
     * @return AutomatedTest_Reporter Automated Test Reporter Class
     */
    public function getReporter() {

        if( is_null($this->_reporter) ) {
            $test_info       = $this->getClassInformation();
            $name_prefix     = "{$test_info->type}-{$test_info->test}-".date('Ymd-His');
            $this->_reporter = new AutomatedTest_Reporter( $name_prefix );
        }

        return $this->_reporter;
    }

    /**
     * Return an array of parameter key names
     *
     * This function can return either an array of all parameters within a test, or just those that are
     * required
     *
     * @param array $configParams Configurable Parameter data @see getConfigurableParams()
     * @param bool  $required     Optional. True to return only required parameters.
     *
     * @return array Array of parameter names
     */
    final protected function getParamNames( $configParams, $required=false ) {

        $params = array();

        foreach( $configParams as $name => $paramConfig ) {
            if( $paramConfig['type'] == 'group' )
                $params = array_merge( $params, $this->getParamNames($paramConfig['config']['children'], $required) );
            elseif( $required && @$paramConfig['required'] )
                array_push( $params, $name );
            elseif( !$required )
                array_push( $params, $name );
        }

        return $params;
    }

    /**
     * Load all PHP Classes from a specified folder
     *
     * When supplying a path, any non-absolute paths (not prefixed with a slash) are loaded relative to
     * the location of this base class. Underscores are converted to slashes in the case of child classes.
     * The path should not contain a trailing slash.
     *
     * The register param, if true, will recurse into directories for classes
     *
     * @see AutomatedTest::prioritizeUnderscores()
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     *
     * @param string $path     The path to load PHP class files from
     * @param bool   $register Scan for test instances
     */
    final protected static function loadDirectory( $path, $register=false ) {

        if( empty($path) || !is_string($path) )
            Throw new InvalidArgumentException("AutomatedTest::loadDirectory() requires path to load");

        // Underscores -> Directory slashes
        $path = str_replace('_', DIRECTORY_SEPARATOR, $path);

        // Check if absolute or relative, path convert to absolute if necessary
        if( $path[0] != DIRECTORY_SEPARATOR )
            $path = __DIR__ . DIRECTORY_SEPARATOR . $path ;

        if( !is_dir($path) ) {
            Throw new RuntimeException("AutomatedTest::loadDirectory(): {$path} is not a valid directory");
        }

        // Scan for PHP files...
        $classes = glob( $path . DIRECTORY_SEPARATOR . '*.php' );
        usort( $classes, array( __CLASS__ , 'prioritiseUnderscores') );

        /** @var array $pathSubstitutions The replacements needed to convert filename to classname */
        $pathSubstitutions = array(
            __DIR__ . DIRECTORY_SEPARATOR => '',
            '_'                           => '',
            DIRECTORY_SEPARATOR           => '_',
            '.php'                        => '',
        );

        // ...and then import.
        foreach( $classes as $classpath ) {

            require_once( $classpath );

            // if not registering, we're only loading in files for later
            if( !$register )
                continue;

            // If this is a registration call, we need to recurse into the folders for each test type class,
            // As each test is represented by a php class file, and we need all their information

            /** @var string $className The class that should be contained within file $classpath */
            $className = str_replace(
                array_keys($pathSubstitutions),
                array_values($pathSubstitutions),
                $classpath
            );

            if( !class_exists($className, false) && !interface_exists($className, false) )
                Throw new UnexpectedValueException("Class {$className} not found in file {$classpath}");

            /** @var ReflectionClass $classTest Used to determine if class is abstract ( TestClass / TestInstance ) */
            $classTest = new ReflectionClass($className);

            // If the class exists, implements the TestClassInterface AND is abstract,
            // it's a Test Type Class and we need to scan its relevant folder
            if( class_exists($className) &&
                in_array('AutomatedTest_TestClassInterface', class_implements($className)) &&
                $classTest->isAbstract()
            ) {
                // scan the child folder
                // don't catch exceptions here, every test class should have a folder
                static::loadDirectory($className, $register);

            // If the class exists, IS NOT abstract, and is descended from an automated test
            // then add it to the registry
            } elseif(
                class_exists($className) &&
                ! $classTest->isAbstract() &&
                in_array('AutomatedTest', class_parents($className) )
            ) {
                // add to the registry
                array_push( static::$_registry, call_user_func(array($className, 'getClassInformation')) );

            } else {
                // TODO: we don't know what this file contains?
            }
        }
    }

    /**
     * Load all required dependencies for operation of this class
     *
     * The current autoloader makes no allowances for subfolder structure, so we have to manually call in any files
     * that we want to use. This function scans and loads all relevant directories.
     */
    final private static function loadDependencies() {
        static::loadDirectory( __CLASS__);
        require_once('lib/vendor/php-webdriver/lib/__init__.php');
    }

    /**
     * Load and store a WebDriver instance
     */
    final public static function getWebDriver() {
        $host   = "http://localhost:4444/wd/hub"; // TODO: abstract to config

//        if( defined('ENVIRONMENT') && ENVIRONMENT == 'dev' )
            $browser = DesiredCapabilities::chrome();
//        else
//            $browser = DesiredCapabilities::phantomjs();

        $driver = RemoteWebDriver::create( $host, $browser );
        static::$_driver = $driver;
    }

    final public static function clearDriverCookies() {
        if( static::$_driver )
            static::$_driver->manage()->deleteAllCookies();
    }

    final public static function saveScreenshot( $path, $file ) {
        if( !is_dir($path) || !is_writable($path) ) {
            $debugException = new OutOfBoundsException("{$path} is not writable");
            Throw new AutomatedTest_Exception("Couldn't save a screenshot for debugging", 0, $debugException);
        }

        static::$_driver->takeScreenshot( $path . DIRECTORY_SEPARATOR . $file );
    }

    /**
     * Close the webdriver
     */
    final public static function closeWebDriver() {
        if( static::$_driver )
            static::$_driver->quit();
    }

    /**
     * Get information on all available tests
     *
     * @param string $filter Filter to a certain type of test class
     *
     * @returns array Array of test information objects
     */
    final public static function getAllTestInformation( $filter=null ) {

        if( !count(static::$_registry) )
            static::loadDirectory(__CLASS__, true);

        /** @var array $testsForSorting Tests to be displayed after sorting */
        $testsForSorting = array();

        if( $filter ) {
            foreach( static::$_registry as $test ) {
                if( strtolower($test->type) == strtolower($filter) )
                    array_push( $testsForSorting, $test );
            }
        } else {
            $testsForSorting = static::$_registry;
        }

        // We want to sort by the name key of each object, custom callback needed
        // Abstract classes should never be present in the test registry
        usort( $testsForSorting, function( $a, $b ) {
            return $a->test - $b->test;
        });

        return $testsForSorting;
    }

    /**
     * Load a specified Automated Test
     *
     * When loading a test, the first parameter is the CLASS of test to load, i.e. Basic, Extended, Selenium.
     * The second parameter specifies the name of the test to load. In order to ensure correct dependency loading,
     * we need to ensure that all required files for the test type class are loaded BEFORE attempting to
     * instantiate the actual test object
     *
     * @see AutomatedTest
     *
     * @throws RuntimeException
     *
     * @param string $class The CLASS of test to load from (see AutomatedTest docblock)
     * @param string $test  The specific test instance to load
     * @return AutomatedTest
     */
    final public static function get( $class, $test ) {

        static::loadDependencies();

        // Define the class name for the test CLASS TYPE
        $testClass = __CLASS__ . '_' . $class;

        // Validate the class
        if( !class_exists($testClass) )
            Throw new RuntimeException("No automated test class '{$testClass}'");
        if( !in_array( __CLASS__, class_parents($testClass)) )
            Throw new RuntimeException("{$testClass} does not inherit from " . __CLASS__);
        if( !in_array('AutomatedTest_TestClassInterface', class_implements($testClass)) )
            Throw new RuntimeException("{$testClass} does not implement AutomatedTest_TestClassInterface");

        // Load in the class dependencies
        call_user_func( array($testClass, 'loadClassDependencies') );

        // Define the instance class name
        $testInstance = $testClass . '_' . $test;

        // Validate the instance
        if( !class_exists($testInstance) )
            Throw new RuntimeException("No automated test {$testInstance}");
        if( !in_array('AutomatedTest', class_parents($testInstance)) )
            Throw new RuntimeException("{$testInstance} does not inherit from class AutomatedTest");

        // Instantiate and return
        return new $testInstance();
    }

    /**
     * Return information about the calling class
     *
     * This test will always return at least the Test Type Class of a given static class,
     * or throw an exception if it is unable to determine the type. If the static class
     * is a test instance, it will return the name of the class
     *
     * @throws UnderflowException
     *
     * @return StdClass Object containing class information
     */
    final public static function getClassInformation() {

        $classSubName = str_replace( __CLASS__ . '_', '', get_called_class() );
        $classParts   = explode('_', $classSubName);

        $result          = new StdClass();
        $classname       = get_called_class();
        $result->class   = $classname;
        $result->display = $classname::$_displayName;

        if( isset($classParts[0]) )
            $result->type = $classParts[0];
        else
            Throw new UnderflowException("Unable to determine class type for " . get_called_class() );

        if( isset($classParts[1]) )
            $result->test = $classParts[1];

        return $result;
    }

    /**
     * Get the base URL for the test control panel
     *
     * @return string URL for test control panel
     */
    public function getTestBaseUrl() {

        $info = $this->getClassInformation();
        $url_type = strtolower( $info->type );
        $url_name = strtolower( $info->test );

        return "/admin/automated-tests/{$url_type}/{$url_name}";
    }

    /**
     * Callback to prioritise files prefixed with underscores in directory loads
     *
     * Because the current autoloader makes no allowances for directory structure, we need to manually
     * scan for and load in any required scripts. This function prioritises files prefixed with underscores
     * when sorting the files for loading, allowing us to include things like interfaces before they are
     * needed by any other files within the same folder
     *
     * @see AutomatedTest::loadDirectory()
     *
     * @param string $a The first value to compare
     * @param string $b The second value to compare
     *
     * @return int
     */
    final protected static function prioritiseUnderscores( $a, $b ) {

        // Not strings - no movement
        if( !is_string($a) || !is_string($b) )
            return 0;

        // We need to extract just the filename from the path, so we can explode on
        // the directory separator and take the last value. However, the array passed
        // into end() is by reference, so we need to squash the E_STRICT errors
        $fna = @end( explode( DIRECTORY_SEPARATOR, $a) );
        $fnb = @end( explode( DIRECTORY_SEPARATOR, $b) );

        // Check for underscore precedence (logical XOR only)
        if( $fna[0] == '_' && $fnb[0] != '_' )
            return -1;
        if( $fna[0] != '_' && $fnb[0] == '_' )
            return 1;

        // No underscores, or both underscores - standard value subtraction
        return strcasecmp( $fna, $fnb );
    }

    /**
     * Return information about the test execution and close the connection to the browser
     *
     * This function effectively severs the connection to the browser, allowing data about
     * the test execution to be returned before the test finishes - as the tests can take an
     * exceptional amount of time it's imperative that we don't keep the user waiting. In practice,
     * the name of the test execution is returned which allows the front end to call in updates on
     * the status of the test via ajax.
     *
     * @param string $name  The name (identifier) for this SPECIFIC execution run
     * @param array  $data  (Optional) Any extra data to be passed (associative array)
     * @param bool   $state (Optional) True [default] for success, false to denote a failure
     */
    public static function returnTestStatus( $name, $data=array(), $state=true ) {

        // GZIP compression will mess with our attempt to close the connection
        @ini_set('zlib.output_compression', 'Off');
        @ini_set('output_buffering', 'Off');
        @ini_set('output_handler', '');
        @apache_setenv('no-gzip', 1);

        // Merge our extra data in with the compulsory fields (extra first, we don't
        // want anyone overwriting the system fields)
        $return_data = array_merge( $data, array(
            'status' => ($state) ? 'success' : 'fail',
            'report' => $name,
        ));

        // Pad out the JSON string - anything less than 4k might not be sent
        $return_json = str_repeat(' ', 4096) . json_encode( $return_data );

        // Tell the server not to wait...
        header("Connection: close", true);
        header("Content-type: application/json", true);
        header("Content-Encoding: none\r\n");
        // Tell the server the exact data length to expect
        header("Content-length: " . strlen($return_json), true);

        echo $return_json;
        // flush output buffers
        ob_flush();
        flush();

        // force a termination of the session - required
        session_write_close();
    }



}
