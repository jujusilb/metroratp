import { initCollectionWidget } from './collection-widget';

function creerConteneur(nbLignesInitiales, prototypeHtml) {
    document.body.innerHTML = `
        <div id="conteneur" data-prototype="${prototypeHtml.replace(/"/g, '&quot;')}">
            ${Array.from({ length: nbLignesInitiales }, (_, i) => `
                <div class="collection-ligne">
                    <input name="parent[items][${i}][valeur]">
                    <button type="button" data-role="retirer"></button>
                </div>
            `).join('')}
        </div>
    `;

    return document.getElementById('conteneur');
}

describe('initCollectionWidget', () => {
    test('ne fait rien si le conteneur est absent', () => {
        expect(() => initCollectionWidget(null)).not.toThrow();
    });

    test('ajoute un bouton "Ajouter" apres le conteneur', () => {
        const conteneur = creerConteneur(0, '<input name="parent[items][__name__][valeur]">');
        initCollectionWidget(conteneur, { addButtonLabel: 'Ajouter un materiel' });

        const bouton = conteneur.nextElementSibling;
        expect(bouton.tagName).toBe('BUTTON');
        expect(bouton.textContent).toBe('Ajouter un materiel');
    });

    test('cliquer sur "Ajouter" insere une nouvelle ligne avec le bon index', () => {
        const conteneur = creerConteneur(2, '<input name="parent[items][__name__][valeur]">');
        initCollectionWidget(conteneur);

        expect(conteneur.children).toHaveLength(2);

        const boutonAjouter = conteneur.nextElementSibling;
        boutonAjouter.click();

        expect(conteneur.children).toHaveLength(3);
        const nouvelleLigne = conteneur.children[2];
        expect(nouvelleLigne.querySelector('input').name).toBe('parent[items][2][valeur]');
    });

    test('cliquer sur "Retirer" sur une ligne existante la supprime du DOM', () => {
        const conteneur = creerConteneur(2, '<input name="parent[items][__name__][valeur]">');
        initCollectionWidget(conteneur);

        expect(conteneur.children).toHaveLength(2);
        conteneur.children[0].querySelector('[data-role="retirer"]').click();

        expect(conteneur.children).toHaveLength(1);
    });

    test('cliquer sur "Retirer" sur une ligne ajoutee dynamiquement la supprime aussi', () => {
        const conteneur = creerConteneur(0, '<input name="parent[items][__name__][valeur]">');
        initCollectionWidget(conteneur);

        conteneur.nextElementSibling.click();
        expect(conteneur.children).toHaveLength(1);

        conteneur.children[0].querySelector('[data-role="retirer"]').click();
        expect(conteneur.children).toHaveLength(0);
    });
});
