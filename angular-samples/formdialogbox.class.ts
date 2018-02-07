import { DialogBox } from "./dialogbox.class";
import { FormDialogElement } from "./formdialogelement.class";
import { ITransformations } from "../interfaces/itransformations.interface";
import { Subject } from "rxjs/Subject";
import { EventEmitter } from "@angular/core";
import { Dictionary } from "../interfaces/dictionary.interface";
import { IFormDialogError } from "../interfaces/iformdialogerror.interface";

/**
 * Form Dialog Box Model
 *
 * The FormDialogBox model extends the functionality of the DialogBox in order to be able to present
 * a number of input types, rather than just simple button choices. Form Dialogs are built through a series
 * of FormDialogElement models, each of which has its own ability to validate and adapt based on the collective
 * form input.
 *
 * @see DialogBox
 * @see FormDialogElement
 * @see FormDialogComponent
 * @see FormDialogElementComponent
 * @see DialogBoxService
 */
export class FormDialogBox extends DialogBox {

  /**
   * A class to apply to identify the correct DialogBox Componentx
   * @see DialogContainer (Template)
   */
  _componentClass      : string   = 'FormDialogBoxComponent';

  /** Override some inherited properties to more suitable default values */
  protected _buttons   : number[] = DialogBox.BOX_OKCANCEL;
  protected _modal     : boolean  = false;
  protected _stackable : boolean  = false;

  /** Elements that make up the Form */
  protected _elements : FormDialogElement[] = [];
  /** On validation, information regarding validation errors will be stored here */
  protected _errors   : IFormDialogError[]  = [];

  /** @see FormDialogBox.setDataTransformationsIn() */
  protected _transformationsIn  : ITransformations = {};
  /** @see FormDialogBox.setDataTransformationsOut() */
  protected _transformationsOut : ITransformations = {};
  /** @see DialogBox.onElementUpdated() */
  protected _elementUpdated     : EventEmitter<FormDialogElement> = new EventEmitter();

  get errors() { return this._errors || [] }

  /**
   * Validate function used to validate the FormData as a whole
   *
   * @see FormDialog.validate()
   * @param   {Dictionary<any>} FormData parameters
   * @returns {boolean}         True for valid, otherwise false
   * @private
   */
  protected _validate: Function = ( params: Dictionary<any> ) => {
    return true;
  };

  /**
   * Apply any incoming data transformations
   *
   * @see DialogBox.setDataTransformationsIn()
   * @param   {Dictionary<any>} Untouched FormData
   * @returns {Dictionary<any>} Transformed FormData
   * @private
   */
  protected _transformDataIn( data: Dictionary<any> ): Dictionary<any> {
    for( let key in data ) {
      data[key] = this._transformationsIn.hasOwnProperty(key)
        ? this._transformationsIn[key]( data[key] ) : data[key];
    }
    return data;
  }

  /**
   * Apply any outgoing data transformations
   *
   * @see DialogBox.setDataTransformationsIn()
   * @param   {Dictionary<any>} Untouched FormData
   * @returns {Dictionary<any>} Transformed FormData
   * @private
   */
  protected _transformDataOut( data: Dictionary<any> ): Dictionary<any> {
    for( let key in data ) {
      data[key] = this._transformationsOut.hasOwnProperty(key)
        ? this._transformationsOut[key]( data[key] ) : data[key];
    }
    return data;
  }

  /**
   * Add A Form Element to the Form
   *
   * Rather than just adding elements to the array manually, we need to sure that the emitters are bound,
   * otherwise the link between the elements and the form is severed.
   *
   * @see FormDialogBox.onElementUpdated()
   * @see FormDialogElement
   * @param {FormDialogElement} formElement
   */
  public addFormElement( formElement: FormDialogElement ) {
    this._elements.push( formElement );
    formElement.onUpdate( ( emitted ) => {
      this._elementUpdated.emit( emitted );
    });
  }

  public getFormElements() {
    return this._elements;
  }

  /**
   * Bind a callback to be executed whenever a form element is updated
   *
   * The callback should have a single parameter, and will receive the FormDialogElement model that was changed.
   *
   * @see FormDialogElement.onUpdate()
   * @param {Function} callback
   */
  public onElementUpdated( callback: Function ) {
    this._elementUpdated.subscribe( callback );
  }

