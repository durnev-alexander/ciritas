<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/cart.php';
require __DIR__.'/app/product_presenter.php';
require __DIR__.'/app/product_text_sections.php';
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
    $q=db()->prepare('SELECT * FROM csSoftShots WHERE SoftProductID=?'.product_shots_active_sql().' ORDER BY '.product_shots_order_sql());
    $q->execute([$id]);
    $shots=$q->fetchAll();
} catch(Throwable) {}

function product_html_remove_backgrounds(string $html): string {
    $clean=preg_replace('/\s(?:bgcolor|background)\s*=\s*(["\']).*?\1/isu','',$html);
    if(is_string($clean)) $html=$clean;
    $clean=preg_replace('/\s(?:bgcolor|background)\s*=\s*[^\s>]+/iu','',$html);
    if(is_string($clean)) $html=$clean;
    $clean=preg_replace_callback('/\sstyle\s*=\s*(["\'])(.*?)\1/isu',function(array $match): string {
        $style=(string)$match[2];
        $without=preg_replace('/(?:^|;)\s*background(?:-[a-z-]+)?\s*:[^;]*/iu','',$style);
        if(is_string($without)) $style=$without;
        $style=preg_replace('/\s*;\s*/u',';',$style)??$style;
        $style=trim($style," ;\t\n\r\0\x0B");
        return $style===''?'':' style='.$match[1].$style.$match[1];
    },$html);
    return is_string($clean)?$clean:$html;
}

function product_html(?string $value): string {
    $html = legacy($value ?? '');
    if ($html === '') return '';
    for($i=0;$i<2 && (str_contains($html,'&lt;') || str_contains($html,'&gt;'));$i++) {
        $decoded=html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8');
        if($decoded===$html) break;
        $html=$decoded;
    }
    return product_html_remove_backgrounds($html);
}

function product_html_has_content(string $html): bool {
    if(trim($html)==='') return false;
    if(preg_match('/<(?:img|video|audio|iframe|object|embed|svg|canvas)\b/i',$html)) return true;
    $text=html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8');
    $text=preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u','',$text)??$text;
    return $text!=='';
}

function product_html_trim_trailing_empty(string $html): string {
    $html=rtrim($html);
    $emptyInline='(?:\s|&nbsp;|&#160;|&#x0*a0;|<br\s*\/?>)*';
    $emptyBlock='<(p|div|span)\b[^>]*>'.$emptyInline.'<\/\1>';
    do {
        $before=$html;
        $pattern='~'.$emptyInline.'(?:'.$emptyBlock.'|<br\s*/?>)'.$emptyInline.'$~iu';
        $clean=preg_replace($pattern,'',$html);
        if(is_string($clean)) $html=$clean;
        $html=rtrim($html);
    } while($html!==$before);
    return $html;
}

$shortDescr = product_html($p['ShortDescr'] ?? '');
$about = product_html($p['About'] ?? '');
$features = product_html($p['Features'] ?? '');
$textSections=product_text_sections_all($id,true);

render_header(legacy($p['Name']));
?>
<section class="section"><div class="container">
<a href="products.php">← Все продукты</a>
<h1><?=htmlspecialchars(legacy($p['Name']))?></h1>
<?php if(product_html_has_content($shortDescr)): ?><div class="lead product-html"><?=$shortDescr?></div><?php endif; ?>

<?php if(product_html_has_content($about)): ?><div class="content-card product-html"><?=$about?></div><?php endif; ?>

<?php if(product_html_has_content($features)): ?><div class="content-card product-html"><h2>Возможности</h2><?=$features?></div><?php endif; ?>

<?php foreach($textSections as $section): $sectionContent=product_html_trim_trailing_empty(product_html($section['Content']??'')); if(!product_html_has_content($sectionContent))continue; ?>
<div class="content-card product-html"><h2><?=htmlspecialchars(legacy((string)($section['Title']??'')))?></h2><?=$sectionContent?></div>
<?php endforeach; ?>

<?php if($shots): ?><div class="content-card"><h2>Скриншоты</h2><div class="product-shot-grid">
<?php foreach($shots as $s): ?><?php $full=legacy((string)($s['FileFull']??'')); $thumb=legacy((string)($s['FileThumb']??'')); $preview=$full!==''?$full:$thumb; if($preview==='') continue; $shotName=legacy((string)($s['Name']??'')); ?>
<a class="product-shot" href="<?=htmlspecialchars(product_photo_asset_url($full!==''?$full:$preview))?>" target="_blank" rel="noopener"><span class="product-shot-title"><?=htmlspecialchars($shotName)?></span><img src="<?=htmlspecialchars(product_photo_asset_url($preview))?>" alt="<?=htmlspecialchars($shotName)?>"></a>
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
