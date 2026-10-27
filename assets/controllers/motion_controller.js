import { Controller } from '@hotwired/stimulus';

// Animate only as content enters the viewport. Content remains visible without JS.
export default class extends Controller {
    connect() {
        this.motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.animations = new Set();
        this.onPreferenceChange = () => {
            if (this.motionPreference.matches) this.stop();
        };
        this.motionPreference.addEventListener('change', this.onPreferenceChange);
        if (this.motionPreference.matches || !window.IntersectionObserver || !Element.prototype.animate) return;
        this.observer = new IntersectionObserver((entries) => {
            const visible = entries.filter((entry) => entry.isIntersecting);
            visible.forEach((entry, index) => {
                this.observer.unobserve(entry.target);
                if (entry.target.dataset.revealed || this.motionPreference.matches) return;
                entry.target.dataset.revealed = 'true';
                const animation = entry.target.animate([
                    { opacity: 0, transform: 'translateY(12px)' },
                    { opacity: 1, transform: 'translateY(0)' },
                ], { duration: 380, delay: Math.min(index * 45, 135), easing: 'cubic-bezier(.2,.7,.3,1)', fill: 'backwards' });
                this.animations.add(animation);
                animation.finished.then(() => this.animations.delete(animation)).catch(() => {});
            });
        }, { threshold: 0.06 });
        this.element.querySelectorAll('[data-reveal], .workspace-content > .page-heading, .workspace-content > .panel, .workspace-content > div > .panel')
            .forEach((element) => this.observer.observe(element));
    }
    stop() {
        this.observer?.disconnect();
        this.animations.forEach((animation) => animation.cancel());
        this.animations.clear();
    }
    disconnect() {
        this.stop();
        this.motionPreference.removeEventListener('change', this.onPreferenceChange);
    }
}
