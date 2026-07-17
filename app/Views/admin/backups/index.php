<?php
/** @var object $user */
/** @var array<string, list<array{name:string,size:int,mtime:int}>> $grups */

use App\Core\Csrf;
use App\Core\Session;

$fmtSize = function (int $b): string {
    if ($b >= 1048576) return number_format($b / 1048576, 1, ',', '.') . ' MB';
    if ($b >= 1024)    return number_format($b / 1024, 0, ',', '.') . ' KB';
    return $b . ' B';
};
$success = Session::pullFlash('success');
?>
<section class="page-head with-action">
    <div>
        <h1>💾 Còpies de seguretat</h1>
        <p class="muted">CSV complet dels inscrits de cada esdeveniment actiu. Es generen automàticament cada 12 hores i es conserven 14 dies.</p>
    </div>
    <form method="post" action="<?= e(base_url('/admin/backups/generar')) ?>">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-primary">Generar còpia ara</button>
    </form>
</section>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (empty($grups)): ?>
    <div class="panel"><p class="muted" style="margin:0;">Encara no hi ha cap còpia. Es generarà automàticament, o pots crear-ne una amb el botó.</p></div>
<?php else: ?>
    <?php foreach ($grups as $carpeta => $files): ?>
        <div class="panel" style="margin-bottom:1rem;">
            <h2 style="margin:0 0 .8rem;font-size:1.05rem;">📁 <?= e($carpeta) ?></h2>
            <table class="data-table">
                <thead><tr><th>Fitxer</th><th>Mida</th><th>Generat</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($files as $f): ?>
                        <tr>
                            <td><?= e($f['name']) ?></td>
                            <td><?= e($fmtSize((int) $f['size'])) ?></td>
                            <td><?= e(date('d/m/Y H:i', (int) $f['mtime'])) ?></td>
                            <td style="text-align:right;">
                                <a class="btn-small" href="<?= e(base_url('/admin/backups/descarregar?c=' . rawurlencode($carpeta) . '&f=' . rawurlencode($f['name']))) ?>">⬇️ Descarregar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
