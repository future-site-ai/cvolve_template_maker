import { Controller } from '@hotwired/stimulus';

/*
 * AI design chat: sends requests with the chosen connection, shows proposed file diffs,
 * and applies or discards them (applying reloads the editor and preview).
 */
export default class extends Controller {
    static targets = ['log', 'input', 'send', 'empty'];
    static values = { url: String, token: String };

    connect() {
        this.scrollDown();
    }

    keydown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this.send();
        }
    }

    suggest(event) {
        this.inputTarget.value = event.currentTarget.dataset.text;
        this.send();
    }

    async send(event) {
        event?.preventDefault();
        const text = this.inputTarget.value.trim();
        if (!text || this.busy) return;
        this.busy = true;
        this.sendTarget.disabled = true;
        if (this.hasEmptyTarget) this.emptyTarget.remove();
        this.append(`<div class="chat-msg user">${this.escape(text)}</div>`);
        this.inputTarget.value = '';
        const waiting = this.append('<div class="chat-msg assistant"><span class="spinner"></span> Designing… this can take up to a minute.</div>');

        const select = this.element.querySelector('select[name=connection]');
        const body = new FormData();
        body.set('message', text);
        if (select) body.set('connection', select.value);
        try {
            const response = await fetch(this.urlValue, { method: 'POST', body, headers: { 'X-CSRF-Token': this.tokenValue, Accept: 'application/json' } });
            const data = await response.json();
            waiting.remove();
            this.append(response.ok ? data.html : `<div class="chat-msg assistant">⚠️ ${this.escape(data.error || 'Request failed.')}</div>`);
        } catch (e) {
            waiting.remove();
            this.append('<div class="chat-msg assistant">⚠️ Network error or timeout. Try again.</div>');
        } finally {
            this.busy = false;
            this.sendTarget.disabled = false;
        }
    }

    async resolve(event) {
        const button = event.currentTarget;
        const message = button.closest('.chat-msg');
        message.querySelectorAll('button').forEach((b) => (b.disabled = true));
        try {
            const response = await fetch(button.dataset.url, { method: 'POST', headers: { 'X-CSRF-Token': this.tokenValue, Accept: 'application/json' } });
            const data = await response.json();
            if (data.html) message.outerHTML = data.html;
            if (data.status === 'applied') window.dispatchEvent(new CustomEvent('studio:reload'));
        } catch (e) {
            message.querySelectorAll('button').forEach((b) => (b.disabled = false));
        }
    }

    append(html) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const node = wrapper.firstElementChild;
        this.logTarget.appendChild(node);
        this.scrollDown();
        return node;
    }

    scrollDown() {
        this.logTarget.scrollTop = this.logTarget.scrollHeight;
    }

    escape(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
