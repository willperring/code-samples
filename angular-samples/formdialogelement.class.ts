import { IFormDialogElementParams } from "../interfaces/iformdialogelementparams.interface";
import { EventEmitter } from "@angular/core";
import { Dictionary } from "../interfaces/dictionary.interface";

/**
 * Form Dialog Element Model
 *
 * Instances of these are used to build the FormDialog Box. It is a good idea to understand the workings
 * of DialogBox and FormDialogBox before reading into this class.
 *
 * FormDialogElement instances are NOT INSTANTIATED DIRECTLY.
 * They are ALWAYS created through FormDialogElement.factory()
 *
 * @see DialogBox
 * @see FormDialogBox
 */
export class FormDialogElement {

  /**
   * Instantiation options for each type of form element instance
   *
   * Moving left to right, the parameters are used as follows:
   *  type         : this is usually used to denote the 'type' attribute on an input html element, although
   *                 is often discarded when using a special tagType
   *  tagType      : used to determine which type of html element <input/>, <textarea/> etc to render the element.
   *  defaultValue : the default value the form element should return, so that validation rules don't need to type
   *                 check, for example calling someValue.length on null when expecting a string value will throw
   *                 an error.
   *  params       : An array of extra type-specific parameters - some types, such as radio groups or
   *                 selects use multiple predetermined options, text inputs can have placeholders, etc...
   *
   * @type {Dictionary<Dictionary<any>>}
   * @private
   */
  private static readonly _types = {
    'text'       : { type: 'text',     tagType: 'single',     defaultValue: '',   params: { placeholder: '' }},
    'email'      : { type: 'email',    tagType: 'single',     defaultValue: '',   params: { placeholder: '' }},
    'password'   : { type: 'password', tagType: 'single',     defaultValue: '',   params: {} },
    'select'     : { type: 'select',   tagType: 'select',     defaultValue: null, params: { options: [] }},
    'radio'      : { type: 'radio',    tagType: 'radio',      defaultValue: null, params: { options: [], blockOptions: false }},
    'checkgroup' : { type: 'checkbox', tagType: 'checkgroup', defaultValue: [],   params: { options: [], blockOptions: false }},
    'checkbox'   : { type: 'checkbox', tagType: 'single',     defaultValue: null, params: { caption: '' }},
    'tagfield'   : { type: 'tagfield', tagType: 'tagfield',   defaultValue: '',   params: {}}
  };

  /**
   * Default universal form element parameters
   *
   * These are parameters that are shared by all form Elements. Any type specific params (in the 'params' field above)
   * are added to these. From top to bottom:
   *  title     : The human-readable label to be shown next to the form element.
   *  helpText  : null, or a string to show a hoverable help flyout to guide the user as to the element's purpose
   *  errorText : The human-readable error message to show if the field is not valid
   *  required  : either a boolean, or a validation function - makes the field required
   *  disabled  : either a boolean, or a validation function - disabled the field
   *  validate  : either null, or a function to determine if the value is valid
   *  fullWidth : true to show the element across both columns (ie, don't show the label)
   *
   * @see FormDialogBox.isValid()
   * @type {IFormDialogElementParams}
   * @private
   */
  private static readonly _defaultParams: IFormDialogElementParams = {
    title     : 'No title',
    helpText  : null,
    errorText : 'This field is not valid',
    required  : false,
    disabled  : false,
    validate  : null,
    fullWidth : false
  };

  /**
   * PRIVATE CONSTRUCTOR: Elements should be
   * @param name Currently unused
   * @param type Currently unused
   */
  private constructor( name, type ) {
    // these params are set in the factory, they could be removed.
  }

  /**
   * Create a new FormDialogElement instance
   *
   * When creating an instance, the first parameter 'name' is the one that will be used to
   * key this element and identify it programatically - it will also be used as the key
   * when returning the form data from the FormDialogBox. As such, it must be unique within
   * the set of elements added to a form.
   *
   * The type parameter should refer to one of the type keys listed in FormDialogElement.types
   *
   * The options is a dictionary to override any of the default parameters, such as the title, or
   * any of the required/disabled/validate functions.
   *
   * @param {string}          name   The name of the variable to be used for this element
   * @param {string}          type   The type of the element to create
   * @param {Dictionary<any>} params Options for instantiation
   * @returns {FormDialogElement}
   */
  static factory( name: string, type: string, params={} ): FormDialogElement {
    if( !FormDialogElement._types.hasOwnProperty(type) )
      throw "FormDialogElement has no type " + type;

    let typeOptions = FormDialogElement._types[type];
    let fdElement   = new FormDialogElement( name, type );

    fdElement._name    = name;
    fdElement._type    = typeOptions.type;
    fdElement._tagType = typeOptions.tagType;
    fdElement._value   = typeOptions.defaultValue;
    fdElement._params  = Object.assign( {},
      FormDialogElement._defaultParams,
      typeOptions.params,
      params
    );

    if( type == 'checkgroup' )
      fdElement._init_checkGroup();

    if( typeof params['validate'] == 'function' ) {
      fdElement.validate( params['validate'] );
    }

    return fdElement;
  }

