document.addEventListener('click', async (evenement) => {
    const bouton = evenement.target.closest('[data-copier], [data-copier-champ]');

    if (!bouton) {
        return;
    }

    const texte = bouton.dataset.copierChamp
        ? document.getElementById(bouton.dataset.copierChamp)?.value
        : bouton.dataset.copier;

    if (!texte) {
        return;
    }

    try {
        await navigator.clipboard.writeText(texte);
        const libelle = bouton.textContent;
        bouton.textContent = 'Copié';
        setTimeout(() => {
            bouton.textContent = libelle;
        }, 1500);
    } catch {
        // Presse-papiers indisponible (page hors contexte sécurisé) : l'utilisateur copie à la main.
        window.prompt('Copiez le texte ci-dessous :', texte);
    }
});
