/**
 * Transforme un champ texte en autocompletion de station : tape "chat", l'utilisateur voit une
 * liste deroulante deduplique (Chatelet, Chateau Landon, Chateau Rouge...) au lieu d'un <select>
 * geant avec une entree par ligne desservant chaque station (voir StationRepository::rechercherParLabel
 * cote backend, deja deduplique par lieu reel). Le choix d'une suggestion remplit un champ
 * cache avec l'id de la station, seul champ realement soumis avec le formulaire.
 *
 * Quand une station est desservie par plusieurs modes (ex: Nation en Metro + RER), une
 * suggestion secondaire par mode apparait sous la suggestion principale ("Nation" = tous modes,
 * "→ RER" = forcer l'entree/sortie par le RER precisement) : voir
 * TrajetFinder::trouverPlusCourtChemin, $modeEntreeOrigine/$modeEntreeDestination.
 *
 * Ne propose que des stations desservies par au moins un des modes actuellement coches (cases
 * "Modes de transport" du formulaire) : une station qui ne serait desservie que par un mode
 * decoche serait de toute facon exclue du calcul par TrajetFinder::dessertesIdsPourStation(),
 * donc la proposer serait un choix garanti sans trajet possible.
 *
 * @param {HTMLInputElement} inputTexte
 * @param {HTMLInputElement} inputCache
 * @param {HTMLInputElement} inputModeCache
 * @param {HTMLElement} conteneurSuggestions
 * @param {{ rechercheUrl: string, modeLabels?: Record<string, string>, delaiMs?: number, longueurMinimale?: number, obtenirModesCoches?: () => string[] }} options
 */
export function initTrajetAutocomplete(inputTexte, inputCache, inputModeCache, conteneurSuggestions, options = {}) {
    if (!inputTexte || !inputCache || !inputModeCache || !conteneurSuggestions || !options.rechercheUrl) {
        return;
    }

    const delaiMs = options.delaiMs ?? 200;
    const longueurMinimale = options.longueurMinimale ?? 2;
    const modeLabels = options.modeLabels ?? {};

    let requeteEnCours = 0;
    let timer = null;

    function effacerSuggestions() {
        conteneurSuggestions.innerHTML = '';
    }

    function libelleStation(station) {
        return station.ville ? station.label + ' (' + station.ville + ')' : station.label;
    }

    function choisir(station, mode, texteAffiche) {
        inputCache.value = station.id;
        inputModeCache.value = mode ?? '';
        inputTexte.value = texteAffiche;
        effacerSuggestions();
    }

    function creerLigneSuggestion(libelleMode, texteAffiche, onClick) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'list-group-item list-group-item-action suggestion-station';

        const pastilleMode = document.createElement('span');
        pastilleMode.className = 'suggestion-mode';
        pastilleMode.textContent = libelleMode;
        item.appendChild(pastilleMode);

        const texte = document.createElement('span');
        texte.className = 'suggestion-label';
        texte.textContent = texteAffiche;
        item.appendChild(texte);

        item.addEventListener('click', onClick);

        return item;
    }

    function afficherSuggestions(stations) {
        effacerSuggestions();

        stations.forEach((station) => {
            const texteBase = libelleStation(station);
            const modes = station.modes ?? [];

            if (modes.length <= 1) {
                // Un seul mode possible (parmi ceux coches) : pas d'ambiguite a lever, la ligne
                // "tous modes" (mode=null) et la ligne "ce mode precis" sont equivalentes pour le
                // calcul de trajet, donc on garde mode=null pour ne pas suffixer le texte affiche.
                const libelleMode = modes[0] ? (modeLabels[modes[0]] ?? modes[0]) : 'Tous';
                conteneurSuggestions.appendChild(
                    creerLigneSuggestion(libelleMode, texteBase, () => choisir(station, null, texteBase)),
                );
                return;
            }

            modes.forEach((mode) => {
                const modeLabel = modeLabels[mode] ?? mode;
                const texteAffiche = texteBase + ' — ' + modeLabel;
                conteneurSuggestions.appendChild(
                    creerLigneSuggestion(modeLabel, texteAffiche, () => choisir(station, mode, texteAffiche)),
                );
            });

            conteneurSuggestions.appendChild(
                creerLigneSuggestion('Tous', texteBase, () => choisir(station, null, texteBase)),
            );
        });
    }

    inputTexte.addEventListener('input', () => {
        // Toute frappe invalide le choix precedent : mieux vaut soumettre "aucune station"
        // (message d'erreur clair) qu'un id qui ne correspond plus au texte affiche.
        inputCache.value = '';
        inputModeCache.value = '';

        if (null !== timer) {
            clearTimeout(timer);
        }

        const recherche = inputTexte.value.trim();
        if (recherche.length < longueurMinimale) {
            effacerSuggestions();
            return;
        }

        timer = setTimeout(() => {
            const idRequete = ++requeteEnCours;

            const params = new URLSearchParams();
            params.set('q', recherche);
            const modesCoches = options.obtenirModesCoches ? options.obtenirModesCoches() : null;
            if (modesCoches) {
                modesCoches.forEach((mode) => params.append('modes[]', mode));
            }

            fetch(options.rechercheUrl + '?' + params.toString())
                .then((response) => response.json())
                .then((stations) => {
                    // Une recherche plus recente est peut-etre deja partie entre-temps : on
                    // ignore une reponse devenue obsolete pour ne pas ecraser l'affichage.
                    if (idRequete === requeteEnCours) {
                        afficherSuggestions(stations);
                    }
                });
        }, delaiMs);
    });

    document.addEventListener('click', (event) => {
        if (event.target !== inputTexte && !conteneurSuggestions.contains(event.target)) {
            effacerSuggestions();
        }
    });

    return { afficherSuggestions, effacerSuggestions };
}
