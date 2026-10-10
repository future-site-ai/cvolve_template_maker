import { Controller } from '@hotwired/stimulus';

/* AI connection form: suggests the default model and hides the key field for keyless providers. */
export default class extends Controller {
    static targets = ['provider', 'model', 'keyRow'];

    connect() {
        this.change(false);
    }

    change(fill = true) {
        const option = this.providerTarget.selectedOptions[0];
        this.modelTarget.placeholder = option.dataset.model || 'model name';
        this.keyRowTarget.hidden = option.dataset.needsKey !== '1' && !['openai_like'].includes(option.value);
    }
}
