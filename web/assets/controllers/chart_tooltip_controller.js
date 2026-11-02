import { Controller } from '@hotwired/stimulus';

/*
 * The timeline chart's tooltips (agent_stat/_timeline_chart.html.twig): a card with the mark's
 * data-tip-heading, data-tip-body and data-tip-extra and a key in its color (data-tip-kind: ap,
 * event or travel), above an upload's dot, at the pointer over an event or a travel, and for the
 * mark with the keyboard's focus.
 *
 * <figure data-controller="chart-tooltip" data-action="pointerover->chart-tooltip#show …">
 *     <g tabindex="0" data-tip-kind="ap" data-tip-heading="…" data-tip-body="…" data-tip-extra="…">…</g>
 *     <div data-chart-tooltip-target="tooltip" hidden>…</div>
 * </figure>
 */
export default class extends Controller {
    static targets = ['tooltip', 'key', 'heading', 'body', 'extra'];

    show(event) {
        const mark = event.target.closest?.('[data-tip-heading]');
        if (!mark || mark === this.mark) {
            return;
        }

        this.mark = mark;
        const { tipKind, tipHeading, tipBody, tipExtra } = mark.dataset;
        this.keyTarget.className = `chart-tooltip-key chart-tooltip-key-${tipKind}`;
        this.headingTarget.textContent = tipHeading;
        this.bodyTarget.textContent = tipBody ?? '';
        this.extraTarget.textContent = tipExtra ?? '';
        this.extraTarget.hidden = !tipExtra;
        this.tooltipTarget.hidden = false;
        this.place(event);
    }

    move(event) {
        if (this.mark && 'ap' !== this.mark.dataset.tipKind) {
            this.place(event);
        }
    }

    hide(event) {
        // Still within the mark (e.g. from its dot to its target circle)
        if (this.mark && event.relatedTarget && this.mark.contains(event.relatedTarget)) {
            return;
        }

        this.mark = null;
        this.tooltipTarget.hidden = true;
    }

    /** Above the dot of an upload, else at the pointer (or the mark's middle); inside the chart. */
    place(event) {
        const figure = this.element.getBoundingClientRect();
        const dot = this.mark.querySelector('.chart-dot');
        let x;
        let y;
        if (dot || undefined === event.clientX) {
            const box = (dot ?? this.mark).getBoundingClientRect();
            x = box.left + box.width / 2;
            y = dot ? box.top : box.top + Math.min(box.height / 2, 40);
        } else {
            x = event.clientX;
            y = event.clientY;
        }

        const tooltip = this.tooltipTarget;
        const gap = 10;
        let left = x - figure.left - tooltip.offsetWidth / 2;
        left = Math.max(0, Math.min(left, figure.width - tooltip.offsetWidth));
        let top = y - figure.top - tooltip.offsetHeight - gap;
        // No room above: below
        if (top < 0) {
            top = y - figure.top + gap + (dot ? dot.getBoundingClientRect().height : 0);
        }

        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
    }
}
