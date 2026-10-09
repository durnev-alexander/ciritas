<?php

function product_list_text(?string $value): string {
    $text = legacy($value ?? '');
    if ($text === '') return '';
    if (str_contains($text, '&lt;') || str_contains($text, '&gt;')) {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
}

function product_shots_order_column(): ?string {
    static $resolved = false;
    static $column = null;
    if ($resolved) return $column;
    $resolved = true;
    try {
        $cols = db()->query('SHOW COLUMNS FROM csSoftShots')->fetchAll();
        $names = [];
        foreach ($cols as $col) {
            $name = (string)($col['Field'] ?? '');
            if ($name !== '') $names[] = $name;
        }
        foreach (['OrderIndex','SortOrder','SortIndex','Position','OrderNum','OrderID','Sort'] as $candidate) {
            foreach ($names as $name) {
                if (strcasecmp($name, $candidate) === 0) {
                    $column = $name;
                    return $column;
                }
            }
        }
        foreach ($names as $name) {
            if (in_array(strtolower($name), ['id','softproductid'], true)) continue;
            if (preg_match('/(order|sort|position|index)/i', $name)) {
                $column = $name;
                return $column;
            }
        }
    } catch (Throwable) {}
    return null;
}

function product_shots_order_sql(): string {
    $column = product_shots_order_column();
    return $column !== null ? '`'.str_replace('`','',$column).'`, ID' : 'ID';
}

function product_image_url(array $product): string {
    static $shotCache = [];
    $id = (int)($product['ID'] ?? 0);
    if ($id < 1) return '';

    if (!array_key_exists($id, $shotCache)) {
        $shotCache[$id] = '';
        try {
            $sql = 'SELECT FileThumb,FileFull FROM csSoftShots WHERE SoftProductID=? ORDER BY '.product_shots_order_sql().' LIMIT 1';
            $q = db()->prepare($sql);
            $q->execute([$id]);
            $shot = $q->fetch();
            if ($shot) {
                // На старом сайте первой в сортировке идёт загрузочная картинка продукта.
                // Для современного списка берём её полноразмерный вариант, не растягивая CSS-ом.
                $file = trim(legacy((string)($shot['FileFull'] ?: $shot['FileThumb'] ?: '')));
                if ($file !== '') {
                    $shotCache[$id] = rtrim((string)cfg('site.photos_url','/photos/'),'/').'/'.rawurlencode($file);
                }
            }
        } catch (Throwable) {}
    }
    return $shotCache[$id];
}

function render_product_list_card(array $product, string $headingTag='h2'): void {
    $id=(int)($product['ID']??0);
    $name=legacy((string)($product['Name']??''));
    $description=product_list_text($product['ShortDescr']??'');
    $image=product_image_url($product);
    $tag=in_array($headingTag,['h2','h3'],true)?$headingTag:'h2';
    ?>
    <article class="product-list-card">
        <<?=$tag?> class="product-list-title"><?=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></<?=$tag?>>
        <div class="product-list-body<?=$image===''?' no-image':''?>">
            <?php if($image!==''): ?><div class="product-list-image"><img src="<?=htmlspecialchars($image,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>" alt="<?=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"></div><?php endif; ?>
            <div class="product-list-copy">
                <?php if($description!==''): ?><p><?=htmlspecialchars($description,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></p><?php endif; ?>
                <a class="product-more" href="product.php?id=<?=$id?>">Подробнее...</a>
            </div>
        </div>
    </article>
    <?php
}
