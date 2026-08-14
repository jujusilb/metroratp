import { formaterLibelleSortie } from './carte-acces';

describe('formaterLibelleSortie', () => {
    test('avec numero', () => {
        expect(formaterLibelleSortie({ label: 'r. de la Gare', numero: '1' })).toBe('Sortie 1 — r. de la Gare');
    });

    test('sans numero', () => {
        expect(formaterLibelleSortie({ label: 'bd de Bonne Nouvelle', numero: null })).toBe('Sortie — bd de Bonne Nouvelle');
    });
});
