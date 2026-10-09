<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/home_products.php';
require __DIR__.'/app/product_presenter.php';
require __DIR__.'/app/site_settings.php';
require __DIR__.'/includes/layout.php';

function home_news_html(?string $value): string {
    $html=legacy($value??'');
    if($html==='') return '';
    if(str_contains($html,'&lt;') || str_contains($html,'&gt;')){
        $html=html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8');
    }
    return $html;
}

function home_news_date(mixed $value): string {
    $raw=(string)$value;
    if($raw==='') return '';
    if(ctype_digit($raw) && (int)$raw>100000000) return date('d.m.Y',(int)$raw);
    return legacy($raw);
}

$products=home_products_rows();
$news=[];
$newsCount=home_news_count();
if($newsCount>0){
    try{
        $news=db()->query('SELECT * FROM csNews WHERE IsPublish=1 ORDER BY NewsDate DESC, ID DESC LIMIT '.(int)$newsCount)->fetchAll();
    }catch(Throwable){}
}

render_header('CIRITAS');
?>
<section class="hero"><div class="container"><h1>Программные решения CIRITAS</h1><p>Современные приложения для автоматизации и управления.</p><a class="btn btn-primary" href="products.php">Все продукты</a></div></section>
<section class="section"><div class="container"><h2>Продукты</h2><?php if($products): ?><div class="product-list"><?php foreach($products as $p) render_product_list_card($p,'h3'); ?></div><?php else: ?><div class="content-card"><p>Продукты для главной страницы пока не выбраны.</p></div><?php endif; ?></div></section>
<?php if($newsCount>0 && $news): ?>
<section class="section home-news-section"><div class="container">
<div class="section-head"><div><h2>Новости</h2><p class="muted">Последние новости ЦИРИТАС</p></div><a href="news.php">Все новости →</a></div>
<div class="home-news-list">
<?php foreach($news as $item): $title=legacy((string)($item['Title']??'')); $short=home_news_html($item['ShortDescr']??''); $date=home_news_date($item['NewsDate']??''); ?>
<article class="home-news-card">
    <div class="home-news-meta"><?php if($date!==''): ?><time><?=htmlspecialchars($date,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></time><?php endif; ?></div>
    <h3><?=htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></h3>
    <?php if($short!==''): ?><div class="home-news-html"><?=$short?></div><?php endif; ?>
</article>
<?php endforeach; ?>
</div>
</div></section>
<?php endif; ?>
<?php render_footer();
