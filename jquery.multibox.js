(function( $, wnd ){

    /**
     * jQuery Multibox Plugin
     *
     * Provides functionality similar to GMail's Autocomplete 'to' field
     *
     * @author Will Perring <will.perring@acknowledgement.co.uk>
     */

    "use strict";

    var _defaultOptions = {
        elementClass  : 'mbx-parent',   // Class name for the parent element
        tagClass      : 'mbx-tag',      // Class name for each individual tag
        allowCustom   : true,           // Allow non-autocomplete tags,
        allowCase     : false,          // Allow usage of cases (upper/lower) in the tags
        autocomplete  : false,          // Use autocomplete. Accepts a string for an ajax lookup, or array/object
        acDelay       : 300,            // delay before autocomplete calls/makes suggestions
        acCount       : 5,              // maximum numer of autocomplete options to display
        acMinChars    : 3,              // minimum number of characters to suggest
        acClass       : 'mbx-suggest',  // class for the autocomplete ul list
        variableName  : 'tags',         // name of form elements for storing tags data
        minInputWidth : 100,            // width, in pixels, of the shortest allowable text input (after this, it goes to a new line)
        maxLength     : 0,              // max length, in characters, of the input
        tags          : null            // tags to added at initalisation. accepts objects or arrays
    };

    var KEY_UP        = 38,
        KEY_DOWN      = 40,
        KEY_ENTER     = 13,
        KEY_BACKSPACE = 8;

    /**
     * MultiBox Object Constructor
     * @param element Element to set up multibox on
     * @param options Options for multibox instance
     * @constructor
     */

    var MultiBox = function( element, options ) {

        /** @var MultiBox Internal reference to the 'self' object */
        var _mbx = this;

        /** @var array Array of tag values in the multibox */
        _mbx.tagValues = [];
        /** @var int   Stores the timeout for the autocomplete */
        _mbx.acTimer   = null;
        /** @var int   Postion of keyboard focus for autocomplete */
        _mbx.acFocus   = 0;
        /** @var bool  Prevent multiple AC ajax calls executing simultaneously */
        _mbx.ajaxLock  = false;

        // Set up the instance options
        options = _mbx.options = $.extend( {}, _defaultOptions, options );

        // Perform a bit of validation on the options
        if( !options.allowCustom && !options.autocomplete )
            throw "MultiBox plugin requires either allowing custom tags, or an autocomplete field";

        /*
         * This builds a number of elements into the parent, which can be accessed
         * with the following properties:
         *
         * _mbx.tags         = tag element container
         * _mbx.values       = values container. also contains...
         * _mbx.autocomplete = ...the container for the autocomplete list
         * _mbx.input        = the text input
         */

        element.addClass( options.elementClass );
        _mbx.tags         = $('<div/>', { 'class': 'mbx-tags'} )
            .appendTo( element );
        _mbx.values       = $('<div/>', { 'class': 'mbx-values'} )
            .appendTo( element );
        _mbx.autocomplete = $('<div/>', { 'class': 'mbx-autocomplete'} )
            .appendTo( _mbx.values );
        _mbx.input  = $('<input/>', {
            'type'  : 'text',
            'class' : 'mbx-input'
        }).appendTo( _mbx.tags );

        /**
         * Resize the input field to the most appropriate width
         * @private
         */

        var _resizeInputField = function() {

            var finalTag = _mbx.input.prev();
            // If no tags yet, use full width
            if( !finalTag.length ) {
                _mbx.input.css( 'width', element.width() - 10 ); // TODO: find a better way to calculate the 10

            // Otherwise, calculate width based on previous tag
            } else {
                var position = finalTag.position(),
                    width    = finalTag.outerWidth();

                // TODO: find a better way to calculate the 20
                var inputWidth = element.width() - ( position.left + width + 20 );
                inputWidth = ( inputWidth < options.minInputWidth )
                    ? element.width() : inputWidth ;

                _mbx.input.css( 'width', inputWidth );
            }
        };

        /**
         * Listen for keystrokes
         * @param   e Event object
         * @private
         */

        var _keystrokeListener = function(e) {

            if(e.keyCode == KEY_ENTER && _mbx.acFocus > 0 ) {
                // Catch a return with a focused AC option
                e.preventDefault();
                e.stopImmediatePropagation();

                var suggestions = _mbx.autocomplete.find('li'),
                    // acFocus counts 0 as no selection, whereas eq() is zero-indexed
                    selected    = suggestions.eq( _mbx.acFocus-1 );

                if( !selected.length )
                    return false;

                _addTag( selected.text(), selected.attr('data-value') );
                _displayAutocompleteMatches( false );
                _mbx.input.val('');
                _resizeInputField();
                return false; // stop the return bubbling

            } else if( e.keyCode == KEY_ENTER && options.allowCustom === true ) {
                // Catch a return stroke for a custom variable
                e.preventDefault();
                e.stopImmediatePropagation();

                var tag = _mbx.input.val();

                if( tag.trim() == '' )
                    return false;

                if( _addTag( tag, tag ) ) {
                    _mbx.input.val('');
                    _displayAutocompleteMatches( false );
                }
                return false; // stop return bubbling

            } else if( e.keyCode == KEY_BACKSPACE ) {
                // Backspace - we don't want this affected by length, so do nothing here!
                // it does need to retrigger the autocomplete search though, so don't
                // return anything

            } else if( e.keyCode == KEY_UP ) {
                _moveAutocompleteFocus( -1 );
                // don't change the AC - it resets the focus
                return false;

            } else if( e.keyCode == KEY_DOWN ) {
                _moveAutocompleteFocus( +1 );
                // don't change the AC - it resets the focus
                return false;

            } else if( options.maxLength > 0 ) {
                if( _mbx.input.val().length >= options.maxLength ) {
                    e.preventDefault();
                    // don't need to trigger autocomplete
                    return false;
                }
            }

            // clear any existing timeout
            if( _mbx.acTimer !== null )
                wnd.clearTimeout( _mbx.acTimer );

            // and if appropriate, set the trigger for the next one
            if( options.autocomplete !== false )
                _mbx.acTimer = wnd.setTimeout( _collectAutocompleteMatches, options.acDelay );

            return true;
        };

        /**
         * Add a tag
         * @param label Display value for tag
         * @param value Value for tag
         * @private
         */

        var _addTag = function( label, value ) {

            value = value.trim();
            label = label.trim()
                .replace(' ', '&nbsp;');

            if( options.allowCase === false ) {
                value = value.toLowerCase();
                label = label.toLowerCase();
            }

            if( _mbx.tagValues.indexOf( value ) !== -1 )
                return false;

            _mbx.tagValues.push( value );

            // set up the hidden input
            var valueElement = $('<input/>', {
                'name'  : options.variableName + '[]',
                'type'  : 'hidden',
                'value' : value
            });

            // set up the label element
            var labelElement = $('<span/>', {
                'class' : options.tagClass,
                'html'  : label + '<a class="mbx-delete">x</a>'
            });

            // add to the DOM
            valueElement.appendTo( _mbx.values );
            labelElement.insertBefore( _mbx.input )
                .data( 'value', value )
                .data( 'valueElement', valueElement );

            // resize the input field
            _resizeInputField();
            return true;
        };

        /**
         * Add a series of tags
         * @param tags
         * @private
         */

        var _addTags = _mbx.addTags = function( tags ) {

            if( typeof tags != 'object' )
                return;

            if( tags instanceof Array) {
                for( var i=0; i<tags.length; i++ ) {
                    _addTag( tags[i], tags[i] );
                }
                return;
            }

            if( tags instanceof Object ) {
                for( var i in tags ) {
                    _addTag( tags[i], i );
                }
                return;
            }
        };

        /**
         * Collect matches for the autocomplete
         * @private
         */

        var _collectAutocompleteMatches = function() {

            var searchFor = _mbx.input.val()
                .toLowerCase();

            if( searchFor.length < options.acMinChars ) {
                _displayAutocompleteMatches( false );
                return;
            }

            // Get allowed values - allows for injection of other methods here later if required
            var values = options.autocomplete;

            // STRING: Ajax call
            if( typeof options.autocomplete == 'string' ) {

                if( _mbx.ajaxLock )
                    return;
                _mbx.ajaxLock = true;

                var data = {
                    search : _mbx.input.val(),
                    used   : _mbx.tagValues,
                    limit  : options.acCount
                };

                $.ajax({
                    url     : options.autocomplete,
                    data    : data,
                    dataType: 'json',
                    method  : 'get',
                    success : function( response ) {
                        var values = _formatAutocompleteOptions( response, false );
                        _displayAutocompleteMatches( values );
                        //console.log( response, values );
                    },
                    error   : function( xhr, text, error ) {
                        //console.log( xhr, text, error );
                    },
                    complete: function() {
                        _mbx.ajaxLock = false;
                    }
                });

            // OBJECT || ARRAY : provided value list
            } else if( values instanceof Array || values instanceof Object ) {

                values = _formatAutocompleteOptions( values, searchFor );
                _displayAutocompleteMatches( values );
            }
        };

        /**
         * Format either an array or a key/value pair into something we can work with
         * @param values    possible values for autocomplete
         * @param searchFor value being searched for - false in the case of ajax requests, where filtering should happen back-end
         * @returns {Array}
         * @private
         */

        var _formatAutocompleteOptions = function( values, searchFor ) {

            // ARRAY: Provided values, no key
            if( values instanceof Array ) {

                // Collect matches
                var matches = [];
                for( var i=0; i<values.length; i++ ) {
                    // check if this value is already in the multibox
                    if( _mbx.tagValues.indexOf( values[i] ) != -1 )
                        continue;
                    // check if the search string is in the value
                    if( searchFor === false || values[i].toLowerCase().indexOf( searchFor ) != -1 )
                        matches.push( [values[i], values[i]] );
                }

            // OBJECT: Provided values, with key
            } else if( values instanceof Object ) {

                // Collect matches
                var matches = [];

                for( var i in values ) {
                    // check if this value is already in the multibox
                    if( _mbx.tagValues.indexOf( values[i] ) != -1 )
                        continue;
                    // check if the search string is in the value
                    if( searchFor === false || values[i].toLowerCase().indexOf( searchFor ) != -1 ) {
                        matches.push( [i, values[i]] );
                    }
                }

            } else {
                // Couldn't work out what the hell to do with it
                return [];
            }

            // Sort by length of match - shorter is better
            matches.sort( function(a, b) {
                return a.length - b.length;
            });

            // slice to the limit
            if( matches.length > options.acCount )
                matches = matches.slice( 0, options.acCount );

            // and display
            return matches;
        };

        /**
         * Display a set of matches for the autocomplete
         * @param matches Array or strings, or 2 value arrays [key, value]
         * @private
         */

        var _displayAutocompleteMatches = function( matches ) {

            _mbx.autocomplete.empty();
            _mbx.acFocus = 0;

            if( !matches.length )
                return;

            var listElement = $('<ul/>', {
                'class' : options.acClass
            });

            for( var i=0; i<matches.length; i++ ) {

                var listItem = $('<li/>', {
                    'text'       : matches[i][1],
                    'data-value' : matches[i][0]
                });
                listElement.append( listItem );
            }
            _mbx.autocomplete.append( listElement );
        };

        /**
         * Move the focus on the autocomplete up or down via the keyboard
         * @param direction
         * @private
         */

        var _moveAutocompleteFocus = function( direction ) {

            var suggestions = _mbx.autocomplete.find('li'),
                suggCount   = suggestions.length;

            // if we don't have an AC, nothing to do
            if( !suggCount )
                return;

            // check some range limits
            var newPosition = _mbx.acFocus + direction;
            if( newPosition > suggCount || newPosition < 0 )
                return;

            _mbx.acFocus = newPosition;
            suggestions.removeClass('focused');

            if( _mbx.acFocus == 0 ) // 0 = no selection
                return;

            // acFocus counts 0 as no selection, but eq() is zero-indexed
            suggestions.eq( _mbx.acFocus-1 )
                .addClass('focused');
        };

        /**
         * Remove a value from the stored values for the MultiBox
         * @param value
         * @private
         */

        var _removeValue = function( value ) {
            var position = _mbx.tagValues.indexOf( value );
            if( position == -1 )
                return;
            delete _mbx.tagValues[ position ];
        };

        /**
         * Click handler to set focus to the input
         */

        element.click( function() {
            _mbx.input.focus();
        });

        /**
         * Click handler for delete buttons
         */

        element.delegate('.mbx-delete', 'click', function(e) {
            e.preventDefault();

            var tag = $(this).closest( '.' + options.tagClass );
            _removeValue( tag.data('value') );

            tag.data('valueElement').detach();
            tag.detach();
            _resizeInputField();
        });

        /**
         * Click handler for autocomplete options
         */

        _mbx.autocomplete.delegate('li', 'click', function() {
            var el    = $(this),
                label = el.text(),
                value = el.attr('data-value');

            _mbx.input.val('');
            _addTag( label, value );
            _displayAutocompleteMatches( false );
        });

        _mbx.autocomplete.delegate('ul', 'hover', function() {
            _mbx.autocomplete.find('li')
               .removeClass('focused');
            _mbx.acFocus = 0;
        });

        /**
         * Keystroke listener binding
         */

        _mbx.input.keydown( _keystrokeListener );

        // Initialise!

        _resizeInputField();
        if( typeof options.tags == 'object' ) {
            _addTags( options.tags );
        }
    };

    /**
     * jQuery Plugin init function
     */

    $.fn.multiBox = function( options ) {

        // If we're calling on a group of objects, they should be standalone,
        // Elements don't operate in groups
        $(this).each( function() {
            var el  = $(this),
                obj = new MultiBox( el, options );

            if( el[0].tagName.toLowerCase() != 'div')
                throw "MultiBox must be called on div elements";

            el.data( 'MultiBox', obj );
        });
    };

    /*
     * Set up a jQuery val() hook for getting the value of the tags
     */

    // Preserve any existing hooks, or set up a container if none
    var originalHooks;
    if( $.valHooks.div ) {
        originalHooks = $.valHooks.div;
    } else {
        originalHooks = false;
        $.valHooks.div = {};
    }

    /**
     * jQuery get hook
     * @param el Element to get value for ( MultiBox parent div )
     * @returns {*}
     */

    $.valHooks.div.get = function( el ) {

        if( !$(el).data('MultiBox') ) {
            return ( originalHooks && originalHooks.get ) ? originalHooks.get(el) : undefined ;
        }

        return $(el).data('MultiBox').tagValues;
    };

    /**
     * jQuery set hook
     * @param el  Element to set value for ( MultiBox parent div )
     * @param val Value to set
     * @returns {*}
     */

    $.valHooks.div.set = function( el, val ) {

        if( !$(el).data('MultiBox') ) {
            return ( originalHooks && originalHooks.set ) ? originalHooks.get(el, val) : undefined ;
        }

        return $(el).data('MultiBox').addTags( val );
    };

})( jQuery, window );
