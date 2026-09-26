document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-toggle-password]').forEach((bouton) => {
        const champ = document.getElementById(bouton.dataset.togglePassword);
        const icone = bouton.querySelector('i');

        if (!champ || !icone) {
            return;
        }

        bouton.addEventListener('click', () => {
            const masque = champ.type === 'password';
            champ.type = masque ? 'text' : 'password';
            icone.classList.toggle('fa-eye', !masque);
            icone.classList.toggle('fa-eye-slash', masque);
            bouton.setAttribute('aria-label', masque ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });
});
