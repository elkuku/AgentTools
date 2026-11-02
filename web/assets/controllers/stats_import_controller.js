import { Controller } from '@hotwired/stimulus';

/*
 * Imports a CSV file of stats in parts with a progress bar (agent_stat/import.html.twig): the form
 * goes to startUrlValue (ImportStart: the file's rows and the next part's URL), then each part is
 * imported with its own request (ImportNext) until all are done, and the page goes to the result
 * on the user's stats. A JSON file (custom medals) is sent as the form is.
 */
export default class extends Controller {
    static targets = ['form', 'submit', 'progress', 'label', 'count', 'bar', 'error'];

    static values = { startUrl: String, progress: String, failed: String };

    async submit(event) {
        const file = this.formTarget.querySelector('input[type=file]')?.files[0];
        if (!file || file.name.toLowerCase().endsWith('.json')) {
            return;
        }

        event.preventDefault();
        this.submitTarget.disabled = true;
        this.errorTarget.hidden = true;
        this.progressTarget.hidden = false;
        this.show(0, 0);

        try {
            const start = await this.post(this.startUrlValue, new FormData(this.formTarget));
            if (start.errors) {
                this.fail(start.errors.join(' '));

                return;
            }

            this.labelTarget.textContent = this.progressValue;
            let next = { done: 0, rows: start.rows, finished: false };
            this.show(0, start.rows);
            while (!next.finished) {
                next = await this.post(start.next);
                this.show(next.done, next.rows);
            }

            window.location.assign(next.redirect);
        } catch (error) {
            this.fail(this.failedValue.replace('%error%', error.message));
        }
    }

    /** The JSON answer; one with errors for 422 (the form), else an error for a failed request. */
    async post(url, body = undefined) {
        const response = await fetch(url, { method: 'POST', body, headers: { Accept: 'application/json' } });
        if (response.ok || 422 === response.status) {
            return response.json();
        }

        throw new Error(`${response.status} ${response.statusText}`);
    }

    show(done, rows) {
        const percent = rows > 0 ? Math.round(done / rows * 100) : 0;
        const format = new Intl.NumberFormat(document.documentElement.lang || undefined);
        this.countTarget.textContent = rows > 0 ? `${format.format(done)} / ${format.format(rows)}` : '';
        this.barTarget.setAttribute('aria-valuenow', String(percent));
        this.barTarget.firstElementChild.style.width = `${percent}%`;
    }

    /** What went wrong; the stats imported before stay. */
    fail(message) {
        this.progressTarget.hidden = true;
        this.submitTarget.disabled = false;
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = false;
    }
}
