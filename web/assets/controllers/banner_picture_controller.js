import { Controller } from '@hotwired/stimulus';

/*
 * A banner picture's loading animation (banners/_picture.html.twig): shown until the picture is
 * loaded, or failed to load.
 *
 * <span data-controller="banner-picture">
 *     <span class="banner-picture-spinner"></span>
 *     <img data-banner-picture-target="image" data-action="load->banner-picture#loaded error->banner-picture#loaded">
 * </span>
 */
export default class extends Controller {
    static targets = ['image'];

    connect() {
        // Already there, e.g. from the cache
        if (this.imageTarget.complete && this.imageTarget.naturalWidth > 0) {
            this.loaded();
        }
    }

    loaded() {
        this.element.classList.add('banner-picture-loaded');
    }
}
