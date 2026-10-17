<?php

function product_list_html(?string $value): string {
    $html = legacy($value ?? '');
    if ($html === '') return '';
    if (str_contains($html, '&lt;') || str_contains($html, '&gt;')) {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $html;
}

function product_shots_columns(): array {
    static $columns = null;
    if ($columns !== null) return $columns;
    $columns = [];
    try {
        foreach (db()->query('SHOW COLUMNS FROM csSoftShots')->fetchAll() as $col) {
            $name = (string)($col['Field'] ?? '');
            if ($name !== '') $columns[] = $name;
        }
    } catch (Throwable) {}
    return $columns;
}

function product_shots_order_column(): ?string {
    static $resolved = false;
    static $column = null;
    if ($resolved) return $column;
    $resolved = true;
    $names = product_shots_columns();
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
    return null;
}

function product_shots_active_column(): ?string {
    static $resolved = false;
    static $column = null;
    if ($resolved) return $column;
    $resolved = true;
    $names = product_shots_columns();
    foreach (['IsActive','Active','IsEnabled','Enabled'] as $candidate) {
        foreach ($names as $name) {
            if (strcasecmp($name, $candidate) === 0) {
                $column = $name;
                return $column;
            }
        }
    }
    return null;
}

function product_shots_order_sql(): string {
    $column = product_shots_order_column();
    return $column !== null ? '`'.str_replace('`','',$column).'`, ID' : 'ID';
}

function product_shots_active_sql(): string {
    $column = product_shots_active_column();
    return $column !== null ? ' AND `'.str_replace('`','',$column).'`=1' : '';
}

function product_photo_asset_url(string $file): string {
    $file = trim(legacy($file));
    if ($file === '') return '';

    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    if (preg_match('~^https?://~i', $file)) {
        if ($isHttps && str_starts_with(strtolower($file), 'http://')) {
            $file = 'https://'.substr($file, 7);
        }
        return $file;
    }

    $base = trim((string)cfg('site.photos_url','/photos/'));
    if ($base === '') $base = '/photos/';
    if ($isHttps && str_starts_with(strtolower($base), 'http://')) {
        $base = 'https://'.substr($base, 7);
    }

    $path = str_replace('\\','/',$file);
    $segments = array_map('rawurlencode', array_filter(explode('/', ltrim($path,'/')), static fn($v) => $v !== ''));
    return rtrim($base,'/').'/'.implode('/',$segments);
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
                $file = trim(legacy((string)($shot['FileFull'] ?: $shot['FileThumb'] ?: '')));
                if ($file !== '') $shotCache[$id] = product_photo_asset_url($file);
            }
        } catch (Throwable) {}
    }
    return $shotCache[$id];
}

function render_product_list_card(array $product, string $headingTag='h2'): void {
    $id=(int)($product['ID']??0);
    $name=legacy((string)($product['Name']??''));
    $description=product_list_html($product['ShortDescr']??'');
    $image=product_image_url($product);
    $tag=in_array($headingTag,['h2','h3'],true)?$headingTag:'h2';
    ?>
    <article class="product-list-card">
        <<?=$tag?> class="product-list-title"><?=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></<?=$tag?>>
        <div class="product-list-body<?=$image===''?' no-image':''?>">
            <?php if($image!==''): ?><div class="product-list-image"><img src="<?=htmlspecialchars($image,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>" alt="<?=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"></div><?php endif; ?>
            <div class="product-list-copy">
                <?php if($description!==''): ?><div class="product-list-html"><?=$description?></div><?php endif; ?>
                <a class="product-more" href="product.php?id=<?=$id?>">Подробнее...</a>
            </div>
        </div>
    </article>
    <?php
}
