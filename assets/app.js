import './styles/app.scss';
import 'bootstrap';
// Police libre hebergee localement (pas de CDN externe), la plus proche visuellement du
// style arrondi de la Parisine (police propriete RATP, non disponible sous licence libre).
import '@fontsource/baloo-2/700.css';
import 'leaflet/dist/leaflet.css';
import { initStyleStationPicker } from './js/style-station-picker';
import { initTrajetCarte } from './js/trajet-carte';
import { initTrajetAutocomplete } from './js/trajet-autocomplete';
import { initCarteReseau } from './js/carte-reseau';
import { initCarteAcces } from './js/carte-acces';
import { initCollectionWidget } from './js/collection-widget';

function auChargement(fn) {
    // Garde-fou standard : si 'DOMContentLoaded' a deja ete emis avant que ce script ne
    // s'execute, un simple addEventListener le raterait silencieusement.
    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', fn);
    } else {
        fn();
    }
}

auChargement(() => {
    const styleStationDessertes = document.getElementById('style_station_dessertes');
    if (styleStationDessertes) {
        initStyleStationPicker(styleStationDessertes, {
            placeholder: '-- Ajouter une station --',
            removeLabel: 'Retirer',
        });
    }

    // Formulaires imbriques (relations datees Materiel<->Ligne/Depot, Depot<->Ligne/Gestionnaire) :
    // remplacent les anciennes pages CRUD dediees (voir TODO.md, simplification des tables de
    // jointure). Chaque conteneur n'existe que sur la page ou son champ correspondant est present.
    [
        ['depot_depotGestionnaires', 'Ajouter un gestionnaire'],
        ['depot_depotLignes', 'Ajouter une ligne'],
        ['depot_materielDepots', 'Ajouter un matériel'],
        ['materiel_materielLignes', 'Ajouter une ligne'],
        ['ligne_horaireLignes', 'Ajouter une plage horaire'],
    ].forEach(([id, addButtonLabel]) => {
        const conteneur = document.getElementById(id);
        if (conteneur) {
            initCollectionWidget(conteneur, { addButtonLabel });
        }
    });

    const trajetForm = document.querySelector('form[data-recherche-station-url]');
    if (trajetForm) {
        const rechercheUrl = trajetForm.dataset.rechercheStationUrl;
        const modeLabels = trajetForm.dataset.modeLabels ? JSON.parse(trajetForm.dataset.modeLabels) : {};
        // Lu a chaque recherche (pas une seule fois au chargement) : l'utilisateur peut cocher/
        // decocher des modes avant de taper dans le champ de gare.
        const obtenirModesCoches = () => Array.from(trajetForm.querySelectorAll('input[name="modes[]"]:checked')).map((c) => c.value);

        initTrajetAutocomplete(
            document.getElementById('origine-recherche'),
            document.getElementById('origine'),
            document.getElementById('origine-mode'),
            document.getElementById('origine-suggestions'),
            { rechercheUrl, modeLabels, obtenirModesCoches },
        );
        initTrajetAutocomplete(
            document.getElementById('destination-recherche'),
            document.getElementById('destination'),
            document.getElementById('destination-mode'),
            document.getElementById('destination-suggestions'),
            { rechercheUrl, modeLabels, obtenirModesCoches },
        );
    }

    // La carte s'affiche dans une modale (masquee par defaut) : Leaflet calcule sa taille a la
    // creation, donc l'initialiser pendant que la modale est encore cachee produirait une carte
    // ratatinee dans un coin. On attend sa premiere ouverture, puis on recalcule la taille
    // (invalidateSize) a chaque reouverture au cas ou la fenetre aurait change de dimensions.
    const carteModal = document.getElementById('carte-modal');
    if (carteModal) {
        const trajetCarteContainer = document.getElementById('trajet-carte');
        let carte = null;
        carteModal.addEventListener('shown.bs.modal', () => {
            if (!carte) {
                const carteDiv = document.getElementById('trajet-carte-map');
                const legende = document.getElementById('trajet-carte-legende');
                carte = initTrajetCarte(carteDiv, legende, JSON.parse(trajetCarteContainer.dataset.carte));
            } else {
                carte.invalidateSize();
            }
        });
    }

    // Meme principe que carte-modal ci-dessus : la carte des sorties ne se construit qu'a la
    // premiere ouverture de son modal (auparavant affichee en permanence dans la page, "prend
    // trop de place" - meme retour que celui deja recu sur la carte du trajet).
    const carteAccesContainer = document.getElementById('carte-acces');
    const carteAccesModal = document.getElementById('carte-acces-modal');
    if (carteAccesContainer && carteAccesModal) {
        let carteAcces = null;
        carteAccesModal.addEventListener('shown.bs.modal', () => {
            if (!carteAcces) {
                carteAcces = initCarteAcces(document.getElementById('carte-acces-map'), JSON.parse(carteAccesContainer.dataset.donnees));
            } else {
                carteAcces.invalidateSize();
            }
        });
    }

    // Meme principe que carte-modal ci-dessus : la carte du reseau ne se construit qu'a la
    // premiere ouverture de son modal (les cases de filtre, elles, restent hors du modal et
    // fonctionnent des le chargement de la page - c'est leur etat au moment de l'ouverture qui
    // determine ce qui s'affiche d'emblee, voir initCarteReseau).
    const carteReseauContainer = document.getElementById('carte-reseau');
    const carteReseauModal = document.getElementById('carte-reseau-modal');
    if (carteReseauContainer && carteReseauModal) {
        let carteReseau = null;
        carteReseauModal.addEventListener('shown.bs.modal', () => {
            if (!carteReseau) {
                carteReseau = initCarteReseau(
                    document.getElementById('carte-reseau-map'),
                    JSON.parse(carteReseauContainer.dataset.donnees),
                    {
                        filtreContainer: document.getElementById('carte-reseau-filtres'),
                        traceUrlTemplate: carteReseauContainer.dataset.traceUrl,
                    },
                );
            } else {
                carteReseau.invalidateSize();
            }
        });
    }

    // Carte des secteurs : le PDF choisi est charge dans l'<object> seulement a l'ouverture du
    // modal (pas au chargement de la page, pour ne jamais telecharger un PDF inutilement).
    const carteSecteurModal = document.getElementById('carte-secteur-modal');
    if (carteSecteurModal) {
        const select = document.getElementById('carte-secteur-select');
        const objet = document.getElementById('carte-secteur-object');
        const lienSecours = document.getElementById('carte-secteur-lien-secours');
        const lienTelecharger = document.getElementById('carte-secteur-lien-telecharger');
        carteSecteurModal.addEventListener('show.bs.modal', () => {
            const url = select.value;
            objet.setAttribute('data', url);
            lienSecours.setAttribute('href', url);
            lienTelecharger.setAttribute('href', url);
        });
    }
});
