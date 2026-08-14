/**
 * Formate une ligne de la bulle au survol d'une station : "Mode:Ligne:Arret", ou
 * "Mode:Gestionnaire:Ligne:Arret" quand le gestionnaire n'est pas RATP (implicite sinon - voir
 * Ligne::getModeFiltre() qui fait la meme distinction bus_ratp/bus_tiers cote backend).
 *
 * @param {{ mode: ?string, ligne: string, gestionnaire: ?string }} desserte
 * @param {string} labelArret
 */
export function formaterLigneDesserte(desserte, labelArret) {
    const parties = [desserte.mode || '?'];
    if (desserte.gestionnaire) {
        parties.push(desserte.gestionnaire);
    }
    parties.push(desserte.ligne);
    parties.push(labelArret);

    return parties.join(':');
}

/**
 * Construit le contenu HTML de la bulle pour une station (une ligne par desserte, dedupliquee).
 *
 * @param {string} label
 * @param {Array<{ mode: ?string, ligne: string, gestionnaire: ?string }>} dessertes
 */
export function formaterBulleStation(label, dessertes) {
    const lignes = [...new Set(dessertes.map((d) => formaterLigneDesserte(d, label)))];

    return lignes.join('<br>');
}

/**
 * Variante de formaterBulleStation qui garde le ligneId de chaque entree (dedupliquee sur
 * ligneId+texte) : sert a la carte du reseau pour rendre chaque ligne de la bulle interactive
 * (survol/clic -> surbrillance du trace de cette Ligne). Fonction pure, testable sans DOM.
 *
 * @param {string} label
 * @param {Array<{ mode: ?string, ligne: string, ligneId: number, gestionnaire: ?string }>} dessertes
 * @return {Array<{ ligneId: number, texte: string }>}
 */
export function construireLignesUniques(dessertes, label) {
    const vues = new Set();
    const lignes = [];
    for (const d of dessertes) {
        const texte = formaterLigneDesserte(d, label);
        const cle = `${d.ligneId}|${texte}`;
        if (!vues.has(cle)) {
            vues.add(cle);
            lignes.push({ ligneId: d.ligneId, texte });
        }
    }

    return lignes;
}
