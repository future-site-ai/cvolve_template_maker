import { Controller } from '@hotwired/stimulus';

/* Light / dark / system theme switch, remembered per browser. */
export default class extends Controller {
    connect() {
        this.apply(this.stored());
    }

    toggle() {
        const order = ['system', 'light', 'dark'];
        const next = order[(order.indexOf(this.stored()) + 1) % order.length];
        try { localStorage.setItem('cvolve-theme', next); } catch (e) { /* private mode */ }
        this.apply(next);
    }

    stored() {
        try { return localStorage.getItem('cvolve-theme') || 'system'; } catch (e) { return 'system'; }
    }

    apply(theme) {
        if (theme === 'system') document.documentElement.removeAttribute('data-theme');
        else document.documentElement.setAttribute('data-theme', theme);
        this.element.title = `Theme: ${theme}`;
        this.element.textContent = { system: '◐', light: '☀', dark: '☾' }[theme];
    }
}
