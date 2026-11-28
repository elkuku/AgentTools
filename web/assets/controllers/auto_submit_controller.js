import { Controller } from '@hotwired/stimulus';

/*
 * Submits the form when one of its fields changes, as the events page's "Paid campaigns" box;
 * without JavaScript the form's button does it.
 */
export default class extends Controller {
    static targets = ['button'];

    connect() {
        this.buttonTargets.forEach((button) => button.classList.add('d-none'));
    }

    submit() {
        this.element.requestSubmit();
    }
}
