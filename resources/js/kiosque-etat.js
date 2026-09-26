const INTERVALLE_MS = 30000;

document.addEventListener('DOMContentLoaded', () => {
    const racine = document.querySelector('[data-kiosque-etat]');

    if (!racine) {
        return;
    }

    const etatInitial = `${racine.dataset.enCoursId}|${racine.dataset.prochaineId}`;

    setInterval(async () => {
        // Ne jamais recharger pendant qu'une fille tape son code.
        const champCode = document.querySelector('input[name="pin"]');
        if (champCode && champCode.value !== '') {
            return;
        }

        try {
            const reponse = await fetch(racine.dataset.url, { headers: { Accept: 'application/json' } });

            if (!reponse.ok) {
                return;
            }

            const etat = await reponse.json();
            const etatActuel = `${etat.en_cours_id ?? ''}|${etat.prochaine?.id ?? ''}`;

            if (etatActuel !== etatInitial) {
                window.location.reload();
            }
        } catch {
            // Réseau indisponible : on réessaie au prochain tour.
        }
    }, INTERVALLE_MS);
});
