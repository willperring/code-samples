import { EventEmitter } from "@angular/core";
import { IPanelTheme } from "../interfaces/ipaneltheme.interface";
import { SafeHtml } from "@angular/platform-browser";
import { Dictionary } from "../interfaces/dictionary.interface";

/**
 * DialogBox Model
 *
 * This is the class that provides the core of all dialog boxes, including FormDialogBox models.
 * As such, it holds static variables regarding button states and combinations, to allow for
 * better readability in the code, but with strict adherence to a standard.
 *
 * @see FormDialogBox
 * @see DialogComponent.abstract
 * @see DialogBoxComponent
 * @see FormDialogBoxComponent
 * @see DialogContainerComponent
 */
export class DialogBox {

  /** Variables for BUTTONS - either assigning buttons, or catching the interaction result */
  static readonly BTN_OK     = 1;
  static readonly BTN_CANCEL = 2;
  static readonly BTN_YES    = 3;
  static readonly BTN_NO     = 4;

  /** Predefined Dialog Box Button combinations */
  static readonly BOX_OK       = [DialogBox.BTN_OK];
  static readonly BOX_OKCANCEL = [DialogBox.BTN_OK,  DialogBox.BTN_CANCEL];
  static readonly BOX_YESNO    = [DialogBox.BTN_YES, DialogBox.BTN_NO];

  /**
   * Theme Information
   *
   * Themes are accessed by a name - in this case, the key. Currently only one piece of information exists,
   * the panelClass - although setting up this was allows for future expansion, either in this class or derived classes
   *
   * @type {Dictionary<IPanelTheme>}
   */
  private static readonly themes: Dictionary<IPanelTheme> = {
    'default' : { panelClass: 'panel-default' },
    'success' : { panelClass: 'panel-success' },
    'warning' : { panelClass: 'panel-warning' },
    'danger'  : { panelClass: 'panel-danger'  },
  };

  /**
   * Construct A DilogBox Model
   *
   * @param {string}   message The message to display
   * @param {number[]} buttons An array of numbers, ideally using the predefined button types or combinations.
   * @param {string}   theme   Name of a theme from DialogBox.themes
   * @param {string}   title   The title of the DialogBox
   */
  constructor( message: string = '', buttons: number[] = DialogBox.BOX_OK, theme: string = null, title: string = null ) {
    this._emitter      = new EventEmitter<[DialogBox,number]>();
    this._repositioner = new EventEmitter<boolean>();
    this.message = message;
    this.buttons = buttons;
    if( theme )
      this.setTheme( theme );
    if( title )
      this.title = title;
  }

  /**
   * A class to apply to identify the correct DialogBox Componentx
   * @see DialogContainer (Template)
   */
  _componentClass      : string      = 'DialogBoxComponent';
  protected _title     : string      = 'Alert!';
  protected _message   : string      = '';
  protected _buttons   : number[]    = DialogBox.BOX_OK;
  protected _theme     : IPanelTheme = DialogBox.themes['default'];
  protected _classes   : string[]    = [];

  /** True prevents OTHER dialog boxes being overlaid on top of THIS one */
  protected _modal     : boolean     = true;
  /** True prevents THIS dialog box being overlaid on top of OTHERS */
  protected _stackable : boolean     = true;

  /**
   * Emits Human Interaction Events
   *
   * This emitter is part of the package returned when queueing a DialogBox into the service for display.
   * It emits an array containing two elements, the instance of this (or derived) class and the identifier
   * of the button interacted with.
   *
   * @see DialogBoxService
   */
  protected _emitter : EventEmitter<[DialogBox,number]>;

  /**
   * Emits a request to be repositioned.
   *
   * The DialogComponent Abstract subscribes to this emitter and on request recalculates
   * the height and width of the component, to recenter it within its container. To manually
   * trigger a reposition, you call DialogBox.requestReposition()
   *
   * @see DialogComponent (Abstract)
   * @see DialogBox.requestReposition()
   */
  protected _repositioner : EventEmitter<boolean>;

  /**
   * Used in repositioning
   * @See DialogComponent (Abstract)
   * @type {string}
   */
  height : string = null;
  width  : string = null;

  get title() { return this._title }
  set title( v: string ) {
    this._title = v;
  }

  get buttons() { return this._buttons }
  set buttons( v: number[] ) {
    this._buttons = v;
  }

  get modal() { return this._modal }
  set modal( v: boolean ) {
    this._modal = v;
  }

  get stackable() { return this._stackable }
  set stackable( v: boolean ) {
    this._stackable = v;
  }

  get message() { return this._message }
  set message( v: string ) {
    this._message = v;
  }

  get theme() { return this._theme }

  /**
   * Sets a theme by Key
   * @see DialogBox.themes
   * @param {string} v Theme key
   */
  public setTheme( v: string ) {
    if( DialogBox.themes.hasOwnProperty(v) ) {
      this._theme = DialogBox.themes[v];
    }
  }

  get emitter() { return this._emitter }

  /**
   * Emits a Button Interaction
   * @see DialogBox.BTN_OK, etc.
   * @param id Button ID
   */
  public emitResult( id ) {
    this._emitter.emit( [this, id] );
  }

  /**
   * Add A custom CSS class to the DialogBox, which can be used to extra visual customisation.
   * @param {string} className CSS class to add.
   */
  public addClass( className: string ) {
    this._classes.push( className );
  }

  /**
   * Attaches as DialogComponent to the reposition emitter
   * @see DialogComponent (Abstract)
   * @param callback
   * @returns {any}
   */
  public subscribeToRepositioner( callback ) {
    return this._repositioner.subscribe( callback );
  }

  /** Recenters the DialogComponent on the screen */
  public requestReposition() {
    this._repositioner.emit(true);
  }

  public getClasses() {
    return this._classes;
  }

  public getStyles() {
    let styles = {};

    if( this.height != null )
      styles['height'] = this.height;
    if( this.width != null )
      styles['width']  = this.width;

    return styles;
  }




}
