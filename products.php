<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/includes/layout.php';
render_header('Продукты');
$q=trim((string)($_GET['q']??''));
$order='Name';
try{
    $cols=db()->query('SHOW COLUMNS FROM csSoftProducts')->fetchAll();
    foreach($cols as $col){
        if(($col['Field']??'')==='OrderIndex'){$order='OrderIndex, Name';break;}
    }
}catch(Throwable){}
$sql="SELECT ID,Name,ShortDescr FROM csSoftProducts WHERE IsActive=1";
$params=[];
if($q!==''){
    $sql.=" AND (Name LIKE ? OR ShortDescr LIKE ?)";
    $params=["%$q%","%$q%"];
}
$sql.=" ORDER BY ".$order;
$st=db()->prepare($sql);
$st->execute($params);
$rows=$st->fetchAll();
?><section class="section"><div class="container"><div class="section-head"><div><h1>Продукты</h1><p class="muted">Программные решения CIRITAS</p></div><form><input class="search" name="q" value="<?=htmlspecialchars($q,ENT_QUOTES)?>" placeholder="Поиск"></form></div><?php if($rows): ?><div class="product-grid"><?php foreach($rows as $r): ?><article class="product-card"><h2><?=htmlspecialchars(legacy($r['Name']))?></h2><p><?=htmlspecialchars(trim(strip_tags(legacy($r['ShortDescr']??''))))?></p><a href="product.php?id=<?=(int)$r['ID']?>">Подробнее →</a></article><?php endforeach; ?></div><?php else: ?><div class="content-card"><p>По вашему запросу продукты не найдены.</p></div><?php endif; ?></div></section><?php render_footer();
