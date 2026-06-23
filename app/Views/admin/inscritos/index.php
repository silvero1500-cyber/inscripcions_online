<?php
/** @var object $user */
/** @var list<array> $eventos */
/** @var list<array> $inscritos */
/** @var array $filters */
/** @var array $counts */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var int $totalPages */
/** @var int $from */
/** @var int $to */
/** @var array $flash */

$flash = $flash ?? [];
$selEvento = (int) ($filters['evento_id'] ?? 0);
$filtersClean = array_filter($filters, fn($v) => $v !== null && $v !== '');
$exportUrl = base_url('/admin/inscritos/export?' . http_build_query($filtersClean));

// Helper per a links de pàgina preservant filtres
$pageUrl = function (int $p) use ($filtersClean): string {
    $params = $filtersClean;
    $params['page'] = $p;
    return '?' . http_build_query($params);
};
?>
<section class="page-head with-action">
    <div>
        <h1>Inscrits</h1>
        <p class="muted">Gestió i exportació de corredors inscrits.</p>
    </div>
    <?php if ($selEvento): ?>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <a class="btn" href="<?= e(base_url('/admin/eventos/' . $selEvento . '/inscritos/import')) ?>">⬆ Importar CSV</a>
            <?php if ($total > 0): ?>
                <a class="btn btn-primary" href="<?= e($exportUrl) ?>">⬇ Exportar CSV</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>

