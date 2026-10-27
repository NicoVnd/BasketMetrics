import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'status', 'file', 'zone', 'selection'];

    dragover(event) {
        event.preventDefault();
        if (!this.submitting) this.zoneTarget.classList.add('drag-active');
    }
    dragleave(event) {
        if (!this.zoneTarget.contains(event.relatedTarget)) this.zoneTarget.classList.remove('drag-active');
    }
    drop(event) {
        event.preventDefault();
        this.zoneTarget.classList.remove('drag-active');
        if (this.submitting || !event.dataTransfer?.files?.length) return;
        if (event.dataTransfer.files.length !== 1) {
            this.selectionTarget.textContent = 'Choisissez une seule archive ZIP par import.';
            return;
        }
        this.fileTarget.files = event.dataTransfer.files;
        this.selected();
    }
    selected() {
        const file = this.fileTarget.files[0];
        let error = '';
        if (file && !file.name.toLowerCase().endsWith('.zip')) error = 'Le fichier doit être une archive ZIP.';
        if (file && file.size > 20000000) error = 'L’archive dépasse la limite de 20 Mo.';
        this.fileTarget.setCustomValidity(error);
        this.selectionTarget.textContent = error || (file ? `Prêt à analyser : ${file.name}` : '');
    }
    submit(event) {
        if (this.submitting) { event.preventDefault(); return; }
        this.selected();
        if (!this.element.reportValidity()) { event.preventDefault(); return; }
        this.submitting = true;
        this.buttonTarget.disabled = true;
        this.buttonTarget.setAttribute('aria-busy', 'true');
        this.buttonTarget.textContent = 'Lecture des documents en cours…';
        this.statusTarget.hidden = false;
        this.element.setAttribute('aria-busy', 'true');
    }
}
