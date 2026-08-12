import { marquerTronconsConcernes } from './trajet-carte';

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
