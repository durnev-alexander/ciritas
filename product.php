<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/includes/layout.php';
$id=(int)($_GET['id']??0);
$st=db()->prepare('SELECT * FROM csSoftProducts WHERE ID=? AND IsActive=1');$st->execute([$id]);$p=$st->fetch();if(!$p){http_response_code(404);exit('Not found');}
render_header(legacy($p['Name']));
?><section class="section"><div class="container"><a href="products.php">← Все продукты</a><h1><?=htmlspecialchars(legacy($p['Name']))?></h1><p class="lead"><?=nl2br(htmlspecialchars(legacy($p['ShortDescr']??'')))?></p><div class="content-card"><?=legacy($p['About']??'')?></div><?php if(!empty($p['Features'])): ?><div class="content-card"><h2>Возможности</h2><?=legacy($p['Features'])?></div><?php endif; ?><?php $d=db()->prepare('SELECT * FROM csSoftDownloads WHERE SoftProductID=? AND IsActive=1 ORDER BY '.(column_exists('csSoftDownloads','OrderIndex')?'OrderIndex,':'').' ID');$d->execute([$id]);$downloads=$d->fetchAll(); if($downloads): ?><div class="content-card"><h2>Скачать</h2><ul><?php foreach($downloads as $f): ?><li><a href="download.php?id=<?=$f['ID']?>"><?=legacy($f['Description']??$f['File'])?></a></li><?php endforeach; ?></ul></div><?php endif; ?></div></section><?php render_footer();
