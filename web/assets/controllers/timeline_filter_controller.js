import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';
import { German } from 'flatpickr/dist/l10n/de.js';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/flatpickr.min.css';

const LOCALES = { de: German, es: Spanish };

/** More uploads on a day: their times in a dropdown. */
const MAX_TIME_BUTTONS = 4;

/*
 * The timeline's date range and filters (agent_stat/_timeline.html.twig), as the app's. The range is
 * picked in a calendar (flatpickr) where only the days with an upload can be clicked (daysValue:
 * Y-m-d → [{id, time}], oldest first); it zooms the chart (the page with from, to and chart=1
 * renders only #timeline-chart-frame, which replaces the one on the page) and filters the list or,
 * on the own timeline (compareUrlValue), picks the two uploads to compare: the first upload of
 * the first day and the last of the last day, all dates from the first upload to the latest; on a
 * day with several uploads its times are buttons (a dropdown for many). The comparison (the page with compare=1) shows
 * instead of the list.
 *
 * The range, the view and the chosen times are in the URL (from, to, view=compare, compareFrom,
 * compareTo; the page renders the chart for from and to), so a reload or a shared link shows the same.
 *
 * The list: the uploads with a milestone always, those with medals (data-medals) or a new level
 * (data-new-level) with their toggle, the events by kind, and everything by the days (both
 * included), a travel on every day of it. A year's label shows with the entries of its year only.
 */
export default class extends Controller {
    static targets = [
        'kind', 'medals', 'newLevels', 'entry', 'year', 'empty', 'list',
        'rangeButton', 'rangeLabel', 'clear', 'calendar',
        'fromTimes', 'fromTimeButtons', 'toTimes', 'toTimeButtons', 'compare', 'compareContent',
    ];

    static values = { days: Object, locale: String, compareUrl: String };

    /** The days (Y-m-d, both included; '' for no limit), the chosen times (upload ids) and what shows. */
    from = '';

    to = '';

    fromId = null;

    toId = null;

    comparing = false;

    /** The days the chart shows, as its query string: the page's own chart at first. */
    zoomed = '';

    connect() {
        this.dayList = Object.keys(this.daysValue).sort();
        this.allDates = this.rangeLabelTarget.textContent.trim();
        this.dateFormat = new Intl.DateTimeFormat(this.localeValue, { dateStyle: 'medium' });
        this.timeFormat = new Intl.DateTimeFormat(this.localeValue, { timeStyle: 'short' });
        this.timeWithSecondsFormat = new Intl.DateTimeFormat(this.localeValue, { timeStyle: 'medium' });

        const hasUploads = this.dayList.length > 0;
        this.picker = flatpickr(this.calendarTarget, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            locale: LOCALES[this.localeValue] ?? 'default',
            // Not flatpickr's enable: a range could not span the days without an upload
            ...(this.dayList.length > 0 && { minDate: this.dayList[0], maxDate: this.dayList.at(-1) }),
            positionElement: this.rangeButtonTarget,
            clickOpens: false,
            disableMobile: true,
            // Only the days with an upload can be clicked (without uploads, only events: every day)
            onDayCreate: (dates, string, instance, day) => {
                const uploads = this.daysValue[instance.formatDate(day.dateObj, 'Y-m-d')] ?? [];
                const upload = uploads.length > 0;
                day.classList.add(upload ? 'timeline-upload-day' : 'timeline-no-upload-day');
                // Several: the times to pick when comparing
                day.classList.toggle('timeline-uploads-day', uploads.length > 1);
                if (!upload && hasUploads) {
                    day.setAttribute('aria-disabled', 'true');
                }
            },
            // Nothing picked yet: the latest upload's month
            onOpen: (dates, string, instance) => {
                if (0 === dates.length && this.dayList.length > 0) {
                    instance.jumpToDate(this.dayList.at(-1));
                }
            },
            onClose: (dates, string, instance) => this.picked(dates.map((date) => instance.formatDate(date, 'Y-m-d'))),
        });

