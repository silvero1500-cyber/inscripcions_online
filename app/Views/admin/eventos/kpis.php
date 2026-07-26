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
/** @var array|null $evolucioEdicions */
/** @var list<array>|null $ingressosEdicions */
/** @var array|null $fidelitzacio */
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

// ── Comparativa d'evolució: últims 90 dies ABANS de la cursa (compte enrere), edició actual vs anterior ──
// 'dia' = dies abans de la cursa (90 = fa 90 dies de la cursa, 0 = dia de la cursa).
// Eix ordenat de 90 -> 0 perquè el temps avanci d'esquerra a dreta cap al dia de la cursa.
$edicionsDies = [];
$edicionsSeries = [];
if ($evolucioEdicions !== null) {
    $edicionsDies = range(90, 0, -1);

    $toAcumulat = function (array $rows, array $dies): array {
        $perDia = [];
        foreach ($rows as $r) { $perDia[$r['dia']] = $r['n']; }
        // Acumulat cronològic: comença pel dia més llunyà (90) cap al dia de la cursa (0)
        $acum = 0; $out = [];
        foreach ($dies as $d) { $acum += ($perDia[$d] ?? 0); $out[$d] = $acum; }
        return $out;
    };

    // Una línia per cada edició existent de la carrera (ordenades per any)
    foreach ($evolucioEdicions['edicions'] as $ed) {
        $edicionsSeries[] = [
            'label' => (string) $ed['any'],
            'data'  => array_values($toAcumulat($ed['punts'], $edicionsDies)),
        ];
    }
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
// Els inscrits sense resposta de xip són els de curses infantils (els menors no
// porten xip), per això aquesta franja s'etiqueta "Curses infantils".
$chipLabels = ['si' => 'Xip propi', 'no' => 'Xip de cessió', 'sense_resposta' => 'Curses infantils'];
$chipTotal = array_sum($porChip);
$pctChip = fn(int $n): string => $chipTotal > 0 ? number_format($n * 100 / $chipTotal, 0) : '0';
$chipData = [];
foreach (['si', 'no', 'sense_resposta'] as $k) {
    if (empty($porChip[$k])) continue;
    $n = (int) $porChip[$k];
    $chipData[] = ['label' => $chipLabels[$k] . ' — ' . $n . ' (' . $pctChip($n) . '%)', 'value' => $n];
}

// Afegeix " — N (X%)" a l'etiqueta (el % es calcula sobre el total de la sèrie),
// igual que al gràfic de xip, perquè la llegenda mostri xifra i percentatge.
$ambPct = function (array $items): array {
    $tot = array_sum(array_map(fn($x) => (int) $x['value'], $items));
    return array_map(function ($x) use ($tot) {
        $pct = $tot > 0 ? number_format((int) $x['value'] * 100 / $tot, 0) : '0';
        return ['label' => $x['label'] . ' — ' . (int) $x['value'] . ' (' . $pct . '%)', 'value' => (int) $x['value']];
    }, $items);
};

// Dades JSON per a JavaScript
$jsData = [
    'tarifa'  => $ambPct(array_map(fn($r) => ['label' => $r['nombre'], 'value' => (int) $r['n']], $porTarifa)),
    'sexo'    => $ambPct(array_map(fn($k) => ['label' => $sexoLabels[$k] ?? $k, 'value' => (int) $porSexo[$k]], array_keys($porSexo))),
    'pagament'=> $ambPct(array_map(fn($k) => ['label' => $k, 'value' => (int) $porPagament[$k]], array_keys($porPagament))),
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
    'edicionsDies'   => $edicionsDies,
    'edicionsSeries' => $edicionsSeries,   // [{label, data[]}] acumulat
    'edicionsAvui'   => $evolucioEdicions['diesFalten'] ?? null,  // dies que falten avui (marca vermella)
    'edicionsActual' => (string) ($evolucioEdicions['anyActual'] ?? ''),  // any de l'edició que s'està veient
    'ingressosEd'    => array_map(fn($r) => ['label' => (string) $r['any'], 'value' => round((float) $r['total'], 2)], $ingressosEdicions ?? []),
    // Fidelització: % repetidors per edició (excloent la primera, que no té anterior)
    'fidelEd'        => (function () use ($fidelitzacio) {
        if ($fidelitzacio === null || empty($fidelitzacio['edicions'])) return [];
        $eds = $fidelitzacio['edicions'];
        $minAny = min(array_map(fn($e) => (int) $e['any'], $eds));
        $out = [];
        foreach ($eds as $e) {
            if ((int) $e['any'] === $minAny) continue; // la 1a no té edició anterior
            $out[] = ['label' => (string) $e['any'], 'value' => (float) $e['pct']];
        }
        return $out;
    })(),
];

// Amaga automàticament els KPIs SENSE dades reals (irrellevants per a l'event),
// independentment de la config: es basa en si hi ha inscrits amb aquell valor.
$mostraTalla = false;
foreach ($tallaTotals as $tl => $n) {
    if ($tl !== 'Sense talla' && (int) $n > 0) { $mostraTalla = true; break; }
}
$mostraChip = ((int) ($porChip['si'] ?? 0) + (int) ($porChip['no'] ?? 0)) > 0;

// Comparativa d'ingressos entre edicions: només si hi ha 2+ edicions i algun ingrés
$ingEd = $ingressosEdicions ?? [];
$mostraIngEd = count($ingEd) >= 2 && array_sum(array_map(fn($r) => (float) $r['total'], $ingEd)) > 0;

// Fidelització: només si hi ha alguna edició amb anterior (2+ edicions)
$fidelEds = ($fidelitzacio !== null) ? ($fidelitzacio['edicions'] ?? []) : [];
$fidelActual = ($fidelitzacio !== null) ? ($fidelitzacio['actual'] ?? null) : null;
$mostraFidel = count($fidelEds) >= 2;
$kpisHidden = $kpisHidden ?? [];
?>
<section class="page-head with-action">
    <div>
        <h1>KPIs · <?= e($evento['titulo']) ?></h1>
        <p class="muted"><?= e(format_date_ca((string) $evento['fecha_evento'], true)) ?></p>
    </div>
    <div style="display:flex;gap:.6rem;">
        <button type="button" class="btn" id="kpiCustomize">⚙️ Personalitzar</button>
        <a class="btn" href="<?= e(base_url('/admin/eventos')) ?>">← Tornar</a>
        <a class="btn" href="<?= e(base_url('/admin/eventos/' . (int) $evento['id'] . '/editar')) ?>">Editar</a>
    </div>
</section>

<div id="kpiCustomPanel" hidden class="panel" style="max-width:460px;margin:0 0 1.2rem;padding:1rem 1.2rem;">
    <h3 style="margin:0 0 .3rem;font-size:1rem;">⚙️ Personalitzar KPIs</h3>
    <p class="muted small" style="margin:0 0 .8rem;">Desmarca els gràfics que no vulguis veure. Es desa al teu compte.</p>
    <div id="kpiCustomList" style="display:flex;flex-direction:column;gap:.4rem;"></div>
</div>
<script>
    window.__kpiCsrf = <?= json_encode(\App\Core\Csrf::token()) ?>;
    window.__kpiSaveUrl = <?= json_encode(base_url('/admin/kpis/prefs')) ?>;
    window.__kpiHidden = <?= json_encode(array_values($kpisHidden), JSON_UNESCAPED_UNICODE) ?>;
</script>

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
    <div class="kpi-panel" data-kpi="categoria" data-kpi-title="Per categoria">
        <h2>Per categoria</h2>
        <div class="kpi-chart-wrap"><canvas id="chartCategoria"></canvas></div>
    </div>
    <div class="kpi-panel" data-kpi="sexe" data-kpi-title="Per sexe">
        <h2>Per sexe</h2>
        <div class="kpi-chart-wrap"><canvas id="chartSexo"></canvas></div>
    </div>
    <div class="kpi-panel" data-kpi="pagament" data-kpi-title="Per mètode de pagament">
        <h2>Per mètode de pagament</h2>
        <div class="kpi-chart-wrap"><canvas id="chartPagament"></canvas></div>
    </div>
    <?php if ($mostraChip): ?>
    <div class="kpi-panel" data-kpi="xip" data-kpi-title="Xip groc">
        <h2>Xip groc: propi o de cessió</h2>
        <div class="kpi-chart-wrap"><canvas id="chartChip"></canvas></div>
    </div>
    <?php endif; ?>
</div>

<div class="kpi-panel" data-kpi="edat" data-kpi-title="Per franja d'edat">
    <div class="kpi-panel-head">
        <h2 id="edatTitol">Per franja d'edat</h2>
        <button type="button" id="edatBack" class="btn btn-small" style="display:none;">← Totes les franges</button>
    </div>
    <p class="muted small" style="margin:.2rem 0 1rem;">Clica una franja per veure'n el desglós per categoria.</p>
    <div class="kpi-chart-wrap kpi-chart-wide"><canvas id="chartEdat"></canvas></div>
</div>

<?php if (!empty($vendaTrams)): ?>
<div class="kpi-panel" data-kpi="trams" data-kpi-title="Venda per trams">
    <h2>Venda per trams</h2>
    <p class="muted small" style="margin:.2rem 0 1rem;">Inscrits segons el preu (tram) que se'ls va aplicar en inscriure's.</p>
    <div class="kpi-chart-wrap kpi-chart-wide"><canvas id="chartTrams"></canvas></div>
</div>
<?php endif; ?>

<?php /* ══ NIVELL 3 · evolució 90 dies + talla ════════════════ */ ?>
<div class="kpi-panel" data-kpi="evolucio" data-kpi-title="Evolució inscrits (90 dies)">
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

<?php if (count($edicionsSeries) > 1): ?>
<div class="kpi-panel" data-kpi="comparativa" data-kpi-title="Comparativa entre edicions">
    <div class="kpi-panel-head">
        <h2>Comparativa entre edicions <span class="muted" style="font-weight:400;font-size:.9rem;">(acumulat, dies abans de la cursa)</span></h2>
        <select id="edicionsRang">
            <option value="90">Últims 90 dies</option>
            <option value="60" selected>Últims 60 dies</option>
            <option value="30">Últims 30 dies</option>
        </select>
    </div>
    <div class="kpi-chart-wrap kpi-chart-wide"><canvas id="chartEdicions"></canvas></div>
</div>
<?php endif; ?>

<?php if ($mostraIngEd): ?>
<div class="kpi-panel" data-kpi="ingressos-ed" data-kpi-title="Ingressos per edició">
    <div class="kpi-panel-head">
        <h2>Ingressos per edició <span class="muted" style="font-weight:400;font-size:.9rem;">(confirmats, comparativa entre anys)</span></h2>
    </div>
    <div class="kpi-chart-wrap kpi-chart-wide"><canvas id="chartIngEd"></canvas></div>
</div>
<?php endif; ?>

<?php if ($mostraFidel): ?>
<div class="kpi-panel" data-kpi="fidelitzacio" data-kpi-title="Fidelització">
    <div class="kpi-panel-head">
        <h2>Fidelització <span class="muted" style="font-weight:400;font-size:.9rem;">(% que ja havien participat en una edició anterior)</span></h2>
        <?php if ($fidelActual !== null): ?>
            <span class="kpi-growth" style="background:#e0f2fe;color:#075985;">
                🔁 <strong><?= e(number_format($fidelActual['pct'], 1, ',', '.')) ?>%</strong> repetidors
                · <?= (int) $fidelActual['novells'] ?> nous
            </span>
        <?php endif; ?>
    </div>
    <div class="kpi-chart-wrap kpi-chart-wide"><canvas id="chartFidel"></canvas></div>
</div>
<?php endif; ?>

<?php if ($mostraTalla): ?>
<div class="kpi-panel" data-kpi="talla" data-kpi-title="Talla samarreta">
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
<?php endif; ?>

<?php /* ══ NIVELL 4 · tops amb % sobre total ═══════════════════ */ ?>
<div class="kpi-grid kpi-grid-2">
    <div class="kpi-panel" data-kpi="top-clubs" data-kpi-title="Top clubs">
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
    <div class="kpi-panel" data-kpi="top-poblacions" data-kpi-title="Top poblacions">
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

    // ── Comparativa entre edicions: acumulat, dies abans de la cursa (compte enrere) ──
    (function () {
        const el = document.getElementById('chartEdicions');
        const sel = document.getElementById('edicionsRang');
        if (!el || !data.edicionsDies || data.edicionsDies.length === 0) return;

        // Línia vertical vermella que marca "avui" (dies que falten per a la cursa)
        const avuiLine = {
            id: 'avuiLine',
            afterDraw(chart) {
                const dia = chart.config._avuiDia;
                if (dia === null || dia === undefined) return;
                const idx = chart.config._avuiIdx;
                if (idx < 0) return;
                const x = chart.scales.x.getPixelForValue(idx);
                const { top, bottom } = chart.chartArea;
                const ctx = chart.ctx;
                ctx.save();
                ctx.strokeStyle = '#dc2626';
                ctx.lineWidth = 2;
                ctx.setLineDash([6, 4]);
                ctx.beginPath();
                ctx.moveTo(x, top);
                ctx.lineTo(x, bottom);
                ctx.stroke();
                ctx.setLineDash([]);
                ctx.fillStyle = '#dc2626';
                ctx.font = 'bold 11px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('Avui', x, top - 4);

                // Diferència d'acumulat al punt d'avui: edició actual vs la de l'any anterior
                const ds = chart.data.datasets;
                const actual = String(data.edicionsActual || '');
                const cur = ds.find(function (s) { return String(s.label) === actual; });
                const prev = ds.find(function (s) { return String(s.label) === String(parseInt(actual, 10) - 1); });
                if (cur && prev && cur.data[idx] !== undefined && prev.data[idx] !== undefined) {
                    const diff = cur.data[idx] - prev.data[idx];
                    const txt = (diff >= 0 ? '+' : '−') + Math.abs(diff) + ' vs ' + prev.label;
                    ctx.font = 'bold 12px sans-serif';
                    ctx.fillStyle = diff >= 0 ? '#15803d' : '#dc2626';
                    const nearEnd = x > chart.chartArea.right - 90;
                    ctx.textAlign = nearEnd ? 'right' : 'left';
                    ctx.fillText(txt, nearEnd ? x - 6 : x + 6, top + 14);
                }
                ctx.restore();
            }
        };

        // Estimació per a l'edició actual: des d'AVUI fins al dia de la cursa,
        // projecta seguint la FORMA mitjana de les altres edicions (normalitzada
        // per la mida actual). projectat[d] = actual_avui × mitjana(altres[d]/altres[avui]).
        function buildEstimacio() {
            const D = data.edicionsAvui;
            if (D === null || D === undefined) return null;
            const actual = String(data.edicionsActual || '');
            const cur = (data.edicionsSeries || []).find(s => String(s.label) === actual);
            if (!cur) return null;
            const jD = data.edicionsDies.indexOf(D);
            if (jD < 0) return null;
            const curAtD = cur.data[jD];
            if (curAtD === null || curAtD === undefined) return null;
            const others = (data.edicionsSeries || []).filter(s => String(s.label) !== actual);
            if (others.length === 0) return null;
            const estFull = data.edicionsDies.map((d, j) => {
                if (d > D) return null;                 // abans d'avui: sense estimació
                let sum = 0, cnt = 0;
                others.forEach(s => { const base = s.data[jD]; if (base > 0) { sum += s.data[j] / base; cnt++; } });
                if (!cnt) return null;
                return Math.round(curAtD * (sum / cnt));
            });
            return estFull;
        }

        let chart = null;
        function render(rang) {
            // edicionsDies va de 90 (fa temps) a 0 (dia de la cursa); ens quedem els últims N dies abans de la cursa
            const idxStart = data.edicionsDies.findIndex(d => d <= rang);
            const dies = data.edicionsDies.slice(idxStart);
            const labels = dies.map(d => d === 0 ? 'Cursa' : `-${d}`);
            const D = data.edicionsAvui;
            let curColor = '#dc2626';
            const datasets = (data.edicionsSeries || []).map((s, idx) => {
                const c = colors[idx % colors.length];
                const isCur = String(s.label) === String(data.edicionsActual || '');
                if (isCur) curColor = c;
                let arr = s.data.slice(idxStart);
                // La línia sòlida de l'edició actual acaba a "Avui" (els dies futurs es projecten amb la discontínua)
                if (isCur && D !== null && D !== undefined) {
                    arr = arr.map((v, k) => (dies[k] < D ? null : v));
                }
                return { label: s.label, data: arr, borderColor: c, backgroundColor: c + '22', tension: 0.3, fill: false, pointRadius: 0, borderWidth: 2, spanGaps: false };
            });
            // Línia d'estimació de l'edició actual (discontínua, mateix color)
            const estFull = buildEstimacio();
            if (estFull) {
                datasets.push({
                    label: (data.edicionsActual || '') + ' (estimació)',
                    data: estFull.slice(idxStart),
                    borderColor: curColor, borderDash: [5, 4], borderWidth: 2,
                    tension: 0.3, fill: false, pointRadius: 0, spanGaps: false
                });
            }
            if (chart) { chart.destroy(); }
            chart = new Chart(el, {
                type: 'line',
                data: { labels: labels, datasets: datasets },
                options: { ...baseOpts, layout: { padding: { top: 16 } }, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
                plugins: [avuiLine]
            });
            chart.config._avuiDia = data.edicionsAvui;
            chart.config._avuiIdx = (data.edicionsAvui !== null && data.edicionsAvui !== undefined)
                ? dies.indexOf(data.edicionsAvui) : -1;
            chart.update();
        }

        render(sel ? parseInt(sel.value, 10) : 60);
        if (sel) sel.addEventListener('change', () => render(parseInt(sel.value, 10)));
    })();

    // ── Ingressos per edició (barres, € per any) ────────────
    (function () {
        const el = document.getElementById('chartIngEd');
        if (!el || !data.ingressosEd || data.ingressosEd.length === 0) return;
        const eur = v => new Intl.NumberFormat('ca-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v);
        new Chart(el, {
            type: 'bar',
            data: {
                labels: data.ingressosEd.map(d => d.label),
                datasets: [{ data: data.ingressosEd.map(d => d.value), backgroundColor: colors, borderRadius: 4 }]
            },
            options: {
                ...baseOpts,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => eur(ctx.parsed.y) } }
                },
                scales: { y: { beginAtZero: true, ticks: { callback: v => eur(v) } } }
            }
        });
    })();

    // ── Fidelització: % repetidors per edició (barres) ──────
    (function () {
        const el = document.getElementById('chartFidel');
        if (!el || !data.fidelEd || data.fidelEd.length === 0) return;
        new Chart(el, {
            type: 'bar',
            data: {
                labels: data.fidelEd.map(d => d.label),
                datasets: [{ data: data.fidelEd.map(d => d.value), backgroundColor: '#0ea5e9', borderRadius: 4 }]
            },
            options: {
                ...baseOpts,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ctx.parsed.y + '% repetidors' } }
                },
                scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }
            }
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

