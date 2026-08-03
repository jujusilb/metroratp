import { calculerProjection, marquerTronconsConcernes } from './trajet-carte';

describe('calculerProjection', () => {
    test('projette les coordonnees extremes sur les bords du viewBox (avec marge)', () => {
        const donnees = {
            reseau: [
                { x1: 0, y1: 0, x2: 100, y2: 50 },
            ],
        };
        const { projeter } = calculerProjection(donnees, { largeur: 200, hauteur: 200, marge: 10 });

        const [x1] = projeter(0, 0);
        const [x2] = projeter(100, 50);

        expect(x1).toBeCloseTo(10, 1);
        expect(x2).toBeCloseTo(190, 1);
    });

    test('inverse l\'axe Y (coordonnee y croissante vers le bas du SVG)', () => {
        const donnees = {
            reseau: [{ x1: 0, y1: 0, x2: 10, y2: 100 }],
        };
        const { projeter } = calculerProjection(donnees, { largeur: 200, hauteur: 200, marge: 0 });

        const [, yBas] = projeter(0, 0);
        const [, yHaut] = projeter(0, 100);

        expect(yBas).toBeGreaterThan(yHaut);
    });

    test('conserve le ratio d\'aspect (pas de deformation)', () => {
        const donnees = {
            reseau: [{ x1: 0, y1: 0, x2: 100, y2: 100 }],
        };
        const { projeter } = calculerProjection(donnees, { largeur: 400, hauteur: 200, marge: 0 });

        const [x1, y1] = projeter(0, 0);
        const [x2, y2] = projeter(100, 100);

        expect(Math.abs(x2 - x1)).toBeCloseTo(Math.abs(y2 - y1), 1);
    });
});

describe('marquerTronconsConcernes', () => {
    test('marque comme concerne un troncon present dans le trajet, dans un sens ou l\'autre', () => {
        const donnees = {
            reseau: [
                { x1: 0, y1: 0, x2: 1, y2: 1, couleur: '#FFCD00' },
                { x1: 5, y1: 5, x2: 6, y2: 6, couleur: '#FFCD00' },
            ],
            trajet: [
                { x1: 1, y1: 1, x2: 0, y2: 0, type: 'troncon' },
            ],
        };

        const resultat = marquerTronconsConcernes(donnees);

        expect(resultat[0].concerne).toBe(true);
        expect(resultat[1].concerne).toBe(false);
    });

    test('ne marque rien si le trajet est vide', () => {
        const donnees = {
            reseau: [{ x1: 0, y1: 0, x2: 1, y2: 1, couleur: '#FFCD00' }],
            trajet: [],
        };

        const resultat = marquerTronconsConcernes(donnees);

        expect(resultat[0].concerne).toBe(false);
    });
});
