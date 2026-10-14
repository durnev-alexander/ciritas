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
function news_edit_date_value(mixed $value): string {
    $raw=trim(a_legacy((string)$value));
    if($raw==='') return date('Y-m-d');
    if(ctype_digit($raw)){
        $timestamp=(int)$raw;
        if($timestamp>0) return date('Y-m-d',$timestamp);
    }
    $timestamp=strtotime($raw);
    return $timestamp!==false?date('Y-m-d',$timestamp):date('Y-m-d');
}
function news_text_has_content(string $html): bool {
    $text=html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8');
    $text=strip_tags($text);
    $text=str_replace("\xc2\xa0",' ',$text);
    return trim($text)!=='';
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $title=trim((string)($_POST['title']??''));
    $date=trim((string)($_POST['news_date']??''));
    $short=(string)($_POST['short_descr']??'');
    $full=(string)($_POST['full_descr']??'');
    $photoFull=(string)($row['PhotoFull']??'');
    $photoThumb=(string)($row['PhotoThumb']??'');
    $publish=isset($_POST['is_publish'])?1:0;
    if($date==='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || strtotime($date)===false){$error='Укажите дату новости.';}
    elseif($title===''){$error='Укажите заголовок новости.';}
    elseif($publish && (!news_text_has_content($short) || !news_text_has_content($full))){$error='Опубликовать новость можно только после заполнения краткого и полного текста.';}
    else{
        try{
            $isNew=$id<1;
            $values=[db_text_admin($date),db_text_admin($title),db_text_admin($short),db_text_admin($full),db_text_admin($photoFull),db_text_admin($photoThumb),$publish];
            if($id>0){
                $values[]=$id;
                db()->prepare('UPDATE csNews SET NewsDate=?,Title=?,ShortDescr=?,FullDescr=?,PhotoFull=?,PhotoThumb=?,IsPublish=? WHERE ID=?')->execute($values);
            }else{
                db()->prepare('INSERT INTO csNews (NewsDate,Title,ShortDescr,FullDescr,PhotoFull,PhotoThumb,IsPublish) VALUES (?,?,?,?,?,?,?)')->execute($values);
                $id=(int)db()->lastInsertId();
            }
            admin_set_notice($isNew?'Новость добавлена.':'Новость изменена.','success');
            redirect('news.php');
        }catch(Throwable $e){$error='Не удалось сохранить новость.';}
    }
    $row=['NewsDate'=>$date,'Title'=>$title,'ShortDescr'=>$short,'FullDescr'=>$full,'PhotoFull'=>$photoFull,'PhotoThumb'=>$photoThumb,'IsPublish'=>$publish];
}

admin_header($id?'Редактирование новости':'Новая новость');
if($error) admin_notice($error,'error');
?>
<div class="card">
<form method="post" id="newsEditForm" novalidate>
<input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>">
<div class="form-grid">
<div class="field full news-date-publish-row">
    <div class="news-date-field"><label for="newsDate">Дата</label><input class="input" id="newsDate" type="date" name="news_date" value="<?=aesc(news_edit_date_value($row['NewsDate']??''))?>" onkeydown="event.preventDefault()" onpaste="event.preventDefault()" ondrop="event.preventDefault()"></div>
    <label class="news-publish-field"><input id="newsPublish" type="checkbox" name="is_publish" <?=!empty($row['IsPublish'])?'checked':''?>> Опубликовать</label>
</div>
<div class="field full"><label>Заголовок</label><input class="input" id="newsTitle" name="title" value="<?=aesc(a_legacy((string)($row['Title']??'')))?>"></div>
<div class="field full"><label>Краткий текст (HTML)</label><textarea class="textarea html-editor" id="newsShortDescr" rows="8" name="short_descr"><?=aesc(a_legacy((string)($row['ShortDescr']??'')))?></textarea></div>
<div class="field full"><label>Полный текст (HTML)</label><textarea class="textarea html-editor" id="newsFullDescr" rows="14" name="full_descr"><?=aesc(a_legacy((string)($row['FullDescr']??'')))?></textarea></div>
</div>
<p class="muted">Поля описания сохраняются как HTML и на публичной странице отображаются с сохранёнными тегами.</p>
<div class="actions"><button class="btn primary" type="submit">Сохранить</button><a class="btn" href="news.php">Отмена</a></div>
</form>
</div>
<style>
.news-date-publish-row{display:flex;align-items:flex-end;gap:18px;flex-wrap:wrap}
.news-date-field{display:flex;flex-direction:column;gap:6px}
.news-date-field .input{width:180px}
.news-publish-field{display:flex;align-items:center;gap:8px;min-height:38px;margin-bottom:1px;white-space:nowrap}
</style>
<script src="assets/js/html-editor.js"></script>
<script>(function(){
var form=document.getElementById('newsEditForm');var date=document.getElementById('newsDate');var title=document.getElementById('newsTitle');var publish=document.getElementById('newsPublish');var shortField=document.getElementById('newsShortDescr');var fullField=document.getElementById('newsFullDescr');
function editorValue(id,field){try{if(window.tinymce){var editor=tinymce.get(id);if(editor)return editor.getContent();}}catch(e){}return field?field.value||'':'';}
function hasText(html){var box=document.createElement('div');box.innerHTML=html||'';return (box.textContent||box.innerText||'').replace(/\u00a0/g,' ').trim()!=='';}
function showError(message){var bar=document.getElementById('adminStatusBar');if(!bar)return;bar.textContent=message;bar.classList.remove('success','empty');bar.classList.add('error');bar.setAttribute('role','status');}
function textsReady(){return hasText(editorValue('newsShortDescr',shortField))&&hasText(editorValue('newsFullDescr',fullField));}
publish.addEventListener('change',function(){if(publish.checked&&!textsReady()){publish.checked=false;showError('Опубликовать новость можно только после заполнения краткого и полного текста.');}});
form.addEventListener('submit',function(event){try{if(window.tinymce)tinymce.triggerSave();}catch(e){}
    if(!date.value){event.preventDefault();showError('Укажите дату новости.');date.focus();return;}
    if(!title.value.trim()){event.preventDefault();showError('Укажите заголовок новости.');title.focus();return;}
    if(publish.checked&&!textsReady()){event.preventDefault();publish.checked=false;showError('Опубликовать новость можно только после заполнения краткого и полного текста.');return;}
});
})();</script>
<?php admin_footer();
