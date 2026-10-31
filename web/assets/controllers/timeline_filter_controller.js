import { Controller } from '@hotwired/stimulus';

/*
 * Filters the timeline list (agent_stat/_timeline.html.twig) in the browser, as the app's: the
 * uploads with a milestone always, those with medals (data-medals) or a new level
 * (data-new-level) with their toggle, the events by kind, and everything by the days shown
 * (from and to, both included; empty: no limit), a travel on every day of it. The days also zoom
 * the chart: the page with from, to and chart=1 renders only #timeline-chart-frame, which replaces
 * the one on the page (agent_stat/_timeline_chart_frame.html.twig). A year's label shows with the
 * entries of its year only.
 *
 * <div data-controller="timeline-filter">
 *     <input type="checkbox" checked data-timeline-filter-target="medals" data-action="timeline-filter#apply">
 *     <input type="checkbox" checked data-timeline-filter-target="newLevels" data-action="timeline-filter#apply">
 *     <input type="checkbox" value="event" checked data-timeline-filter-target="kind" data-action="timeline-filter#apply">
 *     <input type="date" data-timeline-filter-target="from" data-action="timeline-filter#apply">
 *     <input type="date" data-timeline-filter-target="to" data-action="timeline-filter#apply">
 *     <li data-timeline-filter-target="year" data-year="2026">2026</li>
 *     <li data-timeline-filter-target="entry" data-kind="upload" data-from="2026-10-20" data-to="2026-10-20" data-medals>…</li>
 *     <li data-timeline-filter-target="empty" hidden>…</li>
 * </div>
 */
export default class extends Controller {
    static targets = ['kind', 'medals', 'newLevels', 'from', 'to', 'entry', 'year', 'empty'];

    /** The days the chart shows, as its query string: the page's own chart at first. */
    zoomed = '';

    apply() {
        const kinds = this.kindTargets.filter((kind) => kind.checked).map((kind) => kind.value);
        const medals = !this.hasMedalsTarget || this.medalsTarget.checked;
        const newLevels = !this.hasNewLevelsTarget || this.newLevelsTarget.checked;
        // Y-m-d compares as text
        const from = this.hasFromTarget ? this.fromTarget.value : '';
        const to = this.hasToTarget ? this.toTarget.value : '';
        let shown = 0;
        const years = new Set();

        for (const entry of this.entryTargets) {
            const data = entry.dataset;
            const listed = 'upload' === data.kind
                ? 'milestone' in data || (medals && 'medals' in data) || (newLevels && 'newLevel' in data)
                : kinds.includes(data.kind);
            const show = listed && ('' === from || data.to >= from) && ('' === to || data.from <= to);
            entry.hidden = !show;
            if (show) {
                shown++;
                // An entry is one day: its year
                years.add(data.from.slice(0, 4));
            }
        }

        for (const year of this.yearTargets) {
            year.hidden = !years.has(year.dataset.year);
        }

        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = shown > 0;
        }

        // The travel line goes with the travels
        this.element.querySelector('.timeline')?.classList.toggle('timeline-no-travels', !kinds.includes('travel'));

        this.zoom(from, to);
    }

    /** Loads the chart for the days, unless it shows them already; the newest request wins. */
    async zoom(from, to) {
        const query = new URLSearchParams({ ...(from && { from }), ...(to && { to }) }).toString();
        if (query === this.zoomed) {
            return;
        }

        this.zoomed = query;
        this.request?.abort();
        this.request = new AbortController();

        try {
            const response = await fetch(`${window.location.pathname}?${query}${query ? '&' : ''}chart=1`, { signal: this.request.signal });
            if (!response.ok) {
                return;
            }

            const frame = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById('timeline-chart-frame');
            if (frame) {
                document.getElementById('timeline-chart-frame')?.replaceWith(frame);
            }
        } catch (error) {
            if ('AbortError' !== error.name) {
                throw error;
            }
        }
    }
}