<!-- ── Filtres ──────────────────────────────────────────── -->
<form method="get" action="" class="filtres-inscrits">
    <div class="filtres-grid">
        <div class="filtre">
            <label for="f-evento">Esdeveniment</label>
            <select id="f-evento" name="evento_id" onchange="this.form.submit()" required>
                <?php if (count($eventos) === 0): ?>
                    <option value="">No hi ha esdeveniments</option>
                <?php else: ?>
                    <option value="">— Tria —</option>
                    <?php foreach ($eventos as $ev): ?>
                        <option value="<?= (int) $ev['id'] ?>" <?= $selEvento === (int) $ev['id'] ? 'selected' : '' ?>>
                            <?= e($ev['titulo']) ?> · <?= e(date('d/m/Y', strtotime((string) $ev['fecha_evento']))) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div class="filtre">
            <label for="f-estado">Estat</label>
            <select id="f-estado" name="estado" onchange="this.form.submit()">
                <option value="">Actius (sense cancel·lats ni pendents)</option>
                <?php foreach (['pendiente' => 'Pendents', 'confirmado' => 'Confirmats', 'cancelado' => 'Cancel·lats', 'reembolsado' => 'Reembossats'] as $k => $label): ?>
                    <option value="<?= $k ?>" <?= $filters['estado'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtre">
            <label for="f-search">Cerca (nom / email / DNI)</label>
            <input type="text" id="f-search" name="search" value="<?= e((string) ($filters['search'] ?? '')) ?>" placeholder="Marta, dni 12345...">
        </div>
        <div class="filtre">
            <label for="f-club">Club</label>
            <input type="text" id="f-club" name="club" value="<?= e((string) ($filters['club'] ?? '')) ?>">
        </div>
        <div class="filtre filtre-actions">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <?php if ($filters['estado'] || $filters['search'] || $filters['club']): ?>
                <a class="btn" href="?evento_id=<?= $selEvento ?>">Netejar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if (!$selEvento): ?>
    <div class="empty-state">
        <p>Tria un esdeveniment al filtre per veure els inscrits.</p>
    </div>
<?php elseif ($total === 0): ?>
    <div class="empty-state">
        <p>Cap inscrit encara amb aquests filtres.</p>
    </div>
<?php else: ?>

    <!-- ── Resum estats (totals respectant filtres) ──────── -->
    <div class="kpi-grid kpi-grid-4" style="margin-bottom:1.2rem;">
        <div class="kpi-card"><div class="kpi-label">Total filtrat</div><div class="kpi-value"><?= $total ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Confirmats</div><div class="kpi-value" style="color:#16a34a"><?= $counts['confirmado'] ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Pendents</div><div class="kpi-value" style="color:#f59e0b"><?= $counts['pendiente'] ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Cancel·lats</div><div class="kpi-value" style="color:#dc2626"><?= $counts['cancelado'] ?></div></div>
    </div>

    <?php
    // Barra de paginació reutilitzable (la usem dalt i baix del grid)
    $renderPagination = function () use ($page, $totalPages, $from, $to, $total, $perPage, $pageUrl) {
        if ($totalPages <= 1 && $total <= $perPage) return;
        ?>
        <nav class="pager" aria-label="Paginació">
            <div class="pager-info">
                Mostrant <strong><?= $from ?>–<?= $to ?></strong> de <strong><?= $total ?></strong>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="pager-controls">
                    <a class="pager-btn<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= $page > 1 ? e($pageUrl(1)) : '#' ?>">«</a>
                    <a class="pager-btn<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= $page > 1 ? e($pageUrl($page - 1)) : '#' ?>">‹</a>

                    <?php
                    // Mostrar fins a 5 pàgines al voltant de l'actual
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $start + 4);
                    $start = max(1, $end - 4);
                    for ($p = $start; $p <= $end; $p++):
                    ?>
                        <a class="pager-btn<?= $p === $page ? ' active' : '' ?>" href="<?= e($pageUrl($p)) ?>"><?= $p ?></a>
                    <?php endfor; ?>

                    <a class="pager-btn<?= $page >= $totalPages ? ' disabled' : '' ?>" href="<?= $page < $totalPages ? e($pageUrl($page + 1)) : '#' ?>">›</a>
                    <a class="pager-btn<?= $page >= $totalPages ? ' disabled' : '' ?>" href="<?= $page < $totalPages ? e($pageUrl($totalPages)) : '#' ?>">»</a>
                </div>
            <?php endif; ?>
        </nav>
        <?php
    };
    $renderPagination();
    ?>

    <!-- ── Column manager: toggle + panel ── -->
    <?php
    $colDefs = [
        ['key' => 'id',          'label' => '#'],
        ['key' => 'pedido',      'label' => 'Comanda'],
        ['key' => 'created_at',  'label' => 'Data'],
        ['key' => 'nombre',      'label' => 'Nom'],
        ['key' => 'apellido',    'label' => 'Cognoms'],
        ['key' => 'dni',         'label' => 'DNI'],
        ['key' => 'sexo',        'label' => 'Sexe'],
        ['key' => 'talla',       'label' => 'Talla'],
        ['key' => 'email',       'label' => 'Email'],
        ['key' => 'telefono',    'label' => 'Telèfon'],
        ['key' => 'club',        'label' => 'Club'],
        ['key' => 'poblacion',   'label' => 'Població'],
        ['key' => 'cp',          'label' => 'CP'],
        ['key' => 'tarifa',      'label' => 'Tarifa'],
        ['key' => 'precio',      'label' => 'Preu'],
        ['key' => 'dorsal',      'label' => 'Dorsal'],
        ['key' => 'estado',      'label' => 'Estat'],
        ['key' => 'accions',     'label' => 'Accions'],
    ];
    ?>
    <div class="col-manager-bar">
        <button type="button" class="col-manager-toggle" onclick="document.getElementById('colMgr').classList.toggle('open')">
            ⚙ Columnes
        </button>
    </div>
    <div id="colMgr" class="col-manager-panel">
        <h4>Reordena o amaga columnes (canvis es guarden al navegador)</h4>
        <ul class="col-manager-list" id="colMgrList">
            <?php foreach ($colDefs as $c): ?>
                <li class="col-manager-item" draggable="true" data-col="<?= e($c['key']) ?>">
                    <span class="handle">≡</span>
                    <input type="checkbox" id="cm-<?= e($c['key']) ?>" data-col-toggle="<?= e($c['key']) ?>" checked>
                    <label for="cm-<?= e($c['key']) ?>"><?= e($c['label']) ?></label>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="col-manager-actions">
            <button type="button" onclick="resetCols()">Restablir per defecte</button>
            <button type="button" onclick="document.getElementById('colMgr').classList.remove('open')">Tanca</button>
        </div>
    </div>

    <!-- ── Grid de inscrits ── -->
    <div class="grid-wrap">
        <table class="data-grid" id="inscritosGrid">
            <thead>
                <tr>
                    <th data-col="id">#</th>
                    <th data-col="pedido">Comanda</th>
                    <th data-col="created_at">Data</th>
                    <th data-col="nombre">Nom</th>
                    <th data-col="apellido">Cognoms</th>
                    <th data-col="dni">DNI</th>
                    <th data-col="sexo">Sexe</th>
                    <th data-col="talla">Talla</th>
                    <th data-col="email">Email</th>
                    <th data-col="telefono">Telèfon</th>
                    <th data-col="club">Club</th>
                    <th data-col="poblacion">Població</th>
                    <th data-col="cp">CP</th>
                    <th data-col="tarifa">Tarifa</th>
                    <th data-col="precio">Preu</th>
                    <th data-col="dorsal">Dorsal</th>
                    <th data-col="estado">Estat</th>
                    <th data-col="accions">Accions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($inscritos as $i): ?>
                <tr>
                    <td data-col="id"><?= (int)$i['id'] ?></td>
                    <td data-col="pedido">
                        <?php if (!empty($i['pedido_id'])): $pid = (int)$i['pedido_id']; $hue = ($pid * 47) % 360; ?>
                            <span class="badge" style="background:hsl(<?= $hue ?>,65%,92%);color:hsl(<?= $hue ?>,70%,28%);" title="Inscrits de la mateixa comanda (compra conjunta)">#<?= $pid ?></span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td data-col="created_at"><?= e(format_datetime_local((string)$i['created_at'], 'd/m/Y H:i')) ?></td>
                    <td data-col="nombre"><strong><?= e($i['nombre']) ?></strong></td>
                    <td data-col="apellido"><?= e($i['apellido']) ?></td>
                    <td data-col="dni"><?= e($i['dni']) ?></td>
                    <td data-col="sexo"><?= e($i['sexo']) ?></td>
                    <td data-col="talla"><?= e($i['talla_camiseta'] ?? '—') ?></td>
                    <td data-col="email"><?= e($i['email']) ?></td>
                    <td data-col="telefono"><?= e($i['telefono']) ?></td>
                    <td data-col="club"><?= e($i['club'] ?? '—') ?></td>
                    <td data-col="poblacion"><?= e($i['poblacion'] ?? '—') ?></td>
                    <td data-col="cp"><?= e($i['codigo_postal'] ?? '—') ?></td>
                    <td data-col="tarifa"><?= e($i['tarifa_nombre']) ?></td>
                    <td data-col="precio" class="cell-right"><?= e(format_price((float)$i['tarifa_precio'])) ?></td>
                    <td data-col="dorsal" class="cell-right"><?= !empty($i['numero_dorsal']) ? (int)$i['numero_dorsal'] : '—' ?></td>
                    <td data-col="estado">
                        <?php
                        // Si ja ha recollit el dorsal, mostrem "recollit" (verd); si no, l'estat
                        if (!empty($i['dorsal_recollit_at'])) {
                            $estLbl = '✓ recollit';
                            $badge  = 'badge-success';
                        } else {
                            [$estLbl, $badge] = match ($i['estado']) {
                                'confirmado'  => ['confirmat',  'badge-info'],
                                'pendiente'   => ['pendent',    'badge-warning'],
                                'cancelado'   => ['cancel·lat', 'badge-muted'],
                                'reembolsado' => ['reembossat', 'badge-muted'],
                                default       => [$i['estado'], 'badge-muted'],
                            };
                        }
                        ?>
                        <span class="badge <?= $badge ?>"><?= e($estLbl) ?></span>
                    </td>
                    <td data-col="accions" class="cell-actions">
                        <a class="btn-tiny" href="<?= e(base_url('/admin/inscritos/' . (int)$i['id'])) ?>" title="Veure fitxa completa">👁 Fitxa</a>
                        <?php if ($i['estado'] === 'confirmado'): ?>
                            <a class="btn-tiny" href="<?= e(base_url('/admin/inscritos/' . (int)$i['id'] . '/comprovant')) ?>" target="_blank" rel="noopener" title="Comprovant imprimible">📄</a>
                            <a class="btn-tiny" href="<?= e(base_url('/admin/inscritos/' . (int)$i['id'] . '/qr')) ?>" target="_blank" rel="noopener" title="Veure / descarregar QR">QR</a>
                            <form method="post" action="<?= e(base_url('/admin/inscritos/' . (int)$i['id'] . '/reenviar')) ?>" class="inline"
                                  onsubmit="return resendPrompt(this, '<?= e($i['email']) ?>')">
                                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="email_to" value="">
                                <button type="submit" class="btn-tiny" title="Reenviar email de confirmació (pots canviar el correu)">✉ Reenviar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    // Reenviar comprovant: demana a quin correu (prefixat amb el de l'inscrit, editable)
    function resendPrompt(form, defaultEmail) {
        var to = window.prompt('Reenviar el comprovant a aquest correu (pots canviar-lo):', defaultEmail || '');
        if (to === null) return false;          // cancel·lat
        to = to.trim();
        if (to === '') { alert('Indica un correu electrònic.'); return false; }
        form.querySelector('input[name="email_to"]').value = to;
        return true;
    }
    </script>

    <script>
    (function () {
        const STORAGE_KEY = 'inscritos_cols_v1';
        const defaultOrder = <?= json_encode(array_column($colDefs, 'key')) ?>;

        function loadPrefs() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) return { order: defaultOrder.slice(), hidden: [] };
                const p = JSON.parse(raw);
                // Filtrar columnes obsoletes i afegir noves
                const known = new Set(defaultOrder);
                const order = (p.order || []).filter(k => known.has(k));
                defaultOrder.forEach(k => { if (!order.includes(k)) order.push(k); });
                return { order, hidden: (p.hidden || []).filter(k => known.has(k)) };
            } catch (e) {
                return { order: defaultOrder.slice(), hidden: [] };
            }
        }

        function savePrefs(prefs) {
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs)); } catch (e) {}
        }

        function applyPrefs(prefs) {
            const grid = document.getElementById('inscritosGrid');
            if (!grid) return;

            // Reordenar columnes a thead + tbody
            const headRow = grid.querySelector('thead tr');
            const bodyRows = grid.querySelectorAll('tbody tr');

            // Mou un cell a la posició index
            function reorder(row, key, idx) {
                const cell = row.querySelector('[data-col="' + key + '"]');
                if (!cell) return;
                const children = Array.from(row.children);
                const currentIdx = children.indexOf(cell);
                if (currentIdx === idx) return;
                // Insertar al lloc correcte
                const ref = row.children[idx] || null;
                row.insertBefore(cell, ref);
            }

            prefs.order.forEach((key, idx) => {
                reorder(headRow, key, idx);
                bodyRows.forEach(r => reorder(r, key, idx));
            });

            // Aplicar visibilitat
            grid.querySelectorAll('[data-col]').forEach(el => {
                const col = el.getAttribute('data-col');
                el.classList.toggle('col-hidden', prefs.hidden.includes(col));
            });

            // Sincronitzar UI del panel: checkboxes + ordre de la llista
            const list = document.getElementById('colMgrList');
            if (list) {
                prefs.order.forEach((key, idx) => {
                    const item = list.querySelector('[data-col="' + key + '"]');
                    if (item) {
                        const ref = list.children[idx] || null;
                        if (ref !== item) list.insertBefore(item, ref);
                    }
                });
                list.querySelectorAll('input[data-col-toggle]').forEach(cb => {
                    const key = cb.getAttribute('data-col-toggle');
                    cb.checked = !prefs.hidden.includes(key);
                });
            }
        }

        function currentPrefsFromUI() {
            const list = document.getElementById('colMgrList');
            const order = Array.from(list.querySelectorAll('[data-col]')).map(el => el.getAttribute('data-col'));
            const hidden = Array.from(list.querySelectorAll('input[data-col-toggle]'))
                .filter(cb => !cb.checked)
                .map(cb => cb.getAttribute('data-col-toggle'));
            return { order, hidden };
        }

        // Drag and drop reorder
        function bindDragAndDrop() {
            const list = document.getElementById('colMgrList');
            let dragged = null;

            list.querySelectorAll('.col-manager-item').forEach(item => {
                item.addEventListener('dragstart', e => {
                    dragged = item;
                    item.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });
                item.addEventListener('dragend', () => {
                    item.classList.remove('dragging');
                    list.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
                });
                item.addEventListener('dragover', e => {
                    e.preventDefault();
                    if (!dragged || dragged === item) return;
                    item.classList.add('drag-over');
                });
                item.addEventListener('dragleave', () => item.classList.remove('drag-over'));
                item.addEventListener('drop', e => {
                    e.preventDefault();
                    if (!dragged || dragged === item) return;
                    const rect = item.getBoundingClientRect();
                    const after = (e.clientY - rect.top) > rect.height / 2;
                    list.insertBefore(dragged, after ? item.nextSibling : item);
                    item.classList.remove('drag-over');
                    const prefs = currentPrefsFromUI();
                    savePrefs(prefs);
                    applyPrefs(prefs);
                });
            });

            // Checkboxes
            list.querySelectorAll('input[data-col-toggle]').forEach(cb => {
                cb.addEventListener('change', () => {
                    const prefs = currentPrefsFromUI();
                    savePrefs(prefs);
                    applyPrefs(prefs);
                });
            });
        }

        window.resetCols = function () {
            localStorage.removeItem(STORAGE_KEY);
            applyPrefs({ order: defaultOrder.slice(), hidden: [] });
        };

        // Init
        const prefs = loadPrefs();
        applyPrefs(prefs);
        bindDragAndDrop();
    })();
    </script>

    <?php $renderPagination(); ?>
<?php endif; ?>
