<?php
require __DIR__.'/includes/layout.php';
require __DIR__.'/../app/product_seo.php';
$id=(int)($_GET['id']??0);
$seoError='';
try { product_seo_ensure_schema(); } catch(Throwable $e) { $seoError='Не удалось подготовить таблицу SEO-настроек: '.$e->getMessage(); }
$seo=['SeoTitle'=>'','SeoDescription'=>'','SeoKeywords'=>'','IsIndexable'=>1];
if($id>0) $seo=array_merge($seo,product_seo_find($id));
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
    $seo=[
        'SeoTitle'=>trim((string)($_POST['seoTitle']??'')),
        'SeoDescription'=>trim((string)($_POST['seoDescription']??'')),
        'SeoKeywords'=>trim((string)($_POST['seoKeywords']??'')),
        'IsIndexable'=>isset($_POST['seoIndexable'])?1:0,
    ];

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
    if(mb_strlen($seo['SeoTitle'],'UTF-8')>255 || mb_strlen($seo['SeoDescription'],'UTF-8')>500){
        $error='SEO-заголовок или описание превышает допустимую длину.';
    }elseif($seoError!==''){
        $error=$seoError;
    }elseif($rawName===''){
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
            try {
                product_seo_save($id,$seo);
                redirect('product-edit.php?id='.$id.'&saved=1');
            } catch(Throwable $e) {
                $error='Данные продукта сохранены, но SEO-настройки сохранить не удалось: '.$e->getMessage();
            }
        }
    }
}
admin_header($id?'Редактирование продукта':'Новый продукт');
?>
<?php if(!empty($_GET['saved'])) admin_notice('Продукт сохранён.','success'); ?>
<?php if(!empty($error)) admin_notice($error,'error'); ?>
<?php if($seoError!==''&&!isset($error)) admin_notice($seoError,'error'); ?>
<div class="card">
<form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><div class="form-grid">
<div class="field"><label class="label">Название</label><input class="input" name="name" required<?=$id?'':' autofocus'?> value="<?=aesc(a_legacy($row['Name']??''))?>"></div>
<div class="field"><label class="label">Группа</label><select class="select" name="group" required><?php foreach($groups as $g): ?><option value="<?=$g['ID']?>" <?=$row['SoftGroupID']==$g['ID']?'selected':''?>><?=aesc(a_legacy($g['Name']))?></option><?php endforeach; ?></select></div>
<?php if($columns['OrderIndex']): ?><div class="field"><label class="label">Порядок</label><input class="input" type="number" name="orderIndex" value="<?=(int)($row['OrderIndex']??0)?>"></div><?php endif; ?>
<div class="field"><label class="checkbox"><input type="checkbox" name="active" <?=$row['IsActive']?'checked':''?>> Активен на сайте</label><?php if($columns['OrderAllow']): ?><label class="checkbox"><input type="checkbox" name="orderAllow" <?=!empty($row['OrderAllow'])?'checked':''?>> Разрешить оформление заказа</label><?php endif; ?></div>
<div class="field full"><label class="label">Краткое описание (HTML)</label><textarea class="textarea html-editor" name="shortDescr" rows="5"><?=aesc(a_legacy($row['ShortDescr']??''))?></textarea></div>
<?php if($columns['PriceDescr']): ?><div class="field full"><label class="label">Описание цены</label><textarea class="textarea" name="priceDescr" rows="3"><?=aesc(a_legacy($row['PriceDescr']??''))?></textarea></div><?php endif; ?>
<div class="field full"><label class="label">О программе (HTML)</label><textarea class="textarea html-editor" name="about" rows="10"><?=aesc(a_legacy($row['About']??''))?></textarea></div>
<div class="field full"><label class="label">Возможности (HTML)</label><textarea class="textarea html-editor" name="features" rows="8"><?=aesc(a_legacy($row['Features']??''))?></textarea></div>
<div class="field full" style="border-top:1px solid var(--border,#334155);padding-top:18px;margin-top:8px">
<h2 style="margin:0 0 8px">SEO и продвижение</h2>
<div class="help">Настройки для поисковых систем. Если SEO-заголовок пустой, используется название программы. Пустое описание не добавляет meta description.</div>
</div>
<div class="field full"><label class="label">SEO Title (до 255 символов)</label><input class="input" name="seoTitle" maxlength="255" value="<?=aesc($seo['SeoTitle'])?>" placeholder="Название программы и решаемая задача — ЦИРИТАС"></div>
<div class="field full"><label class="label">Meta Description (до 500 символов)</label><textarea class="textarea" name="seoDescription" maxlength="500" rows="3" placeholder="Краткое и конкретное описание преимуществ программы"><?=aesc($seo['SeoDescription'])?></textarea></div>
<div class="field full"><label class="label">Целевые поисковые запросы (для планирования, не meta keywords)</label><textarea class="textarea" name="seoKeywords" rows="3" placeholder="Один запрос на строку"><?=aesc($seo['SeoKeywords'])?></textarea><div class="help">Сохраняются только в админке; поисковые системы не используют метатег keywords для ранжирования.</div></div>
<div class="field full"><label class="checkbox"><input type="checkbox" name="seoIndexable" <?=!empty($seo['IsIndexable'])?'checked':''?>> Разрешить индексацию программы поисковыми системами</label><div class="help">При отключении страница получает noindex и исключается из Sitemap.</div></div>
</div><div class="actions product-edit-actions" style="margin-top:20px"><button class="btn primary">Сохранить</button><?php if($id): ?><a class="btn" href="product-assets.php?id=<?=$id?>">Файлы, скриншоты и цены</a><?php else: ?><span class="btn" aria-disabled="true" style="opacity:.4;pointer-events:none">Файлы, скриншоты и цены</span><?php endif; ?><a class="btn product-edit-back" href="products.php">Отмена</a></div></form></div>
<script src="assets/js/html-editor.js"></script>
<?php admin_footer();
