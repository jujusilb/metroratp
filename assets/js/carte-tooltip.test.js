import { formaterBulleStation, formaterLigneDesserte } from './carte-tooltip';

describe('formaterLigneDesserte', () => {
    test('mode:ligne:arret quand le gestionnaire est RATP (non affiche)', () => {
        expect(formaterLigneDesserte({ mode: 'Bus', ligne: '131', gestionnaire: null }, 'Les Coquettes'))
            .toBe('Bus:131:Les Coquettes');
    });

    test('mode:gestionnaire:ligne:arret quand le gestionnaire n\'est pas RATP', () => {
        expect(formaterLigneDesserte({ mode: 'Bus', ligne: '1232', gestionnaire: 'Keolis' }, 'Avenue de la Gare'))
            .toBe('Bus:Keolis:1232:Avenue de la Gare');
    });

    test('mode inconnu affiche "?"', () => {
        expect(formaterLigneDesserte({ mode: null, ligne: '14', gestionnaire: null }, 'Olympiades'))
            .toBe('?:14:Olympiades');
    });
});

describe('formaterBulleStation', () => {
    test('une ligne par desserte, separees par un retour a la ligne', () => {
        const html = formaterBulleStation('Châtelet', [
            { mode: 'Métro', ligne: '1', gestionnaire: null },
            { mode: 'Métro', ligne: '4', gestionnaire: null },
        ]);

        expect(html).toBe('Métro:1:Châtelet<br>Métro:4:Châtelet');
    });

    test('deduplique les entrees identiques', () => {
        const html = formaterBulleStation('Nation', [
            { mode: 'Métro', ligne: '1', gestionnaire: null },
            { mode: 'Métro', ligne: '1', gestionnaire: null },
        ]);

        expect(html).toBe('Métro:1:Nation');
    });
});
