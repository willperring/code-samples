import { Component, HostBinding, OnInit } from '@angular/core';
import { trigger, state, style, animate, transition, group } from '@angular/animations';
import { DialogComponent } from "../../../classes/dialogcomponent.abstract.class";
import { DomSanitizer } from "@angular/platform-browser";

@Component({
  selector: 'dialogbox',
  templateUrl: './dialogbox.component.html',
  styleUrls: ['./dialogbox.component.scss'],
  animations: [
    trigger('dialogFadeInOut', [
      // transition(':enter', [
      //   style({ transform: 'scale(0.8)', opacity: 0 }),
      //   animate('200ms', style({ transform: 'scale(1)', opacity: 1 }))
      // ]),
      // transition(':leave', [
      //   style({ transform: 'scale(1)', opacity: 1 }),
      //   animate('200ms', style({ transform: 'scale(0.8)', opacity: 0 }))
      // ])
    ])
  ]
})
export class DialogBoxComponent extends DialogComponent implements OnInit {

  @HostBinding('attr.class') cssClass = 'dialogWrapper';

  constructor( private domSanitizer: DomSanitizer ) {
    super();
    console.warn('DialogBoxComponent check const', this.dialogBox);
  }

  getSanitizedMessage() {
    return this.domSanitizer.bypassSecurityTrustHtml( this.dialogBox.message );
  }

  ngOnInit() {
    // this is important - it's defined in the abstract, so to avoid it being accidentally
    // overwritten, I've included it here.
    super.ngOnInit();
  }


}
