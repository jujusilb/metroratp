import L from 'leaflet';
import { formaterLigneDesserte } from './carte-tooltip';

// Echelle approximative degres -> metres (latitude de reference Ile-de-France) : suffisant pour
// comparer des distances localement, pas pour une mesure absolue precise.
const COS_LATITUDE_IDF = Math.cos((48.85 * Math.PI) / 180);

function versMetres([lon, lat]) {
    return [lon * 111320 * COS_LATITUDE_IDF, lat * 111320];
}

/**
 * Projette un point sur le segment [a,b] (coordonnees [lon,lat]) : renvoie le point projete, sa
 * position parametrique t (0..1) et la distance au carre (en metres^2, pour comparaison).
 * Fonction pure, testable sans DOM.
 */
export function projeterSurSegment(p, a, b) {
    const [px, py] = versMetres(p);
    const [ax, ay] = versMetres(a);
    const [bx, by] = versMetres(b);
    const dx = bx - ax;
    const dy = by - ay;
    const longueurCarree = dx * dx + dy * dy;
    let t = 0 === longueurCarree ? 0 : ((px - ax) * dx + (py - ay) * dy) / longueurCarree;
    t = Math.max(0, Math.min(1, t));

    const proj = [a[0] + t * (b[0] - a[0]), a[1] + t * (b[1] - a[1])];
    const [projX, projY] = versMetres(proj);
    const distanceCarree = (px - projX) ** 2 + (py - projY) ** 2;

    return { proj, t, distanceCarree };
}

/**
 * Trouve le point de la ligne brisee (liste de [lon,lat]) le plus proche de p : renvoie l'index
 * du segment concerne, le point projete et la distance au carre. Fonction pure, testable sans DOM.
 */
export function projeterSurLigne(p, composante) {
    let meilleur = null;
    for (let i = 0; i < composante.length - 1; i++) {
        const r = projeterSurSegment(p, composante[i], composante[i + 1]);
        if (null === meilleur || r.distanceCarree < meilleur.distanceCarree) {
            meilleur = { index: i, proj: r.proj, distanceCarree: r.distanceCarree };
        }
    }

    return meilleur;
}

/**
 * Extrait la portion de la composante (liste de [lon,lat]) entre les deux projections donnees
 * (voir projeterSurLigne), dans l'ordre des index (peu importe lequel de projA/projB est "avant"
 * pour l'affichage : une polyligne se dessine pareil dans les deux sens). Fonction pure.
 */
export function extraireSousChemin(composante, projA, projB) {
    const [debut, fin] = projA.index <= projB.index ? [projA, projB] : [projB, projA];
    const points = [debut.proj];
    for (let i = debut.index + 1; i <= fin.index; i++) {
        points.push(composante[i]);
    }
    points.push(fin.proj);

    return points;
}

/**
 * Trouve, parmi les composantes du trace d'une Ligne (une Ligne peut avoir plusieurs
 * branches/variantes disjointes - voir Ligne::trace), celle qui passe le plus pres a la fois de A
 * et de B, puis extrait la portion du trace reel entre les deux. Renvoie null si aucune composante
 * n'est raisonnablement proche des deux points (le trace ne correspond pas a ce trajet).
 *
 * @param {Array<Array<[number, number]>>} traceLigne
 * @param {[number, number]} pointA [lon, lat]
 * @param {[number, number]} pointB [lon, lat]
 */
export function extraireTraceEntreDeuxPoints(traceLigne, pointA, pointB) {
    const DISTANCE_MAX_METRES = 150;

    let meilleur = null;
    for (const composante of traceLigne) {
        if (composante.length < 2) {
            continue;
        }
        const projA = projeterSurLigne(pointA, composante);
        const projB = projeterSurLigne(pointB, composante);
        const score = projA.distanceCarree + projB.distanceCarree;
        if (null === meilleur || score < meilleur.score) {
            meilleur = { composante, projA, projB, score };
        }
    }

    if (null === meilleur || meilleur.score > 2 * DISTANCE_MAX_METRES ** 2) {
        return null;
    }

    return extraireSousChemin(meilleur.composante, meilleur.projA, meilleur.projB);
}

