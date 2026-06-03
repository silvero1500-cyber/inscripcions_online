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
            if (typeof opts.onAdd === 'function') opts.onAdd(node);
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

    // ── Grups d'aforament compartit ─────────────────────────
    var grupoCounter = 0;
    function newGrupoCid() { return 'n' + (++grupoCounter) + Date.now().toString(36); }

    // Reconstrueix les opcions de tots els selects de grup de les tarifes
    // a partir dels grups actuals, preservant la selecció (per cid).
    function refreshGroupSelects() {
        var list = document.getElementById('grupos-list');
        var groups = [];
        if (list) {
            list.querySelectorAll('.grupo-row').forEach(function (row) {
                var cid = row.getAttribute('data-cid');
                var nameInput = row.querySelector('.grupo-nombre');
                var name = (nameInput && nameInput.value.trim()) ? nameInput.value.trim() : 'Grup';
                if (cid) groups.push({ cid: cid, name: name });
            });
        }
        document.querySelectorAll('.tarifa-grupo-select').forEach(function (sel) {
            var current = sel.value;
            sel.innerHTML = '';
            var opt0 = document.createElement('option');
            opt0.value = '';
            opt0.textContent = '— Independent (aforament propi) —';
            sel.appendChild(opt0);
            var keep = false;
            groups.forEach(function (g) {
                var o = document.createElement('option');
                o.value = g.cid;
                o.textContent = g.name;
                if (g.cid === current) { o.selected = true; keep = true; }
                sel.appendChild(o);
            });
            if (!keep) sel.value = '';
            toggleTarifaAforo(sel);
        });
    }

    // Quan una tarifa té grup, desactiva el seu aforo propi (no s'envia ni s'usa)
    function toggleTarifaAforo(sel) {
        var card = sel.closest('.tarifa-row');
        if (!card) return;
        var aforo = card.querySelector('input[name$="[aforo_maximo]"]');
        if (!aforo) return;
        var hasGroup = sel.value !== '';
        aforo.disabled = hasGroup;
        aforo.style.opacity = hasGroup ? '0.5' : '';
        aforo.title = hasGroup ? "Gestionat pel grup d'aforament compartit" : '';
    }

    function setupGrupos() {
        var list = document.getElementById('grupos-list');
        var addBtn = document.getElementById('add-grupo');
        var template = document.getElementById('grupo-template');
        if (!list || !addBtn || !template) return;

        function nextIndex() {
            var max = -1;
            list.querySelectorAll('.grupo-row').forEach(function (r) {
                var i = parseInt(r.getAttribute('data-index'), 10);
                if (!isNaN(i) && i > max) max = i;
            });
            return max + 1;
        }
        function updateMetaG() {
            var n = list.querySelectorAll('.grupo-row').length;
            var counter = document.querySelector('[data-count-for="grupos-list"]');
            if (counter) counter.textContent = String(n);
            var empty = document.querySelector('[data-empty-for="grupos-list"]');
            if (empty) empty.style.display = (n === 0) ? '' : 'none';
        }

        addBtn.addEventListener('click', function () {
            var idx = nextIndex();
            var cid = newGrupoCid();
            var html = template.innerHTML.replace(/__IDX__/g, String(idx)).replace(/__CID__/g, cid);
            var tmp = document.createElement('div');
            tmp.innerHTML = html.trim();
            var node = tmp.firstElementChild;
            list.appendChild(node);
            updateMetaG();
            refreshGroupSelects();
            var f = node.querySelector('input[type="text"]');
            if (f) f.focus();
        });

        list.addEventListener('click', function (e) {
            if (!e.target.closest('.grupo-remove')) return;
            var row = e.target.closest('.grupo-row');
            if (!row) return;
            if (!confirm('Vols eliminar aquest grup? Les seves tarifes passaran a aforament propi.')) return;
            row.remove();
            updateMetaG();
            refreshGroupSelects();
        });

        list.addEventListener('input', function (e) {
            var row = e.target.closest('.grupo-row');
            if (!row) return;
            if (e.target.classList.contains('grupo-nombre')) {
                var t = row.querySelector('.item-title');
                if (t) t.textContent = e.target.value.trim() || 'Grup';
                refreshGroupSelects();
            }
        });

        updateMetaG();
    }

    setupBuilder({
        listId: 'campos-list', addBtnId: 'add-campo', templateId: 'campo-template',
        itemSelector: '.campo-row', removeClass: 'campo-remove',
        titleField: 'etiqueta', confirmMsg: 'Vols eliminar aquest camp?'
    });
    setupBuilder({
        listId: 'tarifas-list', addBtnId: 'add-tarifa', templateId: 'tarifa-template',
        itemSelector: '.tarifa-row', removeClass: 'tarifa-remove',
        titleField: 'nombre', confirmMsg: 'Vols eliminar aquesta tarifa?',
        onAdd: function () { refreshGroupSelects(); }
    });

    setupGrupos();
    refreshGroupSelects();

    // Canvi de grup en una tarifa → activar/desactivar el seu aforo propi
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('tarifa-grupo-select')) {
            toggleTarifaAforo(e.target);
        }
    });

    var form = document.getElementById('evento-form');
    if (form) {
        form.addEventListener('submit', function () {
            reindex('grupos-list', '.grupo-row', 'grupos');
            reindex('tarifas-list', '.tarifa-row', 'tarifas');
            reindex('campos-list', '.campo-row', 'campos');
        });
    }
})();
