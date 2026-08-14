import { bucketPourMode, bucketsPourDessertes } from './carte-reseau';

describe('bucketPourMode', () => {
    test.each([
        ['Métro', 'metro'],
        ['RER', 'rer'],
        ['Tramway', 'tram'],
        ['Bus', 'bus'],
        ['Car', 'bus'],
        ['Train', 'autres'],
        ['Funiculaire', 'autres'],
        ['Téléphérique', 'autres'],
        [null, 'autres'],
    ])('%s -> %s', (mode, bucket) => {
        expect(bucketPourMode(mode)).toBe(bucket);
    });
});

describe('bucketsPourDessertes', () => {
    test('une station mono-mode ne renvoie qu\'un bucket', () => {
        const buckets = bucketsPourDessertes([{ mode: 'Bus' }, { mode: 'Bus' }]);

        expect(buckets).toEqual(new Set(['bus']));
    });

    test('une station multimodale renvoie tous ses buckets', () => {
        const buckets = bucketsPourDessertes([{ mode: 'Métro' }, { mode: 'RER' }, { mode: 'Bus' }]);

        expect(buckets).toEqual(new Set(['metro', 'rer', 'bus']));
    });
});
