<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/product_presenter.php';
require __DIR__.'/includes/layout.php';

function legacy_sort_column(string $table): ?string {
    try {
        $cols=db()->query('SHOW COLUMNS FROM `'.str_replace('`','',$table).'`')->fetchAll();
        $names=[];
        foreach($cols as $col){
            $name=(string)($col['Field']??'');
            if($name!=='') $names[]=$name;
        }
        foreach(['OrderIndex','SortOrder','SortIndex','Position','OrderNum','OrderID','Sort','Order'] as $candidate){
            foreach($names as $name){
                if(strcasecmp($name,$candidate)===0) return $name;
            }
        }
        foreach($names as $name){
            if(in_array(strtolower($name),['id','softgroupid','softproductid'],true)) continue;
            if(preg_match('/(order|sort|position|index)/i',$name)) return $name;
        }
    } catch(Throwable) {}
    return null;
}

function qualified_order_part(string $alias, ?string $column, string $fallback): string {
    if($column!==null){
        return $alias.'.`'.str_replace('`','',$column).'`, '.$alias.'.ID';
    }
    return $alias.'.`'.str_replace('`','',$fallback).'`, '.$alias.'.ID';
}

render_header('Продукты');
$q=trim((string)($_GET['q']??''));
$groupOrder=legacy_sort_column('csSoftGroups');
$productOrder=legacy_sort_column('csSoftProducts');
$order=qualified_order_part('g',$groupOrder,'Name').', '.qualified_order_part('p',$productOrder,'Name');

$sql="SELECT p.* FROM csSoftProducts p LEFT JOIN csSoftGroups g ON g.ID=p.SoftGroupID WHERE p.IsActive=1";
$params=[];
if($q!==''){
    $sql.=" AND (p.Name LIKE ? OR p.ShortDescr LIKE ?)";
    $params=["%$q%","%$q%"];
}
$sql.=" ORDER BY ".$order;
$st=db()->prepare($sql);
$st->execute($params);
$rows=$st->fetchAll();
?>
<section class="section"><div class="container"><div class="section-head"><div><h1>Продукты</h1><p class="muted">Программные решения CIRITAS</p></div><form><input class="search" name="q" value="<?=htmlspecialchars($q,ENT_QUOTES)?>" placeholder="Поиск"></form></div><?php if($rows): ?><div class="product-list"><?php foreach($rows as $r) render_product_list_card($r,'h2'); ?></div><?php else: ?><div class="content-card"><p>По вашему запросу продукты не найдены.</p></div><?php endif; ?></div></section><?php render_footer();
