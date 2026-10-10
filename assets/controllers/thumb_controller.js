import { Controller } from '@hotwired/stimulus';

/* Scales a full-size A4 preview iframe down to fit its card. */
export default class extends Controller {
    connect() {
        this.observer = new ResizeObserver(() => this.fit());
        this.observer.observe(this.element);
    }

    disconnect() {
        this.observer.disconnect();
    }

    fit() {
        const frame = this.element.querySelector('iframe');
        if (frame) frame.style.transform = `scale(${this.element.clientWidth / 794})`;
    }
}
