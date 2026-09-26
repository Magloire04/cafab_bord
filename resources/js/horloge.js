// Affiche l'heure du serveur (et non celle du PC) : l'écart est mesuré au
// chargement de la page, puis l'horloge avance chaque seconde.
function formater(date, fuseau) {
    const jour = new Intl.DateTimeFormat('fr-FR', {
        timeZone: fuseau,
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
    const heure = new Intl.DateTimeFormat('fr-FR', {
        timeZone: fuseau,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    }).format(date);

    return `${jour} · ${heure}`;
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-horloge]').forEach((horloge) => {
        const ecart = Number(horloge.dataset.serveurMs) - Date.now();
        const fuseau = horloge.dataset.fuseau;
        const tic = () => {
            horloge.textContent = formater(new Date(Date.now() + ecart), fuseau);
        };

        tic();
        setInterval(tic, 1000);
    });
});
