import L from 'leaflet';
import { construireLignesUniques } from './carte-tooltip';

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

/**
 * Regroupe les modes fins (Ligne::typeTransport.label) dans les 5 cases a cocher du filtre de la
 * carte (Metro/RER/Tram/Bus/Autres). "Autres" evite qu'un mode absent de la liste explicite
 * demandee par l'utilisateur (Train, Funiculaire, Telepherique...) ne devienne impossible a
 * afficher. Fonction pure, testable sans DOM.
 *
 * @param {?string} mode
 */
export function bucketPourMode(mode) {
    switch (mode) {
        case 'Métro':
            return 'metro';
        case 'RER':
            return 'rer';
        case 'Tramway':
            return 'tram';
        case 'Bus':
        case 'Car':
            return 'bus';
        default:
            return 'autres';
    }
}

/**
 * @param {Array<{ mode: ?string }>} dessertes
 * @return {Set<string>}
 */
export function bucketsPourDessertes(dessertes) {
    return new Set(dessertes.map((d) => bucketPourMode(d.mode)));
}

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
 * station, une bulle listant chaque ligne qui la dessert ("Mode:Ligne:Arret"). Chaque ligne de la
 * bulle est interactive : la survoler met en surbrillance (aperçu leger) le trace reel de cette
 * Ligne sur la carte, cliquer dessus fixe la surbrillance (plus marquee, reste affichee) - de quoi
 * identifier a quelle ligne appartient un arret en cas de correspondance multiple. Un clic sur le
 * fond de carte efface la surbrillance fixee.
 *
 * @param {HTMLElement} mapContainer
 * @param {Array<{ id: number, label: string, lat: number, lon: number, dessertes: Array }>} stations
 * @param {{ filtreContainer: ?HTMLElement, traceUrlTemplate: ?string }} options
 */
export function initCarteReseau(mapContainer, stations, options = {}) {
    if (!mapContainer || !stations || 0 === stations.length) {
        return null;
    }

    const { filtreContainer = null, traceUrlTemplate = null } = options;

    const carte = L.map(mapContainer, { renderer: L.canvas() });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(carte);

    const coucheApercu = L.layerGroup().addTo(carte);
    const coucheConfirmee = L.layerGroup().addTo(carte);
    const cacheTraces = new Map();

    async function obtenirTrace(ligneId) {
        if (cacheTraces.has(ligneId)) {
            return cacheTraces.get(ligneId);
        }
        if (!traceUrlTemplate) {
            return null;
        }

        let donnees = null;
        try {
            const reponse = await fetch(traceUrlTemplate.replace('/0/trace', `/${ligneId}/trace`));
            if (reponse.ok) {
                donnees = await reponse.json();
            }
        } catch {
            donnees = null;
        }
        cacheTraces.set(ligneId, donnees);

        return donnees;
    }

    function dessinerTrace(couche, donneesTrace, styleTrait) {
        couche.clearLayers();
        if (!donneesTrace || !donneesTrace.trace) {
            return;
        }
        for (const composante of donneesTrace.trace) {
            L.polyline(composante.map(([lon, lat]) => [lat, lon]), {
                color: donneesTrace.couleur || '#0d6efd',
                ...styleTrait,
            }).addTo(couche);
        }
    }

    carte.on('click', () => coucheConfirmee.clearLayers());

    carte.on('tooltipopen', (e) => {
        const element = e.tooltip.getElement();
        if (!element) {
            return;
        }
        for (const ligne of element.querySelectorAll('[data-ligne-id]')) {
            const ligneId = Number(ligne.dataset.ligneId);
            ligne.addEventListener('mouseenter', async () => {
                dessinerTrace(coucheApercu, await obtenirTrace(ligneId), { weight: 6, opacity: 0.55 });
            });
            ligne.addEventListener('mouseleave', () => coucheApercu.clearLayers());
            ligne.addEventListener('click', async (evt) => {
                evt.stopPropagation();
                coucheApercu.clearLayers();
                dessinerTrace(coucheConfirmee, await obtenirTrace(ligneId), { weight: 6, opacity: 0.9 });
            });
        }
    });
    carte.on('tooltipclose', () => coucheApercu.clearLayers());

    // Cases decochees par defaut (voir templates/carte/index.html.twig) : seuls les buckets deja
    // coches au moment de l'initialisation (la carte n'est construite qu'a la premiere ouverture
    // du modal, pas au chargement de la page) sont ajoutes d'emblee - pas de filtre => tout visible
    // (comportement de repli si aucun conteneur de filtre n'est fourni).
    const cases = filtreContainer ? [...filtreContainer.querySelectorAll('input[type="checkbox"][data-bucket]')] : [];
    const bucketsActifs = () => new Set(cases.filter((c) => c.checked).map((c) => c.dataset.bucket));
    const actifsInitial = cases.length > 0 ? bucketsActifs() : null;

    const marqueurs = [];
    for (const station of stations) {
        // Les stations desservies par un mode lourd (metro/RER/tram/train) ressortent un peu plus
        // (rayon, couleur) qu'un simple arret de bus, pour rester lisible a l'echelle regionale.
        const modeLourd = station.dessertes.some((d) => d.mode && !['Bus', 'Car'].includes(d.mode));
        const lignes = construireLignesUniques(station.dessertes, station.label);
        const contenuBulle = lignes
            .map((l) => `<div class="carte-bulle-ligne" data-ligne-id="${l.ligneId}">${l.texte}</div>`)
            .join('');
        const buckets = bucketsPourDessertes(station.dessertes);

        const marker = L.circleMarker([station.lat, station.lon], {
            radius: modeLourd ? 5 : 3,
            weight: 1,
            color: '#333',
            fillColor: couleurStation(station.dessertes),
            fillOpacity: 0.9,
        })
            .bindTooltip(contenuBulle, { sticky: true, interactive: true })
        ;

        if (null === actifsInitial || [...buckets].some((b) => actifsInitial.has(b))) {
            marker.addTo(carte);
        }

        marqueurs.push({ marker, buckets });
    }

    if (cases.length > 0) {
        const appliquerFiltre = () => {
            const actifs = bucketsActifs();
            for (const { marker, buckets } of marqueurs) {
                const visible = [...buckets].some((b) => actifs.has(b));
                const surLaCarte = carte.hasLayer(marker);
                if (visible && !surLaCarte) {
                    marker.addTo(carte);
                } else if (!visible && surLaCarte) {
                    carte.removeLayer(marker);
                }
            }
        };
        for (const c of cases) {
            c.addEventListener('change', appliquerFiltre);
        }
    }

    carte.setView([48.8566, 2.3522], 11);

    return carte;
}
