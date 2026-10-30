import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

/*
 * A Bootstrap modal that opens when the page loads with open (e.g. a form in it with errors).
 *
 * <div class="modal fade" data-controller="modal" data-modal-open-value="true">…</div>
 */
export default class extends Controller {
    static values = { open: Boolean };

    connect() {
        if (this.openValue) {
            Modal.getOrCreateInstance(this.element).show();
        }
    }
}
