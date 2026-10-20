import { Controller } from '@hotwired/stimulus';

/*
 * Copies the source target's value to the clipboard.
 *
 * <div data-controller="clipboard">
 *     <input data-clipboard-target="source" value="…" readonly>
 *     <button data-action="clipboard#copy" data-clipboard-target="button">Copy</button>
 * </div>
 */
export default class extends Controller {
    static targets = ['source', 'button'];

    async copy() {
        await navigator.clipboard.writeText(this.sourceTarget.value);

        if (this.hasButtonTarget) {
            const label = this.buttonTarget.textContent;
            this.buttonTarget.textContent = 'Copied!';
            setTimeout(() => { this.buttonTarget.textContent = label; }, 1500);
        }
    }
}
