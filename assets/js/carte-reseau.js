import L from 'leaflet';
import { formaterBulleStation } from './carte-tooltip';

const COULEUR_PAR_MODE = {
    Métro: '#ffcd00',
    RER: '#ee3124',
    Tramway: '#00a88f',
    Train: '#8f4fa8',
    Bus: '#6c757d',
    Car: '#6c757d',
    Funiculaire: '#a45a2a',
    Téléphérique: '#a45a2a',
};

function couleurStation(dessertes) {
    for (const d of dessertes) {
        if (d.mode && 'Bus' !== d.mode && 'Car' !== d.mode) {
            return COULEUR_PAR_MODE[d.mode] || '#6c757d';
        }
    }

    return COULEUR_PAR_MODE.Bus;
}

/**
 * Dessine la carte complete du reseau (toutes les stations, tous modes) avec, au survol d'une
 * station, une bulle listant chaque ligne qui la dessert ("Mode:Ligne:Arret" ou
 * "Mode:Gestionnaire:Ligne:Arret").
 *
 * @param {HTMLElement} mapContainer
 * @param {Array<{ id: number, label: string, lat: number, lon: number, dessertes: Array }>} stations
 */
export function initCarteReseau(mapContainer, stations) {
    if (!mapContainer || !stations || 0 === stations.length) {
        return null;
    }

    const carte = L.map(mapContainer, { renderer: L.canvas() });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(carte);

    for (const station of stations) {
        // Les stations desservies par un mode lourd (metro/RER/tram/train) ressortent un peu plus
        // (rayon, couleur) qu'un simple arret de bus, pour rester lisible a l'echelle regionale.
        const modeLourd = station.dessertes.some((d) => d.mode && !['Bus', 'Car'].includes(d.mode));

        L.circleMarker([station.lat, station.lon], {
            radius: modeLourd ? 5 : 3,
            weight: 1,
            color: '#333',
            fillColor: couleurStation(station.dessertes),
            fillOpacity: 0.9,
        })
            .bindTooltip(formaterBulleStation(station.label, station.dessertes), { sticky: true })
            .addTo(carte);
    }

    carte.setView([48.8566, 2.3522], 11);

    return carte;
}
