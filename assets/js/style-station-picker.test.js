import { initStyleStationPicker } from './style-station-picker';

/**
 * Construit un <select multiple> de test avec les options donnees (label + selected).
 *
 * @param {Array<{ value: string, label: string, selected?: boolean }>} entries
 * @returns {HTMLSelectElement}
 */
function buildRealSelect(entries) {
    const select = document.createElement('select');
    select.id = 'style_station_dessertes';
    select.multiple = true;

    entries.forEach(({ value, label, selected }) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        option.selected = Boolean(selected);
        select.appendChild(option);
    });

    document.body.appendChild(select);

    return select;
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('initStyleStationPicker', () => {
    test("masque le select d'origine et le laisse dans le DOM pour la soumission du formulaire", () => {
        const realSelect = buildRealSelect([
            { value: '1', label: '1 - Châtelet' },
        ]);

        initStyleStationPicker(realSelect);

        expect(realSelect.style.display).toBe('none');
        expect(document.body.contains(realSelect)).toBe(true);
    });

    test('les options non selectionnees apparaissent dans le picker, pas dans la liste', () => {
        const realSelect = buildRealSelect([
            { value: '1', label: '1 - Châtelet' },
            { value: '2', label: '4 - Châtelet' },
        ]);

        const { picker, list } = initStyleStationPicker(realSelect);

        const pickerValues = Array.from(picker.options).map((o) => o.value);
        expect(pickerValues).toEqual(expect.arrayContaining(['1', '2']));
        expect(list.children.length).toBe(0);
    });

    test('les options deja selectionnees apparaissent dans la liste, pas dans le picker', () => {
        const realSelect = buildRealSelect([
            { value: '1', label: '1 - Châtelet', selected: true },
            { value: '2', label: '4 - Châtelet' },
        ]);

        const { picker, list } = initStyleStationPicker(realSelect);

        expect(list.children.length).toBe(1);
        expect(list.textContent).toContain('1 - Châtelet');

        const pickerValues = Array.from(picker.options).map((o) => o.value);
        expect(pickerValues).not.toContain('1');
        expect(pickerValues).toContain('2');
    });

    test('choisir une option dans le picker la deplace vers la liste et la marque selected', () => {
        const realSelect = buildRealSelect([
            { value: '1', label: '1 - Châtelet' },
            { value: '2', label: '4 - Châtelet' },
        ]);

        const { picker, list } = initStyleStationPicker(realSelect);

        picker.value = '1';
        picker.dispatchEvent(new Event('change'));

        expect(list.children.length).toBe(1);
        expect(list.textContent).toContain('1 - Châtelet');

        const optionInRealSelect = realSelect.querySelector('option[value="1"]');
        expect(optionInRealSelect.selected).toBe(true);

        const pickerValues = Array.from(picker.options).map((o) => o.value);
        expect(pickerValues).not.toContain('1');

        // Le picker se reinitialise sur le placeholder apres le choix.
        expect(picker.value).toBe('');
    });

    test('cliquer sur "Retirer" renvoie l\'option dans le picker et la desselectionne', () => {
        const realSelect = buildRealSelect([
            { value: '1', label: '1 - Châtelet', selected: true },
        ]);

        const { picker, list } = initStyleStationPicker(realSelect);

        const removeButton = list.querySelector('button');
        removeButton.dispatchEvent(new Event('click', { bubbles: true }));

        expect(list.children.length).toBe(0);

        const optionInRealSelect = realSelect.querySelector('option[value="1"]');
        expect(optionInRealSelect.selected).toBe(false);

        const pickerValues = Array.from(picker.options).map((o) => o.value);
        expect(pickerValues).toContain('1');
    });

    test('le picker reste trie alphabetiquement apres un ajout puis un retrait', () => {
        const realSelect = buildRealSelect([
            { value: '1', label: 'Bastille' },
            { value: '2', label: 'Châtelet' },
            { value: '3', label: 'Nation' },
        ]);

        const { picker, list } = initStyleStationPicker(realSelect);

        // Choisit "Châtelet", puis le retire : il doit revenir a sa place alphabetique
        // (entre Bastille et Nation), pas juste en fin de liste.
        picker.value = '2';
        picker.dispatchEvent(new Event('change'));

        const removeButton = list.querySelector('button');
        removeButton.dispatchEvent(new Event('click', { bubbles: true }));

        const labels = Array.from(picker.options)
            .filter((o) => o.value !== '')
            .map((o) => o.textContent);

        expect(labels).toEqual(['Bastille', 'Châtelet', 'Nation']);
    });

    test('ne fait rien si aucun select n\'est fourni', () => {
        expect(() => initStyleStationPicker(null)).not.toThrow();
    });

    test('un select sans options ne plante pas : picker vide (hors placeholder), liste vide', () => {
        const realSelect = buildRealSelect([]);

        const { picker, list } = initStyleStationPicker(realSelect);

        expect(picker.options.length).toBe(1); // uniquement le placeholder
        expect(list.children.length).toBe(0);
    });

    test('si toutes les options sont deja selectionnees, le picker ne contient que le placeholder', () => {
        const realSelect = buildRealSelect([
            { value: '1', label: 'Bastille', selected: true },
            { value: '2', label: 'Nation', selected: true },
        ]);

        const { picker, list } = initStyleStationPicker(realSelect);

        expect(picker.options.length).toBe(1);
        expect(list.children.length).toBe(2);
    });

    test('choisir plusieurs stations a la suite les empile toutes dans la liste', () => {
        const realSelect = buildRealSelect([
            { value: '1', label: 'Bastille' },
            { value: '2', label: 'Nation' },
            { value: '3', label: 'Châtelet' },
        ]);

        const { picker, list } = initStyleStationPicker(realSelect);

        picker.value = '1';
        picker.dispatchEvent(new Event('change'));
        picker.value = '3';
        picker.dispatchEvent(new Event('change'));

        expect(list.children.length).toBe(2);
        expect(Array.from(realSelect.options).filter((o) => o.selected).map((o) => o.value).sort())
            .toEqual(['1', '3']);
        expect(picker.options.length).toBe(2); // placeholder + Nation restante
    });

    test('retirer un item au milieu de la liste ne touche pas les autres', () => {
        const realSelect = buildRealSelect([
            { value: '1', label: 'Bastille', selected: true },
            { value: '2', label: 'Nation', selected: true },
            { value: '3', label: 'Châtelet', selected: true },
        ]);

        const { list } = initStyleStationPicker(realSelect);

        const nationItem = Array.from(list.children).find((li) => li.dataset.value === '2');
        nationItem.querySelector('button').dispatchEvent(new Event('click', { bubbles: true }));

        expect(list.children.length).toBe(2);
        const remainingValues = Array.from(list.children).map((li) => li.dataset.value).sort();
        expect(remainingValues).toEqual(['1', '3']);

        expect(realSelect.querySelector('option[value="1"]').selected).toBe(true);
        expect(realSelect.querySelector('option[value="2"]').selected).toBe(false);
        expect(realSelect.querySelector('option[value="3"]').selected).toBe(true);
    });

    test('changer de picker sans choisir de valeur (placeholder) ne fait rien', () => {
        const realSelect = buildRealSelect([
            { value: '1', label: 'Bastille' },
        ]);

        const { picker, list } = initStyleStationPicker(realSelect);

        picker.value = '';
        picker.dispatchEvent(new Event('change'));

        expect(list.children.length).toBe(0);
        expect(picker.options.length).toBe(2); // placeholder + Bastille, inchange
    });

    test('les libelles personnalises (placeholder, bouton retirer) sont respectes', () => {
        const realSelect = buildRealSelect([
            { value: '1', label: 'Bastille', selected: true },
        ]);

        const { picker, list } = initStyleStationPicker(realSelect, {
            placeholder: 'Choisir...',
            removeLabel: 'Enlever',
        });

        expect(picker.options[0].textContent).toBe('Choisir...');
        expect(list.querySelector('button').textContent).toBe('Enlever');
    });
});
