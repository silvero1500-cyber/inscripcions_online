<?php
/** @var list<array> $eventos */
use App\Services\ImageUploader;
?>
<section class="hero">
    <div class="container">
        <h1><?= e(t('home.title')) ?></h1>
        <p class="hero-sub"><?= e(t('home.subtitle')) ?></p>
    </div>
</section>

<section class="container">
    <?php if (count($eventos) === 0): ?>
        <div class="empty-public">
            <h2><?= e(t('home.empty.title')) ?></h2>
            <p><?= e(t('home.empty.desc')) ?></p>
        </div>
    <?php else: ?>
        <div class="event-grid">
            <?php foreach ($eventos as $ev): ?>
                <a href="<?= e(base_url('/eventos/' . $ev['slug'])) ?>" class="event-card">
                    <?php $img = ImageUploader::publicUrl($ev['imagen_portada']); ?>
                    <div class="event-card-img<?= $img ? '' : ' event-card-img-placeholder' ?>"<?= $img ? ' style="background-image:url(\'' . e($img) . '\')"' : '' ?>>
                        <span class="event-card-datebadge">📅 <?= e(format_date_ca((string)$ev['fecha_evento'])) ?></span>
                    </div>
                    <div class="event-card-body">
                        <h3 class="event-card-title"><?= e($ev['titulo']) ?></h3>
                        <div class="event-card-footer">
                            <?php if ($ev['precio_min'] !== null): ?>
                                <span class="price"><?= e(t('home.card.from')) ?> <strong><?= number_format((float)$ev['precio_min'], 2, ',', '.') ?> €</strong></span>
                            <?php else: ?>
                                <span class="muted"><?= e(t('home.card.no_tarifa')) ?></span>
                            <?php endif; ?>
                            <span class="cta"><?= e(t('home.card.cta')) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
