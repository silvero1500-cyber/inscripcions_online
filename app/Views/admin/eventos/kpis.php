<?php
/** @var array $evento */
/** @var array $estados */
/** @var int $totalActivas */
/** @var float $ingresosConfirmados */
/** @var float $ingresosPotenciales */
/** @var array $porSexo */
/** @var array $porChip */
/** @var list<array> $porTarifa */
/** @var array $rangos */
/** @var array $porPagament */
/** @var list<array> $edatCategoria */
/** @var list<array> $vendaTrams */
/** @var list<array> $evolucion */
/** @var list<array> $evolucionCat */
/** @var float|null $creixementMitja */
/** @var list<array> $tallaSexo */
/** @var list<array> $topClubs */
/** @var list<array> $topPoblaciones */
/** @var int $darrers7 */
/** @var array|null $comparativa */
/** @var int|null $aforoMax */

$sexoLabels = ['H' => 'Home', 'M' => 'Dona', 'NB' => 'No binari'];
$tallaOrden = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'Sense talla'];

$pctOcupacion = $aforoMax !== null && $aforoMax > 0
    ? min(100, round($totalActivas * 100 / $aforoMax))
    : null;

// Franges d'edat en ordre fix
$franjaOrden = ['<18', '18-29', '30-39', '40-49', '50-59', '60+'];

// Construir matriu franja → [categoria => n] per al drill-down
$edatTotals = array_fill_keys($franjaOrden, 0);
$edatCatMap = [];                 // [franja][categoria] = n
$categoriesSet = [];
foreach ($edatCategoria as $row) {
    $fr = (string) $row['franja'];
    if (!in_array($fr, $franjaOrden, true)) continue; // ignora "Sense data"
    $cat = (string) $row['categoria'];
    $n = (int) $row['n'];
    $edatTotals[$fr] += $n;
    $edatCatMap[$fr][$cat] = ($edatCatMap[$fr][$cat] ?? 0) + $n;
    $categoriesSet[$cat] = true;
}

// Comparativa: helper de format del delta
$delta = function (int $d): array {
    if ($d > 0) return ['cls' => 'kpi-delta-up',   'txt' => '+' . $d];
    if ($d < 0) return ['cls' => 'kpi-delta-down', 'txt' => (string) $d];
    return ['cls' => 'kpi-delta-flat', 'txt' => '±0'];
};

// ── Evolució 90 dies per categoria → eix de dies + una sèrie per categoria ──
$evoDays = array_values(array_unique(array_map(fn($r) => (string) $r['dia'], $evolucionCat)));
sort($evoDays);
$evoCats = [];
foreach ($evolucionCat as $r) { $evoCats[(string) $r['categoria']] = true; }
$evoCats = array_keys($evoCats);
$evoSeries = [];                          // [categoria] => [dia => n]
foreach ($evolucionCat as $r) {
    $evoSeries[(string) $r['categoria']][(string) $r['dia']] = (int) $r['n'];
}
$evoCatData = [];
foreach ($evoCats as $cat) {
    $evoCatData[] = [
        'label' => $cat,
        'data'  => array_map(fn($d) => (int) ($evoSeries[$cat][$d] ?? 0), $evoDays),
    ];
}

// ── Talla × sexe (apilat): per cada talla, Unisex i Dona ──
$tallaGrupMap = [];                        // [talla][grup] = n
$tallaTotals  = [];
foreach ($tallaSexo as $r) {
    $tl = (string) $r['talla']; $g = (string) $r['grup']; $n = (int) $r['n'];
    $tallaGrupMap[$tl][$g] = ($tallaGrupMap[$tl][$g] ?? 0) + $n;
    $tallaTotals[$tl] = ($tallaTotals[$tl] ?? 0) + $n;
}
$tallaLabels = [];
foreach ($tallaOrden as $tl) { if (isset($tallaTotals[$tl])) $tallaLabels[] = $tl; }
foreach (array_keys($tallaTotals) as $tl) { if (!in_array($tl, $tallaLabels, true)) $tallaLabels[] = $tl; }
$tallaUnisex = array_map(fn($tl) => (int) ($tallaGrupMap[$tl]['Unisex'] ?? 0), $tallaLabels);
$tallaDona   = array_map(fn($tl) => (int) ($tallaGrupMap[$tl]['Dona'] ?? 0), $tallaLabels);

// % sobre el total (per als tops)
$pctTotal = fn(int $n): string => $totalActivas > 0 ? number_format($n * 100 / $totalActivas, 1, ',', '.') . '%' : '—';

