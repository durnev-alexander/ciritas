<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/includes/layout.php';

function news_html(?string $value): string {
    $html = legacy($value ?? '');
    if ($html === '') return '';
    if (str_contains($html, '&lt;') || str_contains($html, '&gt;')) {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $html;
}

function public_news_date(mixed $value): string {
    $raw=trim(legacy((string)$value));
    if($raw==='') return '';
    if(ctype_digit($raw)){
        $timestamp=(int)$raw;
        if($timestamp>0) return date('d.m.Y',$timestamp);
    }
    $timestamp=strtotime($raw);
    return $timestamp!==false?date('d.m.Y',$timestamp):$raw;
}

$q=trim((string)($_GET['q']??''));
render_header('Новости');
?>
<section class="section public-compact-section"><div class="container">
<div class="section-head">
    <div><h1>Новости</h1><p class="muted">Новости компании за весь период</p></div>
    <form method="get"><input class="search" name="q" value="<?=htmlspecialchars($q,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>" placeholder="Поиск"></form>
</div>
<?php
try {
    $sql='SELECT * FROM csNews WHERE IsPublish=1';
    $params=[];
    if($q!==''){
        $sql.=' AND (Title LIKE ? OR ShortDescr LIKE ? OR FullDescr LIKE ?)';
        $needle='%'.$q.'%';
        $params=[$needle,$needle,$needle];
    }
    $sql.=' ORDER BY NewsDate DESC, ID DESC';
    $st=db()->prepare($sql);$st->execute($params);$rows=$st->fetchAll();
    if(!$rows){echo '<div class="content-card"><p class="muted">По вашему запросу новости не найдены.</p></div>';}
    foreach ($rows as $r):
        $title = legacy((string)($r['Title'] ?? ''));
        $short = news_html($r['ShortDescr'] ?? '');
        $full = news_html($r['FullDescr'] ?? '');
?>
<article class="content-card news-entry">
    <h2><?=htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></h2>
    <p class="muted"><?=htmlspecialchars(public_news_date($r['NewsDate'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></p>
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
