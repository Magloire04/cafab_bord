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

    document.addEventListener('submit', (evenement) => {
        const formulaire = evenement.target;

        if (!(formulaire instanceof HTMLFormElement) || !formulaire.dataset.confirmer || formulaire.dataset.confirme === '1') {
            return;
        }

        evenement.preventDefault();
        formulaireEnAttente = formulaire;
        message.textContent = formulaire.dataset.confirmer;
        modale.show();
    });

    valider.addEventListener('click', () => {
        if (!formulaireEnAttente) {
            return;
        }

        formulaireEnAttente.dataset.confirme = '1';
        modale.hide();
        formulaireEnAttente.requestSubmit();
    });
});
