(function($) {

    /**
     * Script Contents
     *
     *  1. Closure-wide functions
     *  2. jQuery 'fn' augmentation
     *  3. NavControl object (Ribbon Navigation on mobile)
     *  4. ScrollControl object (Automate events at scroll positions - used for floating share buttons on mobile )
     *  5. SocialSharer object (Sharing Widget)
     *  6. Collapsible Controller (Collapsible mobile nav elements)
     *  7. PostLoader Controller (Ajax-loading new content, infinite scroll)
     *  8. Content Controller (Length-related content showing/hiding - desktop sidebar 'related posts')
     *  9. Comment Count Controller (loads comment counts from the Disqus API)
     * 10. DOMReady Initialisation Block (initialise everything!)
     */

    // Like a boss...
    "use strict";

    /**
     * Console Polyfill
     * @type {Console|{log: log, info: info, warn: warn, error: error}}
     */
    var console = window.console || {
        log   : function() {},
        info  : function() {},
        warn  : function() {},
        error : function() {}
    };

    /**
     * Return the height of the viewport
     * @returns {number} Height of the viewport
     * @private
     */
    var getViewportHeight = function() {
        return Math.max( document.documentElement.clientHeight, window.innerHeight || 0 );
    };

    /**
     * Return the width of the viewport
     * @returns {number} Width of the viewport
     * @private
     */
    var getViewportWidth = function() {
        return Math.max( document.documentElement.clientWidth, window.innerWidth || 0 );
    };

    /**
     * Return the current scroll position
     * @returns {number} The current scroll position
     * @private
     */
    var getScrollPosition = function() {
        return $(document).scrollTop();
    };

    /**
     * Extract a parameter from the query string
     *
     * @param {string} name Parameter to extract
     * @returns {*}
     */
    var getQueryStringParam = function( name ) {
        var qs = window.location.search.substr(1); // trim off the '?'
        if( !qs || qs == "" )
            return undefined;

        var qsParts = qs.split('&');

        for( var i=0; i<qsParts.length; i++ ) {
            var qsParam = qsParts[i].split('=');
            if( qsParam[0] == name )
                return decodeURIComponent( qsParam[1] );
        }
        return undefined;
    };

    /**
     * Stop click events bubbling up past a certain element
     * @returns {*}
     */
    $.fn.catchClicks = function() {
        return $(this).click( function(e) {
            e.stopPropagation();
        });
    };

    /**
     * Returns an object to control the floating navigation bar
     */
    var NavControl = (function() {

        /** internal reference to the navigation element */
        var _element;

        /**
         * Opens the floating navigation bar
         * @param {Event} e The passed click event
         * @private
         */
        var _open = function( e ) {
            e.preventDefault();
            if( !_element )
                throw "NavControl.open() called before init()";
            _element.addClass('open');
        };

        /**
         * Closes the floating navigation bar
         * @param {Event}   e
         * @param {Boolean} allowEvent Allow event to be executed without interruption
         * @returns {boolean}
         * @private
         */
        var _close = function( e, allowEvent ) {
            if( !allowEvent )
                e.preventDefault();
            if( !_element )
                throw "NavControl.close() called before init()";
            _element.removeClass('open');
            return true; // remove this at your peril... req'd for body click handler
        };

        /**
         * Initialise and bind events to the DOM
         * @private
         */
        var _init = function() {

            _element = $('nav');
            _element.catchClicks()
                .delegate('.nav-close', 'click', _close);

            $('.nav-trigger').catchClicks()
                .click( _open );

            $('body').click( function(e) {
                _close( e, true ); // don't suppress actions outside of the nav click
            });
        };

        return {
            'init'  : _init,
            'open'  : _open,
            'close' : _close
        }

    })();

    /**
     * Returns an object to control the ScrollWatchers
     */
    var ScrollControl = (function() {

        /** Internal reference to the timer used to activate an update */
        var _updateTimer = null,
        /** Internal storage of current position */
            _position    = 0,
        /** Internal storage of bound watchers */
            _watchers    = [];

        /**
         * Scrollwatcher class, to control individually bound elements
         * @param {Element} element The DOM element to act upon
         * @param {object}  options An associative object of options (from and/or to, class or callback)
         * @constructor
         */
        var ScrollWatcher = function( element, options ) {

            options = $.extend({}, {
                latch: false
            }, options);

            if( !options['from'] && !options['to'] )
                throw "Scrollwatchers need either a 'from' or 'to' property";

            var _this  = this,
                _state = null,
                _timer = null;

            /**
             * Test if in active range for object
             * @param {int} position The current scroll position
             * @returns {boolean} True if inside active range
             * @private
             */
            var _inRange = function( position ) {
                if( typeof options['from'] != 'undefined' && position <  options['from'] )
                    return false;
                if( typeof options['to']   != 'undefined' && position >= options['to'] )
                    return false;
                return true;
            };

            if( options['class'] && typeof options['class'] == 'string' ) {
                options.callback = function( state ) {
                    $(this).toggleClass( options['class'], state );
                };
            }

            if( !options['callback'] || typeof options['callback'] != 'function' )
                throw "Scrollwatchers either need a class to apply, or a callback";

            /**
             * Update this scrollwatcher with the current position
             * @param {int} position The current scroll position
             * @returns {boolean} True if there was a change in state
             */
            _this.update = function( position ) {

                var inRange = _inRange( position );
                if( !!options['latch'] && _state == inRange )
                    return false;

                options.callback.apply( element, [inRange] );
                _state = inRange;

                if( !!options['timeoutDelay'] && !!options['timeoutCallback']) {

                    clearTimeout( _timer );
                    _timer = setTimeout( function() {

                        position = getScrollPosition();
                        inRange  = _inRange( position );
                        options['timeoutCallback'].apply( element, [inRange] );

                    }, options['timeoutDelay'] );
                }

                return true;
            }
        };

        /**
         * Initialise the ScrollWatcher controller
         * @private
         */
        var _init = function() {
            // scroll event handler
            $(window).scroll( function() {
                // to 'smooth out' calls to the update function
                clearTimeout( _updateTimer );
                _updateTimer = setTimeout( _update, 100 );
                _position    = getScrollPosition();
            });
        };

        /**
         * Bind a ScrollWatcher to a DOM element
         * @param {Element} element The element to act upon
         * @param {object}  options The options for the Scrollwatcher
         * @private
         */
        var _bind = function( element, options ) {

            if( !element || !element.length )
                return false;

            var watcher = new ScrollWatcher( element, options );
            watcher.update( _position );
            _watchers.push( watcher );
        };

        /**
         * Update all bound Scrollwatchers with current position
         * @private
         */
        var _update = function() {
            for( var i=0; i<_watchers.length; i++ ) {
                _watchers[i].update( _position );
            }
        };

        return {
            'init' : _init,
            'bind' : _bind
        };

    })();

    /**
     * Return a controller for the social sharer widgets
     */
    var SocialSharer = (function(){

        /** Index of sharing methods by platform */
        var _methods = {

            /**
             * Share the link on facebook
             * @param {string} link The link to share
             * @returns {boolean} Always false - don't follow through on links
             */
            'facebook' : function( link ) {
                FB.ui({
                    method : 'share',
                    href   : link
                }, function(response){});
                return false;
            },

            /**
             * Share the link on twitter
             * @param {string} link The link to share
             * @returns {boolean} Always false - don't follow through on links
             */
            'twitter'  : function( link ) {
                var url = "https://twitter.com/intent/tweet?url="
                    + encodeURIComponent( link )
                    + "&count=none/";

                window.open( url, '', 'menubar=no,toolbar=no,resizeable=1,height=300,width=500');
                return false;
            },

            /**
             * Share the link on google plus
             * @param {string} link The link to share
             * @returns {boolean} Always false - don't follow through on links
             */
            'google'   : function( link ) {
                var url = "https://plus.google.com/share?url="
                    + encodeURIComponent( link );

                window.open( url, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=600,width=600');
                return false;
            },

            /**
             * Share the link via email (using sharethis.com widget)
             * @param {string} link The link to share
             * @returns {boolean} Always false - don't follow through on links
             */
            'email'    : function( link ) {
                var url = "http://sharethis.com/share?url="
                    + encodeURIComponent( link ); // other params are title, summary, img

                window.open( url, '', 'menubar=no,toolbar=no,resizeable=1,height=720,width=600' );
                return false;
            }
        };

        /**
         * Click handler for events within a .share-widget element
         * @param {Event} e The click event
         * @returns {*}
         * @private
         */
        var _clickHandler = function( e ) {

            e.preventDefault();

            var el       = $(this),
                platform = el.parent()
                    .attr('class');

            var parent = el.closest('.share-widget'),
                link   = parent.attr('data-url') || window.location.href ;

            return _share( link, platform, el );
        };

        /**
         * Initialise the Controller and bind events to the DOM
         * @private
         */
        var _init = function() {
            $('body').delegate('.share-widget a', 'click', _clickHandler );
        };

        /**
         * Share function for public exposure
         * @param {string}  link     The link to share
         * @param {string}  platform The platform to share on
         * @param {element} el       The element to pass as 'this' (optional)
         * @returns {*}
         * @private
         */
        var _share = function( link, platform, el ) {
            if( platform in _methods )
                return _methods[ platform ].apply( el, [link] );
            throw "No share method for platform '" + platform + "'";
        };

        return {
            'init'  : _init,
            'share' : _share
        };

    })();

    /**
     * Collapsible Element Controller
     *
     * Collapsible elements are used in the navigation menu, to show/hide
     * the category lists
     */
    var Collapsible = (function() {

        /**
         * Click handler function
         *
         * Essentially, document object behaviours are controlled through CSS.
         * Assuming any collapsible item (ul) will be a sibling to the controlling anchor
         * this function traverses up a node to the parent and toggles an 'open' class.
         *
         * Switch elements are denoted using a 'collapsible-trigger' class.
         *
         * @param {Event} e The click event
         * @returns {boolean} False if collapsible action is triggered (overrides default action)
         * @private
         */
        var _clickHandler = function( e ) {
            var link    = $(this),
                item    = link.parent(),
                submenu = item.children('ul');

            if( submenu.length ) {
                // supress default - here & return false
                e.preventDefault();
                item.toggleClass('open');
                return false;
            }

            // If we're not intervening, we need to allow the default action to occur
            return true;
        };

        /**
         * Initialisation of Collapsible Controller
         *
         * Binding Elements should be a top-level element for event delegation rather than
         * direct-to-element binding (i.e, in the case of the navigation, the controller should
         * be bound to 'nav' as that element contains all collapsible children)
         *
         * @param {string} selector JQuery selector for node(s) to bind listen events.
         * @private
         */
        var _init = function( selector ) {
            $(selector).delegate('.collapsible-trigger', 'click', _clickHandler);
        };

        return {
            'init' : _init
        }
    })();

    /**
     * Object to control the Post Loader (infinite scroll)
     *
     * Because of the way WordPress handles pagination, posts are measured in 'pages' and 'overs',
     * rather than 'counts' and 'offsets'. We're limited into pulling the same amount each time, but
     * because of the Hero image, if we pull an even number we'll end up with odd columns, and so on...
     *
     * Currently, both the script loads and the pagination here are set to the same value meaning these
     * aren't used, but the functionality has been built in based around the fact we'll probably need
     * to tune how this works
     */
    var PostLoader = (function() {

        /** Initialisation defaults */
        var _config = {
            'postsPerPage' : 6,
            'autoload'     : false
        };

        // internal state references
        /** last visited scroll position */
        var _lastScrollPosition  = 0,
        /** internal reference to 'load more' elements */
            _elementCollection   = $(null),
        /** the currently-used 'load more' element */
            _watchedElement      = $(null),
        /** the state of the currently-used 'load more' element */
            _elementVisibleState = null;

        // list element references
        /** Desktop content container */
        var _contentDesktop = $(null),
        /** Mobile content container */
            _contentMobile  = $(null);

        // internal storage of posts state
        /** Number of displayed posts */
        var _postCount = 0,
        /** Total number of posts to display from */
            _postTotal = 9999; // need a large value to start

        /** Internal Storage of Count Controller */
        var _countController = false;

        // internal storage of load state variables
        /** Ajax request latch */
        var _postsRequested = false;

        /** Used for readability */
        var State = {
            READY    : 1,
            LOADING  : 2,
            FINISHED : 3
        };

        /**
         * Reassigns visible element information on a resize event
         * @private
         */
        var _resizeCallback = function() {
            var visibleElement = _elementCollection.filter(':visible').first();
            if( _watchedElement && _watchedElement.is(visibleElement) )
                return false;

            // if we got to here, the element has changed - reset stuff
            _watchedElement      = visibleElement;
            _elementVisibleState = null;
        };

        /**
         * Calculates visible position of control elements on scroll
         * @private
         */
        var _scrollCallback = function() {

            if( !_watchedElement.length )
                return;

            /** the current scroll position */
            var _scrollPosition      = getScrollPosition(),
            /** the difference in scroll position */
                _scrollDiff          = ( _scrollPosition - _lastScrollPosition ),
            /** the direction of scroll (-1: up, 1: down) */
                _scrollDirection     = Math.round( _scrollDiff / Math.abs( _scrollDiff )),
            /** the lowest scroll value from which the 'load more' control is visible */
                _visibleAreaMin      = _watchedElement.offset().top
                    + _watchedElement.height()
                    - getViewportHeight(),
            /** the highest scroll value until which the 'load more' control is visible */
                _visibleAreaMax      = _watchedElement.offset().top,
            /** whether the 'load more' control is currently visible */
                _visibleStateCurrent = (
                    _scrollPosition >= _visibleAreaMin &&
                    _scrollPosition  < _visibleAreaMax
                );

            _lastScrollPosition = _scrollPosition;

            // call the state change event only if the state has altered
            if( _elementVisibleState != _visibleStateCurrent ) {
                _onVisibleStateChange( _visibleStateCurrent, _scrollDirection );
                _elementVisibleState  = _visibleStateCurrent;
            }
        };

        /**
         * Called when the 'load more' link is brought into view
         * @param state     Whether the element is visible (true: yes)
         * @param direction The direction of scroll (-1: up, 1: down)
         * @private
         */
        var _onVisibleStateChange = function( state, direction ) {
            if( state == true && direction != -1 && _config.autoload )
                _initiateAjaxRequest();
        };

        /**
         * Set the state of the loading elements
         *
         * @param state
         * @returns {*}
         * @private
         */
        var _setLoaderState = function( state ) {
            _elementCollection.removeClass('loading finished');

            switch( state ) {
                case State.READY:
                    return true;
                case State.LOADING:
                    return _elementCollection.addClass('loading');
                case State.FINISHED:
                    return _elementCollection.addClass('finished');
            }
            return false;
        };

        /**
         * Get parameters for Ajax request based on URL and query string
         *
         * @returns {{url: string, data: {json: number, count: *}}}
         * @private
         */
        var _getRequestParameters = function() {

            var url    = window.location.pathname,
                search = getQueryStringParam( 's' );

            var data = {
                'url'  : url,
                'data' : {
                    'json'  : 1,
                    'count' : _config['postsPerPage']
                }
            }

            if( search != undefined ) {
                data['data']['json']   = 'get_search_results',
                data['data']['search'] = search;
            }

            return data;
        };

        /**
         * Initiate and latch the ajax request for more posts
         *
         * @see     PostLoader
         * @returns {boolean}
         * @private
         */
        var _initiateAjaxRequest = function() {

            if( _postsRequested )
                return false;

            if( _postCount >= _postTotal )
                return _setLoaderState( State.FINISHED );

            // latch the request
            _postsRequested = true;
            _setLoaderState( State.LOADING );

            // calculate the page dynamics
            var currentPage = Math.floor( _postCount / _config['postsPerPage'] ),
                postsOver   = parseInt(   _postCount % _config['postsPerPage'] );

            var requestInformation = _getRequestParameters();
            requestInformation['data']['page'] = currentPage+1;

            return $.ajax({
                'url'      : requestInformation['url'],
                'cache'    : false,
                'dataType' : 'json',
                'type'     : 'get',
                'data'     : requestInformation['data'],
                'success'  : function( response ) {
                    _postTotal  = response.count_total;
                    _postCount += response['count'];
                    _processAjaxResponse( response, currentPage, postsOver );
                },
                'error' : function( err ) {
                    _setLoaderState( State.FINISHED );
                },
                'complete' : function() {
                    _postsRequested = false;
                }
            });
        };

        /**
         * Process a successful response from the server for more posts
         *
         * @param {Object} response Response data from the JSON api
         * @param {number} page     The 'page' of posts loaded
         * @param {number} over     The number of posts from this 'page' already loaded
         * @see   PostLoader
         * @private
         */
        var _processAjaxResponse = function( response, page, over ) {

            if( typeof response['count'] == 'undefined' || response['count'] == 0 ) {
                _setLoaderState( State.FINISHED );
                return false;
            }

            _addDesktopContent( response['posts'].slice(over) );
            _addMobileContent(  response['posts'].slice(over) );
            _setLoaderState( _postCount < _postTotal ? State.READY : State.FINISHED );
        };

        /**
         * Return the Ordinal version of a Cardinal number
         *
         * @param number The carinal number
         * @returns {string} The ordinal number
         * @private
         */
        var _getOrdinal = function( number ) {

            /** Ordinal Strings */
            var s = ["th","st","nd","rd"],
            /** Number, ranged down to 0-99 */
                v = number %100;

            /* Essentially, the ordinal suffixes run the same on every set 0-9, with the exception
             * of 10-19, which stick with 'th' all the way through. So, our pattern matches are as follows:
             *
             * 1. s[ (v-20)%10 ] Essentially a v%10, but only for values >20. Lower than that,
             *                    returns a negative index
             * 2. s[ v ]         Takes care of the numbers 0-3
             * 3. s[0]           If neither of the above matches a value, return a "th";
             */

            return number + ( s[(v-20)%10] || s[v] || s[0] );
        };

        /**
         * Get a formatted date string for content attributes
         *
         * @param {Date} date The date object to format
         * @returns {undefined|string} Formatted date, or undefined if not a Date object
         * @private
         */
        var _getFormattedDate = function( date ) {

            // Only working with date objects
            if( !date instanceof Date )
                return "";

            // Make sure we're building a string object rather than maths
            var ret    = "",
                months = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'];

            ret += months[ date.getMonth() ]
                +  ' '
                +  _getOrdinal( date.getDate() )
                +  ', '
                + date.getFullYear();

            return ret;
        };

        /**
         * Construct the HTML for the post attributes list
         *
         * @param {string} author   The post author name
         * @param {string} posted   The date the post was posted
         * @param {number} comments The number of comments
         * @returns {string} The HTML for the post attributes
         * @private
         */
        var _getPostAttributes = function( author, posted, url ) {

            var html = '<ul class="attributes desktop">',
                date = new Date( posted );

            var dateString = _getFormattedDate( date );

            if( author )
                html += '<li class="author">' + author + '</li>';
            if( posted )
                html += '<li class="posted">' + dateString + '</li>';

            html += '<li class="comments"></li>';
            html += '</ul>';
            return html
        };

        /**
         * Construct the HTML for the share widget element
         *
         * @param {string} url (Optional) The URL for the share widget
         * @returns {string} The HTML for the share widget
         * @private
         */
        var _getShareWidget = function( url ) {

            var widget = ( url )
                ? '<table class="share-widget" data-url="' + url + '">'
                : '<table class="share-widget">' ;

            widget += '<tr>';

            var networks = ['facebook','twitter','google','email'];
            for( var i=0; i<networks.length; i++ ) {
                widget += '<td class="' + networks[i] + '"><a></a></td>';
            }

            widget += '</tr></table>';
            return widget;
        };

        /**
         * Add post elements to the desktop content list
         *
         * @param {Array} content An array of post data from the JSON api
         * @private
         */
        var _addDesktopContent = function( content ) {
            for( var i=0; i<content.length; i++ ) {

                var post = content[i],
                    html = "";

                try { // image first
                    html += '<a href="' + post['url'] + '">';
                    var img = post['thumbnail_images']['desktop-thumb'];
                    html += '<img width="150" height="150" src="'
                        + img['url']
                        + '" />';
                    html += '</a>';
                } catch (e) {
                    /* do nothing, no image */
                    console.warn( e.message );
                }

                html += "<div>";

                try { // then title + excerpt
                    html += '<a href="' + post['url'] + '">';
                    html += '<h2>' + post['title_plain'] + '</h2>';
                    html += '</a>'
                    html += post['excerpt'];
                } catch (e) {
                    /* do nothing, no image */
                    console.warn( e.message );
                }

                try { // attributes + widget
                    html += _getPostAttributes( post['author']['name'], post['date'], post['url'] );
                    html += _getShareWidget( post['url'] );
                } catch (e) {
                    /* do nothing, no image */
                    console.warn( e.message );
                }

                html += "</div>";

                var article = $('<article/>', {
                    'html' : html
                });

                _contentDesktop.find('.load-more')
                    .before( article );

                // If we're using a count controller, we need to add this URL to the list
                if( !!_countController ) {
                    _countController.queue(
                        post['url'],                // URL for post
                        article.find('.comments'),  // Comment field within the generated html
                        function( count ) {         // Callback to insert count and show element
                            $(this).html( (count==1) ? '1 comment' : count + ' comments' )
                                .css('display', 'inline-block');
                        }
                    );
                }
            }

            // ...again, if we're using a CountController we need to instantiate the request
            if( !!_countController )
                _countController.request();
        };

        /**
         * Add post elements to the mobile content list
         *
         * @param {Array} content An array of post data from the JSON api
         * @private
         */
        var _addMobileContent = function( content ) {

            var table         = _contentMobile.find('.grid'),
                startOnOddCol = ( _postCount % 2 == 0 ), // EVEN, because we take into account the hero
                iterationCount, html; // we'll need these in a bit.

            if( startOnOddCol ) {

                /* If we're starting on an odd column, rather than try and pop off the odd cell,
                 * remove the blank cell, inject a new one and then carry on with the loop, we'll
                 * pull the content from the first cell and use that as the initial string value
                 * for our loop,
                 */
                var lastRow  = _contentMobile.find('tr').last(),
                    lastItem = lastRow.children('td')
                        .first()
                        .html();
                iterationCount = 1; // let it know we already have one in the bag
                html           = "<tr><td>" + lastItem + "</td>";
                lastRow.detach();

            } else {

                /* If we're not starting on an odd column, we have zero in the bag and
                 * no prebuilt string, just a good ol' empty value
                 */
                iterationCount = 0; // for controlling the column, 0 indexed
                html           = "";
            }

            // Iterate the posts and build the raw html
            for( var i=0; i<content.length; i++ ) {

                // shortener name for ease of use
                var post = content[i];

                // open the row on even numbers
                if( iterationCount % 2 == 0 )
                    html += "<tr>";
                html += '<td>';

                try { // thumbnail
                    var img = post['thumbnail_images']['mobile-thumb'];
                    html += '<a href="' + post['url'] + '">';
                    html += '<img src="' + img['url'] + '" />';
                    html += '</a>';
                } catch (e) { /* do nothing */ }

                try { // title
                    html += '<a href="' + post['url'] + '">';
                    html += '<h2>' + post['title_plain'] + '</h2>';
                    html += '</a>';
                } catch (e) { /* do nothing */ }

                html += '</td>';
                // and close the row on odd numbers
                if( iterationCount % 2 == 1 )
                    html += "</tr>";

                iterationCount++;
            }

            // if we ended on an odd cell, pad and close the last row
            if( iterationCount % 2 == 1 ) {
                html += "<td></td></tr>";
            }

            table.append( html );
            _equaliseMobileColumns();
        };

        /**
         * Equalise the ends of the mobile columns
         *
         * If we've not reached the end of the posts, and the columns are inequal,
         * hide the last row
         * @private
         */
        var _equaliseMobileColumns = function() {

            var table = _contentMobile.find('.grid')
            // if we're on an odd column (even number of posts, due to hero) hide the last one
            if(
                _postCount % 2 == 0                        // odd number of column posts (even total, because of hero)
                && _postCount <  _postTotal                 // not at the limit
                && _postCount >= _config['postsPerPage']   // at least one page full
            ) {
                table.find('tr')
                    .last()
                    .hide();
            }
        };

        /**
         * Initialise the PostLoader object
         *
         * @param {string} selector jQuery selector for the 'load more' elements
         * @param {Object} options  Initialisation options
         * @returns {boolean}
         * @private
         */
        var _init = function( selector, options, CountController ) {

            // merge defaults with specified options
            _config = $.extend( {}, _config, options );

            // store our collection
            _elementCollection = $(selector);
            if( !_elementCollection.length )
                return false;

            // Store a comment count controller, if we have one
            if( !!CountController )
                _countController = CountController;

            // information about current state
            _postCount = $('.content-list.desktop')
                .children('article').length + 1; // +1 for hero post at the top

            // references to our content blocks
            _contentDesktop = $('.content-list.desktop');
            _contentMobile  = $('.content-list.mobile');

            // bind and initialise the resize watcher
            $(window)['resize']( _resizeCallback ); // bracket notation for ClosureCompiler
            _resizeCallback();

            // bind and resize the scroll watcher
            $(window).scroll( _scrollCallback );
            _scrollCallback();

            // bind the click events
            $(selector).click( _initiateAjaxRequest );

            // last minute display tweaks
            _equaliseMobileColumns();
        };

        return {
            'init' : _init
        };

    })();

    /**
     * Controller for content-length based visibility control
     *
     * The related items in the post sidebar are shown or hidden conditionally, depending on the
     * length of the post against the length of the sidebar
     */
    var ContentController = (function() {

        /** Sidebar element (container) */
        var _sidebarElement = $(null),
        /** Main content container */
            _contentElement = $(null),
        /** Section element containing related posts */
            _relatedElement = $(null),
        /** Whether the event has been called (latch) */
            _eventCalled    = false;

        /**
         * Initialise element references and trigger methods
         *
         * The timeout parameter is used as a 'belt and braces' approach in case the notoriously unreliable
         * document load event doesn't fire
         *
         * @param {number} timeout Seconds to wait before auto-triggering
         * @returns {boolean}
         * @private
         */
        var _init = function( timeout ) {

            _contentElement = $('#main');
            _sidebarElement = $('#sidebar'),
            _relatedElement = $('#sidebar-related');

            if( !_contentElement.length || !_sidebarElement.length )
                return false;

            var deferredTrigger = window['disqusLoaded'];
            if( !deferredTrigger || !deferredTrigger.done )
                return false;

            // just in case this event fails to load
            if( timeout && typeof timeout == 'number' )
                setTimeout( _onDocumentLoaded, timeout );

            deferredTrigger.done( _onDocumentLoaded );
        };

        /**
         * Document Loaded Event Handler - calculate visibility of related items
         * @returns {boolean}
         * @private
         */
        var _onDocumentLoaded = function() {

            // we only want this once
            if( _eventCalled )
                return false;
            _eventCalled = true;

            // prepare the 'related content' for measuring
            // 1. Hide the rows
            var rows = _relatedElement.find('tr');
            rows.hide();

            // show the container (req'd for height)
            _relatedElement.css('visibility', 'hidden')
                .show();

            var heightDiff = 9999,
                rowsShown  = 0;

            while( heightDiff > 50 && rowsShown <= rows.length ) {
                // show the row, then...
                rows.eq( rowsShown ).show();
                // ..calculate the height
                heightDiff = _contentElement.height() - _sidebarElement.height();
                rowsShown++;
            }

            ( !!rowsShown )
                ? _relatedElement.hide()
                    .css('visibility', 'visible')
                    .fadeIn()
                : _relatedElement.hide() ;

            return;

        };

        return {
            "init"    : _init,
            "trigger" : _onDocumentLoaded // expose a trigger method
        };
    })();

    /**
     * Controller to deal with the loading and displaying of Comment Count information
     */
    var CommentCountController = (function() {

        /** Disqus API public Api Key */
        var _APIKEY       = 'fk6MkqPbJQKCjlOpUfvHmav1KukLFE7RVpllP3gqiWrpWMVcPWhJSMDRvboqCwfs',
        /** Queued request information @see _queueUrl() */
            _requestQueue = [];

        /**
         * Initialisation Sweep
         *
         * Search the DOM for anything marked as needing a comment count.
         * These are denoted by using the [data-comment-url] attribute, with the URL to
         * look up
         *
         * @private
         */
        var _init = function() {

            var _countElements = $('[data-comment-url]');

            /**
             * Callback for objects in the DOM at load time
             *
             * Because these have their count URLs passed in through the DOM (as
             * opposed to via the new content insertion functions for ajax-loaded content),
             * this callback not only updates the comment count field, but also strips the
             * attribute from the element once it's done.
             *
             * @param {int} count The comment count to be displayed in this element
             */
            var callback = function( count ) {
                var el = $(this);
                el.html( (count==1) ? "1 comment" : count + " comments" )
                    .removeAttr('data-comment-url')
                    .css('display', 'inline-block');
            };

            // Build up our queue of DOM-present URLs
            _countElements.each( function(index) {
                var element = $(this),
                    url     = $(this).attr('data-comment-url');

                _queueUrl( url, element, callback);
            });

            // Make the API request
            _requestCommentCounts();
        };

        /**
         * Add an Element/URL to the comment count retrieval queue
         *
         * Rather than making an individual request for each comment count, add a URL/element set
         * to the queue for processing later
         *
         * @param {string}   url      The URL to get the comment count for
         * @param {element}  element  The element to display the comment count in
         * @param {function} callback The callback to execute once a count is retrieved
         *
         * @private
         */
        var _queueUrl = function( url, element, callback ) {
            _requestQueue.push( {'url': url, 'element': element, 'callback': callback} );
        };

        /**
         * Initiate the request process for the current queue
         *
         * This is the function to be called once a queue has been built. It is also the
         * function that is publicly exposed for manually calling a retrieval.
         *
         * @returns {boolean}
         * @private
         */
        var _requestCommentCounts = function() {

            // let the queue build if we're on mobile - no need to resolve
            if( getViewportWidth() < 450 || !_requestQueue.length )
                return false;

            var _requestPromise = _getRequestPromise();

            _requestPromise.done( function( response ) {

                if( !response['response'] )
                    return false;

                var _urlCounts = {};

                for( var i in response['response'] ) {
                    var _post = response['response'][i];
                    _urlCounts[ _post['link'] ] = _post['posts'];
                }

                _resolveQueue( _urlCounts );
            });
        };

        /**
         * Resolve the queue with an object of count information
         *
         * The single parameter for this function accepts an Object in the format {url: count}.
         * Any URL objects left unprocessed will remain in the queue for the next request
         *
         * @param {object} counts Object of counts, indexed by the URL
         *
         * @private
         */
        var _resolveQueue = function( counts ) {

            var _remainingQueue = [];

            for( var i=0; i<_requestQueue.length; i++ ) {
                var request = _requestQueue[i];

                if( request['url'] in counts ) {
                    if( typeof request['callback'] == 'function' )
                        request['callback'].apply( request['element'], [counts[request['url']]] );
                } else {
                    _remainingQueue.push( request );
                }
            }

            _requestQueue = _remainingQueue;
        };

        /**
         * Initiate a request to the API and return the promise object
         *
         * This function SHOULD NOT be used to initiate the whole request process.
         * That is covered under _requestCommentCounts()
         *
         * @see _requestCommentCounts()
         *
         * @returns {*}
         * @private
         */
        var _getRequestPromise = function() {

            // We need an array of formatted URLs to make a request for
            var _requestUrls = [];
            for( var i=0; i<_requestQueue.length; i++ ) {
                _requestUrls.push( 'link:' + _requestQueue[i]['url'] );
            }

            return $.ajax({
                type     : 'GET',
                url      : 'https://disqus.com/api/3.0/threads/set.json',
                cache    : false,
                dataType : 'json',
                data     : {
                    api_key : _APIKEY,
                    forum   : window['disqus_shortname'],
                    thread  : _requestUrls
                }
            });
        };

        return {
            'init'    : _init,
            'queue'   : _queueUrl,
            'request' : _requestCommentCounts
        }
    })();

    /**
     * Post DOMReady initialisation
     */
    $(document).ready( function() {

        // Init control modules
        NavControl.init();
        ScrollControl.init();
        SocialSharer.init();
        CommentCountController.init();
        ContentController.init( 5000 );
        // Collapsible functionality removed by request. Code may come in handy later...
        //Collapsible.init('nav');
        PostLoader.init('.load-more', {
            'autoload'     : true,
            'postsPerPage' : window['postsPerPage']
        }, CommentCountController);

        // Bind the floating share buttons on mobile
        ScrollControl.bind( $('.sticky'), {
            'from' :  220,
            'class': 'open',
            'timeoutDelay'    : 2000,
            'timeoutCallback' : function( state ) {
                $(this).removeClass('open');
            }
        });

        // If we're on a mobile device then attempt to hide the address bar
        if( getViewportWidth() <= 450 ) {
            setTimeout(function() {
                window.scrollTo(0,1);
            }, 10);
        }
    });

})( jQuery );