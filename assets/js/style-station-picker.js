/**
 * Transforme un <select multiple> standard en widget "picker" : un menu deroulant qui ne
 * propose que les options pas encore choisies, et une liste en dessous montrant les options
 * choisies avec un bouton "Retirer" pour les repasser dans le menu deroulant. Le <select>
 * d'origine reste dans le DOM (juste masque) pour que la soumission du formulaire continue
 * de fonctionner normalement, y compris sans JavaScript.
 *
 * @param {HTMLSelectElement} realSelect
 * @param {{ placeholder?: string, removeLabel?: string }} [options]
 */
export function initStyleStationPicker(realSelect, options = {}) {
    if (!realSelect) {
        return;
    }

    const placeholderText = options.placeholder ?? '-- Ajouter une station --';
    const removeLabelText = options.removeLabel ?? 'Retirer';

    const picker = document.createElement('select');
    picker.className = 'form-select mb-2';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = placeholderText;
    picker.appendChild(placeholder);

    const list = document.createElement('ul');
    list.className = 'list-group';

    function sortPickerOptions() {
        const pickerOptions = Array.from(picker.querySelectorAll('option[value]:not([value=""])'));
        pickerOptions.sort((a, b) => a.textContent.localeCompare(b.textContent, 'fr'));
        pickerOptions.forEach((option) => picker.appendChild(option));
    }

    function addToPicker(option) {
        const clone = document.createElement('option');
        clone.value = option.value;
        clone.textContent = option.textContent;
        picker.appendChild(clone);
        sortPickerOptions();
    }

    function addToList(option) {
        const item = document.createElement('li');
        item.className = 'list-group-item d-flex justify-content-between align-items-center';
        item.dataset.value = option.value;

        const label = document.createElement('span');
        label.textContent = option.textContent;

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn btn-sm btn-outline-danger';
        removeButton.textContent = removeLabelText;
        removeButton.addEventListener('click', function () {
            option.selected = false;
            item.remove();
            addToPicker(option);
        });

        item.appendChild(label);
        item.appendChild(removeButton);
        list.appendChild(item);
    }

    Array.from(realSelect.options).forEach(function (option) {
        if (option.selected) {
            addToList(option);
        } else {
            addToPicker(option);
        }
    });

    picker.addEventListener('change', function () {
        if (!picker.value) {
            return;
        }

        const option = realSelect.querySelector('option[value="' + picker.value + '"]');
        if (!option) {
            return;
        }

        option.selected = true;
        picker.querySelector('option[value="' + picker.value + '"]').remove();
        picker.value = '';
        addToList(option);
    });

    realSelect.style.display = 'none';
    realSelect.insertAdjacentElement('afterend', list);
    realSelect.insertAdjacentElement('afterend', picker);

    return { picker, list };
}
