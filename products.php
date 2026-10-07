<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/includes/layout.php';
render_header('Продукты');
$q=trim((string)($_GET['q']??''));
$sql="SELECT ID,Name,ShortDescr FROM csSoftProducts WHERE IsActive=1";
$params=[];
if($q!==''){ $sql.=" AND (Name LIKE ? OR ShortDescr LIKE ?)"; $params=["%$q%","%$q%"];} $sql.=" ORDER BY Name";
$st=db()->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
?><section class="section"><div class="container"><div class="section-head"><div><h1>Продукты</h1><p class="muted">Программные решения CIRITAS</p></div><form><input class="search" name="q" value="<?=htmlspecialchars($q,ENT_QUOTES)?>" placeholder="Поиск"></form></div><div class="product-grid"><?php foreach($rows as $r): ?><article class="product-card"><h2><?=htmlspecialchars(legacy($r['Name']))?></h2><p><?=htmlspecialchars(legacy($r['ShortDescr']??''))?></p><a href="product.php?id=<?=$r['ID']?>">Подробнее →</a></article><?php endforeach; ?></div></div></section><?php render_footer();
