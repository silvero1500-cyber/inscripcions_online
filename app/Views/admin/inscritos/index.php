<?php
/** @var object $user */
/** @var list<array> $eventos */
/** @var list<array> $inscritos */
/** @var array $filters */
/** @var array $counts */
/** @var list<string> $franjas */
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
                <option value="">Actius (per defecte)</option>
                <?php foreach (['confirmado' => 'Confirmats', 'reembolsado' => 'Reembossats'] as $k => $label): ?>
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

    <!-- ── Resum (totals respectant filtres) ──────── -->
    <div class="dash-kpis dash-kpis-3" style="margin-bottom:1.4rem;">
        <div class="dash-kpi kpi-blue">
            <span class="dash-kpi-ico">📋</span>
            <span class="dash-kpi-val"><?= $total ?></span>
            <span class="dash-kpi-lbl">Total filtrat</span>
        </div>
        <div class="dash-kpi kpi-green">
            <span class="dash-kpi-ico">✅</span>
            <span class="dash-kpi-val"><?= $counts['confirmado'] ?></span>
            <span class="dash-kpi-lbl">Confirmats</span>
        </div>
        <div class="dash-kpi kpi-indigo">
            <span class="dash-kpi-ico">🎽</span>
            <span class="dash-kpi-val"><?= $recollits ?></span>
            <span class="dash-kpi-lbl">Recollit dorsal</span>
        </div>
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
        ['key' => 'fnac',        'label' => 'Data naix.'],
        ['key' => 'talla',       'label' => 'Talla'],
        ['key' => 'franja',      'label' => 'Franja'],
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
                    <th data-col="fnac">Data naix.</th>
                    <th data-col="talla">Talla</th>
                    <th data-col="franja">Franja</th>
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
            <?php
            $canEdit = in_array($user->rol ?? '', ['superadmin', 'organizador'], true);
            foreach ($inscritos as $i):
            ?>
                <tr data-id="<?= (int)$i['id'] ?>" data-evento="<?= (int)$i['evento_id'] ?>">
                    <td data-col="id"><?= (int)$i['id'] ?></td>
                    <td data-col="pedido">
                        <?php if (!empty($i['pedido_id'])): $pid = (int)$i['pedido_id']; $hue = ($pid * 47) % 360; ?>
                            <span class="badge" style="background:hsl(<?= $hue ?>,65%,92%);color:hsl(<?= $hue ?>,70%,28%);" title="Inscrits de la mateixa comanda (compra conjunta)">#<?= $pid ?></span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td data-col="created_at"><?= e(format_datetime_local((string)$i['created_at'], 'd/m/Y H:i')) ?></td>
                    <td data-col="nombre" data-edit="text" data-field="nombre" data-val="<?= e($i['nombre']) ?>"><strong><?= e($i['nombre']) ?></strong></td>
                    <td data-col="apellido" data-edit="text" data-field="apellido" data-val="<?= e($i['apellido']) ?>"><?= e($i['apellido']) ?></td>
                    <td data-col="dni" data-edit="text" data-field="dni" data-val="<?= e($i['dni']) ?>"><?= e($i['dni']) ?></td>
                    <td data-col="sexo" data-edit="sexo" data-field="sexo" data-val="<?= e($i['sexo']) ?>"><?= e($i['sexo']) ?></td>
                    <td data-col="fnac" data-edit="date" data-field="fecha_nacimiento" data-val="<?= e($i['fecha_nacimiento'] ?? '') ?>"><?= !empty($i['fecha_nacimiento']) ? e(date('d/m/Y', strtotime((string)$i['fecha_nacimiento']))) : '—' ?></td>
                    <td data-col="talla" data-edit="talla" data-field="talla_camiseta" data-val="<?= e($i['talla_camiseta'] ?? '') ?>"><?= e($i['talla_camiseta'] ?? '—') ?></td>
                    <td data-col="franja" data-edit="franja" data-field="franja_temps" data-val="<?= e($i['franja_temps'] ?? '') ?>"><?= e($i['franja_temps'] ?? '—') ?></td>
                    <td data-col="email" data-edit="email" data-field="email" data-val="<?= e($i['email']) ?>"><?= e($i['email']) ?></td>
                    <td data-col="telefono" data-edit="text" data-field="telefono" data-val="<?= e($i['telefono']) ?>"><?= e($i['telefono']) ?></td>
                    <td data-col="club" data-edit="text" data-field="club" data-val="<?= e($i['club'] ?? '') ?>"><?= e($i['club'] ?? '—') ?></td>
                    <td data-col="poblacion" data-edit="text" data-field="poblacion" data-val="<?= e($i['poblacion'] ?? '') ?>"><?= e($i['poblacion'] ?? '—') ?></td>
                    <td data-col="cp" data-edit="text" data-field="codigo_postal" data-val="<?= e($i['codigo_postal'] ?? '') ?>"><?= e($i['codigo_postal'] ?? '—') ?></td>
                    <td data-col="tarifa"><?= e($i['tarifa_nombre']) ?></td>
                    <td data-col="precio" class="cell-right"><?= e(format_price((float)$i['tarifa_precio'])) ?></td>
                    <td data-col="dorsal" class="cell-right" data-edit="dorsal" data-field="numero_dorsal" data-val="<?= !empty($i['numero_dorsal']) ? (int)$i['numero_dorsal'] : '' ?>"><?= !empty($i['numero_dorsal']) ? (int)$i['numero_dorsal'] : '—' ?></td>
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
                        <span class="acc-normal">
                            <?php if ($canEdit): ?>
                                <button type="button" class="btn-tiny acc-edit" title="Editar inscrit">✏️ Editar</button>
                            <?php endif; ?>
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
                        </span>
                        <?php if ($canEdit): ?>
                            <span class="acc-edit-mode" style="display:none;">
                                <button type="button" class="btn-tiny btn-primary acc-save" title="Desar">💾 Desa</button>
                                <button type="button" class="btn-tiny acc-cancel" title="Cancel·lar">✖</button>
                            </span>
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

    <?php $canEditJs = in_array($user->rol ?? '', ['superadmin', 'organizador'], true); ?>
    <?php if ($canEditJs): ?>
    <script>
    // ── Edició inline d'inscrits al grid ──
    (function () {
        var CSRF = <?= json_encode(\App\Core\Csrf::token()) ?>;
        var BASE = <?= json_encode(base_url('/admin/inscritos/')) ?>;
        var SEXOS = [['', '—'], ['H', 'H'], ['M', 'M'], ['NB', 'NB']];
        var TALLAS = <?= json_encode(\App\Models\Inscrito::TALLAS) ?>;
        var FRANJAS = <?= json_encode($franjas) ?>;

        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]); }); }

        // Barra flotant fixa amb Desa/Cancel·la (sempre visible, sense scroll)
        var currentRow = null;
        var bar = document.createElement('div');
        bar.id = 'inline-edit-bar';
        bar.className = 'inline-edit-bar';
        bar.innerHTML = '<span class="ieb-info"></span>'
            + '<button type="button" class="btn btn-primary ieb-save">💾 Desa</button>'
            + '<button type="button" class="btn ieb-cancel">✖ Cancel·la</button>';
        document.body.appendChild(bar);
        bar.querySelector('.ieb-save').addEventListener('click', function () { if (currentRow) saveEdit(currentRow); });
        bar.querySelector('.ieb-cancel').addEventListener('click', function () { if (currentRow) exitEdit(currentRow, true); });

        function makeInput(cell) {
            var type = cell.getAttribute('data-edit');
            var val = cell.getAttribute('data-val') || '';
            var sel, i, o;
            if (type === 'sexo' || type === 'talla' || type === 'franja') {
                sel = document.createElement('select');
                var opts = type === 'sexo' ? SEXOS
                        : (type === 'talla' ? [['', '—']].concat(TALLAS.map(function (t) { return [t, t]; }))
                        : [['', '—']].concat(FRANJAS.map(function (f) { return [f, f]; })));
                for (i = 0; i < opts.length; i++) {
                    o = document.createElement('option');
                    o.value = opts[i][0]; o.textContent = opts[i][1];
                    if (opts[i][0] === val) o.selected = true;
                    sel.appendChild(o);
                }
                return sel;
            }
            var inp = document.createElement('input');
            inp.value = val;
            if (type === 'date') inp.type = 'date';
            else if (type === 'dorsal') { inp.type = 'number'; inp.min = '1'; }
            else if (type === 'email') inp.type = 'email';
            else inp.type = 'text';
            return inp;
        }

        function enterEdit(row) {
            if (row.classList.contains('row-editing')) return;
            if (currentRow && currentRow !== row) exitEdit(currentRow, true); // només una fila a l'hora
            row.classList.add('row-editing');
            var first = null;
            row.querySelectorAll('td[data-edit]').forEach(function (cell) {
                cell.setAttribute('data-html', cell.innerHTML);
                var inp = makeInput(cell);
                inp.className = 'cell-edit-input';
                inp.setAttribute('data-field', cell.getAttribute('data-field'));
                cell.innerHTML = '';
                cell.appendChild(inp);
                if (!first) first = inp;
            });
            row.querySelector('.acc-normal').style.display = 'none';
            row.querySelector('.acc-edit-mode').style.display = '';
            currentRow = row;
            // Mostra la barra fixa amb el nom de l'inscrit
            var nameCell = row.querySelector('td[data-field="nombre"]');
            var ape = row.querySelector('td[data-field="apellido"]');
            var nom = (nameCell ? (nameCell.getAttribute('data-val') || '') : '') + ' ' + (ape ? (ape.getAttribute('data-val') || '') : '');
            bar.querySelector('.ieb-info').textContent = '✏️ Editant: ' + nom.trim();
            bar.classList.add('show');
            if (first) { try { first.focus(); } catch (e) {} }
        }

        function exitEdit(row, restore) {
            row.querySelectorAll('td[data-edit]').forEach(function (cell) {
                if (restore && cell.hasAttribute('data-html')) cell.innerHTML = cell.getAttribute('data-html');
                cell.removeAttribute('data-html');
            });
            row.classList.remove('row-editing');
            row.querySelector('.acc-normal').style.display = '';
            row.querySelector('.acc-edit-mode').style.display = 'none';
            if (currentRow === row) { currentRow = null; bar.classList.remove('show'); }
        }

        function repaint(row, ins) {
            var map = {
                nombre: '<strong>' + esc(ins.nombre) + '</strong>',
                apellido: esc(ins.apellido) || '',
                dni: esc(ins.dni) || '',
                sexo: esc(ins.sexo) || '',
                fecha_nacimiento: esc(ins.fnac),
                talla_camiseta: esc(ins.talla),
                franja_temps: esc(ins.franja),
                email: esc(ins.email),
                telefono: esc(ins.telefono) || '',
                club: esc(ins.club),
                poblacion: esc(ins.poblacion),
                codigo_postal: esc(ins.cp),
                numero_dorsal: esc(ins.dorsal)
            };
            var newVal = {
                nombre: ins.nombre, apellido: ins.apellido, dni: ins.dni, sexo: ins.sexo,
                fecha_nacimiento: ins.fnac_raw, talla_camiseta: ins.talla === '—' ? '' : ins.talla,
                franja_temps: ins.franja === '—' ? '' : ins.franja, email: ins.email, telefono: ins.telefono,
                club: ins.club === '—' ? '' : ins.club, poblacion: ins.poblacion === '—' ? '' : ins.poblacion,
                codigo_postal: ins.cp === '—' ? '' : ins.cp, numero_dorsal: ins.dorsal === '—' ? '' : ins.dorsal
            };
            row.querySelectorAll('td[data-edit]').forEach(function (cell) {
                var f = cell.getAttribute('data-field');
                if (f in map) cell.setAttribute('data-html', map[f]);
                if (f in newVal) cell.setAttribute('data-val', newVal[f]);
            });
        }

        function saveEdit(row) {
            var id = row.getAttribute('data-id');
            var fd = new FormData();
            fd.append('_csrf', CSRF);
            row.querySelectorAll('.cell-edit-input').forEach(function (inp) {
                fd.append(inp.getAttribute('data-field'), inp.value);
            });
            var btn = row.querySelector('.acc-save');
            var barBtn = bar.querySelector('.ieb-save');
            btn.disabled = true; barBtn.disabled = true;
            fetch(BASE + id + '/editar', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
                .then(function (res) {
                    btn.disabled = false; barBtn.disabled = false;
                    if (res.body && res.body.ok) {
                        repaint(row, res.body.inscrito);
                        exitEdit(row, true);
                    } else if (res.body && res.body.errors) {
                        var msgs = Object.keys(res.body.errors).map(function (k) { return res.body.errors[k]; });
                        alert('No s\'ha pogut desar:\n• ' + msgs.join('\n• '));
                    } else {
                        alert((res.body && res.body.message) || 'Error en desar.');
                    }
                })
                .catch(function () { btn.disabled = false; barBtn.disabled = false; alert('Error de connexió.'); });
        }

        document.addEventListener('click', function (e) {
            var t = e.target;
            if (!t.closest) return;
            var row = t.closest('tr[data-id]');
            if (!row) return;
            if (t.closest('.acc-edit')) { e.preventDefault(); enterEdit(row); }
            else if (t.closest('.acc-cancel')) { e.preventDefault(); exitEdit(row, true); }
            else if (t.closest('.acc-save')) { e.preventDefault(); saveEdit(row); }
        });

        // Dreceres: Enter = desa, Esc = cancel·la (quan s'està editant)
        document.addEventListener('keydown', function (e) {
            if (!currentRow) return;
            if (e.key === 'Enter' && !e.shiftKey) {
                var tg = e.target;
                // Enter dins d'un input/select de la fila → desa
                if (tg && tg.classList && tg.classList.contains('cell-edit-input')) {
                    e.preventDefault();
                    saveEdit(currentRow);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                exitEdit(currentRow, true);
            }
        });
    })();
    </script>
    <?php endif; ?>

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
