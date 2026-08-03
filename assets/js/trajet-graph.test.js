import { preparerDonneesGraphe } from './trajet-graph';

describe('preparerDonneesGraphe', () => {
    test('transforme les noeuds avec leur couleur de ligne', () => {
        const graphe = {
            nodes: [
                { id: 1, label: 'Châtelet\n(1)', color: '#ffcd00' },
            ],
            edges: [],
        };

        const { nodes } = preparerDonneesGraphe(graphe);

        expect(nodes).toEqual([
            {
                id: 1,
                label: 'Châtelet\n(1)',
                color: { background: '#ffcd00', border: '#fff' },
                font: { color: '#212529', multi: false },
            },
        ]);
    });

    test('transforme les aretes en distinguant troncon (trait plein) et correspondance (pointilles)', () => {
        const graphe = {
            nodes: [],
            edges: [
                { from: 1, to: 2, label: '2 min', dashes: false, color: '#495057' },
                { from: 2, to: 3, label: '3 min', dashes: true, color: '#dc3545' },
            ],
        };

        const { edges } = preparerDonneesGraphe(graphe);

        expect(edges[0].dashes).toBe(false);
        expect(edges[0].color).toEqual({ color: '#495057' });
        expect(edges[1].dashes).toBe(true);
        expect(edges[1].color).toEqual({ color: '#dc3545' });
    });

    test('conserve toutes les etapes du trajet, dans leur ordre', () => {
        const graphe = {
            nodes: [],
            edges: [
                { from: 1, to: 2, label: '2 min', dashes: false, color: '#495057' },
                { from: 2, to: 3, label: '2 min', dashes: false, color: '#495057' },
                { from: 3, to: 4, label: '3 min', dashes: true, color: '#dc3545' },
            ],
        };

        const { edges } = preparerDonneesGraphe(graphe);

        expect(edges.map((e) => [e.from, e.to])).toEqual([
            [1, 2],
            [2, 3],
            [3, 4],
        ]);
    });

    test('graphe sans noeuds ni aretes ne plante pas', () => {
        const { nodes, edges } = preparerDonneesGraphe({ nodes: [], edges: [] });

        expect(nodes).toEqual([]);
        expect(edges).toEqual([]);
    });
});
