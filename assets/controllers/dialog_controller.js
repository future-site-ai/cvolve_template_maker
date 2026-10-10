import { Controller } from '@hotwired/stimulus';

/* Opens a <dialog> by id: data-action="dialog#open" data-dialog-id-param="x". */
export default class extends Controller {
    open(event) {
        document.getElementById(event.params.id)?.showModal();
    }

    close(event) {
        event.target.closest('dialog')?.close();
    }
}
