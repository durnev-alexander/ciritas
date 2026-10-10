<?php
require __DIR__.'/includes/layout.php';
$id=(int)($_GET['id']??0);
$columns=[]; foreach(['PriceDescr','OrderAllow','OrderIndex'] as $c){$columns[$c]=column_exists('csSoftProducts',$c);} 
$groups=db()->query('SELECT ID,Name FROM csSoftGroups ORDER BY Name')->fetchAll();
$row=['SoftGroupID'=>'','Name'=>'','ShortDescr'=>'','About'=>'','Features'=>'','IsActive'=>1,'OrderAllow'=>0,'PriceDescr'=>'','OrderIndex'=>0];
if($id){$q=db()->prepare('SELECT * FROM csSoftProducts WHERE ID=?');$q->execute([$id]);$row=$q->fetch()?:$row;}

function product_admin_name_key(string $value): string {
    $value=trim(preg_replace('/\s+/u',' ',$value)??$value);
    return function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $rawName=trim((string)($_POST['name']??''));
    $postedRow=[
        'SoftGroupID'=>(int)($_POST['group']??0),
        'Name'=>$rawName,
        'ShortDescr'=>(string)($_POST['shortDescr']??''),
        'About'=>(string)($_POST['about']??''),
        'Features'=>(string)($_POST['features']??''),
        'IsActive'=>isset($_POST['active'])?1:0,
        'OrderAllow'=>isset($_POST['orderAllow'])?1:0,
        'PriceDescr'=>(string)($_POST['priceDescr']??''),
        'OrderIndex'=>(int)($_POST['orderIndex']??0),
    ];
    $row=array_merge($row,$postedRow);

    $data=[
        'SoftGroupID'=>$postedRow['SoftGroupID'],
        'Name'=>admin_db_text($rawName),
        'ShortDescr'=>admin_db_text($postedRow['ShortDescr']),
        'About'=>admin_db_text($postedRow['About']),
        'Features'=>admin_db_text($postedRow['Features']),
        'IsActive'=>$postedRow['IsActive'],
    ];
    if($columns['PriceDescr'])$data['PriceDescr']=admin_db_text($postedRow['PriceDescr']);
    if($columns['OrderAllow'])$data['OrderAllow']=$postedRow['OrderAllow'];
    if($columns['OrderIndex'])$data['OrderIndex']=$postedRow['OrderIndex'];
    if($rawName===''){
        $error='Укажите название продукта.';
    }else{
        $duplicate=false;
        $nameKey=product_admin_name_key($rawName);
        $q=db()->prepare('SELECT ID,Name FROM csSoftProducts WHERE ID<>?');
        $q->execute([$id]);
        foreach($q->fetchAll() as $existing){
            if(product_admin_name_key(a_legacy((string)($existing['Name']??'')))===$nameKey){$duplicate=true;break;}
        }
        if($duplicate){
            $error=$id?'Сохранение запрещено: продукт с таким названием уже существует.':'Добавление запрещено: продукт с таким названием уже существует.';
        }else{
            $names=array_keys($data);$params=array_values($data);
            if($id){$set=implode(',',array_map(fn($n)=>'`'.$n.'`=?',$names));$params[]=$id;db()->prepare('UPDATE csSoftProducts SET '.$set.' WHERE ID=?')->execute($params);}
            else{$quoted=implode(',',array_map(fn($n)=>'`'.$n.'`',$names));$marks=implode(',',array_fill(0,count($names),'?'));db()->prepare('INSERT INTO csSoftProducts ('.$quoted.') VALUES ('.$marks.')')->execute($params);$id=(int)db()->lastInsertId();}
            redirect('product-edit.php?id='.$id.'&saved=1');
        }
    }
}
admin_header($id?'Редактирование продукта':'Новый продукт');
?>
<?php if(!empty($_GET['saved'])) admin_notice('Продукт сохранён.','success'); ?>
<?php if(!empty($error)) admin_notice($error,'error'); ?>
<div class="card">
<form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><div class="form-grid">
<div class="field"><label class="label">Название</label><input class="input" name="name" required<?=$id?'':' autofocus'?> value="<?=aesc(a_legacy($row['Name']??''))?>"></div>
<div class="field"><label class="label">Группа</label><select class="select" name="group" required><?php foreach($groups as $g): ?><option value="<?=$g['ID']?>" <?=$row['SoftGroupID']==$g['ID']?'selected':''?>><?=aesc(a_legacy($g['Name']))?></option><?php endforeach; ?></select></div>
<?php if($columns['OrderIndex']): ?><div class="field"><label class="label">Порядок</label><input class="input" type="number" name="orderIndex" value="<?=(int)($row['OrderIndex']??0)?>"></div><?php endif; ?>
<div class="field"><label class="checkbox"><input type="checkbox" name="active" <?=$row['IsActive']?'checked':''?>> Активен на сайте</label><?php if($columns['OrderAllow']): ?><label class="checkbox"><input type="checkbox" name="orderAllow" <?=!empty($row['OrderAllow'])?'checked':''?>> Разрешить оформление заказа</label><?php endif; ?></div>
<div class="field full"><label class="label">Краткое описание</label><textarea class="textarea" name="shortDescr" rows="3"><?=aesc(a_legacy($row['ShortDescr']??''))?></textarea></div>
<?php if($columns['PriceDescr']): ?><div class="field full"><label class="label">Описание цены</label><textarea class="textarea" name="priceDescr" rows="3"><?=aesc(a_legacy($row['PriceDescr']??''))?></textarea></div><?php endif; ?>
<div class="field full"><label class="label">О программе (HTML)</label><textarea class="textarea" name="about" rows="10"><?=aesc(a_legacy($row['About']??''))?></textarea></div>
<div class="field full"><label class="label">Возможности (HTML)</label><textarea class="textarea" name="features" rows="8"><?=aesc(a_legacy($row['Features']??''))?></textarea></div>
</div><div class="actions product-edit-actions" style="margin-top:20px"><button class="btn primary">Сохранить</button><?php if($id): ?><a class="btn" href="product-assets.php?id=<?=$id?>">Файлы, скриншоты и цены</a><?php else: ?><span class="btn" aria-disabled="true" style="opacity:.4;pointer-events:none">Файлы, скриншоты и цены</span><?php endif; ?><a class="btn product-edit-back" href="products.php">Отмена</a></div></form></div>
<?php admin_footer();
