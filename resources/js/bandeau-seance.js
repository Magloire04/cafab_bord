const INTERVALLE_MS = 30000;
const CLE_NOTIFIEES = 'cafab.seances-notifiees';

function lireNotifiees() {
    try {
        return JSON.parse(localStorage.getItem(CLE_NOTIFIEES) ?? '[]');
    } catch {
        return [];
    }
}

function memoriserNotifiees(ids) {
    try {
        localStorage.setItem(CLE_NOTIFIEES, JSON.stringify(ids.slice(-50)));
    } catch {
        // Stockage indisponible (navigation privée) : on notifiera peut-être deux fois, sans gravité.
    }
}

function afficher(bandeau, lignes) {
    const conteneur = bandeau.querySelector('[data-bandeau-lignes]');
    conteneur.replaceChildren(...lignes.map((ligne) => {
        const paragraphe = document.createElement('p');
        paragraphe.className = `bandeau-ligne ${ligne.type === 'en_cours' ? 'bandeau-en-cours' : 'bandeau-prochaine'}`;
        paragraphe.textContent = ligne.texte;

        return paragraphe;
    }));
    bandeau.hidden = lignes.length === 0;
}

function notifier(lignes) {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    const dejaNotifiees = lireNotifiees();
    const nouvelles = lignes.filter((ligne) => ligne.type === 'en_cours' && !dejaNotifiees.includes(ligne.id));

    nouvelles.forEach((ligne) => {
        // Le tag évite un doublon si deux onglets ouverts notifient au même moment.
        new Notification('Répétition en cours', { body: ligne.texte, tag: `seance-${ligne.id}` });
    });

    if (nouvelles.length > 0) {
        memoriserNotifiees([...dejaNotifiees, ...nouvelles.map((ligne) => ligne.id)]);
    }
}

function mettreAJourBouton(bouton) {
    bouton.hidden = !('Notification' in window) || Notification.permission !== 'default';
}

async function rafraichir(bandeau) {
    try {
        const reponse = await fetch(bandeau.dataset.url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!reponse.ok || reponse.redirected) {
            return; // Session expirée, changement de mot de passe exigé… on garde le dernier état affiché.
        }

        const donnees = await reponse.json();
        afficher(bandeau, donnees.lignes);
        notifier(donnees.lignes);
    } catch {
        // Réseau indisponible ou réponse non JSON : on réessaiera au prochain tour.
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const bandeau = document.querySelector('[data-bandeau-seance]');

    if (!bandeau) {
        return;
    }

    const bouton = bandeau.querySelector('[data-bandeau-notifications]');
    mettreAJourBouton(bouton);
    bouton.addEventListener('click', async () => {
        await Notification.requestPermission();
        mettreAJourBouton(bouton);
        rafraichir(bandeau);
    });

    rafraichir(bandeau);
    setInterval(() => rafraichir(bandeau), INTERVALLE_MS);
});