  /**
   * Initialise the default value for a checkGroup
   *
   * An array of checkboxes should return a dictionary of boolean values,
   * this function will build that as it requires some custom functionality.
   *
   * @private
   */
  private _init_checkGroup() {
    this._value = this._params.options.reduce( (result, current) => {
      result[ current.id ] = false;
      return result;
    }, {});
  }

  private _onUpdate : EventEmitter<any> = new EventEmitter<any>();

  public onUpdate( callback ) {
    return this._onUpdate.subscribe( callback );
  }

  private _name    : string;
  private _type    : string;
  private _tagType : string;
  private _params  : IFormDialogElementParams;

  get name()    { return this._name    }
  get type()    { return this._type    }
  get tagType() { return this._tagType }
  get params()  { return this._params  }

  private _value       : any      = null;
  private _valueFilter : Function = ( value ) => value ;
  private _debounceTimer;

  private _statusMessages: { message: string, type: string }[] = [];
  get statusMessages() { return this._statusMessages };

  get value() { return this._value; }
  set value( v: any ) {
    this._value = this._valueFilter( v );
    if( this._debounceTimer ) {
      clearTimeout( this._debounceTimer );
    }

    this._debounceTimer = setTimeout( () => {
      this._onUpdate.emit( this );
    }, 250);
  }

  public valueFilter( filter: Function ) {
    this._valueFilter = filter;
    return this;
  }

  private _validate : Function = ( value ) => {
    return true;
  };

  public validate( validateFunction: Function ) {
    this._validate = validateFunction;
  }

  isEmpty() {
    if( typeof this._value == 'number' && this._value == 0 )
      return false;
    return ( !this._value );
  }

  /**
   * Tests whether the element is considered required.
   *
   * All Elements can be assigned functions to test whether they are required. As a parameter
   * they receive ALL THE FORM DATA, rather than just themselves. This is so fields can
   * be conditional based on other answers.
   *
   * @param {Dictionary<any>} formData
   * @returns {boolean}
   */
  isRequired( formData: Dictionary<any> = null ): boolean {
    let required = this._params['required'];
    return ( required instanceof Function )
      ? required( formData )
      : required ;
  }

  /**
   * Tests whether this element is considered valid.
   *
   * All Elements can be assigned functions to test whether they are valid. As a parameter
   * they receive ALL THE FORM DATA, rather than just themselves. This is so fields can
   * be conditional based on other answers.
   *
   * @param {Dictionary<any>} formData Form Data Dictionary from the parent FormDialogBox
   * @returns {boolean}
   */
  isValid( formData: Dictionary<any> = null ): boolean {
    let validate = this._params.validate;
    return ( validate instanceof Function )
      ? validate( formData )
      : true;
  }

  /**
   * This is currently unused, but I've provided it in case you need a single method to test whether
   * it meets both VALIDATION and REQUIREMENT rules. This function will check both.
   *
   * @param {Dictionary<any>} formData
   * @returns {boolean}
   */
  isAcceptable( formData: Dictionary<any> = null ): boolean {
    return this.isValid( formData ) && !( this.isRequired(formData) && this.isEmpty() )
  }

  /**
   * Tests whether this element is considered disabled.
   *
   * All Elements can be assigned functions to test whether they are disabled. As a parameter
   * they receive ALL THE FORM DATA, rather than just themselves. This is so fields can
   * be conditional based on other answers.
   *
   * @param {Dictionary<any>} formData
   * @returns {boolean}
   */
  isDisabled( formData: Dictionary<any> = null ): boolean {
    let disabled = this.params.disabled;
    return ( disabled instanceof Function )
      ? disabled( formData )
      : disabled ;
  }

  public addStatusMessage( message: string, type: string ) {
    this._statusMessages.push({ message: message, type: type });
    console.warn('adding status message', message, type, this._statusMessages);
  }

  public clearStatusMessages() {
    this._statusMessages = [];
  }

  toString() {
    return this.name +': ' + this.type;
  }

  /**
   * @deprecated
   * @returns {boolean}
   */
  get valid(): boolean {
    return (
      ( !this._params['required'] ||  (this._value != null && this._value != ''))
      && !!this._validate( this._value )
    );
  }


}
