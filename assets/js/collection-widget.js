/**
 * Ajout/suppression dynamique de lignes pour un champ CollectionType Symfony imbrique (convention
 * standard data-prototype : le conteneur porte le HTML d'une ligne vierge, avec __name__ en
 * placeholder d'index a remplacer). Utilise pour editer les relations datees (MaterielLigne,
 * MaterielDepot, DepotLigne, DepotGestionnaire) directement depuis la fiche du cote "principal",
 * plutot que via une page CRUD separee.
 *
 * Chaque ligne existante ou ajoutee est enveloppee dans le meme bloc (classe .collection-ligne)
 * avec un bouton "Retirer" - supprimer une ligne du DOM suffit, Symfony ignore les entrees absentes
 * a la soumission (pas besoin de champ cache "a supprimer").
 *
 * @param {HTMLElement} container - l'element du CollectionType (porte data-prototype)
 * @param {{ addButtonLabel?: string, removeButtonLabel?: string }} [options]
 */
export function initCollectionWidget(container, options = {}) {
    if (!container) {
        return;
    }

    const addLabel = options.addButtonLabel ?? 'Ajouter une ligne';
    const removeLabel = options.removeButtonLabel ?? 'Retirer cette ligne';

    let index = container.children.length;

    function attacherBoutonRetirer(ligne) {
        const bouton = ligne.querySelector('[data-role="retirer"]');
        if (bouton) {
            bouton.textContent = removeLabel;
            bouton.addEventListener('click', () => ligne.remove());
        }
    }

    Array.from(container.children).forEach(attacherBoutonRetirer);

    const boutonAjouter = document.createElement('button');
    boutonAjouter.type = 'button';
    boutonAjouter.className = 'btn btn-outline-primary btn-sm mt-2 mb-3';
    boutonAjouter.textContent = addLabel;
    boutonAjouter.addEventListener('click', () => {
        const html = container.dataset.prototype.replace(/__name__/g, String(index));
        const template = document.createElement('template');
        template.innerHTML = `<div class="collection-ligne border rounded p-3 mb-2">${html.trim()}<button type="button" class="btn btn-outline-danger btn-sm" data-role="retirer">${removeLabel}</button></div>`;
        const ligne = template.content.firstElementChild;
        attacherBoutonRetirer(ligne);
        container.appendChild(ligne);
        index++;
    });
    container.insertAdjacentElement('afterend', boutonAjouter);

    return { boutonAjouter };
}
