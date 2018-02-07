import { Component, ElementRef, HostBinding, OnChanges, OnInit, SimpleChanges, ViewChild } from '@angular/core';
import { trigger, state, style, animate, transition, group } from '@angular/animations';
import { DialogBoxService } from "../../../services/dialogbox.service";
import { DialogBox } from "../../../classes/dialogbox.class";
import { DialogBoxComponent } from "app/components/generic/dialogbox/dialogbox.component";
import { AppComponent } from "../../../app.component";

@Component({
  selector: 'dialogcontainer',
  templateUrl: './dialogcontainer.component.html',
  styleUrls: ['./dialogcontainer.component.scss'],
  animations: [
    trigger('containerFadeInOut', [
      state('visible', style({ opacity: 1 })),
      transition(':enter', [ animate('0s')  ]),
      transition(':leave', [ animate('0s 550ms', style({ opacity: 0 })) ])
    ]),
    trigger('overlayFadeInOut', [
      state('visible', style( { opacity: 1 })),
      transition(':enter', [
        style({ opacity: 0 }),
        animate('3s ease-in-out')
      ]),
      transition(':leave', [
        animate('0.5s ease-in', style({ opacity: 0 }))
      ])
    ])
  ]
})
export class DialogContainerComponent implements OnInit, OnChanges {

  @HostBinding('attr.class') cssClass = 'container';

  constructor( private dialogService: DialogBoxService ) {
    // bind to service emitter for notifications
    // on update, check if we can pop onto the stack
    this.dialogService.onQueue(
      update => this._onQueue(update)
    ).onDequeue(
      update => this._onDequeue(update)
    );
  }

  private _active    : boolean     = false;
  private _overlayUp : boolean     = false;
  private _dialogs   : DialogBox[] = [];

  overlayVisible() {
    return this._overlayUp;
  }

  getDialogs() {
    return this._dialogs;
  }

  /**
   * Determine if we can add a new DialogBox on top of the current stack.
   *
   * This is considered to be true if we either have no current boxes - i.e, _active is false,
   * Or if our current top Dialog is not modal.
   *
   * @param {DialogBox} dialogBox
   * @returns {boolean}
   * @private
   */
  private _canStack( dialogBox: DialogBox ): boolean  {
    let lastDialog = this._topVisibleDialog();
    return !this._active || ( lastDialog && !lastDialog.modal );
  }

  /**
   * Returns the uppermost (ie, currently visible) DialogBox
   *
   * @returns {DialogBox}
   * @private
   */
  private _topVisibleDialog() {
    return this._dialogs[ this._dialogs.length - 1 ] || null;
  }

  /**
   * Function called when notified of a Queue/Unshift by the DialogBoxService.
   *
   * @param {DialogBox} dialogBox
   * @private
   */
  private _onQueue( dialogBox: DialogBox ) {
    if( this._canStack( dialogBox ) ) {
      this._setUpDialog( this.dialogService.getNextDialog() );
    }
  }

  /**
   * Function called when notified of a Dequeue by the DialogBoxService
   *
   * @param {DialogBox} dequeue
   * @private
   */
  private _onDequeue( dequeue: DialogBox ) {
    this._tearDownDialog( dequeue );
  }

  private _setUpDialog( dialog: DialogBox ) {
    if( !dialog ) {
      console.warn('Pushing null dialog', dialog);
      return false;
    }
    this._active    = true;
    this._overlayUp = true;
    this._dialogs.push( dialog );
  }

  private _tearDownDialog( dialog: DialogBox ) {

    this._dialogs = this._dialogs.filter( dequeue => dialog != dequeue );
    let topDialog = this._topVisibleDialog();
    if( topDialog && topDialog.modal )
      return;

    if( this.dialogService.hasNextDialog( !!topDialog ) ) {
      let next = this.dialogService.getNextDialog();
      return this._setUpDialog( next );
    }

    if( this._dialogs.length <= 0 ) {
      //setTimeout( () => {
        this._overlayUp = false;
        this._active    = false;
      //}, 1 );
    }
  }

  ngOnInit() {
  }

  ngOnChanges( changes: SimpleChanges ) {
    console.warn('container change notify', this._dialogs);
  }

}

