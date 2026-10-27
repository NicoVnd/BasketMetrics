import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    navigate(event) {
        if (event.target.closest('a')) this.element.open = false;
    }
    close() {
        this.element.open = false;
        this.element.querySelector('summary').focus();
    }
}
