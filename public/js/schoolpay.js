document.addEventListener('click', (event) => {
    const target = event.target.closest('[data-confirm]');
    if (!target) {
        return;
    }

    if (!confirm(target.getAttribute('data-confirm'))) {
        event.preventDefault();
    }
});
