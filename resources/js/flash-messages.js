document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert').forEach((alert) => {
        setTimeout(() => {
            alert.classList.add('alert-fade-out');
            setTimeout(() => alert.remove(), 600);
        }, 3000);
    });
});