  /**
   * Set a validation function for the form as a whole.
   *
   * When the form is validated, each FormDialogElement in term is evaluated first, and is given the ENTIRE
   * set of FormData to decide if the correct conditions are met - this means that a form value can be valid or
   * invalid depending on the value of another. VERY useful for building complex forms. As a final step, if all
   * form elements are valid, is an option final step.
   *
   * To use this step, use this function, with a callback as the parameter. The callback should accept a Dictionary
   * of Data from the Form, and return a truthy or falsy value.
   *
   * @param {Function} validateFunction
   * @returns {this}
   */
  public validate( validateFunction: Function ) {
    this._validate = validateFunction;
    return this;
  }

  /**
   * Get FormData that has been run through any necessary outgoing transformations
   * @see DialogBox.setDataTransformationsOut();
   * @returns {Dictionary<any>}
   */
  public getFormData(): Dictionary<any> {
    let data = this._elements.reduce( (result, element, i) => {
      result[ element.name ] = element.value;
      return result;
    }, {} );
    return this._transformDataOut( data );
  }

  /**
   * Set FormData after running through any necessary incoming transformations
   * @see DialogBox.setDataTransformationsOut();
   * @returns {Dictionary<any>}
   */
  public setFormData( newFormData ) {
    newFormData = this._transformDataIn( newFormData );
    for( let element of this._elements ) {
      if( newFormData.hasOwnProperty(element.name) ) {
        element.value = newFormData[element.name];
      }
    }
    return this;
  }

  /**
   * Set Data Transformations for incoming data
   *
   * Data Transformations can be used to modify the formData before it is applied to the FormElements,
   * for example changing integers to their string equivalents, or converting booleans to strings - anything, really.
   * The transformations parameter should be a Dictionary of Functions, each of which accepts a single parameter - the
   * pre-transformation value, and returns a transformed value. For example
   * {
   *    someFormElement  : booleanValue => (booleanValue) ? 'true' : 'false',
   *    otherFormElement : integerValue => integerValue.toString()
   * }
   *
   * @param {ITransformations} transformations Dictionary of Transformation functions
   * @returns {this} Allows method chaining
   */
  public setDataTransformationsIn( transformations: ITransformations ) {
    this._transformationsIn = transformations;
    return this;
  }

  /**
   * Set Data Transformations for outgoing data
   *
   * @see FormDialogBox.setDataTransformationsIn()
   * @param {ITransformations} transformations
   * @returns {this} Allows method chaining
   */
  public setDataTransformationsOut( transformations: ITransformations ) {
    this._transformationsOut = transformations;
    return this;
  }

  /**
   * Check to see if the Form Data meets all validation rules.
   *
   * This function will iterate through each FormDialogElement, testing separately for required/empty and valid,
   * based on each element's rules. If these all pass, the FormDialogBox's own validation function is used.
   *
   * The function itself returns a boolean value, for semantic code purposes, but full details of each error encountered
   * can be found externally though the FormDialogBox.errors getter
   *
   * @see FormDialogBox._errors
   * @see FormDialogElement.isRequired()
   * @see FormDialogElement.isEmpty()
   * @see FormDialogElement.isValid()
   * @returns {boolean}
   */
  public isValid(): boolean {
    let errors: IFormDialogError[] = [];

    let formData = this.getFormData();
    let valid    = true;

    for( let element of this._elements ) {
      if( element && element.isRequired() && element.isEmpty() ) {
        valid = false;
        errors.push({ element: element, error: "This field is required", type: 'required' });
      }
      if( element && !element.isValid(formData) ) {
        valid = false;
        errors.push({ element: element, error: element.params.errorText, type: 'invalid' });
      }
    }
    this._errors = errors;
    return ( valid && this._validate(formData) );
  }

  /**
   * Apply Status Messages to Form Dialog Elements based on an Error Result
   *
   * When validating the form, information regarding the error states is generated and stored in the IFormDialogError
   * interface format. These error messages, or others that are created in the same format, can be then applied using this
   * function. The decoupling of FormDialogBox.isValid() and FormDialogBox.showErrorMessages() is vital as any processing
   * required can take place between the two steps, to change the messages or ignore certain messages, etc...
   *
   * @param {IFormDialogError[]} errors Array of Error Dictionaries in the IFormDialogError format.
   */
  public showErrorMessages( errors: IFormDialogError[] ) {
    this.clearStatusMessages(false);
    for( let error of errors ) {
      error.element.addStatusMessage( error.error, error.type );
    }
    this.requestReposition();
  }

  /**
   * Clear Status Messages from FormDialogElements
   *
   * @see FormDialogBox.showErrorMessages()
   * @param {boolean} reposition Use false to prevent a reposition (for example, before adding new messages)
   */
  public clearStatusMessages( reposition: boolean = true ) {
    for( let element of this._elements ) {
      element.clearStatusMessages();
    }
    // A fancy "if reposition..."
    reposition && this.requestReposition();
  }

}
