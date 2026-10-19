import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'status'];

    submit(event) {
        if (this.submitting) {
            event.preventDefault();
            return;
        }
        this.submitting = true;
        this.buttonTarget.disabled = true;
        this.buttonTarget.textContent = 'Lecture des documents en cours…';
        this.statusTarget.hidden = false;
        this.element.setAttribute('aria-busy', 'true');
    }
}
