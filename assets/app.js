import './stimulus_bootstrap.js';
import './styles/app.css';
import './styles/fonts.css';
import './styles/landing.css';

// Short axis labels keep the plot readable; tooltips retain the full opponent name.
document.addEventListener('chartjs:pre-connect', (event) => {
    const options = event.detail.config.options;
    const theme = getComputedStyle(document.documentElement);
    const color = (token) => theme.getPropertyValue(token).trim();
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    options.animation = reducedMotion ? false : { duration: 480, easing: 'easeOutQuart' };
    options.transitions = {
        resize: { animation: { duration: 0 } },
        active: { animation: { duration: reducedMotion ? 0 : 150 } },
    };
    options.plugins.legend.labels.color = color('--muted');
    options.plugins.tooltip.backgroundColor = color('--surface-raised');
    options.plugins.tooltip.titleColor = color('--text');
    options.plugins.tooltip.bodyColor = color('--text');
    options.plugins.tooltip.borderColor = color('--line');
    options.scales.x.ticks.color = color('--muted');
    options.scales.y.ticks.color = color('--muted');
    options.scales.y.title.color = color('--muted');
    options.scales.y.grid.color = color('--line');
    event.detail.config.data.datasets.forEach((dataset, index) => {
        dataset.borderColor = color(index === 0 ? '--chart-primary' : '--chart-secondary');
        dataset.backgroundColor = dataset.borderColor;
        dataset.borderWidth = 2.5;
        dataset.pointRadius = 2.5;
        dataset.pointHoverRadius = 6;
        dataset.pointHoverBorderWidth = 3;
        dataset.pointHoverBorderColor = color('--surface');
        if (index > 0) dataset.borderDash = [5, 5];
    });
    options.scales.x.ticks.maxRotation = 0;
    options.scales.x.ticks.callback = function (value) {
        return this.getLabelForValue(value).split(' · ')[0];
    };
    options.plugins.legend.labels.font = { family: 'Inter, sans-serif', size: 11 };
    options.plugins.tooltip.callbacks = {
        label: (context) => `${context.dataset.label} : ${context.parsed.y} pts`,
    };
});