/**
 * Dessine la carte du trajet (fond OpenStreetMap + uniquement les arrets/troncons traverses par
 * ce trajet - pas tout le reseau) dans le conteneur DOM donne, avec zoom/deplacement natifs
 * Leaflet. Chaque station porte une bulle (voir carte-tooltip.js) listant les lignes empruntees a
 * cet arret, qui s'ouvre au CLIC sur le numero (pas au survol - trop intrusif avec plusieurs
 * arrets proches). Une fois la bulle ouverte, survoler une ligne met en surbrillance le trace
 * emprunte par cette ligne sur ce trajet ; cliquer dessus suit le lien vers le detail de la
 * Desserte. Construit aussi la legende numerotee dans le conteneur donne.
 *
 * @param {HTMLElement} mapContainer
 * @param {HTMLElement} legendeContainer
 * @param {{ trajet: Array, tracesLignes: Object<string, Array>, stationsInfo: Object<string, Array> }} donnees
 */
export function initTrajetCarte(mapContainer, legendeContainer, donnees) {
    if (!mapContainer || !donnees || !donnees.trajet || 0 === donnees.trajet.length) {
        return null;
    }

    const carte = L.map(mapContainer, { renderer: L.canvas() });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(carte);

    const coucheSurbrillance = L.layerGroup().addTo(carte);
    const segmentsParLigne = new Map();

    const tracesLignes = donnees.tracesLignes || {};
    for (const e of donnees.trajet) {
        if ('correspondance' === e.type) {
            L.polyline([[e.lat1, e.lon1], [e.lat2, e.lon2]], {
                color: e.couleur,
                weight: 4,
                dashArray: '6 4',
            }).addTo(carte);
            continue;
        }

        // Trace reel de la Ligne (suit les rues/rails) quand il est connu, sinon un trait direct
        // entre les deux stations.
        const traceLigne = tracesLignes[e.ligneId];
        const sousChemin = traceLigne ? extraireTraceEntreDeuxPoints(traceLigne, [e.lon1, e.lat1], [e.lon2, e.lat2]) : null;
        const points = sousChemin ? sousChemin.map(([lon, lat]) => [lat, lon]) : [[e.lat1, e.lon1], [e.lat2, e.lon2]];

        L.polyline(points, {
            color: e.couleur,
            weight: 5,
            opacity: 1,
        }).addTo(carte);

        if (!segmentsParLigne.has(e.ligneId)) {
            segmentsParLigne.set(e.ligneId, []);
        }
        segmentsParLigne.get(e.ligneId).push(points);
    }

    carte.on('popupopen', (evt) => {
        const element = evt.popup.getElement();
        if (!element) {
            return;
        }
        for (const ligne of element.querySelectorAll('[data-ligne-id]')) {
            const ligneId = Number(ligne.dataset.ligneId);
            ligne.addEventListener('mouseenter', () => {
                coucheSurbrillance.clearLayers();
                for (const points of segmentsParLigne.get(ligneId) || []) {
                    L.polyline(points, { color: '#000', weight: 9, opacity: 0.35 }).addTo(coucheSurbrillance);
                }
            });
            ligne.addEventListener('mouseleave', () => coucheSurbrillance.clearLayers());
        }
    });
    carte.on('popupclose', () => coucheSurbrillance.clearLayers());

    const stationsInfo = donnees.stationsInfo || {};
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

        const marqueur = L.marker([lat, lon], {
            icon: L.divIcon({
                className: 'carte-station-numero',
                html: `<span>${i}</span>`,
                iconSize: [22, 22],
                iconAnchor: [11, 11],
            }),
        }).addTo(carte);

        const dessertes = stationsInfo[label];
        if (dessertes && dessertes.length > 0) {
            const contenuBulle = dessertes
                .map((d) => (d.desserteUrl
                    ? `<a href="${d.desserteUrl}" class="carte-bulle-ligne" data-ligne-id="${d.ligneId}">${formaterLigneDesserte(d, label)}</a>`
                    : `<div>${formaterLigneDesserte(d, label)}</div>`))
                .join('');
            marqueur.bindPopup(contenuBulle);
        }

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
