import L from 'leaflet';

/**
 * Libelle d'un bandeau "Sortie" (style plan de quartier RATP affiche sur les quais) : "Sortie N —
 * libelle", ou juste "Sortie — libelle" quand l'acces n'a pas de numero. Fonction pure, testable
 * sans DOM.
 *
 * @param {{ label: string, numero: ?string }} acces
 */
export function formaterLibelleSortie(acces) {
    return acces.numero ? `Sortie ${acces.numero} — ${acces.label}` : `Sortie — ${acces.label}`;
}

/**
 * Dessine la mini-carte des accès d'une Station (equivalent "fait maison" du plan de quartier
 * affiche sur les quais : pas de dataset ouvert pour le visuel RATP original, voir TODO.md) - la
 * Station au centre, un bandeau bleu par Acces connu (coordonnees reelles, GTFS) pointant vers sa
 * position. Fond de carte CARTO Positron (plus proche visuellement d'un plan que les tuiles OSM
 * standard : facades des batiments visibles, moins de bruit).
 *
 * @param {HTMLElement} mapContainer
 * @param {{ stationLat: ?number, stationLon: ?number, acces: Array<{ label: string, numero: ?string, lat: number, lon: number }> }} donnees
 */
export function initCarteAcces(mapContainer, donnees) {
    if (!mapContainer || !donnees || null === donnees.stationLat || null === donnees.stationLon) {
        return null;
    }

    const carte = L.map(mapContainer);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 20,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
    }).addTo(carte);

    L.circleMarker([donnees.stationLat, donnees.stationLon], {
        radius: 7,
        weight: 2,
        color: '#000',
        fillColor: '#fff',
        fillOpacity: 1,
    }).addTo(carte);

    const bounds = [[donnees.stationLat, donnees.stationLon]];
    for (const acces of donnees.acces) {
        bounds.push([acces.lat, acces.lon]);

        L.marker([acces.lat, acces.lon], {
            icon: L.divIcon({
                className: 'carte-acces-sortie',
                html: `<span class="badge text-white">${formaterLibelleSortie(acces)}</span>`,
                iconSize: null,
            }),
        }).addTo(carte);
    }

    if (bounds.length > 1) {
        carte.fitBounds(bounds, { padding: [50, 50] });
    } else {
        carte.setView([donnees.stationLat, donnees.stationLon], 17);
    }

    return carte;
}
