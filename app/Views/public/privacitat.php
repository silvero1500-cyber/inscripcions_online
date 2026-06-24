<?php
/** @var string $text */
$lines = preg_split('/\r?\n/', trim($text));
$title = '';
$blocks = [];      // ['type'=>'h2'|'p'|'ul'|'note', 'content'=>...]
$ul = [];
$flushUl = function () use (&$ul, &$blocks) {
    if ($ul) { $blocks[] = ['type' => 'ul', 'items' => $ul]; $ul = []; }
};
foreach ($lines as $i => $ln) {
    $ln = rtrim($ln);
    if ($i === 0 && $ln !== '') { $title = $ln; continue; }   // primera línia = títol
    if (trim($ln) === '') { $flushUl(); continue; }
    if (preg_match('/^\d+\.\s+\S/', $ln)) { $flushUl(); $blocks[] = ['type' => 'h2', 'content' => $ln]; continue; }
    if (str_starts_with(ltrim($ln), '- ')) { $ul[] = ltrim(ltrim($ln), '- '); continue; }
    if (str_starts_with(trim($ln), '[') && str_ends_with(trim($ln), ']')) { $flushUl(); $blocks[] = ['type' => 'note', 'content' => trim($ln, '[] ')]; continue; }
    $flushUl();
    $blocks[] = ['type' => 'p', 'content' => $ln];
}
$flushUl();
?>
<section class="legal-page" style="max-width:820px;margin:2rem auto;padding:0 1.2rem 3rem;line-height:1.65;">
    <h1 style="margin:.5rem 0 1.4rem;font-size:1.9rem;"><?= e($title !== '' ? $title : 'Política de privacitat') ?></h1>

    <?php foreach ($blocks as $b): ?>
        <?php if ($b['type'] === 'h2'): ?>
            <h2 style="margin:1.8rem 0 .5rem;font-size:1.2rem;color:#1e293b;"><?= e($b['content']) ?></h2>
        <?php elseif ($b['type'] === 'ul'): ?>
            <ul style="margin:.4rem 0 .8rem 1.2rem;padding:0;">
                <?php foreach ($b['items'] as $it): ?>
                    <li style="margin:.25rem 0;"><?= e($it) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php elseif ($b['type'] === 'note'): ?>
            <p style="margin:1.6rem 0 0;padding:.8rem 1rem;background:#f1f5f9;border-left:4px solid #94a3b8;border-radius:6px;color:#475569;font-size:.92rem;"><?= e($b['content']) ?></p>
        <?php else: ?>
            <p style="margin:.5rem 0;color:#334155;"><?= e($b['content']) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
