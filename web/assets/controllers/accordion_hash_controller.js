import { Controller } from '@hotwired/stimulus';

/*
 * Opens the accordion section that holds the element the URL's #hash points to (e.g. a banner
 * linked from the timeline), and scrolls to it.
 */
export default class extends Controller {
    connect() {
        const id = decodeURIComponent(window.location.hash.slice(1));
        const target = id ? document.getElementById(id) : null;
        const section = target?.closest('.accordion-collapse');
        if (!target || !section || !this.element.contains(section)) {
            return;
        }

        section.classList.add('show');
        const button = this.element.querySelector(`[data-bs-target="#${section.id}"]`);
        button?.classList.remove('collapsed');
        button?.setAttribute('aria-expanded', 'true');
        target.scrollIntoView();
    }
}
