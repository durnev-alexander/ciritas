<?php
require __DIR__.'/includes/layout.php';
require __DIR__.'/../app/product_presenter.php';

$id=(int)($_GET['id']??0);
$q=db()->prepare('SELECT * FROM csSoftProducts WHERE ID=?');
$q->execute([$id]);
$p=$q->fetch();
if(!$p){http_response_code(404);exit('Product not found');}

function asset_db_text(string $value): string {
    $charset=strtolower((string)cfg2('db.charset','utf8mb4'));
    if(str_starts_with($charset,'utf8')) return $value;
    $converted=@iconv('UTF-8','Windows-1251//TRANSLIT',$value);
    return $converted!==false?$converted:$value;
}
function asset_safe_name(string $name): string {
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    $base=pathinfo($name,PATHINFO_FILENAME);
    $latin=function_exists('transliterator_transliterate')?transliterator_transliterate('Any-Latin; Latin-ASCII',$base):@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$base);
    if(!is_string($latin)||$latin==='')$latin=$base;
    $base=preg_replace('/[^A-Za-z0-9._-]+/','-',$latin);
    $base=trim((string)$base,'-_.');
    if($base==='')$base='file';
    return $base.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).($ext!==''?'.'.$ext:'');
}
function asset_upload(array $file,string $dir): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return '';
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)throw new RuntimeException('Ошибка загрузки файла. Код: '.(int)$file['error']);
    if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('Не удалось создать каталог загрузки.');
    if(!is_writable($dir))throw new RuntimeException('Каталог загрузки недоступен для записи: '.$dir);
    $name=asset_safe_name((string)($file['name']??'file'));
    if(!move_uploaded_file((string)$file['tmp_name'],rtrim($dir,'/\\').DIRECTORY_SEPARATOR.$name))throw new RuntimeException('Не удалось сохранить загруженный файл.');
    return $name;
}
function asset_make_thumb(string $dir,string $fullName): string {
    $srcPath=rtrim($dir,'/\\').DIRECTORY_SEPARATOR.$fullName;
    $info=@getimagesize($srcPath);
    if(!$info||!function_exists('imagecreatetruecolor'))return $fullName;
    [$w,$h,$type]=$info;if($w<1||$h<1)return $fullName;
    $loader=match($type){IMAGETYPE_JPEG=>'imagecreatefromjpeg',IMAGETYPE_PNG=>'imagecreatefrompng',IMAGETYPE_GIF=>'imagecreatefromgif',defined('IMAGETYPE_WEBP')?IMAGETYPE_WEBP:-1=>'imagecreatefromwebp',default=>null};
    if(!$loader||!function_exists($loader))return $fullName;
    $src=@$loader($srcPath);if(!$src)return $fullName;
    $maxW=420;$maxH=280;$scale=min($maxW/$w,$maxH/$h,1);$tw=max(1,(int)round($w*$scale));$th=max(1,(int)round($h*$scale));
    $dst=imagecreatetruecolor($tw,$th);
    if($type===IMAGETYPE_PNG||$type===IMAGETYPE_GIF){imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,255,255,255,127);imagefilledrectangle($dst,0,0,$tw,$th,$transparent);}
    imagecopyresampled($dst,$src,0,0,0,0,$tw,$th,$w,$h);
    $thumb='thumb-'.$fullName;$out=rtrim($dir,'/\\').DIRECTORY_SEPARATOR.$thumb;
    $saved=match($type){IMAGETYPE_JPEG=>imagejpeg($dst,$out,85),IMAGETYPE_PNG=>imagepng($dst,$out,6),IMAGETYPE_GIF=>imagegif($dst,$out),defined('IMAGETYPE_WEBP')?IMAGETYPE_WEBP:-1=>function_exists('imagewebp')?imagewebp($dst,$out,85):false,default=>false};
    imagedestroy($src);imagedestroy($dst);return $saved?$thumb:$fullName;
}
function asset_photo_url(string $file): string {return product_photo_asset_url($file);}
function asset_columns(string $table): array {
    try{$cols=db()->query('SHOW COLUMNS FROM `'.str_replace('`','',$table).'`')->fetchAll();$names=[];foreach($cols as $col){$name=(string)($col['Field']??'');if($name!=='')$names[]=$name;}return $names;}catch(Throwable){return [];}
}
function asset_find_column(string $table,array $candidates): ?string {
    $names=asset_columns($table);foreach($candidates as $candidate){foreach($names as $name){if(strcasecmp($name,$candidate)===0)return $name;}}return null;
}
function asset_sort_column(string $table): ?string {
    $column=asset_find_column($table,['OrderIndex','SortOrder','SortIndex','Position','OrderNum','OrderID','Sort','Order']);
    if($column!==null)return $column;
    foreach(asset_columns($table) as $name){if(in_array(strtolower($name),['id','softproductid'],true))continue;if(preg_match('/(order|sort|position|index)/i',$name))return $name;}
    return null;
}
function asset_ensure_sort_column(string $table,bool $alphabetic=false): ?string {
    $column=asset_sort_column($table);if($column!==null)return $column;
    try{
        db()->exec('ALTER TABLE `'.str_replace('`','',$table).'` ADD COLUMN SortOrder INT NOT NULL DEFAULT 0');
        $productIds=db()->query('SELECT DISTINCT SoftProductID FROM `'.str_replace('`','',$table).'` ORDER BY SoftProductID')->fetchAll(PDO::FETCH_COLUMN);
        $order=$alphabetic?'Title,ID':'ID';$select=db()->prepare('SELECT ID FROM `'.str_replace('`','',$table).'` WHERE SoftProductID=? ORDER BY '.$order);
        $update=db()->prepare('UPDATE `'.str_replace('`','',$table).'` SET SortOrder=? WHERE ID=? AND SoftProductID=?');
        foreach($productIds as $productId){$productId=(int)$productId;$select->execute([$productId]);$pos=10;foreach($select->fetchAll() as $row){$update->execute([$pos,(int)$row['ID'],$productId]);$pos+=10;}}
        return 'SortOrder';
    }catch(Throwable){return null;}
}
function asset_ensure_shot_active_column(): ?string {
    $column=asset_find_column('csSoftShots',['IsActive','Active','IsEnabled','Enabled']);if($column!==null)return $column;
    try{db()->exec('ALTER TABLE csSoftShots ADD COLUMN IsActive TINYINT(1) NOT NULL DEFAULT 1');return 'IsActive';}catch(Throwable){return null;}
}
function asset_normalize(string $table,int $productId,string $column): array {
    $safeTable='`'.str_replace('`','',$table).'`';$safe='`'.str_replace('`','',$column).'`';
    $q=db()->prepare('SELECT ID,'.$safe.' SortValue FROM '.$safeTable.' WHERE SoftProductID=? ORDER BY '.$safe.',ID');$q->execute([$productId]);$rows=$q->fetchAll();
    $update=db()->prepare('UPDATE '.$safeTable.' SET '.$safe.'=? WHERE ID=? AND SoftProductID=?');$pos=10;
    foreach($rows as &$row){$row['SortValue']=$pos;$update->execute([$pos,(int)$row['ID'],$productId]);$pos+=10;}unset($row);return $rows;
}
function asset_move(string $table,int $productId,string $column,int $asset,bool $up): void {
    $rows=asset_normalize($table,$productId,$column);$index=null;foreach($rows as $i=>$row){if((int)$row['ID']===$asset){$index=$i;break;}}if($index===null)return;
    $other=$up?$index-1:$index+1;if(!isset($rows[$other]))return;$a=$rows[$index];$b=$rows[$other];$safe='`'.str_replace('`','',$column).'`';$safeTable='`'.str_replace('`','',$table).'`';
    $q=db()->prepare('UPDATE '.$safeTable.' SET '.$safe.'=? WHERE ID=? AND SoftProductID=?');$q->execute([(int)$b['SortValue'],(int)$a['ID'],$productId]);$q->execute([(int)$a['SortValue'],(int)$b['ID'],$productId]);
}

