import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.min.css';

/*
 * A world map with a dot where each banner starts, colored by its list (published, done, to-do),
 * on the banners page (banners/index.html.twig). A tap on a dot shows the banner, linking to
 * Bannergress. The map fits the banners; without any it shows the world. The legend's toggles
 * show or hide a list (the map keeps its view).
 *
 * <div data-controller="banner-map" data-banner-map-banners-value="[{&quot;lat&quot;: 50.9, &quot;lng&quot;: 6.9,
 *      &quot;title&quot;: &quot;…&quot;, &quot;url&quot;: &quot;…&quot;, &quot;list&quot;: &quot;done&quot;}]">
 *     <div data-banner-map-target="map"></div>
 *     <input type="checkbox" value="done" checked data-banner-map-target="list" data-action="banner-map#filter">
 * </div>
 */
export default class extends Controller {
    static targets = ['map', 'list'];

    static values = { banners: Array };

    connect() {
        this.map = L.map(this.mapTarget, { worldCopyJump: true }).setView([20, 0], 1);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(this.map);

        // The list colors are CSS variables per theme, as the legend's
        const style = getComputedStyle(this.mapTarget);
        const surface = style.getPropertyValue('--bs-body-bg').trim() || '#fff';
        const points = [];
        this.layers = {};
        // Done and to-do below, the own published banners on top
        for (const list of ['todo', 'done', 'published']) {
            const color = style.getPropertyValue(`--banner-${list}`).trim();
            this.layers[list] = L.layerGroup().addTo(this.map);
            for (const banner of this.bannersValue.filter((b) => b.list === list)) {
                const point = [banner.lat, banner.lng];
                points.push(point);
                L.circleMarker(point, { radius: 6, color: surface, weight: 2, fillColor: color, fillOpacity: 1 })
                    .bindPopup(() => this.popup(banner))
                    .addTo(this.layers[list]);
            }
        }

        if (points.length > 0) {
            this.map.fitBounds(points, { padding: [24, 24], maxZoom: 13 });
        }
    }

    /** Shows the lists whose toggle is on. */
    filter() {
        for (const toggle of this.listTargets) {
            const layer = this.layers[toggle.value];
            if (toggle.checked) {
                layer.addTo(this.map);
            } else {
                layer.remove();
            }
        }
    }

    disconnect() {
        this.map?.remove();
    }

    /** The banner's title linking to Bannergress, built as nodes: titles are user content. */
    popup(banner) {
        const link = document.createElement('a');
        link.href = banner.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = banner.title;
        const container = document.createElement('div');
        container.append(link, document.createElement('br'), banner.label);

        return container;
    }
}
