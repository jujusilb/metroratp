import L from 'leaflet';

/**
 * Determine, pour chaque troncon du reseau, s'il fait partie du trajet trouve (pour le mettre
 * en evidence). Fonction pure, testable sans DOM.
 *
 * @param {{ reseau: Array<{lat1:number,lon1:number,lat2:number,lon2:number}>, trajet: Array<{lat1:number,lon1:number,lat2:number,lon2:number,type:string}> }} donnees
 */
export function marquerTronconsConcernes(donnees) {
    const cles = new Set();
    for (const e of donnees.trajet) {
        cles.add([e.lat1, e.lon1, e.lat2, e.lon2].join(','));
        cles.add([e.lat2, e.lon2, e.lat1, e.lon1].join(','));
    }

    return donnees.reseau.map((t) => ({
        ...t,
        concerne: cles.has([t.lat1, t.lon1, t.lat2, t.lon2].join(',')),
    }));
}

/**
 * Dessine la carte du trajet (fond OpenStreetMap + reseau attenue + trajet surligne) dans le
 * conteneur DOM donne, avec zoom/deplacement natifs Leaflet. Construit aussi la legende numerotee
 * dans le conteneur donne.
 *
 * @param {HTMLElement} mapContainer
 * @param {HTMLElement} legendeContainer
 * @param {{ reseau: Array, trajet: Array }} donnees
 */
export function initTrajetCarte(mapContainer, legendeContainer, donnees) {
    if (!mapContainer || !donnees || !donnees.reseau || 0 === donnees.reseau.length) {
        return null;
    }

    const carte = L.map(mapContainer, { renderer: L.canvas() });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(carte);

    const reseauMarque = marquerTronconsConcernes(donnees);

    const stationsVues = new Set();
    for (const t of reseauMarque) {
        L.polyline([[t.lat1, t.lon1], [t.lat2, t.lon2]], {
            color: t.couleur,
            weight: t.concerne ? 5 : 2,
            opacity: t.concerne ? 1 : 0.35,
        }).addTo(carte);

        for (const [lat, lon] of [[t.lat1, t.lon1], [t.lat2, t.lon2]]) {
            const cle = `${lat},${lon}`;
            if (stationsVues.has(cle)) {
                continue;
            }
            stationsVues.add(cle);
            L.circleMarker([lat, lon], {
                radius: 3,
                color: '#6c757d',
                weight: 1,
                fillColor: '#fff',
                fillOpacity: 1,
            }).addTo(carte);
        }
    }

    for (const e of donnees.trajet) {
        if ('correspondance' !== e.type) {
            continue;
        }
        L.polyline([[e.lat1, e.lon1], [e.lat2, e.lon2]], {
            color: e.couleur,
            weight: 4,
            dashArray: '6 4',
        }).addTo(carte);
    }

    const stationsTrajet = new Map();
    for (const e of donnees.trajet) {
        if (!stationsTrajet.has(e.labelDepart)) {
            stationsTrajet.set(e.labelDepart, [e.lat1, e.lon1]);
        }
        if (!stationsTrajet.has(e.labelArrivee)) {
            stationsTrajet.set(e.labelArrivee, [e.lat2, e.lon2]);
        }
    }

    if (legendeContainer) {
        legendeContainer.innerHTML = '';
    }

    const bounds = [];
    let i = 1;
    for (const [label, [lat, lon]] of stationsTrajet) {
        bounds.push([lat, lon]);

        L.marker([lat, lon], {
            icon: L.divIcon({
                className: 'carte-station-numero',
                html: `<span>${i}</span>`,
                iconSize: [22, 22],
                iconAnchor: [11, 11],
            }),
        }).addTo(carte);

        if (legendeContainer) {
            const col = document.createElement('div');
            col.className = 'col d-flex gap-1 text-muted';
            const num = document.createElement('span');
            num.className = 'fw-medium text-body';
            num.textContent = `${i}.`;
            col.appendChild(num);
            col.appendChild(document.createTextNode(label));
            legendeContainer.appendChild(col);
        }

        i++;
    }

    if (bounds.length > 0) {
        carte.fitBounds(bounds, { padding: [30, 30] });
    } else {
        carte.setView([48.8566, 2.3522], 11);
    }

    return carte;
}
