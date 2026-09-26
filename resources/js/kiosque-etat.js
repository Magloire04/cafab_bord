const INTERVALLE_MS = 30000;

document.addEventListener('DOMContentLoaded', () => {
    const racine = document.querySelector('[data-kiosque-etat]');

    if (!racine) {
        return;
    }

    const etatInitial = `${racine.dataset.enCoursId}|${racine.dataset.prochaineId}`;

    // Ne jamais recharger pendant qu'une fille tape son code.
    const codeEnCoursDeSaisie = () => {
        const champCode = document.querySelector('input[name="pin"]');

        return champCode !== null && champCode.value !== '';
    };

    setInterval(async () => {
        if (codeEnCoursDeSaisie()) {
            return;
        }

        try {
            const reponse = await fetch(racine.dataset.url, { headers: { Accept: 'application/json' } });

            if (!reponse.ok) {
                return;
            }

            const etat = await reponse.json();
            const etatActuel = `${etat.en_cours_id ?? ''}|${etat.prochaine?.id ?? ''}`;

            // Nouvelle vérification : la fille a pu commencer à taper pendant la requête.
            if (etatActuel !== etatInitial && !codeEnCoursDeSaisie()) {
                window.location.reload();
            }
        } catch {
            // Réseau indisponible : on réessaie au prochain tour.
        }
    }, INTERVALLE_MS);
});
