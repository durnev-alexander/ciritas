<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/includes/layout.php';
render_header('CIRITAS');
$products=db()->query('SELECT ID,Name,ShortDescr FROM csSoftProducts WHERE IsActive=1 ORDER BY ID DESC LIMIT 6')->fetchAll();
?><section class="hero"><div class="container"><h1>Программные решения CIRITAS</h1><p>Современные приложения для автоматизации и управления.</p><a class="btn btn-primary" href="products.php">Все продукты</a></div></section><section class="section"><div class="container"><h2>Продукты</h2><div class="product-grid"><?php foreach($products as $p): ?><article class="product-card"><h3><?=htmlspecialchars(legacy($p['Name']))?></h3><p><?=htmlspecialchars(legacy($p['ShortDescr']??''))?></p><a href="product.php?id=<?=$p['ID']?>">Подробнее →</a></article><?php endforeach; ?></div></div></section><?php render_footer();