// ── Xip groc: valor absolut + (%) al mateix label, per veure's sense hover ──
$chipLabels = ['si' => 'Xip propi', 'no' => 'Xip de cessió', 'sense_resposta' => 'Sense resposta'];
$chipTotal = array_sum($porChip);
$pctChip = fn(int $n): string => $chipTotal > 0 ? number_format($n * 100 / $chipTotal, 0) : '0';
$chipData = [];
foreach (['si', 'no', 'sense_resposta'] as $k) {
    if (empty($porChip[$k])) continue;
    $n = (int) $porChip[$k];
    $chipData[] = ['label' => $chipLabels[$k] . ' — ' . $n . ' (' . $pctChip($n) . '%)', 'value' => $n];
}

// Dades JSON per a JavaScript
$jsData = [
    'tarifa'  => array_map(fn($r) => ['label' => $r['nombre'], 'value' => (int) $r['n']], $porTarifa),
    'sexo'    => array_map(fn($k) => ['label' => $sexoLabels[$k] ?? $k, 'value' => (int) $porSexo[$k]], array_keys($porSexo)),
    'pagament'=> array_map(fn($k) => ['label' => $k, 'value' => (int) $porPagament[$k]], array_keys($porPagament)),
    'chip'    => $chipData,
    'edat'    => array_map(fn($f) => ['label' => $f, 'value' => (int) $edatTotals[$f]], $franjaOrden),
    'edatCat' => $edatCatMap,        // [franja][categoria] = n
    'vendaTrams' => array_map(fn($r) => [
        'label' => $r['tarifa'] . ' · ' . number_format((float) $r['preu'], 2, ',', '.') . ' €',
        'value' => (int) $r['n'],
    ], $vendaTrams),
    'evolucion'   => array_map(fn($r) => ['label' => $r['dia'], 'value' => (int) $r['n']], $evolucion),
    'evoDays'     => $evoDays,
    'evoCat'      => $evoCatData,     // [{label, data[]}]
    'tallaLabels' => $tallaLabels,
    'tallaUnisex' => $tallaUnisex,
    'tallaDona'   => $tallaDona,
];
?>
<section class="page-head with-action">
    <div>
        <h1>KPIs · <?= e($evento['titulo']) ?></h1>
        <p class="muted"><?= e(format_date_ca((string) $evento['fecha_evento'], true)) ?></p>
    </div>
    <div style="display:flex;gap:.6rem;">
        <a class="btn" href="<?= e(base_url('/admin/eventos')) ?>">← Tornar</a>
        <a class="btn" href="<?= e(base_url('/admin/eventos/' . (int) $evento['id'] . '/editar')) ?>">Editar</a>
    </div>
</section>

<?php $barEvento = $evento; $barActual = 'kpis'; require __DIR__ . '/../partials/cursa_bar.php'; ?>