$downloadSortColumn=asset_sort_column('csSoftDownloads');
$priceSortColumn=asset_ensure_sort_column('csSoftPrices',true);
$shotSortColumn=asset_ensure_sort_column('csSoftShots');
$shotActiveColumn=asset_ensure_shot_active_column();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    try{
        $action=$_POST['action']??'';$asset=(int)($_POST['asset_id']??0);
        if($action==='file_save'){
            $fileName=trim((string)($_POST['file']??''));if(isset($_FILES['upload'])&&($_FILES['upload']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$fileName=asset_upload($_FILES['upload'],(string)cfg2('site.uploads_path',__DIR__.'/../Uploads'));
            $description=asset_db_text((string)($_POST['description']??''));$dbFile=asset_db_text($fileName);
            if($asset){db()->prepare('UPDATE csSoftDownloads SET Description=?,File=?,IsActive=? WHERE ID=? AND SoftProductID=?')->execute([$description,$dbFile,isset($_POST['active'])?1:0,$asset,$id]);}
            else{
                if($fileName==='')throw new RuntimeException('Выберите файл или укажите его имя.');
                if($downloadSortColumn!==null){$safe='`'.str_replace('`','',$downloadSortColumn).'`';$rows=asset_normalize('csSoftDownloads',$id,$downloadSortColumn);$position=(int)($_POST['position']??0);$max=count($rows)+1;if($position<1)$position=$max;if($position>$max)$position=$max;$update=db()->prepare('UPDATE csSoftDownloads SET '.$safe.'=? WHERE ID=? AND SoftProductID=?');foreach($rows as $i=>$row){$place=$i+1;$update->execute([($place>=$position?$place+1:$place)*10,(int)$row['ID'],$id]);}db()->prepare('INSERT INTO csSoftDownloads (SoftProductID,Description,File,IsActive,'.$safe.') VALUES (?,?,?,?,?)')->execute([$id,$description,$dbFile,isset($_POST['active'])?1:0,$position*10]);}
                else db()->prepare('INSERT INTO csSoftDownloads (SoftProductID,Description,File,IsActive) VALUES (?,?,?,?)')->execute([$id,$description,$dbFile,isset($_POST['active'])?1:0]);
            }
        }elseif(($action==='file_sort_up'||$action==='file_sort_down')&&$asset){if($downloadSortColumn===null)throw new RuntimeException('В таблице файлов не найдено поле индекса сортировки.');asset_move('csSoftDownloads',$id,$downloadSortColumn,$asset,$action==='file_sort_up');}
        elseif($action==='file_delete'&&$asset){db()->prepare('DELETE FROM csSoftDownloads WHERE ID=? AND SoftProductID=?')->execute([$asset,$id]);if($downloadSortColumn!==null)asset_normalize('csSoftDownloads',$id,$downloadSortColumn);}
        elseif($action==='price_save'){
            $title=asset_db_text((string)($_POST['title']??''));$price=str_replace(',','.',trim((string)($_POST['price']??'0')));if(!is_numeric($price))throw new RuntimeException('Цена должна быть числом.');
            if($asset)db()->prepare('UPDATE csSoftPrices SET Title=?,Price=? WHERE ID=? AND SoftProductID=?')->execute([$title,$price,$asset,$id]);
            else if($priceSortColumn!==null){$safe='`'.str_replace('`','',$priceSortColumn).'`';$rows=asset_normalize('csSoftPrices',$id,$priceSortColumn);$position=(int)($_POST['position']??0);$max=count($rows)+1;if($position<1)$position=$max;if($position>$max)$position=$max;$update=db()->prepare('UPDATE csSoftPrices SET '.$safe.'=? WHERE ID=? AND SoftProductID=?');foreach($rows as $i=>$row){$place=$i+1;$update->execute([($place>=$position?$place+1:$place)*10,(int)$row['ID'],$id]);}db()->prepare('INSERT INTO csSoftPrices (SoftProductID,Title,Price,'.$safe.') VALUES (?,?,?,?)')->execute([$id,$title,$price,$position*10]);}
            else db()->prepare('INSERT INTO csSoftPrices (SoftProductID,Title,Price) VALUES (?,?,?)')->execute([$id,$title,$price]);
        }elseif(($action==='price_sort_up'||$action==='price_sort_down')&&$asset){if($priceSortColumn===null)throw new RuntimeException('В таблице цен не найдено поле индекса сортировки.');asset_move('csSoftPrices',$id,$priceSortColumn,$asset,$action==='price_sort_up');}
        elseif($action==='price_delete'&&$asset){db()->prepare('DELETE FROM csSoftPrices WHERE ID=? AND SoftProductID=?')->execute([$asset,$id]);if($priceSortColumn!==null)asset_normalize('csSoftPrices',$id,$priceSortColumn);}
        elseif($action==='shot_save'&&$asset){
            $description=asset_db_text((string)($_POST['description']??''));
            if($shotActiveColumn!==null){$safeActive='`'.str_replace('`','',$shotActiveColumn).'`';db()->prepare('UPDATE csSoftShots SET Name=?,'.$safeActive.'=? WHERE ID=? AND SoftProductID=?')->execute([$description,isset($_POST['active'])?1:0,$asset,$id]);}
            else db()->prepare('UPDATE csSoftShots SET Name=? WHERE ID=? AND SoftProductID=?')->execute([$description,$asset,$id]);
        }elseif(($action==='shot_sort_up'||$action==='shot_sort_down')&&$asset){if($shotSortColumn===null)throw new RuntimeException('В таблице скриншотов не найдено поле индекса сортировки.');asset_move('csSoftShots',$id,$shotSortColumn,$asset,$action==='shot_sort_up');}
        elseif($action==='shot_add'){
            if(!isset($_FILES['shot'])||($_FILES['shot']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)throw new RuntimeException('Выберите изображение.');
            $photosPath=(string)cfg2('site.photos_path',__DIR__.'/../photos');$full=asset_upload($_FILES['shot'],$photosPath);$thumb=asset_make_thumb($photosPath,$full);$description=asset_db_text((string)($_POST['description']??''));
            $columns=['SoftProductID','Name','FileFull','FileThumb'];$values=[$id,$description,asset_db_text($full),asset_db_text($thumb)];$marks=['?','?','?','?'];
            if($shotActiveColumn!==null){$columns[]='`'.str_replace('`','',$shotActiveColumn).'`';$values[]=isset($_POST['active'])?1:0;$marks[]='?';}
            if($shotSortColumn!==null){$rows=asset_normalize('csSoftShots',$id,$shotSortColumn);$position=(int)($_POST['position']??0);$max=count($rows)+1;if($position<1)$position=$max;if($position>$max)$position=$max;$safeSort='`'.str_replace('`','',$shotSortColumn).'`';$update=db()->prepare('UPDATE csSoftShots SET '.$safeSort.'=? WHERE ID=? AND SoftProductID=?');foreach($rows as $i=>$row){$place=$i+1;$update->execute([($place>=$position?$place+1:$place)*10,(int)$row['ID'],$id]);}$columns[]=$safeSort;$values[]=$position*10;$marks[]='?';}
            db()->prepare('INSERT INTO csSoftShots ('.implode(',',$columns).') VALUES ('.implode(',',$marks).')')->execute($values);
        }elseif($action==='shot_delete'&&$asset){db()->prepare('DELETE FROM csSoftShots WHERE ID=? AND SoftProductID=?')->execute([$asset,$id]);if($shotSortColumn!==null)asset_normalize('csSoftShots',$id,$shotSortColumn);}
        redirect('product-assets.php?id='.$id);
    }catch(Throwable $e){$error=$e->getMessage();}
}

$files=[];$prices=[];$shots=[];
try{$order=$downloadSortColumn!==null?'`'.str_replace('`','',$downloadSortColumn).'`,ID':'ID';$q=db()->prepare('SELECT * FROM csSoftDownloads WHERE SoftProductID=? ORDER BY '.$order);$q->execute([$id]);$files=$q->fetchAll();}catch(Throwable){}
try{$order=$priceSortColumn!==null?'`'.str_replace('`','',$priceSortColumn).'`,ID':'Title,ID';$q=db()->prepare('SELECT * FROM csSoftPrices WHERE SoftProductID=? ORDER BY '.$order);$q->execute([$id]);$prices=$q->fetchAll();}catch(Throwable){}
try{$order=$shotSortColumn!==null?'`'.str_replace('`','',$shotSortColumn).'`,ID':'ID';$q=db()->prepare('SELECT * FROM csSoftShots WHERE SoftProductID=? ORDER BY '.$order);$q->execute([$id]);$shots=$q->fetchAll();}catch(Throwable){}

admin_header('Ресурсы: '.a_legacy($p['Name']));
if($error):?><div class="flash"><?=aesc($error)?></div><?php endif;?>
<style>
.asset-files-table,.asset-prices-table,.asset-shots-table{table-layout:auto}
.asset-files-table .asset-description{width:66.666%}.asset-files-table .asset-file{width:33.333%}
.asset-files-table .asset-active,.asset-files-table .asset-sort,.asset-files-table .asset-action,.asset-prices-table .asset-price-sort,.asset-prices-table .asset-price-action,.asset-shots-table .asset-shot-active,.asset-shots-table .asset-shot-sort,.asset-shots-table .asset-shot-action{width:1%;white-space:nowrap}
.asset-files-table .actions,.asset-prices-table .actions,.asset-shots-table .actions{flex-wrap:nowrap}
.asset-files-table .asset-position,.asset-prices-table .asset-price-position,.asset-shots-table .asset-shot-position{width:100%;min-width:76px;box-sizing:border-box;text-align:center}
.asset-files-table .asset-position::-webkit-inner-spin-button,.asset-files-table .asset-position::-webkit-outer-spin-button,.asset-prices-table .asset-price-position::-webkit-inner-spin-button,.asset-prices-table .asset-price-position::-webkit-outer-spin-button,.asset-shots-table .asset-shot-position::-webkit-inner-spin-button,.asset-shots-table .asset-shot-position::-webkit-outer-spin-button{opacity:1;display:block}
.asset-file-picker{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap}
.asset-file-field{position:relative;display:flex;align-items:stretch}.asset-file-field .input{padding-right:48px}.asset-file-field .asset-file-trigger{position:absolute;right:4px;top:4px;bottom:4px;min-width:36px;padding:0 10px}
.asset-prices-table .asset-price-title{width:66.666%}.asset-prices-table .asset-price-value{width:33.333%}
.asset-shots-table .asset-shot{width:240px;min-width:240px}.asset-shots-table .asset-shot-description{width:100%}
.asset-shot-preview{display:flex;width:240px;height:180px;align-items:center;justify-content:center;overflow:hidden;border-radius:8px;background:var(--panel-soft,#f4f6f8)}
.asset-shot-preview img{display:block;width:240px;height:180px;object-fit:contain}.asset-shot-preview.empty{font-size:13px;color:var(--muted)}
</style>

<div class="card">
<div class="section-title" style="margin-bottom:16px"><div><h2>Файлы для скачивания</h2><div class="help">Можно загрузить новый файл в каталог Uploads или оставить существующее имя файла.</div></div><div class="admin-products-summary">Всего: <?=count($files)?></div></div>
<div class="table-wrap"><table class="table asset-files-table"><thead><tr><th class="asset-description">Описание</th><th class="asset-file">Файл</th><th class="asset-active">Активен</th><th class="asset-sort">Сортировка</th><th class="asset-action">Действия</th></tr></thead><tbody>
<?php foreach($files as $i=>$f):?><tr><form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="asset_id" value="<?=$f['ID']?>"><td class="asset-description"><input class="input" name="description" value="<?=aesc(a_legacy($f['Description']??''))?>"></td><td class="asset-file"><input class="input" name="file" value="<?=aesc(a_legacy($f['File']??''))?>"></td><td class="asset-active"><input type="checkbox" name="active" <?=!empty($f['IsActive'])?'checked':''?>></td><td class="asset-sort"><?php if($downloadSortColumn!==null):?><div class="actions"><button class="btn sort-btn" name="action" value="file_sort_up" <?=$i===0?'disabled':''?> title="Выше">↑</button><button class="btn sort-btn" name="action" value="file_sort_down" <?=$i===count($files)-1?'disabled':''?> title="Ниже">↓</button></div><?php else:?><span class="muted">—</span><?php endif;?></td><td class="asset-action"><div class="actions"><button class="btn" name="action" value="file_save">Сохранить</button><button class="btn danger js-asset-delete" type="button" data-delete-action="file_delete" data-asset-id="<?=(int)$f['ID']?>" data-asset-label="<?=aesc(a_legacy((string)($f['Description']??$f['File']??'')))?>">Удалить</button></div></td></form></tr><?php endforeach;?>
<tr><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="file_save"><td class="asset-description"><input class="input" name="description" placeholder="Описание загрузки"></td><td class="asset-file"><div class="asset-file-field"><input class="input" id="newDownloadFileName" name="file" placeholder="Имя файла в Uploads"><label class="btn asset-file-trigger" for="newDownloadUpload" title="Выбрать файл">…</label><input class="asset-file-picker" id="newDownloadUpload" type="file" name="upload"></div></td><td class="asset-active"><input type="checkbox" name="active" checked></td><td class="asset-sort"><input class="input asset-position" type="number" name="position" min="1" max="<?=count($files)+1?>" value="<?=count($files)+1?>" title="Место в списке"></td><td class="asset-action"><div class="actions"><button class="btn primary">Добавить</button></div></td></form></tr>
</tbody></table></div></div>
<script>(function(){var picker=document.getElementById('newDownloadUpload'),name=document.getElementById('newDownloadFileName');if(picker&&name)picker.addEventListener('change',function(){if(picker.files&&picker.files[0])name.value=picker.files[0].name;});})();</script>

<div class="card" style="margin-top:18px">
<div class="section-title" style="margin-bottom:16px"><div><h2>Цены на лицензии</h2><div class="help">Здесь укажите все виды лицензий и их цены</div></div><div class="admin-products-summary">Всего: <?=count($prices)?></div></div>
<div class="table-wrap"><table class="table asset-prices-table"><thead><tr><th class="asset-price-title">Название</th><th class="asset-price-value">Цена</th><th class="asset-price-sort">Сортировка</th><th class="asset-price-action">Действия</th></tr></thead><tbody>
<?php foreach($prices as $i=>$pr):?><tr><form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="asset_id" value="<?=$pr['ID']?>"><td class="asset-price-title"><input class="input" name="title" value="<?=aesc(a_legacy($pr['Title']??''))?>"></td><td class="asset-price-value"><input class="input" name="price" inputmode="decimal" value="<?=aesc((string)($pr['Price']??''))?>"></td><td class="asset-price-sort"><?php if($priceSortColumn!==null):?><div class="actions"><button class="btn sort-btn" name="action" value="price_sort_up" <?=$i===0?'disabled':''?> title="Выше">↑</button><button class="btn sort-btn" name="action" value="price_sort_down" <?=$i===count($prices)-1?'disabled':''?> title="Ниже">↓</button></div><?php else:?><span class="muted">—</span><?php endif;?></td><td class="asset-price-action"><div class="actions"><button class="btn" name="action" value="price_save">Сохранить</button><button class="btn danger js-asset-delete" type="button" data-delete-action="price_delete" data-asset-id="<?=(int)$pr['ID']?>" data-asset-label="<?=aesc(a_legacy((string)($pr['Title']??'')))?>">Удалить</button></div></td></form></tr><?php endforeach;?>
<tr><form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="price_save"><td class="asset-price-title"><input class="input" name="title" placeholder="Название лицензии"></td><td class="asset-price-value"><input class="input" name="price" inputmode="decimal" value="0"></td><td class="asset-price-sort"><input class="input asset-price-position" type="number" name="position" min="1" max="<?=count($prices)+1?>" value="<?=count($prices)+1?>" title="Место в списке"></td><td class="asset-price-action"><div class="actions"><button class="btn primary">Добавить</button></div></td></form></tr>
</tbody></table></div></div>

<div class="card" style="margin-top:18px">
<div class="section-title" style="margin-bottom:16px"><div><h2>Скриншоты</h2><div class="help">Скриншоты выводятся в заданном порядке. Первый активный скриншот используется как изображение программы в списках.</div></div><div class="admin-products-summary">Всего: <?=count($shots)?></div></div>
<div class="table-wrap"><table class="table asset-shots-table"><thead><tr><th class="asset-shot">Скрин</th><th class="asset-shot-description">Описание</th><th class="asset-shot-active">Активен</th><th class="asset-shot-sort">Сортировка</th><th class="asset-shot-action">Действия</th></tr></thead><tbody>
<?php foreach($shots as $i=>$s):?><?php $full=a_legacy((string)($s['FileFull']??''));$thumb=a_legacy((string)($s['FileThumb']??''));$preview=$full!==''?$full:$thumb;$active=$shotActiveColumn===null||!empty($s[$shotActiveColumn]);?><tr><form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="asset_id" value="<?=(int)$s['ID']?>"><td class="asset-shot"><?php if($preview!==''):?><a class="asset-shot-preview" href="<?=aesc(asset_photo_url($full!==''?$full:$preview))?>" target="_blank" rel="noopener"><img src="<?=aesc(asset_photo_url($preview))?>" alt=""></a><?php else:?><div class="asset-shot-preview empty">Нет изображения</div><?php endif;?></td><td class="asset-shot-description"><input class="input" name="description" value="<?=aesc(a_legacy((string)($s['Name']??'')))?>" placeholder="Описание скриншота"></td><td class="asset-shot-active"><input type="checkbox" name="active" <?=$active?'checked':''?>></td><td class="asset-shot-sort"><?php if($shotSortColumn!==null):?><div class="actions"><button class="btn sort-btn" name="action" value="shot_sort_up" <?=$i===0?'disabled':''?> title="Выше">↑</button><button class="btn sort-btn" name="action" value="shot_sort_down" <?=$i===count($shots)-1?'disabled':''?> title="Ниже">↓</button></div><?php else:?><span class="muted">—</span><?php endif;?></td><td class="asset-shot-action"><div class="actions"><button class="btn" name="action" value="shot_save">Сохранить</button><button class="btn danger js-asset-delete" type="button" data-delete-action="shot_delete" data-asset-id="<?=(int)$s['ID']?>" data-asset-label="<?=aesc(a_legacy((string)($s['Name']??'')))?>">Удалить</button></div></td></form></tr><?php endforeach;?>
<tr><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="shot_add"><td class="asset-shot"><div class="asset-file-field"><input class="input" id="newShotFileName" value="" placeholder="Выберите изображение" readonly><label class="btn asset-file-trigger" for="newShotUpload" title="Выбрать изображение">…</label><input class="asset-file-picker" id="newShotUpload" type="file" name="shot" accept="image/jpeg,image/png,image/gif,image/webp" required></div></td><td class="asset-shot-description"><input class="input" name="description" placeholder="Описание скриншота"></td><td class="asset-shot-active"><input type="checkbox" name="active" checked></td><td class="asset-shot-sort"><input class="input asset-shot-position" type="number" name="position" min="1" max="<?=count($shots)+1?>" value="<?=count($shots)+1?>" title="Место в списке"></td><td class="asset-shot-action"><div class="actions"><button class="btn primary">Добавить</button></div></td></form></tr>
</tbody></table></div></div>
<script>(function(){var picker=document.getElementById('newShotUpload'),name=document.getElementById('newShotFileName');if(picker&&name)picker.addEventListener('change',function(){name.value=picker.files&&picker.files[0]?picker.files[0].name:'';});})();</script>

<div id="assetDeleteModal" class="admin-modal" hidden aria-hidden="true"><div class="admin-modal-backdrop" data-modal-close></div><div class="admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="assetDeleteTitle"><h2 id="assetDeleteTitle">Подтверждение удаления</h2><p><span id="assetDeleteQuestion">Удалить запись?</span><span id="assetDeleteLabel" class="admin-modal-product"></span></p><form method="post" class="admin-modal-actions"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" id="assetDeleteAction" name="action"><input type="hidden" id="assetDeleteId" name="asset_id"><button class="btn" type="button" data-modal-close>Отмена</button><button class="btn" type="submit">Удалить</button></form></div></div>
<script>(function(){var modal=document.getElementById('assetDeleteModal'),actionInput=document.getElementById('assetDeleteAction'),idInput=document.getElementById('assetDeleteId'),question=document.getElementById('assetDeleteQuestion'),label=document.getElementById('assetDeleteLabel'),lastTrigger=null;function openModal(button){lastTrigger=button;var action=button.dataset.deleteAction||'';actionInput.value=action;idInput.value=button.dataset.assetId||'';question.textContent=action==='price_delete'?'Удалить цену/лицензию?':action==='shot_delete'?'Удалить скриншот?':'Удалить запись о файле?';label.textContent=button.dataset.assetLabel||'';modal.hidden=false;modal.setAttribute('aria-hidden','false');document.body.classList.add('admin-modal-open');var cancel=modal.querySelector('[data-modal-close]');if(cancel)cancel.focus();}function closeModal(){modal.hidden=true;modal.setAttribute('aria-hidden','true');document.body.classList.remove('admin-modal-open');if(lastTrigger)lastTrigger.focus();}document.querySelectorAll('.js-asset-delete').forEach(function(button){button.addEventListener('click',function(){openModal(button);});});modal.querySelectorAll('[data-modal-close]').forEach(function(button){button.addEventListener('click',closeModal);});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!modal.hidden)closeModal();});})();</script>

<div class="actions" style="margin-top:18px;justify-content:flex-start"><a class="btn" href="product-edit.php?id=<?=$id?>">← К продукту</a><a class="btn" href="../product.php?id=<?=$id?>" target="_blank">Открыть на сайте ↗</a></div>
<?php admin_footer();
