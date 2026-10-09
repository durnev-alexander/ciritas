<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/cart.php';
require __DIR__.'/includes/layout.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['_cart_csrf'])) $_SESSION['_cart_csrf'] = bin2hex(random_bytes(24));

$id=(int)($_GET['id']??0);
$st=db()->prepare('SELECT * FROM csSoftProducts WHERE ID=? AND IsActive=1');
$st->execute([$id]);
$p=$st->fetch();
if(!$p){http_response_code(404);exit('Not found');}

$prices=[];
try {
    $q=db()->prepare('SELECT * FROM csSoftPrices WHERE SoftProductID=? ORDER BY ID');
    $q->execute([$id]);
    $prices=$q->fetchAll();
} catch(Throwable) {}

$shots=[];
try {
    $q=db()->prepare('SELECT * FROM csSoftShots WHERE SoftProductID=? ORDER BY ID');
    $q->execute([$id]);
    $shots=$q->fetchAll();
} catch(Throwable) {}

function product_photo_url(string $file): string {
    $base=rtrim((string)cfg('site.photos_url','/photos/'),'/').'/';
    return $base.rawurlencode($file);
}

function product_html(?string $value): string {
    $html = legacy($value ?? '');
    if ($html === '') return '';

    // Часть старых записей хранит HTML-теги как сущности (&lt;...&gt;).
    // Декодируем их, чтобы на новом сайте применялось исходное форматирование.
    if (str_contains($html, '&lt;') || str_contains($html, '&gt;')) {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return $html;
}

$shortDescr = product_html($p['ShortDescr'] ?? '');
$about = product_html($p['About'] ?? '');
$features = product_html($p['Features'] ?? '');

render_header(legacy($p['Name']));
?>
<section class="section"><div class="container">
<a href="products.php">← Все продукты</a>
<h1><?=htmlspecialchars(legacy($p['Name']))?></h1>
<?php if($shortDescr!==''): ?><div class="lead product-html"><?=$shortDescr?></div><?php endif; ?>

<?php if($about!==''): ?><div class="content-card product-html"><?=$about?></div><?php endif; ?>

<?php if($features!==''): ?><div class="content-card product-html"><h2>Возможности</h2><?=$features?></div><?php endif; ?>

<?php if($shots): ?><div class="content-card"><h2>Скриншоты</h2><div class="product-shot-grid">
<?php foreach($shots as $s): ?><?php $full=legacy((string)($s['FileFull']??'')); $thumb=legacy((string)($s['FileThumb']??'')); $preview=$full!==''?$full:$thumb; if($preview==='') continue; $shotName=legacy((string)($s['Name']??'')); ?>
<a class="product-shot" href="<?=htmlspecialchars(product_photo_url($full!==''?$full:$preview))?>" target="_blank" rel="noopener"><span class="product-shot-title"><?=htmlspecialchars($shotName)?></span><img src="<?=htmlspecialchars(product_photo_url($preview))?>" alt="<?=htmlspecialchars($shotName)?>"></a>
<?php endforeach; ?>
</div></div><?php endif; ?>

<?php if($prices): ?><div class="content-card"><h2>Цены и лицензии</h2><div class="license-list">
<?php foreach($prices as $price): ?><div class="license-row"><div><strong><?=htmlspecialchars(legacy((string)($price['Title']??$price['Name']??'Лицензия')))?></strong><div class="muted"><?=number_format((float)($price['Price']??0),0,',',' ')?> ₽</div></div><?php if(!isset($p['OrderAllow']) || (int)$p['OrderAllow']===1): ?><form class="inline-form" method="post" action="cart.php"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_cart_csrf'])?>"><input type="hidden" name="action" value="add"><input type="hidden" name="price_id" value="<?=(int)$price['ID']?>"><input class="input qty-input" type="number" name="amount" min="1" max="999" value="1"><button class="btn btn-primary" type="submit">В корзину</button></form><?php endif; ?></div><?php endforeach; ?>
</div></div><?php endif; ?>

<?php
$downloads=[];
try {
    $order='ID';
    $cols=db()->query('SHOW COLUMNS FROM csSoftDownloads')->fetchAll();
    foreach($cols as $col){if(($col['Field']??'')==='OrderIndex'){$order='OrderIndex, ID';break;}}
    $d=db()->prepare('SELECT * FROM csSoftDownloads WHERE SoftProductID=? AND IsActive=1 ORDER BY '.$order);
    $d->execute([$id]);
    $downloads=$d->fetchAll();
} catch(Throwable) {}
?>
<?php if($downloads): ?><div class="content-card"><h2>Скачать</h2><ul class="download-list"><?php foreach($downloads as $f): ?><li><a href="download.php?id=<?=(int)$f['ID']?>"><?=htmlspecialchars(legacy((string)($f['Description']??$f['File'])))?></a><?php if(!empty($f['Downloads'])): ?><span class="muted">Скачиваний: <?=(int)$f['Downloads']?></span><?php endif; ?></li><?php endforeach; ?></ul></div><?php endif; ?>
</div></section><?php render_footer();