<?php /* ══ NIVELL 1 · 4 targetes ══════════════════════════════ */ ?>
<div class="kpi-grid kpi-grid-4">
    <div class="kpi-card kpi-card-lvl1">
        <div class="kpi-label">Total inscrits</div>
        <div class="kpi-value"><?= $totalActivas ?></div>
        <div class="kpi-sub">
            <?= (int) ($estados['confirmado'] ?? 0) ?> confirmats · <?= (int) ($estados['pendiente'] ?? 0) ?> pendents
        </div>
        <?php if ($comparativa !== null): $d = $delta($comparativa['deltaTotal']); ?>
            <div class="kpi-cmp <?= $d['cls'] ?>">
                <?= e($d['txt']) ?> vs edició <?= (int) $comparativa['anyAnterior'] ?> (a data d'avui)
            </div>
        <?php endif; ?>
    </div>

    <div class="kpi-card kpi-card-lvl1">
        <div class="kpi-label">Ingressos confirmats</div>
        <div class="kpi-value"><?= e(format_price($ingresosConfirmados)) ?></div>
        <div class="kpi-sub">Potencial: <?= e(format_price($ingresosPotenciales)) ?></div>
    </div>

    <div class="kpi-card kpi-card-lvl1">
        <div class="kpi-label">Aforament</div>
        <?php if ($aforoMax !== null): ?>
            <div class="kpi-value"><?= $pctOcupacion ?>%</div>
            <div class="kpi-sub"><?= $totalActivas ?> / <?= $aforoMax ?> places</div>
            <div class="kpi-progress"><div class="kpi-progress-bar" style="width:<?= $pctOcupacion ?>%"></div></div>
        <?php else: ?>
            <div class="kpi-value">∞</div>
            <div class="kpi-sub">Sense límit</div>
        <?php endif; ?>
        <?php if ($comparativa !== null && $comparativa['previsio'] !== null): ?>
            <div class="kpi-cmp kpi-delta-prev">
                Previsió: ~<?= (int) $comparativa['previsio'] ?> inscrits
                <span class="muted">(creixement edició <?= (int) $comparativa['anyAnterior'] ?>)</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="kpi-card kpi-card-lvl1">
        <div class="kpi-label">Inscrits últims 7 dies</div>
        <div class="kpi-value"><?= $darrers7 ?></div>
        <?php if ($comparativa !== null): $d = $delta($comparativa['delta7']); ?>
            <div class="kpi-cmp <?= $d['cls'] ?>">
                <?= e($d['txt']) ?> vs mateixos 7 dies edició <?= (int) $comparativa['anyAnterior'] ?>
            </div>
        <?php else: ?>
            <div class="kpi-sub muted">Sense edició anterior per comparar</div>
        <?php endif; ?>
    </div>
</div>

<?php /* ══ NIVELL 2 · desglossos ══════════════════════════════ */ ?>
<div class="kpi-grid kpi-grid-4">
    <div class="kpi-panel">
        <h2>Per categoria</h2>
        <div class="kpi-chart-wrap"><canvas id="chartCategoria"></canvas></div>
    </div>
    <div class="kpi-panel">
        <h2>Per sexe</h2>
        <div class="kpi-chart-wrap"><canvas id="chartSexo"></canvas></div>
    </div>
    <div class="kpi-panel">
        <h2>Per mètode de pagament</h2>
        <div class="kpi-chart-wrap"><canvas id="chartPagament"></canvas></div>
    </div>
    <div class="kpi-panel">
        <h2>Xip groc: propi o de cessió</h2>
        <div class="kpi-chart-wrap"><canvas id="chartChip"></canvas></div>
    </div>
</div>

<div class="kpi-panel">
    <div class="kpi-panel-head">
        <h2 id="edatTitol">Per franja d'edat</h2>
        <button type="button" id="edatBack" class="btn btn-small" style="display:none;">← Totes les franges</button>
    </div>
    <p class="muted small" style="margin:.2rem 0 1rem;">Clica una franja per veure'n el desglós per categoria.</p>
    <div class="kpi-chart-wrap kpi-chart-wide"><canvas id="chartEdat"></canvas></div>
</div>

<?php if (!empty($vendaTrams)): ?>
<div class="kpi-panel">
    <h2>Venda per trams</h2>
    <p class="muted small" style="margin:.2rem 0 1rem;">Inscrits segons el preu (tram) que se'ls va aplicar en inscriure's.</p>
    <div class="kpi-chart-wrap kpi-chart-wide"><canvas id="chartTrams"></canvas></div>
</div>
<?php endif; ?>

<?php /* ══ NIVELL 3 · evolució 90 dies + talla ════════════════ */ ?>
<div class="kpi-panel">
    <div class="kpi-panel-head">
        <h2>Evolució inscrits <span class="muted" style="font-weight:400;font-size:.9rem;">(últims 90 dies)</span></h2>
        <?php if ($creixementMitja !== null): ?>
            <span class="kpi-growth">📈 Creixement diari mitjà: <strong><?= e(number_format($creixementMitja, 1, ',', '.')) ?>%</strong></span>
        <?php endif; ?>
    </div>
    <?php if (count($evolucion) === 0): ?>
        <p class="muted">Cap inscripció recent.</p>
    <?php else: ?>
        <div class="kpi-chart-wrap kpi-chart-wide"><canvas id="chartEvolucion"></canvas></div>
    <?php endif; ?>
</div>

<div class="kpi-panel">
    <h2>Talla samarreta <span class="muted" style="font-weight:400;font-size:.9rem;">(unisex / dona)</span></h2>
    <div class="kpi-grid kpi-grid-2">
        <div>
            <?php if (empty($tallaLabels)): ?>
                <p class="muted">Cap inscrit amb talla encara.</p>
            <?php else: ?>
                <div class="kpi-chart-wrap"><canvas id="chartTalla"></canvas></div>
            <?php endif; ?>
        </div>
        <div>
            <h3 style="margin:0 0 .8rem;font-size:1rem;">Estimació de comanda</h3>
            <p class="muted small" style="margin:0 0 .8rem;">
                Quantes samarretes necessitaràs si arribes a un total objectiu, segons la distribució actual.
            </p>
            <div class="form-row">
                <label for="objectiu">Inscrits objectiu</label>
                <input type="number" id="objectiu" min="<?= max(1, $totalActivas) ?>" step="1" value="<?= max(100, $totalActivas) ?>">
            </div>
            <table class="data-table kpi-table-estim">
                <thead><tr><th>Talla</th><th>Unisex</th><th>Dona</th><th>Total</th><th>Estimat</th></tr></thead>
                <tbody id="tblEstim"></tbody>
                <tfoot><tr><td><strong>Total amb talla</strong></td><td id="totUni">0</td><td id="totDona">0</td><td id="totalActual">0</td><td id="totalEstim">0</td></tr></tfoot>
            </table>
            <p class="muted small" style="margin:.8rem 0 0;">Els inscrits "sense talla" no compten per a la proporció.</p>
        </div>
    </div>
</div>

<?php /* ══ NIVELL 4 · tops amb % sobre total ═══════════════════ */ ?>
<div class="kpi-grid kpi-grid-2">
    <div class="kpi-panel">
        <h2>Top clubs</h2>
        <?php if (count($topClubs) === 0): ?>
            <p class="muted">Cap inscrit ha indicat club.</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Club</th><th>Inscrits</th><th>% del total</th></tr></thead>
                <tbody>
                    <?php foreach ($topClubs as $r): ?>
                        <tr>
                            <td><?= e($r['club']) ?></td>
                            <td><?= (int) $r['n'] ?></td>
                            <td><?= e($pctTotal((int) $r['n'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <div class="kpi-panel">
        <h2>Top poblacions</h2>
        <?php if (count($topPoblaciones) === 0): ?>
            <p class="muted">Cap inscrit ha indicat població.</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Població</th><th>Inscrits</th><th>% del total</th></tr></thead>
                <tbody>
                    <?php foreach ($topPoblaciones as $r): ?>
                        <tr>
                            <td><?= e($r['poblacion']) ?></td>
                            <td><?= (int) $r['n'] ?></td>
                            <td><?= e($pctTotal((int) $r['n'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script src="<?= e(asset('js/chart.umd.min.js')) ?>"></script>
<script>
(function () {
    const data = <?= json_encode($jsData, JSON_UNESCAPED_UNICODE) ?>;
    const colors = ['#1e88c2', '#dc2626', '#16a34a', '#f59e0b', '#8b5cf6', '#0ea5e9', '#ec4899', '#64748b'];
    const baseOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };

    function makePie(id, dataset) {
        if (!dataset || dataset.length === 0) return;
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'doughnut',
            data: { labels: dataset.map(d => d.label), datasets: [{ data: dataset.map(d => d.value), backgroundColor: colors }] },
            options: baseOpts
        });
    }

    function makeLine(id, dataset, color) {
        if (!dataset || dataset.length === 0) return;
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'line',
            data: { labels: dataset.map(d => d.label), datasets: [{
                data: dataset.map(d => d.value), borderColor: color || '#1e88c2',
                backgroundColor: 'rgba(30, 136, 194, .1)', tension: 0.3, fill: true, pointRadius: 3
            }] },
            options: { ...baseOpts, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }

    function makeBarSimple(id, dataset, color) {
        if (!dataset || dataset.length === 0) return;
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'bar',
            data: { labels: dataset.map(d => d.label), datasets: [{ data: dataset.map(d => d.value), backgroundColor: color || '#1e88c2' }] },
            options: { ...baseOpts, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }

    makePie('chartCategoria', data.tarifa);
    makePie('chartSexo', data.sexo);
    makePie('chartPagament', data.pagament);
    makePie('chartChip', data.chip);
    makeBarSimple('chartTrams', data.vendaTrams);

    // ── Evolució 90 dies: una línia per categoria ───────────
    (function () {
        const el = document.getElementById('chartEvolucion');
        if (!el || !data.evoDays || data.evoDays.length === 0) return;
        const datasets = (data.evoCat || []).map((s, idx) => {
            const c = colors[idx % colors.length];
            return { label: s.label, data: s.data, borderColor: c, backgroundColor: c + '22', tension: 0.3, fill: false, pointRadius: 0, borderWidth: 2 };
        });
        new Chart(el, {
            type: 'line',
            data: { labels: data.evoDays.map(d => d.slice(5)), datasets: datasets },
            options: { ...baseOpts, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    })();

    // ── Talla samarreta apilada (Unisex / Dona) ─────────────
    (function () {
        const el = document.getElementById('chartTalla');
        if (!el || !data.tallaLabels || data.tallaLabels.length === 0) return;
        new Chart(el, {
            type: 'bar',
            data: {
                labels: data.tallaLabels,
                datasets: [
                    { label: 'Unisex', data: data.tallaUnisex, backgroundColor: '#1e88c2' },
                    { label: 'Dona',   data: data.tallaDona,   backgroundColor: '#ec4899' }
                ]
            },
            options: {
                ...baseOpts,
                plugins: { legend: { position: 'bottom' } },
                scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    })();

    // ── Estimador de comanda (per talla, amb desglós unisex/dona) ──
    (function () {
        const labels = data.tallaLabels || [];
        const uni = data.tallaUnisex || [];
        const dona = data.tallaDona || [];
        const tot = labels.map((_, i) => (uni[i] || 0) + (dona[i] || 0));
        // Exclou "Sense talla" del càlcul de proporció
        const idxValid = labels.map((l, i) => l === 'Sense talla' ? -1 : i).filter(i => i >= 0);
        const totalConTalla = idxValid.reduce((s, i) => s + tot[i], 0);

        const inputObj = document.getElementById('objectiu');
        const tbody = document.getElementById('tblEstim');
        const totUniEl = document.getElementById('totUni');
        const totDonaEl = document.getElementById('totDona');
        const totActEl = document.getElementById('totalActual');
        const totEstEl = document.getElementById('totalEstim');
        if (!tbody) return;

        function refrescar() {
            const objectiu = Math.max(1, parseInt((inputObj && inputObj.value) || '0', 10));
            let tU = 0, tD = 0, tA = 0, tE = 0;
            tbody.innerHTML = '';
            if (totalConTalla === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="muted" style="text-align:center;">Cap inscrit amb talla encara.</td></tr>';
                [totUniEl, totDonaEl, totActEl, totEstEl].forEach(e => { if (e) e.textContent = '0'; });
                return;
            }
            idxValid.forEach(i => {
                const total = tot[i];
                const estim = Math.round(total / totalConTalla * objectiu);
                tU += uni[i] || 0; tD += dona[i] || 0; tA += total; tE += estim;
                tbody.innerHTML += `<tr><td><strong>${labels[i]}</strong></td><td>${uni[i] || 0}</td><td>${dona[i] || 0}</td><td>${total}</td><td><strong>${estim}</strong></td></tr>`;
            });
            if (totUniEl) totUniEl.textContent = tU;
            if (totDonaEl) totDonaEl.textContent = tD;
            if (totActEl) totActEl.textContent = tA;
            if (totEstEl) totEstEl.textContent = tE;
        }
        if (inputObj) { inputObj.addEventListener('input', refrescar); }
        refrescar();
    })();

    // ── Per franja d'edat amb drill-down per categoria ──────
    const edatEl = document.getElementById('chartEdat');
    const titol = document.getElementById('edatTitol');
    const btnBack = document.getElementById('edatBack');
    let edatChart = null;

    function renderFranges() {
        titol.textContent = "Per franja d'edat";
        btnBack.style.display = 'none';
        const ds = data.edat;
        if (edatChart) edatChart.destroy();
        edatChart = new Chart(edatEl, {
            type: 'bar',
            data: { labels: ds.map(d => d.label), datasets: [{ data: ds.map(d => d.value), backgroundColor: '#1e88c2' }] },
            options: {
                ...baseOpts,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                onClick: function (evt, els) {
                    if (!els.length) return;
                    renderCategoria(ds[els[0].index].label);
                }
            }
        });
    }

    function renderCategoria(franja) {
        const cats = data.edatCat[franja] || {};
        const labels = Object.keys(cats);
        const values = labels.map(k => cats[k]);
        titol.textContent = "Franja " + franja + " · per categoria";
        btnBack.style.display = 'inline-block';
        if (edatChart) edatChart.destroy();
        if (labels.length === 0) {
            edatChart = new Chart(edatEl, {
                type: 'bar',
                data: { labels: ['Sense dades'], datasets: [{ data: [0], backgroundColor: '#cbd5e1' }] },
                options: { ...baseOpts, plugins: { legend: { display: false } } }
            });
            return;
        }
        edatChart = new Chart(edatEl, {
            type: 'bar',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colors }] },
            options: { ...baseOpts, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }

    btnBack.addEventListener('click', renderFranges);
    renderFranges();
})();
</script>
