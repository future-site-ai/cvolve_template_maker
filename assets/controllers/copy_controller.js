import { Controller } from '@hotwired/stimulus';

/* Copies the text of the "source" target to the clipboard. */
export default class extends Controller {
    static targets = ['source', 'button'];

    async copy() {
        const text = this.sourceTarget.value ?? this.sourceTarget.textContent;
        try {
            await navigator.clipboard.writeText(text.trim());
            this.buttonTarget.textContent = 'Copied';
            setTimeout(() => (this.buttonTarget.textContent = 'Copy'), 1500);
        } catch (e) { /* clipboard unavailable */ }
    }
}
