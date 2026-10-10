import { Controller } from '@hotwired/stimulus';
import { EditorView, basicSetup } from 'codemirror';
import { EditorState } from '@codemirror/state';
import { keymap } from '@codemirror/view';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { json } from '@codemirror/lang-json';
import { oneDark } from '@codemirror/theme-one-dark';

/*
 * The template studio: one CodeMirror editor with a state per file, debounced autosave,
 * validation results and a live preview that reloads after every save.
 */
export default class extends Controller {
    static targets = ['initial', 'editor', 'tab', 'state', 'problems', 'problemCount', 'frame', 'spinner', 'sample', 'locale', 'zoom', 'pdfLink', 'syntax', 'title', 'export'];
    static values = { saveUrl: String, filesUrl: String, previewUrl: String, token: String, syntax: String };

    connect() {
        this.files = JSON.parse(this.initialTarget.textContent);
        this.dirty = new Set();
        this.states = {};
        this.current = 'template';
        this.dark = document.documentElement.dataset.theme === 'dark'
            || (!document.documentElement.dataset.theme && window.matchMedia('(prefers-color-scheme: dark)').matches);

        for (const name of Object.keys(this.files)) {
            this.states[name] = this.createState(name, this.files[name]);
        }
        this.view = new EditorView({ state: this.states.template, parent: this.editorTarget });
        this.frameTarget.addEventListener('load', () => this.afterPreview());
        // The first load may finish before this controller connects
        this.afterPreview();
        this.resizeObserver = new ResizeObserver(() => this.zoom());
        this.resizeObserver.observe(this.frameTarget);
        this.onKey = (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 's') {
                e.preventDefault();
                this.save();
            }
        };
        document.addEventListener('keydown', this.onKey);
        window.addEventListener('studio:reload', this.reloadFiles);
        this.updateProblemCount();
    }

    disconnect() {
        document.removeEventListener('keydown', this.onKey);
        window.removeEventListener('studio:reload', this.reloadFiles);
        this.view?.destroy();
        this.resizeObserver?.disconnect();
    }

    createState(name, doc) {
        const language = name === 'css' ? css() : (name === 'template' ? html() : json());
        return EditorState.create({
            doc,
            extensions: [
                basicSetup,
                language,
                EditorView.lineWrapping,
                ...(this.dark ? [oneDark] : []),
                keymap.of([]),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) this.changed(name);
                }),
            ],
        });
    }

    switch(event) {
        const name = event.currentTarget.dataset.file;
        if (name === this.current) return;
        this.states[this.current] = this.view.state;
        this.current = name;
        this.view.setState(this.states[name]);
        this.tabTargets.forEach((t) => t.classList.toggle('active', t.dataset.file === name));
    }

    changed(name) {
        this.dirty.add(name);
        this.markTabs();
        this.setState('Unsaved changes…', 'dirty');
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.save(), 900);
    }

    changeSyntax() {
        this.syntaxValue = this.syntaxTarget.value;
        const tab = this.tabTargets.find((t) => t.dataset.file === 'template');
        if (tab) tab.textContent = this.syntaxValue === 'mustache' ? 'template.html' : 'template.html.twig';
        this.dirty.add('template');
        this.save();
    }

    async save() {
        clearTimeout(this.timer);
        this.states[this.current] = this.view.state;
        const payload = { syntax: this.syntaxValue };
        for (const name of this.dirty) payload[name] = this.states[name].doc.toString();
        if (Object.keys(payload).length === 1 && !this.forceSave) {
            this.refresh();
            return;
        }
        this.setState('Saving…', '');
        try {
            const response = await fetch(this.saveUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue, Accept: 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || response.statusText);
            this.dirty.clear();
            this.markTabs();
            this.showProblems(data.errors);
            this.titleTarget.textContent = data.name;
            this.setState(data.errors.length ? `Saved ${data.saved_at} · ${data.errors.length} problem(s)` : `Saved ${data.saved_at}`, data.errors.length ? 'error' : '');
            this.refresh();
        } catch (e) {
            this.setState('Not saved: ' + e.message, 'error');
        }
    }

    // After an applied AI change or a restore: load the files from the server
    reloadFiles = async () => {
        const data = await (await fetch(this.filesUrlValue, { headers: { Accept: 'application/json' } })).json();
        for (const name of ['template', 'css', 'schema', 'manifest']) {
            this.states[name] = this.createState(name, data[name]);
        }
        this.view.setState(this.states[this.current]);
        this.syntaxValue = data.syntax;
        this.syntaxTarget.value = data.syntax;
        this.dirty.clear();
        this.markTabs();
        this.showProblems(data.errors);
        this.setState('Updated', '');
        this.refresh();
    };

    refresh() {
        this.spinnerTarget.hidden = false;
        try {
            this.scroll = this.frameTarget.contentWindow?.scrollY ?? 0;
        } catch (e) {
            this.scroll = 0;
        }
        const params = new URLSearchParams({ sample: this.sampleTarget.value, locale: this.localeTarget.value, _: Date.now() });
        this.frameTarget.src = `${this.previewUrlValue}?${params}`;
        this.pdfLinkTarget.href = this.pdfLinkTarget.href.split('?')[0] + `?sample=${this.sampleTarget.value}&locale=${this.localeTarget.value}`;
    }

    afterPreview() {
        this.spinnerTarget.hidden = true;
        this.zoom();
        try {
            if (this.scroll) this.frameTarget.contentWindow.scrollTo(0, this.scroll);
        } catch (e) { /* sandboxed */ }
    }

    zoom() {
        try {
            const body = this.frameTarget.contentDocument?.body;
            if (!body) return;
            // "Fit": the A4 sheet (210mm ≈ 794px) plus its margins fills the pane width
            const value = this.zoomTarget.value;
            body.style.zoom = value === 'fit' ? Math.min(1.2, (this.frameTarget.clientWidth - 24) / 842).toFixed(3) : value;
        } catch (e) { /* not same origin */ }
    }

    showProblems(errors) {
        const box = this.problemsTarget;
        box.innerHTML = '';
        if (!errors.length) {
            box.innerHTML = '<div class="ok">✓ Valid template: ready to export.</div>';
        } else {
            const ul = document.createElement('ul');
            errors.forEach((e) => {
                const li = document.createElement('li');
                li.textContent = e;
                ul.appendChild(li);
            });
            box.appendChild(ul);
        }
        this.exportTarget.classList.toggle('btn-primary', !errors.length);
        this.updateProblemCount(errors.length);
    }

    updateProblemCount(count) {
        const n = count ?? this.problemsTarget.querySelectorAll('li').length;
        this.problemCountTarget.textContent = n ? `Problems (${n})` : 'Problems';
    }

    markTabs() {
        this.tabTargets.forEach((tab) => {
            tab.querySelector('.dot')?.remove();
            if (this.dirty.has(tab.dataset.file)) tab.insertAdjacentHTML('beforeend', '<span class="dot"></span>');
        });
    }

    setState(text, kind) {
        this.stateTarget.textContent = text;
        this.stateTarget.className = 'save-state ' + kind;
    }
}
