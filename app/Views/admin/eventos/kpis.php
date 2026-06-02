<?php
/** @var array $evento */
/** @var array $estados */
/** @var int $totalActivas */
/** @var float $ingresosConfirmados */
/** @var float $ingresosPotenciales */
/** @var array $porSexo */
/** @var array $porTalla */
/** @var list<array> $porTarifa */
/** @var array $rangos */
/** @var list<array> $topClubs */
/** @var list<array> $topPoblaciones */
/** @var list<array> $evolucion */
/** @var int|null $aforoMax */

$sexoLabels = ['H' => 'Home', 'M' => 'Dona', 'NB' => 'No binari'];
$tallaOrden = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'Sense talla'];

// Reordenar tallas siguiendo el orden lógico
$porTallaOrd = [];
foreach ($tallaOrden as $t) {
    if (isset($porTalla[$t])) $porTallaOrd[$t] = (int)$porTalla[$t];
}
// Añadir cualquier talla extra que no esté en el orden definido
foreach ($porTalla as $t => $n) {
    if (!isset($porTallaOrd[$t])) $porTallaOrd[$t] = (int)$n;
}

$pctOcupacion = $aforoMax !== null && $aforoMax > 0
    ? min(100, round($totalActivas * 100 / $aforoMax))
    : null;

// Datos JSON para JavaScript
$jsData = [
    'sexo'   => array_map(fn($k) => ['label' => $sexoLabels[$k] ?? $k, 'value' => (int)$porSexo[$k]], array_keys($porSexo)),
    'talla'  => array_map(fn($t) => ['label' => $t, 'value' => (int)$porTallaOrd[$t]], array_keys($porTallaOrd)),
    'tarifa' => array_map(fn($r) => ['label' => $r['nombre'], 'value' => (int)$r['n']], $porTarifa),
    'rangos' => [
        ['label' => '<18',  'value' => (int)($rangos['r1'] ?? 0)],
        ['label' => '18-29','value' => (int)($rangos['r2'] ?? 0)],
        ['label' => '30-39','value' => (int)($rangos['r3'] ?? 0)],
        ['label' => '40-49','value' => (int)($rangos['r4'] ?? 0)],
        ['label' => '50-59','value' => (int)($rangos['r5'] ?? 0)],
        ['label' => '60+',  'value' => (int)($rangos['r6'] ?? 0)],
    ],
    'evolucion' => array_map(fn($r) => ['label' => $r['dia'], 'value' => (int)$r['n']], $evolucion),
];
?>
<section class="page-head with-action">
    <div>
        <h1>KPIs · <?= e($evento['titulo']) ?></h1>
        <p class="muted"><?= e(format_date_ca((string)$evento['fecha_evento'], true)) ?></p>
    </div>
    <div style="display:flex;gap:.6rem;">
        <a class="btn" href="<?= e(base_url('/admin/eventos')) ?>">← Tornar</a>
        <a class="btn" href="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/editar')) ?>">Editar</a>
    </div>
</section>

<!-- ── Resumen general ────────────────────────────────────── -->
<div class="kpi-grid kpi-grid-4">
    <div class="kpi-card">
        <div class="kpi-label">Total inscrits</div>
        <div class="kpi-value"><?= $totalActivas ?></div>
        <div class="kpi-sub">
            <?= (int)($estados['confirmado'] ?? 0) ?> confirmats ·
            <?= (int)($estados['pendiente'] ?? 0) ?> pendents
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Ingressos confirmats</div>
        <div class="kpi-value"><?= e(format_price($ingresosConfirmados)) ?></div>
        <div class="kpi-sub">Potencial: <?= e(format_price($ingresosPotenciales)) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Aforament</div>
        <?php if ($aforoMax !== null): ?>
            <div class="kpi-value"><?= $pctOcupacion ?>%</div>
            <div class="kpi-sub"><?= $totalActivas ?> / <?= $aforoMax ?> places</div>
            <div class="kpi-progress"><div class="kpi-progress-bar" style="width:<?= $pctOcupacion ?>%"></div></div>
        <?php else: ?>
            <div class="kpi-value">∞</div>
            <div class="kpi-sub">Sense límit</div>
        <?php endif; ?>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Cancel·lats</div>
        <div class="kpi-value"><?= (int)($estados['cancelado'] ?? 0) ?></div>
        <div class="kpi-sub"><?= (int)($estados['reembolsado'] ?? 0) ?> reembossats</div>
    </div>
</div>

<!-- ── Per sexe + per edat ────────────────────────────────── -->
<div class="kpi-grid kpi-grid-2">
    <div class="kpi-panel">
        <h2>Per sexe</h2>
        <div class="kpi-chart-wrap"><canvas id="chartSexo"></canvas></div>
    </div>
    <div class="kpi-panel">
        <h2>Per franja d'edat</h2>
        <div class="kpi-chart-wrap"><canvas id="chartEdat"></canvas></div>
    </div>
</div>

