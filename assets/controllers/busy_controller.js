import { Controller } from '@hotwired/stimulus';

/* Shows progress on long form submissions (AI import and generation). */
export default class extends Controller {
    static targets = ['button'];

    start() {
        if (!this.hasButtonTarget) return;
        this.buttonTarget.disabled = true;
        this.buttonTarget.innerHTML = '<span class="spinner"></span> Working… (up to a minute)';
    }
}
