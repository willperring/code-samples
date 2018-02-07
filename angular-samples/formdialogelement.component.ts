import { Component, HostBinding, Input, OnInit } from '@angular/core';
import { FormDialogElement } from "../../../classes/formdialogelement.class";
import { FormDialogBoxComponent } from "../formdialogbox/formdialogbox.component";

@Component({
  selector: 'formdialogelement',
  templateUrl: './formdialogelement.component.html',
  styleUrls: ['./formdialogelement.component.scss']
})
export class FormDialogElementComponent implements OnInit {

  @HostBinding('class.valid')    classValid    : boolean = false;
  @HostBinding('class.invalid')  classInvalid  : boolean = false;
  @HostBinding('class.required') classRequired : boolean = false;
  @HostBinding('class.disabled') classDisabled : string  = null;

  @Input() formElement     : FormDialogElement;
  @Input() dialogComponent : FormDialogBoxComponent;

  constructor() { }

  private _statusNotes : { text: string, type: string }[] = [];

  public getInputType() {
    return this.formElement.tagType;
  }

  public onChange() {
    this.updateClassInformation();
  }

  public onCheckGroupChange( optionId, state ) {
    let optionArray         = this.formElement.value;
    optionArray[ optionId ] = state;
    this.formElement.value  = optionArray;
  }

  public updateClassInformation() {
    // This one is a special case, as we need the attribute to contain the word 'disabled' for some browsers.
    // It also needs to be truthy/falsy for the presence of the attribute.
    this.classDisabled = this.getDisabledState() ? 'disabled' : null ;
    // These are all booleans...
    this.classValid    = this.getValidState()  && !this.classDisabled;
    this.classInvalid  = !this.getValidState() && !this.classDisabled;
    this.classRequired = this.getRequiredState();
  }

  public addStatusNote( text: string, type: string = "" ) {
    this._statusNotes.push({ text: text, type: type });
    return this;
  }

  public clearStatusNotes() {
    this._statusNotes = [];
    return this;
  }

  protected getValidState(): boolean {
    let formData = this.dialogComponent.getFormData();
    return this.formElement.isValid( formData );
  }

  protected getRequiredState(): boolean {
    let formData = this.dialogComponent.getFormData();
    return this.formElement.isRequired( formData );
  }

  protected getDisabledState(): boolean {
    let formData = this.dialogComponent.getFormData();
    return this.formElement.isDisabled( formData );
  }

  ngOnInit() {
    if( this.formElement ) {
      this.formElement.onUpdate( (data) => {
        this.updateClassInformation();
      })
    }

    if( this.dialogComponent ) {
      this.dialogComponent.onElementUpdated( updatedElement => {
        if( updatedElement != this.formElement ) {
          this.updateClassInformation();
        }
      });
    }

    this.updateClassInformation();
  }

}
