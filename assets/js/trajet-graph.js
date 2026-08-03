import { Network } from 'vis-network';
import { DataSet } from 'vis-data';

/**
 * Transforme les donnees brutes (nodes/edges recues du backend) au format attendu par
 * vis-network. Fonction pure, separee du rendu (qui depend du DOM/canvas), pour rester
 * facilement testable.
 *
 * @param {{ nodes: Array<{id: number, label: string, color: string}>, edges: Array<{from: number, to: number, label: string, dashes: boolean, color: string}> }} graphe
 */
export function preparerDonneesGraphe(graphe) {
    const nodes = graphe.nodes.map((node) => ({
        id: node.id,
        label: node.label,
        color: { background: node.color, border: '#fff' },
        font: { color: '#fff', multi: false },
    }));

    const edges = graphe.edges.map((edge) => ({
        from: edge.from,
        to: edge.to,
        label: edge.label,
        dashes: edge.dashes,
        color: { color: edge.color },
        font: { size: 10, align: 'top' },
        arrows: 'to',
    }));

    return { nodes, edges };
}

/**
 * Dessine le graphe du trajet dans le conteneur DOM donne. Retourne l'instance Network
 * (utile pour la tester manuellement ou la detruire), ou null si les donnees sont absentes.
 *
 * @param {HTMLElement} container
 * @param {{ nodes: Array, edges: Array }} graphe
 */
export function initTrajetGraph(container, graphe) {
    if (!container || !graphe) {
        return null;
    }

    const { nodes, edges } = preparerDonneesGraphe(graphe);

    const data = {
        nodes: new DataSet(nodes),
        edges: new DataSet(edges),
    };

    const options = {
        nodes: {
            shape: 'dot',
            size: 18,
        },
        edges: {
            smooth: { type: 'continuous' },
        },
        physics: {
            stabilization: true,
            barnesHut: {
                springLength: 120,
            },
        },
        layout: {
            improvedLayout: true,
        },
    };

    return new Network(container, data, options);
}
