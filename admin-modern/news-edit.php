<?php
require __DIR__.'/includes/layout.php';

$id=(int)($_GET['id']??$_POST['id']??0);
$row=['NewsDate'=>date('Y-m-d'),'Title'=>'','ShortDescr'=>'','FullDescr'=>'','PhotoFull'=>'','PhotoThumb'=>'','IsPublish'=>1];
$error='';

if($id>0){
    try{$q=db()->prepare('SELECT * FROM csNews WHERE ID=?');$q->execute([$id]);$found=$q->fetch();if($found)$row=$found;else{$error='Новость не найдена.';}}
    catch(Throwable){$error='Не удалось загрузить новость.';}
}

function db_text_admin(string $value): string {
    $charset=strtolower((string)cfg('db.charset','latin1'));
    if($value===''||str_contains($charset,'utf8')) return $value;
    return mb_convert_encoding($value,'Windows-1251','UTF-8');
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $title=trim((string)($_POST['title']??''));
    $date=trim((string)($_POST['news_date']??''));
    $short=(string)($_POST['short_descr']??'');
    $full=(string)($_POST['full_descr']??'');
    $photoFull=trim((string)($_POST['photo_full']??''));
    $photoThumb=trim((string)($_POST['photo_thumb']??''));
    $publish=isset($_POST['is_publish'])?1:0;
    if($title===''){$error='Укажите заголовок новости.';}
    else{
        try{
            $values=[db_text_admin($date),db_text_admin($title),db_text_admin($short),db_text_admin($full),db_text_admin($photoFull),db_text_admin($photoThumb),$publish];
            if($id>0){
                $values[]=$id;
                db()->prepare('UPDATE csNews SET NewsDate=?,Title=?,ShortDescr=?,FullDescr=?,PhotoFull=?,PhotoThumb=?,IsPublish=? WHERE ID=?')->execute($values);
            }else{
                db()->prepare('INSERT INTO csNews (NewsDate,Title,ShortDescr,FullDescr,PhotoFull,PhotoThumb,IsPublish) VALUES (?,?,?,?,?,?,?)')->execute($values);
                $id=(int)db()->lastInsertId();
            }
            redirect('news.php?saved=1');
        }catch(Throwable $e){$error='Не удалось сохранить новость.';}
    }
    $row=['NewsDate'=>$date,'Title'=>$title,'ShortDescr'=>$short,'FullDescr'=>$full,'PhotoFull'=>$photoFull,'PhotoThumb'=>$photoThumb,'IsPublish'=>$publish];
}

admin_header($id?'Редактирование новости':'Новая новость');
?>
<div class="card">
<?php if($error): ?><div class="alert error"><?=aesc($error)?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>">
<div class="form-grid">
<div class="field"><label>Дата</label><input class="input" type="text" name="news_date" value="<?=aesc(a_legacy((string)($row['NewsDate']??'')))?>"></div>
<div class="field"><label><input type="checkbox" name="is_publish" <?=!empty($row['IsPublish'])?'checked':''?>> Опубликована</label></div>
<div class="field full"><label>Заголовок</label><input class="input" name="title" required value="<?=aesc(a_legacy((string)($row['Title']??'')))?>"></div>
<div class="field full"><label>Краткий текст (HTML)</label><textarea class="textarea html-editor" rows="8" name="short_descr"><?=aesc(a_legacy((string)($row['ShortDescr']??'')))?></textarea></div>
<div class="field full"><label>Полный текст (HTML)</label><textarea class="textarea html-editor" rows="14" name="full_descr"><?=aesc(a_legacy((string)($row['FullDescr']??'')))?></textarea></div>
<div class="field"><label>Фото — полный файл</label><input class="input" name="photo_full" value="<?=aesc(a_legacy((string)($row['PhotoFull']??'')))?>"></div>
<div class="field"><label>Фото — миниатюра</label><input class="input" name="photo_thumb" value="<?=aesc(a_legacy((string)($row['PhotoThumb']??'')))?>"></div>
</div>
<p class="muted">Поля описания сохраняются как HTML и на публичной странице отображаются с сохранёнными тегами.</p>
<div class="actions"><button class="btn primary" type="submit">Сохранить</button><a class="btn" href="news.php">Отмена</a></div>
</form>
</div>
<script src="assets/js/html-editor.js"></script>
<?php admin_footer();
