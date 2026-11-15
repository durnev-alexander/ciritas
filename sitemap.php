<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/product_seo.php';

// Sitemap is generated from published product records, not hand-maintained.
try {
    $query = db()->query('SELECT ID FROM csSoftProducts WHERE IsActive=1 ORDER BY ID');
    $products = $query->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Retry-After: 300');
    exit('Sitemap temporarily unavailable');
}

header('Content-Type: application/xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
$base = 'https://www.ciritas.ru';
$urls = [
    $base.'/',
    $base.'/products.php',
    $base.'/news.php',
    $base.'/support.php',
    $base.'/contacts.php',
];
 $excluded=product_seo_hidden_ids();
foreach ($products as $id) {
    $id=(int)$id;
    if($id>0&&!isset($excluded[$id]))$urls[]=$base.'/product.php?id='.$id;
}
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($urls as $url) {
    echo '  <url><loc>',htmlspecialchars($url, ENT_XML1|ENT_QUOTES, 'UTF-8'),'</loc></url>',"\n";
}
echo '</urlset>';
