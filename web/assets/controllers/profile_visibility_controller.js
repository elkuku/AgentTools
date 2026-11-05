import { Controller } from '@hotwired/stimulus';

/*
 * The profile parts other users see: the other parts are only shown with the agent part, so
 * without it they are disabled and unchecked, as in the app's settings. Their checks come back
 * when the agent part is checked again. The browser does not send disabled parts.
 *
 * <fieldset data-controller="profile-visibility">
 *     <input type="checkbox" data-profile-visibility-target="agent" data-action="profile-visibility#update">
 *     <fieldset data-profile-visibility-target="group">
 *         <span data-profile-visibility-target="on">…</span>
 *         <span data-profile-visibility-target="off">…</span>
 *         <input type="checkbox" data-profile-visibility-target="part">
 *     </fieldset>
 * </fieldset>
 */
export default class extends Controller {
    static targets = ['agent', 'group', 'on', 'off', 'part'];

    connect() {
        this.checked = new Set();
        this.update();
    }

    update() {
        const on = this.agentTarget.checked;

        if (!on && !this.groupTarget.disabled) {
            this.checked = new Set(this.partTargets.filter((part) => part.checked));
        }
        this.partTargets.forEach((part) => {
            part.checked = on && (this.groupTarget.disabled ? this.checked.has(part) : part.checked);
        });

        this.groupTarget.disabled = !on;
        this.onTarget.hidden = !on;
        this.offTarget.hidden = on;
    }
}
