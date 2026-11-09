/** B04: tutorial accesible y progresivo, sin datos personales ni permisos del dispositivo. */
export function tourIndex(index, direction, length) {
    if (!Number.isInteger(length) || length < 1) return 0;
    return Math.max(0, Math.min(length - 1, index + direction));
}

export function tourProgressLabel(index, length) {
    return `Paso ${index + 1} de ${length}`;
}

export function initializeGuideTour(root = document) {
    const dialog = root.querySelector('[data-help-tour]');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const steps = [...dialog.querySelectorAll('[data-tour-step]')];
    if (steps.length === 0) return;

    const back = dialog.querySelector('[data-tour-back]');
    const next = dialog.querySelector('[data-tour-next]');
    const complete = dialog.querySelector('[data-tour-complete-form]');
    const counter = dialog.querySelector('[data-tour-counter]');
    const heading = dialog.querySelector('#tour-heading');
    const skip = dialog.querySelector('[data-tour-skip-form]');
    let index = 0;
    let opener = null;

    const render = () => {
        steps.forEach((step, position) => { step.hidden = position !== index; });
        back.disabled = index === 0;
        next.hidden = index === steps.length - 1;
        complete.hidden = index !== steps.length - 1;
        counter.textContent = tourProgressLabel(index, steps.length);
    };

    const open = (source = null) => {
        if (dialog.open) return;
        opener = source;
        index = 0;
        render();
        dialog.showModal(); // Elemento nativo: captura foco y mantiene la navegación por Tab.
        heading.focus();
    };

    next.addEventListener('click', () => {
        index = tourIndex(index, 1, steps.length);
        render();
        steps[index].querySelector('h3')?.setAttribute('tabindex', '-1');
        steps[index].querySelector('h3')?.focus();
    });
    back.addEventListener('click', () => {
        index = tourIndex(index, -1, steps.length);
        render();
        steps[index].querySelector('h3')?.setAttribute('tabindex', '-1');
        steps[index].querySelector('h3')?.focus();
    });

    // Escape también se guarda en PostgreSQL como omitido, no solo en el navegador.
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        skip.requestSubmit();
    });
    dialog.addEventListener('close', () => opener?.focus());

    for (const button of root.querySelectorAll('[data-tour-open]')) {
        button.addEventListener('click', () => open(button));
    }
    if (dialog.hasAttribute('data-tour-auto')) open();
}
