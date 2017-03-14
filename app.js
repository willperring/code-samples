/**
 * BarclayCard Partner Finance Prototype
 *
 * @author Will Perring <will.perring@iris-worldwide.com>
 * @version 0.1
 */
(function( $, wnd ) {

	/* Structure of this file
	1. Config options
	2. Interface Control Class
	3. Class Definitions (Sequence first, then Chain Items)
	    i.    Sequence
	    ii.   OneShot
	    iii.  DialogBox
	    iv.   ConfirmationBox
	    v.    InputBox
	    vi.   Keystrokes
	    vii.  ClickDemonstration
	    viii. ResultEvaluator
	    ix.   Toast
	    x.    Delay
	*/

	//
	// Config
	//

	var config = {
		clickDemoDelay     : 800,
		clickDemoTime      : 1.5,
		defaultCursorStart : { left: 10, top: 10 },
		keyRepeatSpeed     : 100,
		toastDefaultTime   : 5000,
		toastDelayTime     : 1500,
		toastFadeTime      : 1200,
		watcherEvents      : "click blur keyup"
	};

	//
	// Functions - General Usage
	//

	/**
	 * Returns the co-ordinates relative to the document origin for a specific element
	 *
	 * @param jQuery target Target element
	 * @return { left: int, top: int }
	 */
	var findTargetCenter = function( target ) {

		if( !target )
			throw("findTargetCenter() requires jQuery element");

		var _origin = target.offset();

		return {
			left : _origin.left + parseInt( target.outerWidth()  / 2 ),
			top  : _origin.top  + parseInt( target.outerHeight() / 2 )
		};
	};

	//
	// Interface Control
	//

	/**
	 * Interface Controller
	 * @singleton
	 */
	wnd.UI = (function() {

		/** Used for element references */
		var _el;

		/** Bind UI elements to the object */
		this.bind = function() {
			_el = {
				overlay : $('#overlay'),
				cursor  : $('#cursor')
			};

			// The whole point of this is to stop interaction
			// Also, we don't want this bubbling to the Watcher functions
			_el.overlay.click( function( e ) {
				e.stopImmediatePropagation();
				e.preventDefault();
				return false;
			});
		};

		/** Internal storage of fake cursor X position */
		var _mx        = 0;
		/** Internal storage of fake cursor Y position */
		var _my        = 0;
		/** Internal storage of animation state */
		var _animating = false;

		/** Show the mouse demo layer */
		this.enableMouseLayer = function() {
			_el.overlay.show();
		};

		/** Hide the mouse demo layer */
		this.disableMouseLayer = function() {
			_el.overlay.hide();
		};

		/**
		 * Reposition the mouse demo cursor
		 *
		 * @param int left Left co-ordinate of new cursor position
		 * @param int top  Top co-ordinate of new cursor position
		 */
		this.setCursorPosition = function( left, top ) {
			_mx = left;
			_my = top;
			_el.cursor.css({ left: left, top: top });
		};

		/**
		 * Animate the mouse demo cursor to a new position
		 *
		 * @see config.clickDemoTime
		 *
		 * @param int      left     Left co-ordinate of new cursor position
		 * @param int      top      Top co-ordinate of new cursor position
		 * @param Function callback Function to be executed after animation
		 */
		this.animateCursorTo = function( left, top, callback ) {

			if( _animating )
				throw("Mouse already animating. Do you have a race condition?");

			// Pythagoras, y'all...
			var dist = Math.sqrt(
				Math.pow( Math.abs( _mx - left), 2 ) +
				Math.pow( Math.abs( _my - top ), 2 )
			);

			// So our animations are constant speed
			var time = dist * config.clickDemoTime;
			callback = callback || function() {};

			_animating = true;
			_el.cursor.delay( config.clickDemoDelay ).animate({
				left: left,
				top:  top
			}, time, function() {
				/** Clear the animation state and call the callback */
				var finalAction = function() {
					_animating = false;
					callback();
				};
				setTimeout( finalAction, config.clickDemoDelay );
			});
		};

		return this;

	})();

	//
	// Class Definitions
	//

	/**
	 * Sequence Object
	 *
	 * The sequence object is the core of the scripting system. This allows
	 * a chain of events, demonstrations and user interactions to be queued up
	 * and repeated, advanced and rewound as necessary.
	 *
	 * The resolver object is passed to the execution stages of each step in the
	 * chain so that control over whether the step should be repeated, advanced, or
	 * the sequence completed or aborted can be handled within the step functions
	 * themselves. This is handled using Function.apply()
	 *
	 * Note: *ALL* items within a chain must expose an execute() method
	 *
	 * The resolver object is passed to all execute() functions as the 'this'
	 * reference, so to control sequence you can call the resolver methods as:
	 *     this.advance();
	 *     this.repeat();, etc...
	 *
	 * @constructor
	 * @param array    chain    Array of steps in the script
	 * @param function complete Callback to be executed on chain complete
	 * @param function abort    Callback to be executed on chain abort
	 */
	wnd.Sequence = function( chain, complete, abort ) {

		if( !chain.length )
			throw("Sequence requires array chain");

		/** @var Sequence Reference to self for use in nested functions */
		var _self     = this;
		/** @var int Internal pointer to current step in chain */
		var _pointer  = 0;
		/** @var int Reference to timeout set to notify of potential chain stall */
		var _warning  = null;

		/** @var {function} Functions passed to chain steps in order to provide sequence control */
		var _resolver = {

			/** Repeat a sequence step */
			repeat  : function() {
				_callStep();
			},
			/** Advance a sequence step */
			advance : function() {
				_pointer++;
				if( _pointer < chain.length ) {
					_callStep();
				} else if( typeof complete == "function" ) {
					clearTimeout( _warning );
					complete();
				}
			},
			/** Abort sequence */
			abort : function() {
				clearTimeout( _warning );
				if( typeof abort == "function" )
					abort();
			},
			/** Complete sequence */
			complete : function() {
				clearTimeout( _warning );
				if( typeof complete == "function" )
					complete();
			}
		};

		/**
		 * Call the next step in the chain
		 *
		 * This function, used internally within the class, both checks for the
		 * presence of and calls the steps' execute() function, passing in the
		 * resolver object as the reference for 'this' (Function.apply()). It
		 * also sets up a 15 second timeout to provide a console warning of a
		 * potential chain stall. This can happen through chain steps not calling
		 * any of the resolver methods to progress. This does not happen on the
		 * last step of a sequence.
		 */
		var _callStep = function() {

			/** Current step, as defined by pointer */
			var step = chain[ _pointer ];

			if( !step.execute || typeof step.execute != "function" )
				throw("Chain Steps must expose an execute() method");

			step.execute.apply( _resolver, [] );

			clearTimeout( _warning );

			// If it's the last element in the chain,
			// Don't set up the stall warning
			if( (_pointer+1) >= chain.length )
				return;

			_warning = wnd.setTimeout( function() {
				console.warn("15 seconds elapsed. Has your chain stalled?");
			}, 15000 );
		};

		/**
		 * Execute a sequence
		 *
		 * Begin the sequence at the first step. 
		 */
		this.execute = function() {
			_callStep();
		};

		/** Reset the internal pointer to zero */
		this.rewind = function() {
			_pointer = 0;
		};
	};

	/**
	 * CHAIN ITEM: Fire off a single function
	 *
	 * In reality, this is really just a sort of code container function
	 * to fill in the gaps between the other stage types
	 * whilst I'm putting this together. I wouldn't put this but into
	 * production.
	 *
	 * The function is 'fired', and then the sequence is immediately advanced.
	 *
	 * @param Function ammunition Function to be 'fired'
	 */
	wnd.OneShot = function( ammunition ) {
		this.execute = function() {
			try {
				// If we return specifically false, don't advance
				// This means we can use the resolver
				if( ammunition.apply( this, [] ) === false )
					return;
			} catch( e ) {
				console.warn("OneShot failed, skipping");
			}
			this.advance();
		};
	};

	/**
	 * CHAIN ITEM: Single Button Dialog Box
	 *
	 * This item presents a simple information alert with one option, to close.
	 * This will ultimately be turned into something prettier than an alert
	 *
	 * @param String text Text to display on dialog box
	 */
	wnd.DialogBox = function( text ) {

		this.execute = function() {
			alert( text );
			this.advance();
		};
	};

	/**
	 * CHAIN ITEM: Double Button Confirmation Box
	 *
	 * Present an OK / Cancel dialog box with information
	 * This will ultimately be turned into something prettier than a confirm
	 *
	 * @param String   text   Text to display on dialog box
	 * @param Function ok     Callback to be executed on clicking 'OK'
	 * @param Function cancel Callback to be executed on clicking 'Cancel'
	 */
	wnd.ConfirmationBox = function( text, ok, cancel ) {

		this.execute = function() {
			/** Callback function to be executed, based on confirm result */
			var cbFunction = confirm( text ) ? ok : cancel ;

			// allows the use of 'advance' and 'repeat' strings, etc
			if( typeof cbFunction == 'string' && typeof this[cbFunction] == 'function' )
				return this[cbFunction]();

			cbFunction.apply( this, [] );
		};
	};

	/**
	 * CHAIN ITEM: Input Dialog Box
	 *
	 * Present a dialog box allowing the user to enter text.
	 * This will ultimately be turned into something prettier than a prompt.
	 *
	 * Calls to this function may also be made with (text, evaluator) params.
	 * The function passed as evaluator must accept one param, the value entered
	 * by the user
	 *
	 * @param String   text         Text to display on dialog box
	 * @param String   defaultValue (optional) Default value to display
	 * @param Function evaluator    Callback to be executed on data, params (value)
	 */
	wnd.InputBox = function( text, defaultValue, evaluator ) {

		if( typeof text != 'string' || ( typeof evaluator != 'function' && typeof defaultValue != 'function') )
			throw("InputBox requires at least text string and evaluator function");

		/// Rewrite the params if second if function
		if( typeof defaultValue == 'function' ) {
			evaluator    = defaultValue;
			defaultValue = '';
		};

		this.execute = function() {
			var result = prompt( text, defaultValue ) || '';
			evaluator.apply( this, [result] );
		};
	};

	/**
	 * CHAIN ITEM: Send Keystrokes to a target
	 *
	 * This item mimics the inputting of text to a field. The keys are 'hit'
	 * one at a time, with the speed controlled by the config.keyRepeatSpeed
	 * setting above.
	 *
	 * @see config.keyRepeatSpeed
	 *
	 * @param jQuery target Element to recieve 'keystrokes'
	 * @param String text   Keystrokes to send
	 */
	wnd.Keystrokes = function( target, text ) {

		if( !target || typeof text != 'string' )
			throw("Keystrokes() expects a jQuery object and a string");

		if( !target.length )
			throw("Keystrokes() target selector contains no objects");

		/** Internal reference to current keystroke */
		var _pointer = 0;

		this.execute = function() {

			target.focus();

			/** So we can access our resolver object from the interval */
			var _resolve = this;

			/** Store the reference to our interval so we can clear it */
			var _intervalRef;
			/** Function called by interval */
			var _intervalFunction = function() {
				target.val( text.substr(0, ++_pointer) );
				if( _pointer >= text.length ) {
					clearTimeout( _intervalRef );
					_resolve.advance();
				}
			}

			_intervalRef = wnd.setInterval( _intervalFunction, config.keyRepeatSpeed );
		};
	};

	/**
	 * CHAIN ITEM: Send a click to a target
	 *
	 * The click demonstration tool is used to show users where they need to
	 * be interacting, as well as this it can also be used to drive the narrative
	 * of the training piece, directing the attention of the end user to the location
	 * required. If no source is provided, the cursor starts from the position stated in
	 * config.defaultCursorStart
	 *
	 * @see config.clickDemoDelay
	 * @see config.clickDemoTime
	 * @see config.defaultCursorStart
	 * @see UI
	 *
	 * @param jQuery target Element for 'click'
	 * @param jQuery source (optional) Starting element of cursor
	 */
	wnd.ClickDemonstration = function( target, source ) {

		this.execute = function() {

			/** So we can access our resolver object from the callback below */
			var _resolve = this;

			/** Starting co-ordinates */
			var _origin = ( source )
				? findTargetCenter( source )
				: config.defaultCursorStart ;

			/** Ending co-ordinates */
			var _dest = findTargetCenter( target );

			UI.setCursorPosition( _origin.left, _origin.top );
			UI.enableMouseLayer();
			UI.animateCursorTo( _dest.left, _dest.top, function() {
				target.focus();
				UI.disableMouseLayer();
				_resolve.advance();
			});
		};
	};

	/**
	 * CHAIN ITEM: Wait for a specific result of user interaction
	 *
	 * The Result Evaluator is the key to allowing users to fill out
	 * the interface. Essentially, a function is provided to the class which is then
	 * bound to the document, catching events that bubble up. On the specified events,
	 * the function is evaluated and, if returning something considered truthy,
	 * the sequence is advanced.
	 *
	 * @see config.watcherEvents
	 *
	 * @param Function evaluator Callback to be executed on watch events
	 * @param String   events    (optional) Events to bind on
	 */
	wnd.ResultEvaluator = function( evaluator, events ) {

		this.execute = function() {

			/** So we can access our resolver later */
			var _resolve = this;
			events = events || config.watcherEvents ;

			/**
			 * Wrapper function for the evaluator
			 *
			 * The passed evaluator is wrapped in a function that is tasked
			 * with removing the event binding once the condition is met - this
			 * is essential, otherwise we'd end up with a whole load of watcher
			 * functions still active on non-existent elements
			 */
			var _evalWrapper = function( event ) {
				if( evaluator( event ) ) {
					$(document).off( events, _evalWrapper );
					_resolve.advance();
				}
			};

			$(document).on( events, _evalWrapper );
		};
	};

	/**
	 * CHAIN ITEM: Toast notifiers
	 *
	 * Not sure where the name 'toast' came from, but it's used in android for a similar
	 * effect, so we'll run with that. These are small notification boxes that sit in the
	 * top right of the UI (for desktop, anyway) that provide a prompt to the user.
	 *
	 * Regarding the 'closeCondition' param:
	 * This can accept a number of different formats, depending on the behaviour that you
	 * desire. You can pass in:
	 * An integer : this will become the number of milliseconds that the Toast box appears for
	 * False      : the toast must be closed manually
	 * Chain Step : The toast becomes linked to the completetion of the chain step
	 *
	 * @see config.toastFadeTime
	 * @see config.toastDefaultTime
	 * @see config.toastDelayTime
	 *
	 * @param string message        Message to display in notification
	 * @param mixed  closeCondition (optional) Condition for auto-closing
	 */
	wnd.Toast = function( message, closeCondition ) {

		this.execute = function() {

			/** Used for function latching */
			var _killed  = false;
			/** So we can access our resolver */
			var _resolve = this;

			/** HTML for the toast element */
			var html = '<div class="toast">'
				+ '<a class="close">Close</a>'
				+ '<p>' + message + '</p>'
				+ '</div>';

			var toast = $(html);
			toast.appendTo('#toastContainer').hide().fadeIn();

			/**
			 * Kill the toast notification
			 *
			 * This controls the animation sequence and subsequent detachment of
			 * the notification box. It can be called by a timeout, or if the user
			 * clicks the close button. Because of this, it's a latching function.
			 */
			var _kill = function() {

				// Latch - assigning the animations multiple times can cause issues
				if( _killed ) return;
				_killed = true;

				var fadeTime = parseInt( config.toastFadeTime / 2 );

				toast.fadeTo( fadeTime / 2, 0, function() {
					toast.animate({ height: 0, margin: 0 }, fadeTime, function() {
						toast.detach();
					});
				});
			};

			toast.find('a.close').click( _kill );

			// If our 'closeCondition is a linked item - i.e, a chain step
			if( typeof closeCondition == 'object' && typeof closeCondition.execute == 'function' ) {

				/** Resolver intercept. Closes the toast on Sequence advancement */
				var interceptor = {
					advance  : function() { _kill(); _resolve.advance();  },
					complete : function() { _kill(); _resolve.complete(); },
					abort    : function() { _kill(); _resolve.abort();    },
					repeat   : function() { _resolve.repeat(); }
				};

				return closeCondition.execute.apply( interceptor, [] );
			}

			// We only set a killtime if we haven't explicitly denied
			if( closeCondition !== false )
				setTimeout( _kill, closeCondition || config.toastDefaultTime );
			// We only want to advance if we're not dependent
			setTimeout( _resolve.advance, config.toastDelayTime );
		};
	};

	/**
	 * CHAIN ITEM: Delay
	 *
	 * Isssues a delay before advancing
	 *
	 * @param int time Milliseconds to wait before advancing
	 */
	wnd.Delay = function( time ) {

		this.execute = function() {
			setTimeout( this.advance, time );
		};

	};

})( jQuery, window );
