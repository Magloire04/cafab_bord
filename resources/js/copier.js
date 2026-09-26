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

        // Le libellé d'origine est mémorisé une seule fois : un double clic lirait
        // sinon « Copié » comme libellé à restaurer et le bouton y resterait bloqué.
        bouton.dataset.libelleOrigine ??= bouton.textContent;
        bouton.textContent = 'Copié';
        clearTimeout(Number(bouton.dataset.minuterieCopie));
        bouton.dataset.minuterieCopie = String(setTimeout(() => {
            bouton.textContent = bouton.dataset.libelleOrigine;
        }, 1500));
    } catch {
        // Presse-papiers indisponible (page hors contexte sécurisé) : l'utilisateur copie à la main.
        window.prompt('Copiez le texte ci-dessous :', texte);
    }
});
