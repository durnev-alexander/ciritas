<?php

function product_list_text(?string $value): string {
    $text = legacy($value ?? '');
    if ($text === '') return '';
    if (str_contains($text, '&lt;') || str_contains($text, '&gt;')) {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
}

function product_image_url(array $product): string {
    $preferred = [
        'Image', 'ImageFile', 'ImageName', 'Picture', 'PictureFile', 'Logo', 'LogoFile',
        'Img', 'Photo', 'PhotoFile', 'ImageSmall', 'SmallImage', 'Icon', 'IconFile'
    ];
    $candidates = [];
    foreach ($preferred as $field) {
        if (array_key_exists($field, $product) && trim((string)$product[$field]) !== '') {
            $candidates[] = (string)$product[$field];
        }
    }
    foreach ($product as $field => $value) {
        if (!is_scalar($value) || trim((string)$value) === '') continue;
        if (preg_match('/(image|img|picture|logo|photo|icon|pic)/i', (string)$field)) {
            $candidates[] = (string)$value;
        }
    }

    foreach (array_unique($candidates) as $raw) {
        $value = trim(legacy($raw));
        if ($value === '') continue;
        if (preg_match("~<img[^>]+src=[\"']([^\"']+)[\"']~i", $value, $m)) $value = $m[1];
        if (preg_match('~^https?://~i', $value) || str_starts_with($value, '//')) return $value;
        if (str_starts_with($value, '/')) return $value;

        $value = ltrim(str_replace('\\', '/', $value), '/');
        $root = dirname(__DIR__);
        $paths = [
            [$root.'/'.$value, '/'.$value],
            [$root.'/images/'.$value, '/images/'.$value],
            [$root.'/Images/'.$value, '/Images/'.$value],
            [$root.'/img/'.$value, '/img/'.$value],
            [(string)cfg('site.photos_path').'/'.$value, rtrim((string)cfg('site.photos_url','/photos/'),'/').'/'.$value],
        ];
        foreach ($paths as [$file, $url]) {
            if (is_file($file)) return $url;
        }
    }

    static $shotCache = [];
    $id = (int)($product['ID'] ?? 0);
    if ($id > 0) {
        if (!array_key_exists($id, $shotCache)) {
            $shotCache[$id] = '';
            try {
                $q = db()->prepare('SELECT FileThumb,FileFull FROM csSoftShots WHERE SoftProductID=? ORDER BY ID LIMIT 1');
                $q->execute([$id]);
                $shot = $q->fetch();
                if ($shot) {
                    $file = trim(legacy((string)($shot['FileThumb'] ?: $shot['FileFull'] ?: '')));
                    if ($file !== '') $shotCache[$id] = rtrim((string)cfg('site.photos_url','/photos/'),'/').'/'.rawurlencode($file);
                }
            } catch (Throwable) {}
        }
        return $shotCache[$id];
    }
    return '';
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
