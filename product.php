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

render_header(legacy($p['Name']));
?>
<section class="section"><div class="container">
<a href="products.php">← Все продукты</a>
<h1><?=htmlspecialchars(legacy($p['Name']))?></h1>
<p class="lead"><?=nl2br(htmlspecialchars(legacy($p['ShortDescr']??'')))?></p>

<div class="product-actions"><a class="btn" href="cart.php">Корзина</a></div>
<div class="content-card"><?=legacy($p['About']??'')?></div>

<?php if(!empty($p['Features'])): ?><div class="content-card"><h2>Возможности</h2><?=legacy($p['Features'])?></div><?php endif; ?>

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
<?php if($downloads): ?><div class="content-card"><h2>Скачать</h2><ul><?php foreach($downloads as $f): ?><li><a href="download.php?id=<?=(int)$f['ID']?>"><?=htmlspecialchars(legacy((string)($f['Description']??$f['File'])))?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
</div></section><?php render_footer();
