<?php
/**
 * Barra compacta de la cursa (carrera). Es mostra a Inscrits / Recollida / KPIs
 * quan l'event seleccionat pertany a una carrera. Dóna context + accessos ràpids
 * per saltar entre apartats de la mateixa edició.
 *
 * Variables esperades en scope (via require des de la vista):
 *   $barEvento : array  L'event seleccionat (amb carrera_id, anio_edicion, slug, id)
 *   $barActual : string 'inscrits' | 'recollida' | 'kpis'  (apartat actiu)
 *   $user      : object Usuari actual (per al botó Editar, superadmin)
 */
$barEvento = $barEvento ?? null;
$barActual = $barActual ?? '';

if (is_array($barEvento) && !empty($barEvento['carrera_id'])):
    $carrera = \App\Models\Carrera::findById((int) $barEvento['carrera_id']);
    if ($carrera):
        $bEv   = (int) $barEvento['id'];
        $bAny  = !empty($barEvento['anio_edicion']) ? (int) $barEvento['anio_edicion'] : null;
        $bSlug = (string) ($barEvento['slug'] ?? '');
        $isSuperBar = (($user->rol ?? '') === 'superadmin');
?>
<nav class="cursa-bar" aria-label="Cursa">
    <div class="cursa-bar-title">
        🏁 <strong><?= e($carrera['nombre']) ?></strong>
        <?php if ($bAny !== null): ?><span class="cursa-bar-ed">Edició <?= $bAny ?></span><?php endif; ?>
    </div>
    <div class="cursa-bar-links">
        <a class="cursa-bar-link<?= $barActual === 'recollida' ? ' active' : '' ?>"
           href="<?= e(base_url('/admin/recollida?evento_id=' . $bEv)) ?>">🎽 Recollida</a>
        <a class="cursa-bar-link<?= $barActual === 'inscrits' ? ' active' : '' ?>"
           href="<?= e(base_url('/admin/inscritos?evento_id=' . $bEv)) ?>">📋 Inscrits</a>
        <a class="cursa-bar-link<?= $barActual === 'kpis' ? ' active' : '' ?>"
           href="<?= e(base_url('/admin/eventos/' . $bEv . '/kpis')) ?>">📊 KPIs</a>
        <?php if ($bSlug !== ''): ?>
            <a class="cursa-bar-link" href="<?= e(base_url('/eventos/' . $bSlug)) ?>" target="_blank" rel="noopener">👁️ Veure pública</a>
        <?php endif; ?>
        <?php if ($isSuperBar): ?>
            <a class="cursa-bar-link<?= $barActual === 'editar' ? ' active' : '' ?>"
               href="<?= e(base_url('/admin/eventos/' . $bEv . '/editar')) ?>">✏️ Editar</a>
        <?php endif; ?>
    </div>
</nav>
<?php
    endif;
endif;
