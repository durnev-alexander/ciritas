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
    if($base==='') $base='file';
    return $base.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).($ext!==''?'.'.$ext:'');
}
function asset_upload(array $file,string $dir): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return '';
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new RuntimeException('Ошибка загрузки файла. Код: '.(int)$file['error']);
    if(!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('Не удалось создать каталог загрузки.');
    if(!is_writable($dir)) throw new RuntimeException('Каталог загрузки недоступен для записи: '.$dir);
    $name=asset_safe_name((string)($file['name']??'file'));
    if(!move_uploaded_file((string)$file['tmp_name'],rtrim($dir,'/\\').DIRECTORY_SEPARATOR.$name)) throw new RuntimeException('Не удалось сохранить загруженный файл.');
    return $name;
}
function asset_make_thumb(string $dir,string $fullName): string {
    $srcPath=rtrim($dir,'/\\').DIRECTORY_SEPARATOR.$fullName;
    $info=@getimagesize($srcPath);
    if(!$info || !function_exists('imagecreatetruecolor')) return $fullName;
    [$w,$h,$type]=$info;
    if($w<1||$h<1) return $fullName;
    $loader=match($type){IMAGETYPE_JPEG=>'imagecreatefromjpeg',IMAGETYPE_PNG=>'imagecreatefrompng',IMAGETYPE_GIF=>'imagecreatefromgif',defined('IMAGETYPE_WEBP')?IMAGETYPE_WEBP:-1=>'imagecreatefromwebp',default=>null};
    if(!$loader || !function_exists($loader)) return $fullName;
    $src=@$loader($srcPath); if(!$src) return $fullName;
    $maxW=420;$maxH=280;$scale=min($maxW/$w,$maxH/$h,1);$tw=max(1,(int)round($w*$scale));$th=max(1,(int)round($h*$scale));
    $dst=imagecreatetruecolor($tw,$th);
    if($type===IMAGETYPE_PNG || $type===IMAGETYPE_GIF){imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,255,255,255,127);imagefilledrectangle($dst,0,0,$tw,$th,$transparent);}
    imagecopyresampled($dst,$src,0,0,0,0,$tw,$th,$w,$h);
    $thumb='thumb-'.$fullName;$out=rtrim($dir,'/\\').DIRECTORY_SEPARATOR.$thumb;
    $saved=match($type){IMAGETYPE_JPEG=>imagejpeg($dst,$out,85),IMAGETYPE_PNG=>imagepng($dst,$out,6),IMAGETYPE_GIF=>imagegif($dst,$out),defined('IMAGETYPE_WEBP')?IMAGETYPE_WEBP:-1=>function_exists('imagewebp')?imagewebp($dst,$out,85):false,default=>false};
    imagedestroy($src);imagedestroy($dst);
    return $saved?$thumb:$fullName;
}
function asset_photo_url(string $file): string {
    return product_photo_asset_url($file);
}

