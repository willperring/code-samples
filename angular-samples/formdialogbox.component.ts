import { Component, HostBinding, Input, OnInit } from '@angular/core';
import { DialogBox } from "../../../classes/dialogbox.class";
import { FormDialogBox } from "../../../classes/formdialogbox.class";
import { DialogComponent } from "../../../classes/dialogcomponent.abstract.class";
import { DomSanitizer } from "@angular/platform-browser";

@Component({
  selector: 'formdialogbox',
  templateUrl: './formdialogbox.component.html',
  styleUrls: ['./../dialogbox/dialogbox.component.scss']
})
export class FormDialogBoxComponent extends DialogComponent implements OnInit {

  @Input() dialogBox : FormDialogBox;

  @HostBinding('attr.class') cssClass = 'formDialogWrapper';
  constructor( private domSanitizer: DomSanitizer ) {
    super();
  }

  getFormElements() {
    return this.dialogBox.getFormElements();
  }

  getSanitizedMessage() {
    return this.domSanitizer.bypassSecurityTrustHtml( this.dialogBox.message );
  }

  onElementUpdated( callback: Function ) {
    this.dialogBox.onElementUpdated( callback );
  }

  getFormData() {
    return this.dialogBox.getFormData();
  }

  // This is used in the template to get a backreference
  getSelf() {
    return this;
  }

  ngOnInit() {
    // this is important - it's defined in the abstract, so to avoid it being accidentally
    // overwritten, I've included it here.
    super.ngOnInit();
  }

}
