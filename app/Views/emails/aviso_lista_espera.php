<?php
/** @var array $evento */
/** @var array $entry */
/** @var string $url */
/** @var string $baseUrl */
$nom = trim((string)($entry['nombre'] ?? ''));
?>
<div style="font-family:Arial,Helvetica,sans-serif;color:#1f2937;max-width:560px;margin:0 auto;">
    <h2 style="color:#1e88c2;margin:0 0 .6rem;"><?= e(t('waitlist.email_title')) ?></h2>

    <p style="font-size:15px;line-height:1.5;">
        <?php if ($nom !== ''): ?><?= e(t('waitlist.email_hello', ['name' => $nom])) ?><br><?php endif; ?>
        <?= e(t('waitlist.email_intro', ['event' => $evento['titulo']])) ?>
    </p>

    <p style="font-size:15px;line-height:1.5;"><?= e(t('waitlist.email_hurry')) ?></p>

    <p style="text-align:center;margin:1.6rem 0;">
        <a href="<?= e($url) ?>"
           style="background:#1e88c2;color:#fff;text-decoration:none;padding:12px 26px;border-radius:8px;font-weight:700;display:inline-block;">
            <?= e(t('waitlist.email_cta')) ?>
        </a>
    </p>

    <p style="font-size:13px;color:#6b7280;line-height:1.5;">
        <?= e(t('waitlist.email_ignore')) ?><br>
        <a href="<?= e($url) ?>" style="color:#1e88c2;"><?= e($url) ?></a>
    </p>
</div>
