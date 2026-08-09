import './styles/app.scss';
import 'bootstrap';
// Police libre hebergee localement (pas de CDN externe), la plus proche visuellement du
// style arrondi de la Parisine (police propriete RATP, non disponible sous licence libre).
import '@fontsource/baloo-2/700.css';
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
        initTrajetAutocomplete(
            document.getElementById('origine-recherche'),
            document.getElementById('origine'),
            document.getElementById('origine-mode'),
            document.getElementById('origine-suggestions'),
            { rechercheUrl, modeLabels },
        );
        initTrajetAutocomplete(
            document.getElementById('destination-recherche'),
            document.getElementById('destination'),
            document.getElementById('destination-mode'),
            document.getElementById('destination-suggestions'),
            { rechercheUrl, modeLabels },
        );
    }

    const trajetCarteContainer = document.getElementById('trajet-carte');
    if (trajetCarteContainer && trajetCarteContainer.dataset.carte) {
        const svg = document.getElementById('trajet-carte-svg');
        const legende = document.getElementById('trajet-carte-legende');
        initTrajetCarte(svg, legende, JSON.parse(trajetCarteContainer.dataset.carte));
    }
});