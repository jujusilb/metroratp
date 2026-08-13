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
 * Dessine la carte du trajet (fond OpenStreetMap + reseau attenue + trajet surligne) dans le
 * conteneur DOM donne, avec zoom/deplacement natifs Leaflet. Construit aussi la legende numerotee
 * dans le conteneur donne.
 *
 * @param {HTMLElement} mapContainer
 * @param {HTMLElement} legendeContainer
 * @param {{ reseau: Array, trajet: Array, tracesLignes: Object<string, Array> }} donnees
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

        // Par-dessus la ligne droite deja dessinee par le fond de reseau (meme couleur) : quand
        // le trace reel de la Ligne est connu, on affiche le trajet suivant vraiment les rues/
        // rails plutot qu'un trait direct entre les deux stations.
        const traceLigne = tracesLignes[e.ligneId];
        if (!traceLigne) {
            continue;
        }
        const sousChemin = extraireTraceEntreDeuxPoints(traceLigne, [e.lon1, e.lat1], [e.lon2, e.lat2]);
        if (!sousChemin) {
            continue;
        }
        L.polyline(sousChemin.map(([lon, lat]) => [lat, lon]), {
            color: e.couleur,
            weight: 5,
            opacity: 1,
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
