// Toute <form data-confirmer="…"> passe par la fenêtre de confirmation avant d'être envoyée.
document.addEventListener('DOMContentLoaded', () => {
    const element = document.getElementById('modale-confirmation');

    if (!element) {
        return;
    }

    const modale = new window.bootstrap.Modal(element);
    const message = element.querySelector('[data-confirmation-message]');
    const valider = element.querySelector('[data-confirmation-valider]');
    let formulaireEnAttente = null;
    let boutonEnAttente = null;

    const oublier = () => {
        formulaireEnAttente = null;
        boutonEnAttente = null;
    };

    document.addEventListener('submit', (evenement) => {
        const formulaire = evenement.target;

        if (!(formulaire instanceof HTMLFormElement) || !formulaire.dataset.confirmer || formulaire.dataset.confirme === '1') {
            return;
        }

        evenement.preventDefault();
        formulaireEnAttente = formulaire;
        boutonEnAttente = evenement.submitter ?? null;
        message.textContent = formulaire.dataset.confirmer;
        modale.show();
    });

    valider.addEventListener('click', () => {
        // Une seule confirmation par demande : un second clic ne trouve plus rien à envoyer.
        const formulaire = formulaireEnAttente;
        const bouton = boutonEnAttente;
        oublier();

        if (!formulaire) {
            return;
        }

        modale.hide();

        // requestSubmit déclenche « submit » de façon synchrone : le marqueur ne vit que pendant cet appel,
        // il ne reste donc pas sur une page restaurée depuis le cache de l'historique.
        formulaire.dataset.confirme = '1';

        try {
            formulaire.requestSubmit(bouton);
        } finally {
            delete formulaire.dataset.confirme;
        }
    });

    // Annuler, Échap ou clic hors de la fenêtre : la demande en attente est abandonnée.
    element.addEventListener('hidden.bs.modal', oublier);
});
