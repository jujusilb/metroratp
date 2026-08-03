/**
 * Calcule l'echelle et les offsets necessaires pour projeter les coordonnees du plan
 * schematique (echelle arbitraire IDFM) dans un viewBox SVG de taille donnee, en conservant
 * le ratio d'aspect (letterboxing si besoin). Fonction pure, testable sans DOM.
 *
 * @param {{ reseau: Array<{x1:number,y1:number,x2:number,y2:number}> }} donnees
 * @param {{ largeur: number, hauteur: number, marge: number }} dimensions
 */
export function calculerProjection(donnees, dimensions) {
    const { largeur, hauteur, marge } = dimensions;
    const xs = [];
    const ys = [];
    for (const t of donnees.reseau) {
        xs.push(t.x1, t.x2);
        ys.push(t.y1, t.y2);
    }

    const xMin = Math.min(...xs);
    const xMax = Math.max(...xs);
    const yMin = Math.min(...ys);
    const yMax = Math.max(...ys);

    const echelle = Math.min((largeur - 2 * marge) / (xMax - xMin), (hauteur - 2 * marge) / (yMax - yMin));
    const decalageX = (largeur - (xMax - xMin) * echelle) / 2;
    const decalageY = (hauteur - (yMax - yMin) * echelle) / 2;

    return {
        projeter(x, y) {
            return [(x - xMin) * echelle + decalageX, hauteur - ((y - yMin) * echelle + decalageY)];
        },
    };
}

/**
 * Determine, pour chaque troncon du reseau, s'il fait partie du trajet trouve (pour le mettre
 * en evidence). Fonction pure, testable sans DOM.
 *
 * @param {{ reseau: Array<{x1:number,y1:number,x2:number,y2:number}>, trajet: Array<{x1:number,y1:number,x2:number,y2:number,type:string}> }} donnees
 */
export function marquerTronconsConcernes(donnees) {
    const cles = new Set();
    for (const e of donnees.trajet) {
        cles.add([e.x1, e.y1, e.x2, e.y2].join(','));
        cles.add([e.x2, e.y2, e.x1, e.y1].join(','));
    }

    return donnees.reseau.map((t) => ({
        ...t,
        concerne: cles.has([t.x1, t.y1, t.x2, t.y2].join(',')),
    }));
}

const ns = 'http://www.w3.org/2000/svg';

function el(tag, attrs) {
    const e = document.createElementNS(ns, tag);
    for (const k in attrs) {
        e.setAttribute(k, attrs[k]);
    }
    return e;
}

/**
 * Dessine la carte du trajet (plan schematique + trajet surligne) dans le conteneur DOM donne,
 * avec zoom (molette, taille des ronds compensee pour rester constante a l'ecran) et
 * deplacement (cliquer-glisser). Construit aussi la legende numerotee dans le conteneur donne.
 *
 * @param {HTMLElement} svgContainer
 * @param {HTMLElement} legendeContainer
 * @param {{ reseau: Array, trajet: Array }} donnees
 */