$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    try{
        $action=$_POST['action']??'';
        $asset=(int)($_POST['asset_id']??0);
        if($action==='file_save'){
            $fileName=trim((string)($_POST['file']??''));
            if(isset($_FILES['upload']) && ($_FILES['upload']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
                $fileName=asset_upload($_FILES['upload'],(string)cfg2('site.uploads_path',__DIR__.'/../Uploads'));
            }
            $description=asset_db_text((string)($_POST['description']??''));
            $dbFile=asset_db_text($fileName);
            if($asset){
                db()->prepare('UPDATE csSoftDownloads SET Description=?,File=?,IsActive=? WHERE ID=? AND SoftProductID=?')->execute([$description,$dbFile,isset($_POST['active'])?1:0,$asset,$id]);
            }else{
                if($fileName==='') throw new RuntimeException('Выберите файл или укажите его имя.');
                db()->prepare('INSERT INTO csSoftDownloads (SoftProductID,Description,File,IsActive) VALUES (?,?,?,?)')->execute([$id,$description,$dbFile,isset($_POST['active'])?1:0]);
            }
        }elseif($action==='file_delete'&&$asset){
            db()->prepare('DELETE FROM csSoftDownloads WHERE ID=? AND SoftProductID=?')->execute([$asset,$id]);
        }elseif($action==='price_save'){
            $title=asset_db_text((string)($_POST['title']??''));
            $price=str_replace(',','.',trim((string)($_POST['price']??'0')));
            if(!is_numeric($price)) throw new RuntimeException('Цена должна быть числом.');
            if($asset){
                db()->prepare('UPDATE csSoftPrices SET Title=?,Price=? WHERE ID=? AND SoftProductID=?')->execute([$title,$price,$asset,$id]);
            }else{
                db()->prepare('INSERT INTO csSoftPrices (SoftProductID,Title,Price) VALUES (?,?,?)')->execute([$id,$title,$price]);
            }
        }elseif($action==='price_delete'&&$asset){
            db()->prepare('DELETE FROM csSoftPrices WHERE ID=? AND SoftProductID=?')->execute([$asset,$id]);
        }elseif($action==='shot_save'&&$asset){
            db()->prepare('UPDATE csSoftShots SET Name=? WHERE ID=? AND SoftProductID=?')->execute([asset_db_text((string)($_POST['title']??'')),$asset,$id]);
        }elseif($action==='shot_add'){
            if(!isset($_FILES['shot']) || ($_FILES['shot']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) throw new RuntimeException('Выберите изображение.');
            $photosPath=(string)cfg2('site.photos_path',__DIR__.'/../photos');
            $full=asset_upload($_FILES['shot'],$photosPath);
            $thumb=asset_make_thumb($photosPath,$full);
            $sortColumn=product_shots_order_column();
            if($sortColumn!==null){
                $safeSort='`'.str_replace('`','',$sortColumn).'`';
                $nextQ=db()->prepare('SELECT COALESCE(MAX('.$safeSort.'),0)+1 FROM csSoftShots WHERE SoftProductID=?');
                $nextQ->execute([$id]);
                $next=(int)$nextQ->fetchColumn();
                db()->prepare('INSERT INTO csSoftShots (SoftProductID,Name,FileFull,FileThumb,'.$safeSort.') VALUES (?,?,?,?,?)')->execute([$id,asset_db_text((string)($_POST['title']??'')),asset_db_text($full),asset_db_text($thumb),$next]);
            }else{
                db()->prepare('INSERT INTO csSoftShots (SoftProductID,Name,FileFull,FileThumb) VALUES (?,?,?,?)')->execute([$id,asset_db_text((string)($_POST['title']??'')),asset_db_text($full),asset_db_text($thumb)]);
            }
        }elseif($action==='shot_delete'&&$asset){
            db()->prepare('DELETE FROM csSoftShots WHERE ID=? AND SoftProductID=?')->execute([$asset,$id]);
        }
        redirect('product-assets.php?id='.$id);
    }catch(Throwable $e){$error=$e->getMessage();}
}

$files=[];$prices=[];$shots=[];
try{$q=db()->prepare('SELECT * FROM csSoftDownloads WHERE SoftProductID=? ORDER BY ID');$q->execute([$id]);$files=$q->fetchAll();}catch(Throwable){}
try{$q=db()->prepare('SELECT * FROM csSoftPrices WHERE SoftProductID=? ORDER BY ID');$q->execute([$id]);$prices=$q->fetchAll();}catch(Throwable){}
try{
    $q=db()->prepare('SELECT * FROM csSoftShots WHERE SoftProductID=? ORDER BY '.product_shots_order_sql());
    $q->execute([$id]);
    $shots=$q->fetchAll();
}catch(Throwable){
    try{$q=db()->prepare('SELECT * FROM csSoftShots WHERE SoftProductID=? ORDER BY ID');$q->execute([$id]);$shots=$q->fetchAll();}catch(Throwable){}
}

admin_header('Ресурсы: '.a_legacy($p['Name']));
if($error): ?><div class="flash"><?=aesc($error)?></div><?php endif; ?>
<div class="card">
<div class="section-title"><div><h2>Файлы для скачивания</h2><div class="help">Можно загрузить новый файл в каталог Uploads или оставить существующее имя файла.</div></div></div>
<div class="table-wrap"><table class="table"><thead><tr><th>Описание</th><th>Файл</th><th>Активен</th><th></th></tr></thead><tbody>
<?php foreach($files as $f): ?><tr><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="file_save"><input type="hidden" name="asset_id" value="<?=$f['ID']?>"><td><textarea class="textarea" name="description" rows="3"><?=aesc(a_legacy($f['Description']??''))?></textarea></td><td><input class="input" name="file" value="<?=aesc(a_legacy($f['File']??''))?>"><div class="help" style="margin-top:6px">Заменить файлом:</div><input type="file" name="upload"></td><td><input type="checkbox" name="active" <?=!empty($f['IsActive'])?'checked':''?>></td><td><div class="actions"><button class="btn">Сохранить</button><button class="btn danger" name="action" value="file_delete" onclick="return confirm('Удалить запись о файле?')">Удалить</button></div></td></form></tr><?php endforeach; ?>
<tr><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="file_save"><td><textarea class="textarea" name="description" rows="3" placeholder="Описание загрузки"></textarea></td><td><input class="input" name="file" placeholder="Имя уже существующего файла"><div class="help" style="margin:6px 0">или загрузить:</div><input type="file" name="upload"></td><td><input type="checkbox" name="active" checked></td><td><button class="btn primary">Добавить</button></td></form></tr>
</tbody></table></div></div>

<div class="card" style="margin-top:18px"><h2>Цены</h2><div class="table-wrap"><table class="table"><thead><tr><th>Название</th><th>Цена</th><th></th></tr></thead><tbody><?php foreach($prices as $pr): ?><tr><form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="price_save"><input type="hidden" name="asset_id" value="<?=$pr['ID']?>"><td><input class="input" name="title" value="<?=aesc(a_legacy($pr['Title']??''))?>"></td><td><input class="input" name="price" inputmode="decimal" value="<?=aesc((string)($pr['Price']??''))?>"></td><td><div class="actions"><button class="btn">Сохранить</button><button class="btn danger" name="action" value="price_delete" onclick="return confirm('Удалить цену?')">Удалить</button></div></td></form></tr><?php endforeach; ?><tr><form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="price_save"><td><input class="input" name="title" placeholder="Название лицензии"></td><td><input class="input" name="price" inputmode="decimal" value="0"></td><td><button class="btn primary">Добавить</button></td></form></tr></tbody></table></div></div>

<div class="card" style="margin-top:18px"><div class="section-title"><div><h2>Скриншоты</h2><div class="help">Порядок совпадает со старой сортировкой сайта: первая запись используется как картинка программы в списках. Новые скриншоты автоматически добавляются в конец. В админке показывается полноразмерное изображение.</div></div></div><div class="shot-grid"><?php foreach($shots as $s): ?><?php $full=a_legacy((string)($s['FileFull']??'')); $thumb=a_legacy((string)($s['FileThumb']??'')); $preview=$full!==''?$full:$thumb; ?><form method="post" class="shot-card"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="shot_save"><input type="hidden" name="asset_id" value="<?=$s['ID']?>"><?php if($preview!==''): ?><a class="shot-preview" href="<?=aesc(asset_photo_url($preview))?>" target="_blank"><img src="<?=aesc(asset_photo_url($preview))?>" alt=""></a><div class="help" style="margin-bottom:8px"><?=aesc($preview)?></div><?php else: ?><div class="shot-preview empty">Нет изображения</div><?php endif; ?><input class="input" name="title" value="<?=aesc(a_legacy($s['Name']??''))?>" placeholder="Название"><div class="actions" style="margin-top:10px"><button class="btn">Сохранить</button><button class="btn danger" name="action" value="shot_delete" onclick="return confirm('Удалить запись о скриншоте?')">Удалить</button></div></form><?php endforeach; ?><form method="post" enctype="multipart/form-data" class="shot-card"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="shot_add"><div class="upload-box">Новый скриншот</div><input class="input" name="title" placeholder="Название"><input type="file" name="shot" accept="image/jpeg,image/png,image/gif,image/webp" required style="margin-top:10px"><button class="btn primary" style="margin-top:10px">Загрузить</button></form></div></div>

<div class="actions" style="margin-top:18px;justify-content:flex-start"><a class="btn" href="product-edit.php?id=<?=$id?>">← К продукту</a><a class="btn" href="../product.php?id=<?=$id?>" target="_blank">Открыть на сайте ↗</a></div>
<?php admin_footer();