// ── Personalitzador de KPIs (amagar/mostrar, desat per usuari) ──
(function () {
    var btn = document.getElementById('kpiCustomize');
    var panel = document.getElementById('kpiCustomPanel');
    var list = document.getElementById('kpiCustomList');
    if (!btn || !panel || !list) return;

    function panels() { return Array.prototype.slice.call(document.querySelectorAll('[data-kpi]')); }

    // Aplica l'estat desat (amaga els KPIs que l'usuari havia ocultat)
    (function applyHidden() {
        var h = window.__kpiHidden || [];
        panels().forEach(function (p) {
            if (h.indexOf(p.getAttribute('data-kpi')) !== -1) p.style.display = 'none';
        });
    })();

    function currentHidden() {
        return panels().filter(function (p) { return p.style.display === 'none'; })
                       .map(function (p) { return p.getAttribute('data-kpi'); });
    }

    function save() {
        var body = new FormData();
        body.append('_csrf', window.__kpiCsrf || '');
        currentHidden().forEach(function (id) { body.append('hidden[]', id); });
        fetch(window.__kpiSaveUrl, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .catch(function () {});
    }

    function buildList() {
        list.innerHTML = '';
        panels().forEach(function (p) {
            var id = p.getAttribute('data-kpi');
            var title = p.getAttribute('data-kpi-title') || id;
            var visible = p.style.display !== 'none';
            var lbl = document.createElement('label');
            lbl.className = 'inline-check';
            lbl.style.cssText = 'display:flex;align-items:center;gap:.5rem;';
            var cb = document.createElement('input');
            cb.type = 'checkbox'; cb.checked = visible;
            cb.addEventListener('change', function () {
                p.style.display = cb.checked ? '' : 'none';
                save();
            });
            lbl.appendChild(cb);
            lbl.appendChild(document.createTextNode(' ' + title));
            list.appendChild(lbl);
        });
    }

    btn.addEventListener('click', function () {
        if (panel.hidden) { buildList(); panel.hidden = false; }
        else { panel.hidden = true; }
    });
})();
</script>
