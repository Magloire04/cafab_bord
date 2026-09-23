document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-flash').forEach((flash) => {
        setTimeout(() => {
            flash.classList.add('is-hiding');
            setTimeout(() => flash.remove(), 400);
        }, 4000);
    });
});
