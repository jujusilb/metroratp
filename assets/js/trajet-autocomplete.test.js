import { initTrajetAutocomplete } from './trajet-autocomplete';

const MODE_LABELS = { metro: 'Métro', rer: 'RER', tram: 'Tram' };

function buildDom() {
    const inputTexte = document.createElement('input');
    inputTexte.type = 'text';
    document.body.appendChild(inputTexte);

    const inputCache = document.createElement('input');
    inputCache.type = 'hidden';
    document.body.appendChild(inputCache);

    const inputModeCache = document.createElement('input');
    inputModeCache.type = 'hidden';
    document.body.appendChild(inputModeCache);

    const conteneurSuggestions = document.createElement('div');
    document.body.appendChild(conteneurSuggestions);

    return { inputTexte, inputCache, inputModeCache, conteneurSuggestions };
}

function mockFetchJson(payload) {
    global.fetch = jest.fn(() => Promise.resolve({
        json: () => Promise.resolve(payload),
    }));
}

afterEach(() => {
    document.body.innerHTML = '';
    jest.useRealTimers();
    delete global.fetch;
});

describe('initTrajetAutocomplete', () => {
    test('ne fait rien si un element ou rechercheUrl manque', () => {
        const { inputTexte, inputCache, inputModeCache, conteneurSuggestions } = buildDom();

        expect(() => initTrajetAutocomplete(null, inputCache, inputModeCache, conteneurSuggestions, { rechercheUrl: '/x' })).not.toThrow();
        expect(() => initTrajetAutocomplete(inputTexte, inputCache, inputModeCache, conteneurSuggestions, {})).not.toThrow();
    });

    test('une frappe trop courte ne declenche pas de requete', () => {
        const { inputTexte, inputCache, inputModeCache, conteneurSuggestions } = buildDom();
        mockFetchJson([]);
        jest.useFakeTimers();

        initTrajetAutocomplete(inputTexte, inputCache, inputModeCache, conteneurSuggestions, { rechercheUrl: '/trajet/recherche-station' });

        inputTexte.value = 'c';
        inputTexte.dispatchEvent(new Event('input'));
        jest.runAllTimers();

        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('une frappe suffisante interroge le backend apres le delai et affiche les suggestions', async () => {
        const { inputTexte, inputCache, inputModeCache, conteneurSuggestions } = buildDom();
        mockFetchJson([
            { id: 12, label: 'Châtelet', ville: 'Paris', modes: ['metro'] },
            { id: 34, label: 'Château Landon', ville: 'Paris', modes: ['metro'] },
        ]);
        jest.useFakeTimers();

        initTrajetAutocomplete(inputTexte, inputCache, inputModeCache, conteneurSuggestions, { rechercheUrl: '/trajet/recherche-station' });

        inputTexte.value = 'chat';
        inputTexte.dispatchEvent(new Event('input'));
        await jest.advanceTimersByTimeAsync(200);

        expect(global.fetch).toHaveBeenCalledWith('/trajet/recherche-station?q=chat');
        // 1 station = 1 ligne (un seul mode chacune, pas d'ambiguite a lever).
        expect(conteneurSuggestions.children.length).toBe(2);
        expect(conteneurSuggestions.textContent).toContain('Châtelet (Paris)');
        expect(conteneurSuggestions.textContent).toContain('Château Landon (Paris)');
    });

    test('une station a un seul mode affiche ce mode a gauche (pas "Tous")', async () => {
        const { inputTexte, inputCache, inputModeCache, conteneurSuggestions } = buildDom();
        mockFetchJson([{ id: 12, label: 'Châtelet', ville: 'Paris', modes: ['metro'] }]);
        jest.useFakeTimers();

        initTrajetAutocomplete(inputTexte, inputCache, inputModeCache, conteneurSuggestions, {
            rechercheUrl: '/trajet/recherche-station',
            modeLabels: MODE_LABELS,
        });

        inputTexte.value = 'chat';
        inputTexte.dispatchEvent(new Event('input'));
        await jest.advanceTimersByTimeAsync(200);

        const pastille = conteneurSuggestions.querySelector('.suggestion-mode');
        expect(pastille.textContent).toBe('Métro');
    });

    test('transmet les modes actuellement coches a la recherche', async () => {
        const { inputTexte, inputCache, inputModeCache, conteneurSuggestions } = buildDom();
        mockFetchJson([]);
        jest.useFakeTimers();

        initTrajetAutocomplete(inputTexte, inputCache, inputModeCache, conteneurSuggestions, {
            rechercheUrl: '/trajet/recherche-station',
            obtenirModesCoches: () => ['metro', 'rer'],
        });

        inputTexte.value = 'chat';
        inputTexte.dispatchEvent(new Event('input'));
        await jest.advanceTimersByTimeAsync(200);

        expect(global.fetch).toHaveBeenCalledWith('/trajet/recherche-station?q=chat&modes%5B%5D=metro&modes%5B%5D=rer');
    });

    test('choisir une suggestion remplit le champ cache et le champ texte, puis vide les suggestions', async () => {
        const { inputTexte, inputCache, inputModeCache, conteneurSuggestions } = buildDom();
        mockFetchJson([{ id: 12, label: 'Châtelet', ville: 'Paris', modes: ['metro'] }]);
        jest.useFakeTimers();

        initTrajetAutocomplete(inputTexte, inputCache, inputModeCache, conteneurSuggestions, { rechercheUrl: '/trajet/recherche-station' });

        inputTexte.value = 'chat';
        inputTexte.dispatchEvent(new Event('input'));
        await jest.advanceTimersByTimeAsync(200);

        conteneurSuggestions.querySelector('button').dispatchEvent(new Event('click', { bubbles: true }));

        expect(inputCache.value).toBe('12');
        expect(inputModeCache.value).toBe('');
        expect(inputTexte.value).toBe('Châtelet (Paris)');
        expect(conteneurSuggestions.children.length).toBe(0);
    });

    test('une station a plusieurs modes affiche une ligne par mode, en plus d\'une ligne "Tous"', async () => {
        const { inputTexte, inputCache, inputModeCache, conteneurSuggestions } = buildDom();
        mockFetchJson([{ id: 7, label: 'Nation', ville: null, modes: ['metro', 'rer'] }]);
        jest.useFakeTimers();

        initTrajetAutocomplete(inputTexte, inputCache, inputModeCache, conteneurSuggestions, {
            rechercheUrl: '/trajet/recherche-station',
            modeLabels: MODE_LABELS,
        });

        inputTexte.value = 'nation';
        inputTexte.dispatchEvent(new Event('input'));
        await jest.advanceTimersByTimeAsync(200);

        // 1 ligne par mode (Metro, RER) + 1 ligne "Tous".
        expect(conteneurSuggestions.children.length).toBe(3);
        expect(conteneurSuggestions.textContent).toContain('Nation — Métro');
        expect(conteneurSuggestions.textContent).toContain('Nation — RER');

        const pastilles = Array.from(conteneurSuggestions.querySelectorAll('.suggestion-mode')).map((p) => p.textContent);
        expect(pastilles).toEqual(['Métro', 'RER', 'Tous']);
    });

    test('choisir un mode precis remplit le champ mode cache et suffixe le texte affiche', async () => {
        const { inputTexte, inputCache, inputModeCache, conteneurSuggestions } = buildDom();
        mockFetchJson([{ id: 7, label: 'Nation', ville: null, modes: ['metro', 'rer'] }]);
        jest.useFakeTimers();

        initTrajetAutocomplete(inputTexte, inputCache, inputModeCache, conteneurSuggestions, {
            rechercheUrl: '/trajet/recherche-station',
            modeLabels: MODE_LABELS,
        });

        inputTexte.value = 'nation';
        inputTexte.dispatchEvent(new Event('input'));
        await jest.advanceTimersByTimeAsync(200);

        const boutonRer = Array.from(conteneurSuggestions.querySelectorAll('button')).find((b) => b.textContent.includes('RER'));
        boutonRer.dispatchEvent(new Event('click', { bubbles: true }));

        expect(inputCache.value).toBe('7');
        expect(inputModeCache.value).toBe('rer');
        expect(inputTexte.value).toBe('Nation — RER');
        expect(conteneurSuggestions.children.length).toBe(0);
    });

    test('retaper apres un choix vide le champ cache et le champ mode (evite de soumettre un id/mode perime)', () => {
        const { inputTexte, inputCache, inputModeCache, conteneurSuggestions } = buildDom();
        mockFetchJson([]);
        jest.useFakeTimers();

        initTrajetAutocomplete(inputTexte, inputCache, inputModeCache, conteneurSuggestions, { rechercheUrl: '/trajet/recherche-station' });

        inputCache.value = '12';
        inputModeCache.value = 'rer';
        inputTexte.value = 'Châtelet (Paris)X';
        inputTexte.dispatchEvent(new Event('input'));

        expect(inputCache.value).toBe('');
        expect(inputModeCache.value).toBe('');
    });

    test('une reponse obsolete (recherche depassee par une plus recente) est ignoree', async () => {
        const { inputTexte, inputCache, inputModeCache, conteneurSuggestions } = buildDom();
        jest.useFakeTimers();

        let resoudrePremiere;
        global.fetch = jest.fn()
            .mockImplementationOnce(() => new Promise((resolve) => {
                resoudrePremiere = resolve;
            }))
            .mockImplementationOnce(() => Promise.resolve({
                json: () => Promise.resolve([{ id: 2, label: 'Nation', ville: null, modes: ['metro'] }]),
            }));

        initTrajetAutocomplete(inputTexte, inputCache, inputModeCache, conteneurSuggestions, { rechercheUrl: '/trajet/recherche-station' });

        inputTexte.value = 'na';
        inputTexte.dispatchEvent(new Event('input'));
        await jest.advanceTimersByTimeAsync(200);

        inputTexte.value = 'nat';
        inputTexte.dispatchEvent(new Event('input'));
        await jest.advanceTimersByTimeAsync(200);

        // La deuxieme requete (plus recente) a deja rempli l'affichage avec "Nation".
        expect(conteneurSuggestions.textContent).toContain('Nation');

        // La premiere requete repond en retard : elle ne doit pas ecraser l'affichage a jour.
        resoudrePremiere({ json: () => Promise.resolve([{ id: 1, label: 'Ancienne réponse', ville: null, modes: ['metro'] }]) });
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();

        expect(conteneurSuggestions.textContent).not.toContain('Ancienne réponse');
    });

    test('cliquer en dehors du champ et des suggestions les efface', () => {
        const { inputTexte, inputCache, inputModeCache, conteneurSuggestions } = buildDom();
        const { afficherSuggestions } = initTrajetAutocomplete(inputTexte, inputCache, inputModeCache, conteneurSuggestions, { rechercheUrl: '/trajet/recherche-station' });

        afficherSuggestions([{ id: 1, label: 'Nation', ville: null, modes: ['metro'] }]);
        expect(conteneurSuggestions.children.length).toBe(1);

        document.body.dispatchEvent(new Event('click', { bubbles: true }));

        expect(conteneurSuggestions.children.length).toBe(0);
    });
});
