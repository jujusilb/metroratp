import './styles/app.scss';
import 'bootstrap';
// Police libre hebergee localement (pas de CDN externe), la plus proche visuellement du
// style arrondi de la Parisine (police propriete RATP, non disponible sous licence libre).
import '@fontsource/baloo-2/700.css';
import 'leaflet/dist/leaflet.css';
import { initStyleStationPicker } from './js/style-station-picker';
import { initTrajetCarte } from './js/trajet-carte';
import { initTrajetAutocomplete } from './js/trajet-autocomplete';

document.addEventListener('DOMContentLoaded', () => {
    const styleStationDessertes = document.getElementById('style_station_dessertes');
    if (styleStationDessertes) {
        initStyleStationPicker(styleStationDessertes, {
            placeholder: '-- Ajouter une station --',
            removeLabel: 'Retirer',
        });
    }

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
});