export function initTrajetCarte(svgContainer, legendeContainer, donnees) {
    if (!svgContainer || !donnees || !donnees.reseau || 0 === donnees.reseau.length) {
        return null;
    }

    const W = 900;
    const H = 620;
    const MARGE = 30;

    svgContainer.setAttribute('viewBox', `0 0 ${W} ${H}`);

    const { projeter } = calculerProjection(donnees, { largeur: W, hauteur: H, marge: MARGE });
    const reseauMarque = marquerTronconsConcernes(donnees);

    for (const t of reseauMarque) {
        const [x1, y1] = projeter(t.x1, t.y1);
        const [x2, y2] = projeter(t.x2, t.y2);
        svgContainer.appendChild(el('line', {
            x1, y1, x2, y2,
            stroke: t.couleur,
            'stroke-width': t.concerne ? 5 : 2,
            'stroke-linecap': 'round',
            opacity: t.concerne ? 1 : 0.5,
        }));
    }

    for (const e of donnees.trajet) {
        if ('correspondance' !== e.type) {
            continue;
        }
        const [x1, y1] = projeter(e.x1, e.y1);
        const [x2, y2] = projeter(e.x2, e.y2);
        svgContainer.appendChild(el('line', {
            x1, y1, x2, y2, stroke: e.couleur, 'stroke-width': 4, 'stroke-dasharray': '6 4', 'stroke-linecap': 'round',
        }));
    }

    const elementsAZoomCompenser = [];

    const stationsVues = new Set();
    for (const t of donnees.reseau) {
        for (const [x, y] of [[t.x1, t.y1], [t.x2, t.y2]]) {
            const cle = `${x},${y}`;
            if (stationsVues.has(cle)) {
                continue;
            }
            stationsVues.add(cle);
            const [px, py] = projeter(x, y);
            const c = el('circle', { cx: px, cy: py, r: 3, fill: 'var(--bs-body-bg, #fff)', stroke: '#6c757d', 'stroke-width': 1 });
            svgContainer.appendChild(c);
            elementsAZoomCompenser.push({ node: c, baseR: 3, baseStrokeWidth: 1 });
        }
    }

    const stationsTrajet = new Map();
    for (const e of donnees.trajet) {
        if (!stationsTrajet.has(e.labelDepart)) {
            stationsTrajet.set(e.labelDepart, [e.x1, e.y1]);
        }
        if (!stationsTrajet.has(e.labelArrivee)) {
            stationsTrajet.set(e.labelArrivee, [e.x2, e.y2]);
        }
    }

    if (legendeContainer) {
        legendeContainer.innerHTML = '';
    }
    let i = 1;
    for (const [label, [x, y]] of stationsTrajet) {
        const [px, py] = projeter(x, y);
        const c = el('circle', { cx: px, cy: py, r: 3.5, fill: 'var(--bs-body-bg, #fff)', stroke: '#333', 'stroke-width': 1.2 });
        svgContainer.appendChild(c);
        elementsAZoomCompenser.push({ node: c, baseR: 3.5, baseStrokeWidth: 1.2 });

        const t = el('text', { x: px, y: py + 2.2, 'font-size': 6, 'font-weight': 500, fill: 'currentColor', 'text-anchor': 'middle' });
        t.textContent = i;
        svgContainer.appendChild(t);
        elementsAZoomCompenser.push({ node: t, baseFontSize: 6, y0: py });

        if (legendeContainer) {
            const col = document.createElement('div');
            col.className = 'col d-flex gap-1 text-muted';
            const num = document.createElement('span');
            num.className = 'fw-medium text-body';
            num.textContent = `${i}.`;
            col.appendChild(num);
            col.appendChild(document.createTextNode(label));
            legendeContainer.appendChild(col);
        }

        i++;
    }

    const viewBox = { x: 0, y: 0, w: W, h: H };
    const appliquerViewBox = () => {
        svgContainer.setAttribute('viewBox', `${viewBox.x} ${viewBox.y} ${viewBox.w} ${viewBox.h}`);

        const facteurZoom = W / viewBox.w;
        for (const item of elementsAZoomCompenser) {
            if ('baseR' in item) {
                item.node.setAttribute('r', item.baseR / facteurZoom);
                item.node.setAttribute('stroke-width', item.baseStrokeWidth / facteurZoom);
            } else if ('baseFontSize' in item) {
                const taille = item.baseFontSize / facteurZoom;
                item.node.setAttribute('font-size', taille);
                item.node.setAttribute('y', item.y0 + taille * 0.35);
            }
        }
    };

    svgContainer.addEventListener('wheel', (e) => {
        e.preventDefault();
        const rect = svgContainer.getBoundingClientRect();
        const mx = ((e.clientX - rect.left) / rect.width) * viewBox.w + viewBox.x;
        const my = ((e.clientY - rect.top) / rect.height) * viewBox.h + viewBox.y;
        const facteur = e.deltaY < 0 ? 0.88 : 1.12;
        const nouveauW = Math.min(W, Math.max(20, viewBox.w * facteur));
        const nouveauH = nouveauW * (H / W);
        viewBox.x = mx - (mx - viewBox.x) * (nouveauW / viewBox.w);
        viewBox.y = my - (my - viewBox.y) * (nouveauH / viewBox.h);
        viewBox.w = nouveauW;
        viewBox.h = nouveauH;
        appliquerViewBox();
    }, { passive: false });

    let panning = false;
    let startX = 0;
    let startY = 0;
    let startVB = null;
    svgContainer.addEventListener('pointerdown', (e) => {
        panning = true;
        startX = e.clientX;
        startY = e.clientY;
        startVB = { x: viewBox.x, y: viewBox.y };
        svgContainer.style.cursor = 'grabbing';
        svgContainer.setPointerCapture(e.pointerId);
    });
    svgContainer.addEventListener('pointermove', (e) => {
        if (!panning) {
            return;
        }
        const rect = svgContainer.getBoundingClientRect();
        viewBox.x = startVB.x - ((e.clientX - startX) / rect.width) * viewBox.w;
        viewBox.y = startVB.y - ((e.clientY - startY) / rect.height) * viewBox.h;
        appliquerViewBox();
    });
    svgContainer.addEventListener('pointerup', () => {
        panning = false;
        svgContainer.style.cursor = 'grab';
    });
    svgContainer.addEventListener('pointerleave', () => {
        panning = false;
        svgContainer.style.cursor = 'grab';
    });

    return { projeter };
}
