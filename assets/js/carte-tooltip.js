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
