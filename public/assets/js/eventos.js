/* Eventos · gestió visual de tarifes i camps personalitzats
   (afegir → columna dreta, eliminar, ordenar per arrossegament o fletxes) */
(function () {
    'use strict';

    function defaultTitle(itemSelector) {
        return itemSelector === '.tarifa-row' ? 'Tarifa' : 'Camp';
    }

    function updateMeta(listId, itemSelector) {
        var list = document.getElementById(listId);
        if (!list) return;
        var items = list.querySelectorAll(itemSelector);
        var n = items.length;

        var counter = document.querySelector('[data-count-for="' + listId + '"]');
        if (counter) counter.textContent = String(n);

        var empty = document.querySelector('[data-empty-for="' + listId + '"]');
        if (empty) empty.style.display = (n === 0) ? '' : 'none';

        // Deshabilitar fletxes als extrems
        items.forEach(function (row, i) {
            var up = row.querySelector('.move-up');
            var down = row.querySelector('.move-down');
            if (up) up.disabled = (i === 0);
            if (down) down.disabled = (i === n - 1);
        });
    }

    function setupBuilder(opts) {
        var list = document.getElementById(opts.listId);
        var addBtn = document.getElementById(opts.addBtnId);
        var template = document.getElementById(opts.templateId);
        if (!list || !addBtn || !template) return;

        var itemSelector = opts.itemSelector;

        function nextIndex() {
            var max = -1;
            list.querySelectorAll(itemSelector).forEach(function (r) {
                var i = parseInt(r.getAttribute('data-index'), 10);
                if (!isNaN(i) && i > max) max = i;
            });
            return max + 1;
        }

        // ── Afegir ──────────────────────────────────────────
        addBtn.addEventListener('click', function () {
            var idx = nextIndex();
            var html = template.innerHTML.replace(/__IDX__/g, String(idx));
            var tmp = document.createElement('div');
            tmp.innerHTML = html.trim();
            var node = tmp.firstElementChild;
            list.appendChild(node);
            updateMeta(opts.listId, itemSelector);
            var firstInput = node.querySelector('input[type="text"], input[type="number"]');
            if (firstInput) firstInput.focus();
        });

        // ── Clics: eliminar / pujar / baixar ────────────────
        list.addEventListener('click', function (e) {
            var row = e.target.closest(itemSelector);
            if (!row) return;
            if (e.target.closest('.' + opts.removeClass)) {
                if (!confirm(opts.confirmMsg)) return;
                row.remove();
                updateMeta(opts.listId, itemSelector);
            } else if (e.target.closest('.move-up')) {
                var prev = row.previousElementSibling;
                if (prev) { list.insertBefore(row, prev); updateMeta(opts.listId, itemSelector); }
            } else if (e.target.closest('.move-down')) {
                var next = row.nextElementSibling;
                if (next) { list.insertBefore(next, row); updateMeta(opts.listId, itemSelector); }
            }
        });

        // ── Títol viu segons el camp principal ──────────────
        list.addEventListener('input', function (e) {
            var row = e.target.closest(itemSelector);
            if (!row || !e.target.name) return;
            if (e.target.name.slice(-('[' + opts.titleField + ']').length) === '[' + opts.titleField + ']') {
                var t = row.querySelector('.item-title');
                if (t) t.textContent = e.target.value.trim() || defaultTitle(itemSelector);
            }
        });

        // ── Arrossegament (només des del handle) ────────────
        var dragEl = null;
        list.addEventListener('mousedown', function (e) {
            var h = e.target.closest('.drag-handle');
            if (h) { var row = h.closest(itemSelector); if (row) row.draggable = true; }
        });
        // Si es deixa anar sense arrossegar, desarmar
        list.addEventListener('mouseup', function () {
            list.querySelectorAll(itemSelector + '[draggable="true"]').forEach(function (r) {
                if (!r.classList.contains('dragging')) r.draggable = false;
            });
        });
        list.addEventListener('dragstart', function (e) {
            var row = e.target.closest(itemSelector);
            if (!row) return;
            dragEl = row;
            row.classList.add('dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', ''); } catch (_) {}
            }
        });
        list.addEventListener('dragend', function () {
            if (dragEl) { dragEl.classList.remove('dragging'); dragEl.draggable = false; dragEl = null; }
            updateMeta(opts.listId, itemSelector);
        });
        list.addEventListener('dragover', function (e) {
            if (!dragEl) return;
            e.preventDefault();
            var after = getDragAfter(list, itemSelector, e.clientY);
            if (after == null) list.appendChild(dragEl);
            else list.insertBefore(dragEl, after);
        });

        updateMeta(opts.listId, itemSelector);
    }

    function getDragAfter(list, itemSelector, y) {
        var els = Array.prototype.slice.call(list.querySelectorAll(itemSelector + ':not(.dragging)'));
        var closest = { offset: -Infinity, element: null };
        els.forEach(function (child) {
            var box = child.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) closest = { offset: offset, element: child };
        });
        return closest.element;
    }

    // Reenumerar índexs en ordre visual abans d'enviar
    // (el backend guarda 'orden' = posició dins de l'array del POST)
    function reindex(listId, itemSelector, prefix) {
        var list = document.getElementById(listId);
        if (!list) return;
        var re = new RegExp('^' + prefix + '\\[\\d+\\]');
        Array.prototype.slice.call(list.querySelectorAll(itemSelector)).forEach(function (row, i) {
            row.setAttribute('data-index', String(i));
            row.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace(re, prefix + '[' + i + ']');
            });
        });
    }

    setupBuilder({
        listId: 'campos-list', addBtnId: 'add-campo', templateId: 'campo-template',
        itemSelector: '.campo-row', removeClass: 'campo-remove',
        titleField: 'etiqueta', confirmMsg: 'Vols eliminar aquest camp?'
    });
    setupBuilder({
        listId: 'tarifas-list', addBtnId: 'add-tarifa', templateId: 'tarifa-template',
        itemSelector: '.tarifa-row', removeClass: 'tarifa-remove',
        titleField: 'nombre', confirmMsg: 'Vols eliminar aquesta tarifa?'
    });

    var form = document.getElementById('evento-form');
    if (form) {
        form.addEventListener('submit', function () {
            reindex('tarifas-list', '.tarifa-row', 'tarifas');
            reindex('campos-list', '.campo-row', 'campos');
        });
    }
})();
