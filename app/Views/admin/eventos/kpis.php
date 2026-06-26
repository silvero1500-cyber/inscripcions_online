<?php
/** @var array $evento */
/** @var array $estados */
/** @var int $totalActivas */
/** @var float $ingresosConfirmados */
/** @var float $ingresosPotenciales */
/** @var array $porSexo */
/** @var list<array> $porTarifa */
/** @var array $rangos */
/** @var array $porPagament */
/** @var list<array> $edatCategoria */
/** @var list<array> $vendaTrams */
/** @var list<array> $evolucion */
/** @var int $darrers7 */
/** @var array|null $comparativa */
/** @var int|null $aforoMax */

$sexoLabels = ['H' => 'Home', 'M' => 'Dona', 'NB' => 'No binari'];

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

// Dades JSON per a JavaScript
$jsData = [
    'tarifa'  => array_map(fn($r) => ['label' => $r['nombre'], 'value' => (int) $r['n']], $porTarifa),
    'sexo'    => array_map(fn($k) => ['label' => $sexoLabels[$k] ?? $k, 'value' => (int) $porSexo[$k]], array_keys($porSexo)),
    'pagament'=> array_map(fn($k) => ['label' => $k, 'value' => (int) $porPagament[$k]], array_keys($porPagament)),
    'edat'    => array_map(fn($f) => ['label' => $f, 'value' => (int) $edatTotals[$f]], $franjaOrden),
    'edatCat' => $edatCatMap,        // [franja][categoria] = n
    'vendaTrams' => array_map(fn($r) => [
        'label' => $r['tarifa'] . ' · ' . number_format((float) $r['preu'], 2, ',', '.') . ' €',
        'value' => (int) $r['n'],
    ], $vendaTrams),
    'evolucion' => array_map(fn($r) => ['label' => $r['dia'], 'value' => (int) $r['n']], $evolucion),
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
<div class="kpi-grid kpi-grid-3">
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

<div class="kpi-panel">
    <h2>Evolució (últims 30 dies)</h2>
    <?php if (count($evolucion) === 0): ?>
        <p class="muted">Cap inscripció recent.</p>
    <?php else: ?>
        <div class="kpi-chart-wrap kpi-chart-wide"><canvas id="chartEvolucion"></canvas></div>
    <?php endif; ?>
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
    makeBarSimple('chartTrams', data.vendaTrams);
    makeLine('chartEvolucion', data.evolucion);

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
