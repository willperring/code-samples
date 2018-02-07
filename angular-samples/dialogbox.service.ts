import { EventEmitter, Injectable } from '@angular/core';
import { DialogBox } from "../classes/dialogbox.class";
import { environment } from "../../environments/environment";
import { SaveResultNotifier } from "../interfaces/saveresultnotifier.interface";

@Injectable()
export class DialogBoxService implements SaveResultNotifier {

  /**
   * @typedef {Object}   DialogQueueResult
   * @param   {Function} subscribe Function to allow subscriptions to DialogBox result.
   */

  /** Queue to hold pending DialogBox and FormDialogBox classes */
  private _queue          : DialogBox[] = [];
  /** Emits when new Dialogs are added to the queue (queued or unshifted) */
  private _queueEmitter   : EventEmitter<DialogBox>;
  /** Emits when Dialogs are removed from the queue */
  private _dequeueEmitter : EventEmitter<DialogBox>;

  constructor() {
    this._queueEmitter   = new EventEmitter();
    this._dequeueEmitter = new EventEmitter();
  }

  /**
   * Bind a callback to be executed when a DialogBox is queued.
   *
   * @param   callback
   * @returns {DialogBoxService}
   */
  onQueue( callback ) {
    this._queueEmitter.subscribe( callback );
    return this;
  }

  /**
   * Bind a callback to be executed when a DialogBox is removed from the queue.
   *
   * @param   callback
   * @returns {DialogBoxService}
   */
  onDequeue( callback ) {
    this._dequeueEmitter.subscribe( callback );
    return this;
  }

  /**
   * Add a DialogBox to the END of the queue.
   *
   * @param   {DialogBox} dialog
   * @returns {DialogQueueResult} Subscription Object
   */
  queueDialog( dialog: DialogBox ) {
    this._queue.push( dialog );
    console.warn('db queue', dialog, this._queue);
    this._queueEmitter.emit( dialog );
    return this._bindNotifier( dialog );
  }

  /**
   * Add a DialogBox to the START of a queue.
   *
   * @param   {DialogBox} dialog
   * @returns {DialogQueueResult} Subscription Object
   */
  unshiftDialog( dialog: DialogBox ) {
    this._queue.unshift( dialog );
    console.warn('db unshift', dialog, this._queue);
    this._queueEmitter.emit( dialog );
    return this._bindNotifier( dialog );
  }

  /**
   * Check whether we have a DialogBox queued that we can activate.
   *
   * This is called by a DialogContainerComponent to check if we can pull a new dialog box from
   * the service. If the container already has a DB up in place, the next DB needs to be
   * stackable. So, though the logic expression below looks complicated we can explain it as
   * such, from left to right:
   * 1. Check the queue length. If we have no queue, this fails and the AND cannot be met, returning.
   * 2. If we have a queue, next examine the ensureStackable param. If this is FALSE (note the inversion),
   *    then the OR condition is satisifed and will return. In the event that the param is TRUE...
   * 3. Check if the requirement to be stackable is the same as the stackable param. Remember, if we had no
   *    queue, or the stackable check was not required, we would have failed by now. Simply checking the stackable
   *    property of the DB is not sufficient, as that would not allow a uniform result if the check parameter
   *    isn't activated. The entire right-hand of the AND expression needs to only return false on the condition that
   *    the parameter is activated AND the property is false.
   *
   * @param   {boolean} ensureStackable Ensure that we check that the next DB is stackable.
   * @returns {boolean}
   */
  hasNextDialog( ensureStackable: boolean ) {
    // whoo boy, that's some mind-inverting logic. See explanation above.
    console.warn('checking next dialog', this._queue[0], this._queue);
    return ( this._queue.length > 0 && ( !ensureStackable || ( !!ensureStackable == !!this._queue[0].stackable )));
  }

  /**
   * Return the next DialogBox in the queue
   *
   * Unlike its far more complex sibling above, this function is pretty simple... ;)
   *
   * @returns {DialogBox}
   */
  getNextDialog() {
    // this will shift it off the stack, so is considered destructive to the queue.
    console.log('about to shift', this._queue);
    return this._queue.shift();
  }

  /**
   * Bind a DialogBox's emitter with a service notifier
   *
   * The primary emitter-subscriber relationship on a DialogBox is left open,
   * the emitter is returned by the queueing functions so that the point of creation
   * can determine how best to handle DB interactions. Here we generate a 'middle step',
   * that allows us to 'intercept' the instruction to close the dialog box and send that
   * instruction to the components that are subscribed to this service.
   *
   * @param   {DialogBox}  dialogBox
   * @returns {DialogQueueResult} Subscription Object
   * @private
   */
  private _bindNotifier( dialogBox: DialogBox ) {

    // Function to trigger closing of the dialog, ultimately
    // passed to the subscription handler exposed by queuing
    // A dialog box.
    let close = () => {
      this._dequeueEmitter.emit( dialogBox );
    };

    let emitter    = new EventEmitter<any>();
    let subscribed = false;

    dialogBox.emitter.subscribe( ([dialogBox, buttonId]) => {
      if( !subscribed ) {
        if( !environment.production )
          console.warn('Someone didn\'t set up a handle function for their dialog?');
        close();
      }
      emitter.emit( [close, buttonId, dialogBox] );
    });

    return {
      subscribe: function( callback ) {
        subscribed = true;
        return emitter.subscribe( callback );
      }
    };

  }

  /**
   * Queue an appropriate dialog based on the number of updated and failed segments
   *
   * @see EditStateService.completeEdits()
   *
   * @param {number} updatedSegmentCount
   * @param {number} failedSegmentCount
   */
  notifySaveResult( updatedSegmentCount: number, failedSegmentCount: number ): void {
    let updateStates : string[] = [];
    let dialogTheme  : string   = 'success';
    if( updatedSegmentCount > 0 ) {
      let count =   updatedSegmentCount.toString();
      let noun  = ( updatedSegmentCount == 1 ) ? ' segment was' : ' segments were';
      updateStates.push( count + noun + ' successfuly saved' );
    }

    if( failedSegmentCount > 0 ) {
      let count =   failedSegmentCount.toString();
      let noun  = ( failedSegmentCount == 1 ) ? ' segment ' : ' segments ';
      updateStates.push( count + noun + ' could not be updated' );
      dialogTheme = 'warning';
    }

    if( !updateStates.length ) {
      updateStates = ['Server did not respond as expected. Please refresh and verify all changes'];
      dialogTheme  = 'danger';
    }

    let db = new DialogBox( updateStates.join(', and '), DialogBox.BOX_OK, dialogTheme );
    this.queueDialog( db );
  }

}

