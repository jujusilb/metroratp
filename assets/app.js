import './styles/app.scss';
import 'bootstrap';
// Police libre hebergee localement (pas de CDN externe), la plus proche visuellement du
// style arrondi de la Parisine (police propriete RATP, non disponible sous licence libre).
import '@fontsource/baloo-2/700.css';
import { initStyleStationPicker } from './js/style-station-picker';
import { initTrajetGraph } from './js/trajet-graph';

document.addEventListener('DOMContentLoaded', () => {
    const styleStationDessertes = document.getElementById('style_station_dessertes');
    if (styleStationDessertes) {
        initStyleStationPicker(styleStationDessertes, {
            placeholder: '-- Ajouter une station --',
            removeLabel: 'Retirer',
        });
    }

    const trajetGrapheContainer = document.getElementById('trajet-graphe');
    if (trajetGrapheContainer && trajetGrapheContainer.dataset.graphe) {
        initTrajetGraph(trajetGrapheContainer, JSON.parse(trajetGrapheContainer.dataset.graphe));
    }
});