        this.restore();
    }

    /** The range, the view and the times from the URL. */
    restore() {
        const params = new URLSearchParams(window.location.search);
        const [from, to] = this.range(params.get('from') ?? '', params.get('to') ?? params.get('from') ?? '');
        if (from) {
            this.from = from;
            this.to = to;
            this.picker.setDate([from, to], false);
            // The page's chart shows them already
            this.zoomed = new URLSearchParams({ from: params.get('from'), ...(params.get('to') && { to: params.get('to') }) }).toString();
        }
        this.fromId = Number(params.get('compareFrom')) || null;
        this.toId = Number(params.get('compareTo')) || null;
        this.unswap();
        if (this.hasCompareTarget && 'compare' === params.get('view')) {
            this.comparing = true;
            this.element.querySelector('#timeline-view-compare').checked = true;
        }

        if (from || this.comparing) {
            this.apply();
        }
    }

    /** The range in the URL, without changing the browser's history. */
    remember() {
        const params = new URLSearchParams(window.location.search);
        for (const key of ['from', 'to', 'view', 'compareFrom', 'compareTo']) {
            params.delete(key);
        }
        if (this.from) {
            params.set('from', this.from);
            params.set('to', this.to);
        }
        if (this.comparing) {
            params.set('view', 'compare');
            if (this.fromId) {
                params.set('compareFrom', this.fromId);
            }
            if (this.toId) {
                params.set('compareTo', this.toId);
            }
        }

        const query = params.toString();
        window.history.replaceState(window.history.state, '', `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`);
    }

    /**
     * The days of a range as upload days (from the keyboard or the URL a day without one): the
     * nearest upload days inside it; ['', ''] for none.
     */
    range(first, last) {
        if (!first || this.dayList.length === 0) {
            return [first, last || first];
        }

        const from = this.dayList.find((day) => day >= first);
        const to = this.dayList.findLast((day) => day <= (last || first));

        return from && to && from <= to ? [from, to] : ['', ''];
    }

    disconnect() {
        this.picker?.destroy();
    }

    openCalendar() {
        this.picker.open();
    }

    /** One day or two picked: the range; nothing: unchanged. */
    picked(days) {
        if (0 === days.length) {
            return;
        }

        const [from, to] = this.range(days[0], days[1] ?? days[0]);
        if (!from) {
            return;
        }
        if (from === this.from && to === this.to) {
            return;
        }

        this.from = from;
        this.to = to;
        this.fromId = null;
        this.toId = null;
        this.apply();
    }

    clear() {
        this.picker.clear();
        this.from = '';
        this.to = '';
        this.fromId = null;
        this.toId = null;
        this.apply();
    }

    /** The list or the comparison (radio buttons with the value list or compare). */
    view(event) {
        this.comparing = 'compare' === event.target.value;
        this.apply();
    }

    /** A time button or the times' dropdown: that upload at the start or the end. */
    pickTime(event) {
        const control = event.currentTarget;
        this[`${control.dataset.end}Id`] = Number(control.dataset.id ?? control.value);
        this.unswap();

        this.renderTimes();
        this.loadComparison();
        this.remember();
    }

    apply() {
        this.rangeLabelTarget.textContent = '' === this.from
            ? this.allDates
            : [this.from, this.to].filter((day, index, days) => 0 === index || day !== days[0]).map((day) => this.day(day)).join(' – ');
        this.clearTarget.hidden = '' === this.from;

        this.filterList();
        this.zoom(this.from, this.to);

        if (this.hasCompareTarget) {
            this.listTarget.hidden = this.comparing;
            this.compareTarget.hidden = !this.comparing;
        }
        this.renderTimes();
        if (this.comparing) {
            this.loadComparison();
        }
        this.remember();
    }

    filterList() {
        const kinds = this.kindTargets.filter((kind) => kind.checked).map((kind) => kind.value);
        const medals = !this.hasMedalsTarget || this.medalsTarget.checked;
        const newLevels = !this.hasNewLevelsTarget || this.newLevelsTarget.checked;
        // Y-m-d compares as text
        const { from, to } = this;
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
    }

    /** Times chosen the other way round on one day (or in the URL): swapped, as the comparison shows them. */
    unswap() {
        const { from, to, swapped } = this.compared();
        if (swapped) {
            [this.fromId, this.toId] = [from[1], to[1]];
        }
    }

    /**
     * The uploads compared: [day, id] of the start and the end; on one day the earlier one is the
     * start (swapped: they were chosen the other way round).
     */
    compared() {
        const fromDay = this.from || this.dayList[0];
        const toDay = this.to || this.dayList.at(-1);
        const from = [fromDay, this.fromId ?? this.daysValue[fromDay]?.[0]?.id];
        const to = [toDay, this.toId ?? this.daysValue[toDay]?.at(-1)?.id];

        const uploads = fromDay === toDay ? this.daysValue[fromDay] ?? [] : [];
        const index = (id) => uploads.findIndex((upload) => upload.id === id);
        if (index(from[1]) > index(to[1])) {
            return { from: [fromDay, to[1]], to: [toDay, from[1]], swapped: true };
        }

        return { from, to, swapped: false };
    }

    /**
     * The times of the compared days with several uploads: buttons, a dropdown for more than
     * MAX_TIME_BUTTONS (they would not fit on a phone).
     */
    renderTimes() {
        if (!this.hasFromTimesTarget) {
            return;
        }

        const compared = this.compared();
        for (const end of ['from', 'to']) {
            const [day, chosen] = compared[end];
            const uploads = this.comparing ? this.daysValue[day] ?? [] : [];
            const labels = this.timeLabels(day, uploads);
            let controls = [];

            if (uploads.length > MAX_TIME_BUTTONS) {
                const select = document.createElement('select');
                select.className = 'form-select form-select-sm timeline-time-select';
                select.dataset.end = end;
                select.dataset.action = 'timeline-filter#pickTime';
                select.setAttribute('aria-label', this[`${end}TimesTarget`].querySelector('span').textContent);
                select.replaceChildren(...uploads.map((upload, index) => new Option(labels[index], upload.id, false, upload.id === chosen)));
                controls = [select];
            } else if (uploads.length > 1) {
                const group = document.createElement('div');
                group.className = 'btn-group btn-group-sm';
                group.setAttribute('role', 'group');
                group.replaceChildren(...uploads.map((upload, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = `btn ${upload.id === chosen ? 'btn-primary' : 'btn-outline-primary'}`;
                    button.textContent = labels[index];
                    button.dataset.end = end;
                    button.dataset.id = upload.id;
                    button.dataset.action = 'timeline-filter#pickTime';
                    button.setAttribute('aria-pressed', String(upload.id === chosen));

                    return button;
                }));
                controls = [group];
            }

            this[`${end}TimesTarget`].hidden = 0 === controls.length;
            this[`${end}TimeButtonsTarget`].replaceChildren(...controls);
        }
    }

    /** The uploads' times in the page's language, with the seconds where two share a minute. */
    timeLabels(day, uploads) {
        const minutes = uploads.map((upload) => upload.time.slice(0, 5));

        return uploads.map((upload, index) => {
            const date = new Date(`${day}T${upload.time}`);

            return minutes.indexOf(minutes[index]) !== minutes.lastIndexOf(minutes[index])
                ? this.timeWithSecondsFormat.format(date)
                : this.timeFormat.format(date);
        });
    }

    /** Loads what changed between the compared uploads; the newest request wins. */
    async loadComparison() {
        if (!this.hasCompareUrlValue) {
            return;
        }

        const { from, to } = this.compared();
        const query = new URLSearchParams({ compare: 1, ...(from[1] && { compareFrom: from[1] }), ...(to[1] && { compareTo: to[1] }) });

        this.compareRequest?.abort();
        this.compareRequest = new AbortController();

        try {
            const response = await fetch(`${this.compareUrlValue}?${query}`, { signal: this.compareRequest.signal });
            if (response.ok) {
                this.compareContentTarget.innerHTML = await response.text();
            }
        } catch (error) {
            if ('AbortError' !== error.name) {
                throw error;
            }
        }
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

    day(day) {
        return this.dateFormat.format(new Date(`${day}T00:00`));
    }
}
