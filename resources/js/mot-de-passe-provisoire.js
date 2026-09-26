const JEUX = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%&*?-_+='];

function aleatoire(max) {
    const tampon = new Uint32Array(1);
    const limite = Math.floor(0x100000000 / max) * max; // rejet des tirages biaisés

    do {
        crypto.getRandomValues(tampon);
    } while (tampon[0] >= limite);

    return tampon[0] % max;
}

export function genererMotDePasse(longueur = 10) {
    const tous = JEUX.join('');
    const caracteres = JEUX.map((jeu) => jeu[aleatoire(jeu.length)]);

    while (caracteres.length < longueur) {
        caracteres.push(tous[aleatoire(tous.length)]);
    }

    for (let i = caracteres.length - 1; i > 0; i -= 1) {
        const j = aleatoire(i + 1);
        [caracteres[i], caracteres[j]] = [caracteres[j], caracteres[i]];
    }

    return caracteres.join('');
}

document.addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest('[data-generer-mot-de-passe]');

    if (!bouton) {
        return;
    }

    const champ = document.getElementById(bouton.dataset.genererMotDePasse);

    if (champ) {
        champ.value = genererMotDePasse();
    }
});
