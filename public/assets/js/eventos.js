/* Eventos · gestión dinámica de tarifas y campos personalizados */
(function () {
    'use strict';

    function setupDynamicList(listId, addBtnId, templateId, itemSelector, removeClass, confirmMsg) {
        const list     = document.getElementById(listId);
        const addBtn   = document.getElementById(addBtnId);
        const template = document.getElementById(templateId);
        if (!list || !addBtn || !template) return;

        function nextIndex() {
            const rows = list.querySelectorAll(itemSelector);
            let max = -1;
            rows.forEach(function (r) {
                const i = parseInt(r.getAttribute('data-index'), 10);
                if (!isNaN(i) && i > max) max = i;
            });
            return max + 1;
        }

        addBtn.addEventListener('click', function () {
            const idx  = nextIndex();
            const html = template.innerHTML.replace(/__IDX__/g, String(idx));
            const tmp  = document.createElement('div');
            tmp.innerHTML = html.trim();
            const node = tmp.firstChild;
            list.appendChild(node);
            const firstInput = node.querySelector('input[type="text"], input[type="number"]');
            if (firstInput) firstInput.focus();
        });

        list.addEventListener('click', function (e) {
            if (!e.target.classList.contains(removeClass)) return;
            const row = e.target.closest(itemSelector);
            if (!row) return;
            if (!confirm(confirmMsg)) return;
            row.remove();
        });
    }

    setupDynamicList('campos-list',  'add-campo',  'campo-template',
                     '.campo-row',  'campo-remove',  'Vols eliminar aquest camp?');
    setupDynamicList('tarifas-list', 'add-tarifa', 'tarifa-template',
                     '.tarifa-row', 'tarifa-remove', 'Vols eliminar aquesta tarifa?');
})();
