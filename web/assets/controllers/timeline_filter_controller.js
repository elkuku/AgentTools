import { Controller } from '@hotwired/stimulus';

/*
 * Filters the timeline list (agent_stat/_timeline.html.twig) by kind and year, in the browser;
 * with "medals" only the uploads with medal upgrades (data-medals).
 *
 * <div data-controller="timeline-filter">
 *     <input type="checkbox" value="upload" checked data-timeline-filter-target="kind" data-action="timeline-filter#apply">
 *     <input type="checkbox" data-timeline-filter-target="medals" data-action="timeline-filter#apply">
 *     <select data-timeline-filter-target="year" data-action="timeline-filter#apply"><option value="">All</option>…</select>
 *     <li data-timeline-filter-target="entry" data-kind="upload" data-years="2025 2026">…</li>
 *     <li data-timeline-filter-target="empty" hidden>…</li>
 * </div>
 */
export default class extends Controller {
    static targets = ['kind', 'medals', 'year', 'entry', 'empty'];

    apply() {
        const kinds = this.kindTargets.filter((kind) => kind.checked).map((kind) => kind.value);
        const year = this.hasYearTarget ? this.yearTarget.value : '';
        const medalsOnly = this.hasMedalsTarget && this.medalsTarget.checked;
        let shown = 0;

        for (const entry of this.entryTargets) {
            const show = kinds.includes(entry.dataset.kind)
                && ('' === year || entry.dataset.years.split(' ').includes(year))
                && (!medalsOnly || 'upload' !== entry.dataset.kind || 'medals' in entry.dataset);
            entry.hidden = !show;
            shown += show ? 1 : 0;
        }

        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = shown > 0;
        }
    }
}
