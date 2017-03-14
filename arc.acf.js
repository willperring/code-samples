(function( $ ){

    /**
     * Agent Complaint Form Javascript file
     * @author Will Perring <@willperring>
     */

    "use strict";

    /** Console polyfill - good old IE6 */
    var console = window.console || { log: function() {} };

    /**
     * Pagination Controller Class
     * @param element The .acf-pagination UL to attach to
     * @constructor
     */
    var PaginationController = function( element ) {

        /** References / Collector functions for internal elements */
        var el = {
            arrows : {
                prev : element.find('.arrow.prev'),
                next : element.find('.arrow.next')
            },
            numbers : {
                first : element.find('.first'),
                last  : element.find('.last'),
                icons : function() {
                    return element.find('.numbered')
                }
            }
        };

        /**
         * Respond to pagination information from the ComplaintController AJAX calls
         *
         * @param pageData Pagination Data
         */
        this.paginate = function( pageData ) {

            /** Create URL from complaint ID
             *
             * @param index Complaint ID
             * @returns {string} URL
             */
            var url = function( index ) {
                return '/complaint/all/' + index;
            };

            // just to make the maths easier to read
            var current   = pageData.current,
                total     = pageData.total,
                iconCount = pageData.icons.length,
                iconFirst = pageData.icons[0],
                iconLast  = pageData.icons[ iconCount-1 ];

            // ARROWS
            // Adjust visibility, and alter URLs of both
            el.arrows.prev.toggleClass( 'hidden', current == 1 )
                .find('.icon')
                .attr( 'href', url(current - 1));
            el.arrows.next.toggleClass( 'hidden', current == total )
                .find('.icon')
                .attr( 'href', url(current + 1));

            // NUMBERS
            // Adjust first and last urls and text, and visibility
            el.numbers.first.toggleClass( 'hidden', iconFirst == 1 );
            el.numbers.last.toggleClass(  'hidden', iconLast  == total )
                .find('.icon')
                .attr( 'href', url(total) )
                .text( total );

            // Deal with the icon-y numbers
            el.numbers.icons().detach();
            for( var i=0; i<iconCount; i++ ) {
                var index      = pageData.icons[i],
                    classname  = "icon " + ( index == current ? 'selected' : '' ),
                    button     = '<a class="' + classname + '" href="' + url(index) + '">' + index + '</a>',
                    link       = '<li class="numbered">' + button + '</li>' + "\n";
                el.numbers.last.before( link );
            }
        };

        /**
         * Return a reference to the parent element
         * @returns {*} Parent Element Reference
         */
        this.getParent = function() {
            return element;
        }

    };

    /**
     * Controller for toggle switch elements
     * @param {Element} element Parent element for toggle group
     * @constructor
     */
    var ToggleController = function( element ) {

        /** Form element in which to store value (picked up by the submit reques */
        var formElement  = element.find('input[type="hidden"]'),
        /** Array of the toggle values */
            toggles      = element.children('a'),
            complaint_id = element.attr('data-complaint-id');

        if( !complaint_id )
            return;

        // locks
        var _ajax = false;

        /**
         * Handle clicks to the toggle elements
         *
         * Pretty basic, only has to collect the value, store it in the form element,
         * and update the visual elements.
         *
         * @param e
         * @private
         */
        var _clickHandler = function( e ) {
            e.preventDefault();

            var el    = $(this),
                value = $(this).text();

            _makeRequest( value, el );
        };

        /**
         * Initiate the AJAX request to update the complaint Status
         *
         * The element receiving the click is passed into this as the second parameter, so that in
         * the case of a successful update but no returned status the active toggle option can be
         * set
         *
         * @see _setActiveHighlight()
         *
         * @param {string}  state New status for Complaint
         * @param {Element} el    Element receiving the click
         * @private
         */
        var _makeRequest = function( state, el ) {

            if( _ajax )
                return;

            var url = '/complaint/' + complaint_id + '/status';

            $.getJSON( url, {to: state})
                .success( function(response) {
                    if( response.complaint_status )
                        _setActiveHighlight( response.complaint_status );
                    else if( el )
                        _setActiveHighlight( el );
                })
                .error( function() {
                    alert('Something went wrong! Unable to update status');
                })
                .complete( function() {
                    _ajax = false;
                });
        };

        /**
         * Set the active option within the Toggle element
         *
         * This function will, by default, attempt to highlight the correct option by the
         * returned complaint status. However, as a fallback if a jQuery element is passed
         * in instead of a string value, that element will recieve the active state
         *
         * @param {string|Element} el Either a valid complaint status, or an element to activate
         * @private
         */
        var _setActiveHighlight = function( el ) {

            toggles.removeClass( 'active' );

            if( el instanceof jQuery ) {
                el.addClass('active');

            } else if( typeof el == 'string' ) {
                var selector = 'a[data-value="' + el + '"]';
                $( selector).addClass('active');

            } else {
                alert('Unable to work out which toggle option to highlight');
            }

        };

        // Delegate the click handler out to the parent element
        element.delegate( 'a','click', _clickHandler );
    };

    /**
     * Pinger to keep complaint record locks alive
     *
     * @param id
     * @param timeout
     * @constructor
     */
    var KeepAlivePinger = function( id, timeout ) {

        /** Complaint PING url */
        var beacon_url = '/complaint/' + id + '/ping',
        /** Time between pings, in milliseconds. Defaults to 60 */
            timeout    = ( timeout * 1000 ) || 60000; // specified seconds, default 60

        /** Used to prevent re-hitting a 404 */
        var halt = false;

        /**
         * Send the Ajax request and return the promise object
         * @returns {jQuery.promise}
         * @private
         */
        var _sendPing = function() {
            return $.ajax({
                url    : beacon_url,
                method : 'get',
                cache  : false,
                statusCode: {
                    404 : function() {
                        halt = true;
                    }
                }
            });
        };

        /**
         * Send a ping, and queue the next one once it completes
         */
        var ping = function() {
            _sendPing().complete( function() {
                if( !halt )
                    setTimeout( ping, timeout );
            });
        };

        // A quick check to make sure we've not been given some string,
        // or other junk id value
        if( id == parseInt(id) )
            ping();
    };

    /**
     * Collect form values for AJAX calls
     *
     * This function crawls the page for data-value elements (inputs, selects, etc...) and builds an
     * object of their values.
     *
     * @returns {{}} Object of form data
     * @private
     */
    var _obtainFormFields = function() {

        var formData = {};

        /** All the types of elements that will have data values for submission */
        var dataFields = $('input[type="text"], input[type="radio"]:checked, select, input[type="hidden"], textarea');
        dataFields.each( function() {
            var el    = $(this),
                name  = el.prop('name'),
                value = el.val();

            if( !name ) return;
            formData[ name ] = value;
        });
        return formData;
    };

    /**
     * Display a list of errors from an AJAX response
     *
     * @param response The ajax response, or false to clear displayed errors
     * @private
     */
    var _displayErrors = function( response ) {

        var container = $('#error-container'),
            list      = container.find('ul');

        list.empty();
        container.hide();

        if( response === false )
            return;

        if( response === null) {
            response = {};
        }

        if( typeof response.errors == 'undefined' ) {
            response.errors = ["Something unexpected went wrong, please try again shortly"];
        }

        for( var i=0; i<response.errors.length; i++ ) {
            list.append('<li><span>' + response.errors[i] + '</span></li>');
        }

        container.show();
        $(window).scrollTop( 0 );
    };

    /**
     * Submit a Complaint Click Handler
     * @param e Event information
     */
    var submitComplaint = function( e ) {

        e.preventDefault();
        var data = _obtainFormFields();

        var el = $(this);
        el.attr('disabled','disabled');

        $.ajax({
            url      : '/complaint/submit',
            data     : data,
            dataType : 'json',
            type     : 'post',
            success  : function( response ) {
                if( !response.status || response.status != 'success' ) {
                    _displayErrors( response );
                    return;
                }
                _displayErrors( false );
                window.location = '/complaint/all?submitted';
            },
            error : function( xhr, err, response ) {
                _displayErrors( null );
            },
            complete : function() {
                el.removeAttr('disabled');
            }
        });
    };

    /**
     * Submit Notes (Edit Complaint) Click Handler
     * @param {Event} e Event Information
     */
    var submitNotes = function( e ) {

        e.preventDefault();
        var data = _obtainFormFields(),
            url  = window.location.href.replace('/edit', '/update');

        var el = $(this);
        el.attr('disabled', 'disabled');

        $.ajax({
            url      : url,
            data     : data,
            dataType : 'json',
            type     : 'post',
            success  : function( response ) {
                if( !response.status || response.status != 'success' ) {
                    _displayErrors( response );
                    return;
                }
                _displayErrors( false );
                window.location = window.location.href.replace('/edit', '');
            },
            error : function( response ) {
                _displayErrors( null );
            },
            complete : function( response ) {
                el.removeAttr('disabled');
            }
        });
    };

    /**
     * AJAX Data Loader Singleton Object
     *
     * This is responsible for maintaining information about the filter state, requesting new
     * data from the server, and updating the complaint table and pagination information once a
     * request has been returned.
     *
     * @author Will Perring <@willperring>
     */
    var ajaxDataLoader = (function(){

        /** Dictionary of data to be sent */
        var _data  = {},
        /** Dictionary of data in last request ( used for preventing duplicate requests ) */
            _last  = {},
        /** Storing request timer information */
            _timer = null,
        /** Storing whether a request is currently active */
            _ajax  = false;

        /** Reference to a pagination controller */
        var _pager  = false,
            _loader = $(null),
        /** Reference to the table object that we're populating */
            _table  = $(null);

        /** Callback to be executed after data loaded */
        var _onDataLoaded = function() {};

        /**
         * Set the table to populate
         * @param {Element} table Reference to table element
         * @private
         */
        var _setTable = function( table ) {
            _table  = table;
            _loader = table.find('.ajax-loader')
        };

        /**
         * Show or hide the preloader image
         * @param {Boolean} state True to show, False to hide
         * @private
         */
        var _showLoading = function( state ) {
            _loader.toggle( state );
        };

        /**
         * Set the pagination controller
         * @param {PaginationController} pager Pagination Controller for page
         * @private
         */
        var _setPagination = function( pager ) {
            _pager = pager;
            _pager.getParent().delegate('a.icon', 'click', function(e) {
                e.preventDefault();
                var el  = $(this),
                    url = el.attr('href')
                        .replace('/all','/ajax');
                _request( url, true );
            });
        };

        /**
         * Notify event callback
         *
         * This callback is applied to field elements that undergo changes. This takes the new
         * value and places it into the request data object
         *
         * @param {Event} e Event information
         * @private
         */
        var _notify = function( e ) {
            var el    = $(this),
                key   = el.attr('name'),
                value = el.val();

            if( value == '' && typeof _data[key] == 'undefined' )
                return;

            _data[ key ] = value;
            _call();
        };

        /**
         * Initiate a timed call
         *
         * This function will instigate a request for new data after half a second, unless the function
         * is called again, which will reset the timer. This is used for attaching to things like keyup actions,
         * where a call is not required after every single stroke, only when typing has stopped for long enough
         * to indicate that the new value is successfully input
         *
         * @private
         */
        var _call = function() {
            clearTimeout( _timer );
            _timer = setTimeout( _request, 500 );
        };

        /**
         * Check whether a request is considered valid
         *
         * To be considered valid, a request must have some data to send, and be different
         * in some way from the last request sent.
         *
         * @returns {boolean} true if valid, false if not
         * @private
         */
        var _requestIsValid = function() {

            for( var i in _data ) {
                if( typeof _last[i] == 'undefined' )
                    return true;
                if( _data[i] != _last[i] )
                    return true;
            }
            return false;
        };

        /**
         * Send an AJAX request for more data
         *
         * Unlike _call(), this function will directly summon new data from the server. It does perform a validity
         * test using _requestIsValid(), although this can be bypassed using the 'force' parameter. Also, a URL other
         * than the default can be passed in as the first parameter. If wishing to use the default URL and force the request,
         * a URL of false will simply bypass the override.
         *
         * THIS METHOD SHOULD NOT BE EXPOSED PUBLICLY
         *
         * @param {string}  url   The custom URL to fetch from
         * @param {boolean} force Whether to force the request (no validity test)
         * @private
         */
        var _request = function( url, force ) {

            if( _ajax || (!force && !_requestIsValid()) ) {
                return;
            }

            _ajax = true;
            _showLoading( true );

            $.ajax({
                url      : url || '/complaint/ajax',
                data     : _data,
                dataType : 'json',
                type     : 'get',
                success  : function( response ) {
                    _last = $.extend({}, _data);
                    if( response.status == 'success' && response.rows )
                        _update( response.rows );
                    if( _pager && response.pages )
                        _pager.paginate( response.pages );
                    _onDataLoaded();
                },
                error    : function() {
                    // TODO: change copy
                    console.log('Something untoward happened!');
                },
                complete : function() {
                    _ajax = false;
                    _showLoading( false );
                }
            });
        };

        /**
         * Update the table with data from the server
         * @param {Array} rows Array of Row Data
         * @private
         */
        var _update = function( rows ) {

            if( !_table )
                return;

            _table.find('.row').detach();
            for( var i=0; i<rows.length; i++ ) {
                _table.append( _createRow(rows[i], (i%2 == 0 ? 'even':'odd') ) );
            }
        };

        /**
         * Create a table row from Complaint Data
         *
         * @param {{}}     data      Complaint Data
         * @param {string} classname Class for the row (odd | even)
         * @returns {*|HTMLElement}
         * @private
         */
        var _createRow = function( data, classname ) {

            var row    = $('<tr class="row ' + classname + '"/>'),
                fields = ['status', 'insert_date', 'agent_name', 'bp_name', 'agency'];

            for( var i=0; i<fields.length; i++ ) {
                var field = fields[i],
                    value = data[field] || '';

                // prepend the status indicator to
                if( field == 'status' )
                    value = '<img class="acf-indicator" data-stamp="' + data.stamp +'" /> ' + value;

                row.append('<td>' + value + '</td>');
            }

            row.append('<td class="right"><a class="view-complaint" href="/complaint/'+ data['id'] +'">View</a></td>');
            return row;
        };

        /**
         * Set a callback to be executed on Data load
         *
         * @param callback
         * @private
         */
        var _setOnDataLoaded = function( callback ) {
            if( typeof callback == 'function' )
                _onDataLoaded = callback;
        };

        /** Publicly exposed methods */
        return {
            setTable      : _setTable,
            setPagination : _setPagination,
            call          : _call,
            notify        : _notify,
            onData        : _setOnDataLoaded
        };

    })();

    /**
     * Bind event handlers to form filter fields
     *
     * @param {Element} filters jQuery collection of filter elements
     */
    var bindFormFilters = function( filters ) {

        filters.filter('.date').datepicker({
            dateFormat: 'yy-mm-dd',
            prevText: "&lt;",
            nextText: "&gt;"
        });

        /**
         * Cement the width of each filter table cell
         *
         * This stops the table dynamically resizing once we start manipulating
         * the contents of the cells
         */
        $('.table-filter td').each( function() {
            var td = $(this);
            td.width( td.width() );
        });

        /**
         * Bind events to each element in turn
         *
         * Each element is attached to the notify function on the ajaxDataLoader, so that any updates
         * to the value of the field are pushed up onto the pending request data. Furthermore, each field
         * is wrapped and given an associated clear button.
         */
        filters.each( function() {

            var element = $(this),
                parent  = element.parent('.field-wrap');

            element.change( ajaxDataLoader.notify )
                .blur(  ajaxDataLoader.notify )
                //.keyup( ajaxDataLoader.notify ) // now we have autocompletes - no need for this
                .blur(); // this triggers the notify so we have initial values

            if( parent ) {
                element.css('float', 'left');
                parent.append('<a class="field-clear"/>');
                parent.find('.field-clear').css('height', element.outerHeight() );

                var width = parent.width();
                parent.width( width + 1 );
                parent.height( element.outerHeight() );

                // parent width - button width - input padding
                element.width( width - 27 - 11 );
            }
        });

        /**
         * Click handler for field clear buttons
         */
        $('.filter-wrap').delegate('.field-clear', 'click', function(e) {
            var input = $(this).siblings('.filter');
            input.val('')
                .change(); // call - required to update the dataloader

            ajaxDataLoader.call.apply( input );

            if( !input.is('.date') )
                input.focus();
        });

        $('.filter-reset').click( function() {
            filters.each( function() {
                $(this).val('').change();
            });
        });
    };

    /**
     * Bind autocomplete fields to their relevant data sources
     *
     * Autocompletes can be populated from string-based attributes, or ajax endpoints.
     * This function cycles and processes each in turn, hooking it up to a jQuery UI
     * autocomplete with the correct data source.
     *
     * @param elements
     */
    var bindAutocompleteFields = function( elements ) {

        elements.each( function() {

            var el      = $(this),
                ac_data = el.attr('data-autocomplete');

            if( !ac_data )
                return;

            if( ac_data.substr(0, 5) == 'ajax:' ) {
                // LOAD FROM AJAX - using the supplied keyword
                var key = ac_data.substr(5); console.log('/complaint/all/autocomplete/' + key );
                el.autocomplete({
                    source : '/complaint/all/autocomplete/' + key,
                    close  : function() { el.change(); }
                });

            } else {
                // LOAD FROM SUPPLIED OPTIONS - explode on the comma
                var options = ac_data.split(',');
                el.autocomplete({
                    source : options,
                    close  : function() { el.change(); }
                });
            }
        });
    };

    /**
     * Update colours of ACF indicators based on the age of the complaint
     *
     * Currently, the age of complaints on the listing page is denoted using coloured indicators.
     * The goal is for ARC agents to process complaints within 24 hours, so currently the time
     * goes as following, based on the age of the complaint:
     *  0 - 18 hours  : GREEN
     *  18 - 22 hours : AMBER
     *  22+ hours     : RED
     */
    var IndicatorController = (function() {

        /** Used for caching the indicator elements */
        var _indicators = false,
        /** Holds the timestamp at the point an update cycle beings */
            _stampNow   = 0,
        /** Holds a reference to the cycle timer */
            _timer      = null;

        /**
         * Instantiation method, collect elements to cycle
         * @param selector Optional selector to limit scan to children of an element
         * @private
         */
        var _init = function( selector ) {

            if( selector )
                _indicators = $(selector).find('.acf-indicator[data-stamp]');
            else
                _indicators = $('.acf-indicator[data-stamp]');

            if( _indicators && _indicators.length ) {
                clearTimeout( _timer );
                _cycle();
            }

        };

        var _obtainElements = function( selector ) {
            if( selector )
                _indicators = $(selector).find('.acf-indicator[data-stamp]');
            else
                _indicators = $('.acf-indicator[data-stamp]');
        }

        /**
         * Iterate the cached elements, updating their age
         * @private
         */
        var _cycle = function() {
            _stampNow = new Date().getTime() / 1000;
            _indicators.each( _calculateStage );
            _timer    = setTimeout( _cycle, 60000 ); // every minute
        };

        /**
         * Callback executed on each indicator element
         *
         * This is applied directly as parameter to jQuery's
         * each() function. Comparing the element's stamp against the stamp
         * when the cycle was started gives you one of three possible colours.
         *
         * @param index
         * @returns {*}
         * @private
         */
        var _calculateStage = function( index ) {
            var el    = $(this),
                stamp = $(this).attr('data-stamp'),
                diff  = _stampNow - stamp,
                hours = diff / 60 / 60;

            if( hours < 18 )
                return el.addClass('green').removeClass('amber red');
            else if( hours < 22 )
                return el.addClass('amber').removeClass('green red');
            else
                return el.addClass('red').removeClass('amber green');

        };

        // Public bindings
        return {
            init : _init
        };

    })();

    /**
     * Post DOMReady binding / instantiation
     */
    $(document).ready( function() {

        // Bind 'save' buttons to their click handlers
        $('.save-complaint').click( submitComplaint );
        $('.save-complaint-notes').click( submitNotes );
        //$('select').megaSelect();

        // Bind the ajaxDataLoader to the data table element...
        var table = $('#complaintList');
        if( table.length ) {
            bindFormFilters( $('.table-filter .filter') );
            // ..and attach it to the ajaxDataLoader
            ajaxDataLoader.setTable( table );
        }

        // Bind the pagination controller to the UL element...
        var pagination = $('.acf-pagination');
        if( pagination.length ) {
            var pager = new PaginationController( pagination );
            // ...and attach it to the ajaxDataLoader
            ajaxDataLoader.setPagination( pager );
        }

        // Bind toggles - although we don't currently use them.
        var toggles = $('.toggle-control');
        toggles.each( function() {
            var el = $(this);
            new ToggleController( el );
        });

        // Bind the autocompletes to their relevant endpoints
        var autocompletes = $('[data-autocomplete]');
        if( autocompletes.length ) {
            bindAutocompleteFields( autocompletes );
        }

        // Check for any Complaint ping IDs
        var pinger_forms = $('[data-ping-id]');
        pinger_forms.each( function() {
            new KeepAlivePinger( $(this).attr('data-ping-id'), 5 );
        });

        // This has its own tests, just let it rip...
        IndicatorController.init();
        ajaxDataLoader.onData( function() {
            // an init is basically a refresh
            IndicatorController.init();
        });

    });

})( jQuery );