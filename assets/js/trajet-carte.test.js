import {
    extraireSousChemin,
    extraireTraceEntreDeuxPoints,
    marquerTronconsConcernes,
    projeterSurLigne,
    projeterSurSegment,
} from './trajet-carte';

describe('marquerTronconsConcernes', () => {
    test('marque comme concerne un troncon present dans le trajet, dans un sens ou l\'autre', () => {
        const donnees = {
            reseau: [
                { lat1: 48.8, lon1: 2.3, lat2: 48.81, lon2: 2.31, couleur: '#FFCD00' },
                { lat1: 48.9, lon1: 2.4, lat2: 48.91, lon2: 2.41, couleur: '#FFCD00' },
            ],
            trajet: [
                { lat1: 48.81, lon1: 2.31, lat2: 48.8, lon2: 2.3, type: 'troncon' },
            ],
        };

        const resultat = marquerTronconsConcernes(donnees);

        expect(resultat[0].concerne).toBe(true);
        expect(resultat[1].concerne).toBe(false);
    });

    test('ne marque rien si le trajet est vide', () => {
        const donnees = {
            reseau: [{ lat1: 48.8, lon1: 2.3, lat2: 48.81, lon2: 2.31, couleur: '#FFCD00' }],
            trajet: [],
        };

        const resultat = marquerTronconsConcernes(donnees);

        expect(resultat[0].concerne).toBe(false);
    });
});

describe('projeterSurSegment', () => {
    test('projette un point sur le segment le plus proche (perpendiculaire)', () => {
        // Segment est-ouest a lat=48.85 ; point juste au nord du milieu.
        const r = projeterSurSegment([2.35, 48.851], [2.30, 48.85], [2.40, 48.85]);

        expect(r.t).toBeCloseTo(0.5, 1);
        expect(r.proj[1]).toBeCloseTo(48.85, 5);
    });

    test('accroche aux extremites (t borne a [0,1]) quand le point est hors segment', () => {
        const r = projeterSurSegment([2.20, 48.85], [2.30, 48.85], [2.40, 48.85]);

        expect(r.t).toBe(0);
        expect(r.proj).toEqual([2.30, 48.85]);
    });
});

describe('projeterSurLigne', () => {
    test('trouve le bon segment sur une ligne brisee a plusieurs segments', () => {
        const ligne = [[2.30, 48.85], [2.35, 48.85], [2.35, 48.90]];
        // Plus proche du deuxieme segment (vertical).
        const r = projeterSurLigne([2.351, 48.87], ligne);

        expect(r.index).toBe(1);
        expect(r.proj[1]).toBeCloseTo(48.87, 3);
    });
});

describe('extraireSousChemin', () => {
    test('extrait les points intermediaires entre deux projections, dans l\'ordre des index', () => {
        const composante = [[0, 0], [1, 0], [2, 0], [3, 0]];
        const projA = { index: 0, proj: [0.5, 0] };
        const projB = { index: 2, proj: [2.5, 0] };

        const resultat = extraireSousChemin(composante, projA, projB);

        expect(resultat).toEqual([[0.5, 0], [1, 0], [2, 0], [2.5, 0]]);
    });

    test('fonctionne quel que soit l\'ordre passe (A apres B)', () => {
        const composante = [[0, 0], [1, 0], [2, 0]];
        const projA = { index: 1, proj: [1.5, 0] };
        const projB = { index: 0, proj: [0.5, 0] };

        const resultat = extraireSousChemin(composante, projA, projB);

        expect(resultat).toEqual([[0.5, 0], [1, 0], [1.5, 0]]);
    });
});

describe('extraireTraceEntreDeuxPoints', () => {
    test('choisit la composante la plus proche des deux points parmi plusieurs branches', () => {
        const traceLigne = [
            [[2.30, 48.85], [2.40, 48.85]], // loin des points cibles
            [[2.30, 48.90], [2.35, 48.90], [2.40, 48.90]], // proche
        ];

        const resultat = extraireTraceEntreDeuxPoints(traceLigne, [2.30, 48.90], [2.40, 48.90]);

        expect(resultat).not.toBeNull();
        expect(resultat[0][1]).toBeCloseTo(48.90, 2);
    });

    test('renvoie null si aucune composante n\'est proche des deux points', () => {
        const traceLigne = [[[2.30, 48.85], [2.40, 48.85]]];

        const resultat = extraireTraceEntreDeuxPoints(traceLigne, [2.30, 49.50], [2.40, 49.50]);

        expect(resultat).toBeNull();
    });
});
