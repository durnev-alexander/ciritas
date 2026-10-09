<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/home_products.php';
require __DIR__.'/app/product_presenter.php';
require __DIR__.'/includes/layout.php';
render_header('CIRITAS');
$products=home_products_rows();
?><section class="hero"><div class="container"><h1>Программные решения CIRITAS</h1><p>Современные приложения для автоматизации и управления.</p><a class="btn btn-primary" href="products.php">Все продукты</a></div></section><section class="section"><div class="container"><h2>Продукты</h2><?php if($products): ?><div class="product-list"><?php foreach($products as $p) render_product_list_card($p,'h3'); ?></div><?php else: ?><div class="content-card"><p>Продукты для главной страницы пока не выбраны.</p></div><?php endif; ?></div></section><?php render_footer();
