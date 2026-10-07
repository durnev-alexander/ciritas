<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/includes/layout.php';

function news_html(?string $value): string {
    $html = legacy($value ?? '');
    if ($html === '') return '';

    // Some legacy news entries store their HTML tags as entities.
    // Decode them so the original formatting is applied on the modern site.
    if (str_contains($html, '&lt;') || str_contains($html, '&gt;')) {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return $html;
}

render_header('Новости');
?>
<section class="section"><div class="container"><h1>Новости</h1>
<?php
try {
    $rows = db()->query('SELECT * FROM csNews WHERE IsPublish=1 ORDER BY NewsDate DESC, ID DESC')->fetchAll();
    foreach ($rows as $r):
        $title = legacy((string)($r['Title'] ?? ''));
        $short = news_html($r['ShortDescr'] ?? '');
        $full = news_html($r['FullDescr'] ?? '');
?>
<article class="content-card news-entry">
    <h2><?=htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></h2>
    <p class="muted"><?=htmlspecialchars((string)($r['NewsDate'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></p>
    <?php if ($short !== ''): ?><div class="news-html"><?=$short?></div><?php endif; ?>
    <?php if ($full !== ''): ?><div class="news-html"><?=$full?></div><?php endif; ?>
</article>
<?php
    endforeach;
} catch (Throwable $e) {
    echo '<p>Новости временно недоступны.</p>';
}
?>
</div></section>
<?php render_footer();
