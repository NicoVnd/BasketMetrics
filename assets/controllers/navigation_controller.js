import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle'];
    toggle() {
        const open = this.toggleTarget.getAttribute('aria-expanded') !== 'true';
        this.toggleTarget.setAttribute('aria-expanded', String(open));
        this.element.classList.toggle('navigation-open', open);
    }
    close(event) {
        if (event.key === 'Escape' && this.toggleTarget.getAttribute('aria-expanded') === 'true') {
            this.toggle();
            this.toggleTarget.focus();
        }
    }
    connect() { this.element.setAttribute('data-action', 'keydown.esc->navigation#close'); }
}