<!-- ── Per talla + estimador comanda ──────────────────────── -->
<div class="kpi-panel">
    <h2>Per talla de samarreta</h2>
    <div class="kpi-grid kpi-grid-2">
        <div>
            <div class="kpi-chart-wrap"><canvas id="chartTalla"></canvas></div>
        </div>
        <div>
            <h3 style="margin:0 0 .8rem;font-size:1rem;">Estimació de comanda</h3>
            <p class="muted small" style="margin:0 0 .8rem;">
                Calcula quantes samarretes necessitaràs si arribes a un total objectiu, basant-se en la
                distribució actual.
            </p>
            <div class="form-row">
                <label for="objectiu">Inscrits objectiu</label>
                <input type="number" id="objectiu" min="<?= max(1, $totalActivas) ?>" step="1" value="<?= max(100, $totalActivas) ?>">
            </div>
            <table class="data-table kpi-table-estim">
                <thead><tr><th>Talla</th><th>Actual</th><th>Estimat</th></tr></thead>
                <tbody id="tblEstim"></tbody>
                <tfoot><tr><td><strong>Total amb talla</strong></td><td id="totalActual">0</td><td id="totalEstim">0</td></tr></tfoot>
            </table>
            <p class="muted small" style="margin:.8rem 0 0;">
                Els inscrits "sense talla" no compten per a la proporció.
            </p>
        </div>
    </div>
</div>

<!-- ── Per tarifa ─────────────────────────────────────────── -->
<div class="kpi-panel">
    <h2>Per tarifa</h2>
    <div class="kpi-chart-wrap kpi-chart-wide"><canvas id="chartTarifa"></canvas></div>
    <table class="data-table" style="margin-top:1.2rem;">
        <thead>
            <tr><th>Tarifa</th><th>Preu</th><th>Inscrits</th><th>Ingrés (potencial)</th></tr>
        </thead>
        <tbody>
            <?php foreach ($porTarifa as $r): ?>
                <tr>
                    <td><?= e($r['nombre']) ?></td>
                    <td><?= e(format_price((float)$r['precio'])) ?></td>
                    <td><?= (int)$r['n'] ?></td>
                    <td><?= e(format_price((float)$r['precio'] * (int)$r['n'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ── Top clubs + top poblacions ─────────────────────────── -->
<div class="kpi-grid kpi-grid-2">
    <div class="kpi-panel">
        <h2>Top clubs</h2>
        <?php if (count($topClubs) === 0): ?>
            <p class="muted">Cap inscrit ha indicat club.</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Club</th><th>Inscrits</th></tr></thead>
                <tbody>
                    <?php foreach ($topClubs as $r): ?>
                        <tr><td><?= e($r['club']) ?></td><td><?= (int)$r['n'] ?></td></tr>
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
                <thead><tr><th>Població</th><th>Inscrits</th></tr></thead>
                <tbody>
                    <?php foreach ($topPoblaciones as $r): ?>
                        <tr><td><?= e($r['poblacion']) ?></td><td><?= (int)$r['n'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- ── Evolució últims 30 dies ────────────────────────────── -->
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
    const baseOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    };

    function makePie(id, dataset) {
        if (dataset.length === 0) return;
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'doughnut',
            data: {
                labels: dataset.map(d => d.label),
                datasets: [{ data: dataset.map(d => d.value), backgroundColor: colors }]
            },
            options: baseOpts
        });
    }

    function makeBar(id, dataset, color) {
        if (dataset.length === 0) return;
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'bar',
            data: {
                labels: dataset.map(d => d.label),
                datasets: [{ data: dataset.map(d => d.value), backgroundColor: color || '#1e88c2' }]
            },
            options: { ...baseOpts, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }

    function makeLine(id, dataset, color) {
        if (dataset.length === 0) return;
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'line',
            data: {
                labels: dataset.map(d => d.label),
                datasets: [{
                    data: dataset.map(d => d.value),
                    borderColor: color || '#1e88c2',
                    backgroundColor: 'rgba(30, 136, 194, .1)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 3
                }]
            },
            options: { ...baseOpts, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }

    makePie('chartSexo', data.sexo);
    makeBar('chartEdat', data.rangos);
    makeBar('chartTalla', data.talla);
    makePie('chartTarifa', data.tarifa);
    makeLine('chartEvolucion', data.evolucion);

    // ── Estimador de samarretes ─────────────────────────────
    const tallas = data.talla.filter(t => t.label !== 'Sense talla');
    const totalConTalla = tallas.reduce((s, t) => s + t.value, 0);
    const inputObj = document.getElementById('objectiu');
    const tbody = document.getElementById('tblEstim');
    const totActEl = document.getElementById('totalActual');
    const totEstEl = document.getElementById('totalEstim');

    function refrescar() {
        const objetivo = Math.max(1, parseInt(inputObj.value || '0', 10));
        let totAct = 0, totEst = 0;
        tbody.innerHTML = '';
        if (totalConTalla === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="muted" style="text-align:center;">Cap inscrit amb talla encara.</td></tr>';
            totActEl.textContent = '0';
            totEstEl.textContent = '0';
            return;
        }
        tallas.forEach(t => {
            const prop = t.value / totalConTalla;
            const estim = Math.round(prop * objetivo);
            totAct += t.value;
            totEst += estim;
            tbody.innerHTML += `<tr><td><strong>${t.label}</strong></td><td>${t.value}</td><td><strong>${estim}</strong></td></tr>`;
        });
        totActEl.textContent = totAct;
        totEstEl.textContent = totEst;
    }

    if (inputObj) {
        inputObj.addEventListener('input', refrescar);
        refrescar();
    }
})();
</script>
