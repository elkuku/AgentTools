import { Controller } from '@hotwired/stimulus';

/*
 * The site's styled tooltips, as the timeline chart's (chart_tooltip_controller.js): for an element
 * with data-tooltip (the heading) and data-tooltip-body, on hover and on the keyboard's focus;
 * above the element, below without room. On <body>; a click or scrolling hides it.
 *
 * <body data-controller="tooltip" data-action="pointerover->tooltip#show …">
 *     <img data-tooltip="Explorer" data-tooltip-body="Gold · 2,600" alt="…">
 *     <div data-tooltip-target="tooltip" hidden>…</div>
 * </body>
 */
export default class extends Controller {
    static targets = ['tooltip', 'heading', 'body'];

    show(event) {
        const element = event.target.closest?.('[data-tooltip]');
        if (!element || element === this.element || element === this.current) {
            return;
        }

        this.current = element;
        this.headingTarget.textContent = element.dataset.tooltip;
        this.bodyTarget.textContent = element.dataset.tooltipBody ?? '';
        this.bodyTarget.hidden = !element.dataset.tooltipBody;
        this.tooltipTarget.hidden = false;
        this.place(element);
    }

    hide(event) {
        // Still within the element (e.g. from a button to its image)
        if (this.current && event?.relatedTarget && this.current.contains(event.relatedTarget)) {
            return;
        }

        this.current = null;
        this.tooltipTarget.hidden = true;
    }

    place(element) {
        const box = element.getBoundingClientRect();
        const tooltip = this.tooltipTarget;
        const gap = 6;
        const margin = 8;
        const left = Math.max(margin, Math.min(box.left + box.width / 2 - tooltip.offsetWidth / 2, window.innerWidth - tooltip.offsetWidth - margin));
        let top = box.top - tooltip.offsetHeight - gap;
        if (top < margin) {
            top = box.bottom + gap;
        }

        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
    }
}
