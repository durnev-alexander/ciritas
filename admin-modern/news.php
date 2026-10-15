<?php
require __DIR__.'/includes/layout.php';
require __DIR__.'/../app/site_settings.php';

$error='';

function news_admin_date(mixed $value): string {
    $raw=trim(a_legacy((string)$value));
    if($raw==='') return '';
    if(ctype_digit($raw)){
        $timestamp=(int)$raw;
        if($timestamp>0) return date('d.m.Y',$timestamp);
    }
    $timestamp=strtotime($raw);
    return $timestamp!==false?date('d.m.Y',$timestamp):$raw;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $action=(string)($_POST['action']??'');
    try{
        if($action==='save_home_news_count'){
            $count=max(0,min(50,(int)($_POST['home_news_count']??3)));
            site_setting_set('HomeNewsCount',(string)$count);
            admin_set_notice('Настройка главной страницы сохранена.','success');
            redirect('news.php');
        }
        if($action==='delete_news'){
            $id=(int)($_POST['id']??0);
            if($id<1) throw new RuntimeException('Новость не найдена.');
            $q=db()->prepare('SELECT ID,Title,IsPublish FROM csNews WHERE ID=?');
            $q->execute([$id]);
            $row=$q->fetch();
            if(!$row) throw new RuntimeException('Новость не найдена.');
            if(!empty($row['IsPublish'])) throw new RuntimeException('Опубликованную новость удалить нельзя. Сначала снимите её с публикации.');
            db()->prepare('DELETE FROM csNews WHERE ID=? AND IsPublish=0')->execute([$id]);
            admin_set_notice('Новость удалена.','success');
            redirect('news.php');
        }
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

$rows=[];
try{$rows=db()->query('SELECT * FROM csNews ORDER BY NewsDate DESC,ID DESC')->fetchAll();}catch(Throwable){$error=$error?:'Не удалось загрузить новости.';}
$homeNewsCount=home_news_count();
admin_header('Новости');
if($error) admin_notice($error,'error');
?>
<div class="card" style="margin-bottom:18px">
<form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="save_home_news_count">
<div class="toolbar">
    <div><h2>Новости на главной</h2><div class="help">Количество последних опубликованных новостей, выводимых под списком программ. Значение 0 отключает блок.</div></div>
</div>
<div class="toolbar" style="justify-content:flex-start;margin-bottom:0"><input class="input" style="width:130px" type="number" name="home_news_count" min="0" max="50" value="<?=$homeNewsCount?>"><button class="btn primary">Сохранить</button></div>
</form></div>

<div class="card">
<div class="toolbar">
    <div><h2>Новости</h2><div class="help">Создание и редактирование новостей сайта.</div></div>
    <div class="admin-products-summary">Всего: <?=count($rows)?></div>
</div>
<div class="toolbar news-filter-row">
    <input id="newsSearch" class="input" type="search" placeholder="Поиск по названию новости...">
    <a class="btn primary" href="news-edit.php">+ Добавить новость</a>
</div>
<style>
.news-table .news-title{width:100%}
.news-table .news-date,.news-table .news-status,.news-table .news-actions{width:1%;white-space:nowrap}
.news-table .news-actions .actions{justify-content:flex-start;flex-wrap:nowrap}
</style>
<div class="table-wrap"><table class="table news-table" id="newsTable"><thead><tr><th class="news-date">Дата</th><th class="news-title">Заголовок</th><th class="news-status">Статус</th><th class="news-actions">Действия</th></tr></thead><tbody>
<?php foreach($rows as $r): $title=a_legacy((string)($r['Title']??''));$published=!empty($r['IsPublish']); ?><tr data-title="<?=aesc($title)?>"><td class="news-date"><?=aesc(news_admin_date($r['NewsDate']??''))?></td><td class="news-title"><?=aesc($title)?></td><td class="news-status"><span class="badge <?=$published?'on':'off'?>"><?=$published?'Опубликована':'Скрыта'?></span></td><td class="news-actions"><div class="actions"><a class="btn" href="news-edit.php?id=<?=(int)$r['ID']?>">Изменить</a><?php if($published): ?><button class="btn" type="button" disabled title="Сначала снимите новость с публикации">Удалить</button><?php else: ?><button class="btn danger js-news-delete" type="button" data-news-id="<?=(int)$r['ID']?>" data-news-title="<?=aesc($title)?>">Удалить</button><?php endif; ?></div></td></tr><?php endforeach; ?>
</tbody></table></div>
</div>

<div id="newsDeleteModal" class="admin-modal" hidden aria-hidden="true">
    <div class="admin-modal-backdrop" data-modal-close></div>
    <div class="admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="newsDeleteTitle">
        <h2 id="newsDeleteTitle">Подтверждение удаления</h2>
        <p>Удалить новость? Это действие нельзя отменить.<span id="newsDeleteName" class="admin-modal-product"></span></p>
        <form method="post" class="admin-modal-actions">
            <input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>">
            <input type="hidden" name="action" value="delete_news">
            <input type="hidden" id="newsDeleteId" name="id" value="">
            <button class="btn" type="button" data-modal-close>Отмена</button>
            <button class="btn" type="submit">Удалить</button>
        </form>
    </div>
</div>
<script>(function(){
var search=document.getElementById('newsSearch');var rows=[].slice.call(document.querySelectorAll('#newsTable tbody tr'));
function lower(value){return (value||'').toLocaleLowerCase('ru-RU')}
function filter(){var q=lower((search.value||'').trim());rows.forEach(function(row){row.style.display=!q||lower(row.dataset.title||'').indexOf(q)!==-1?'':'none';});}
if(search)search.addEventListener('input',filter);
var modal=document.getElementById('newsDeleteModal');var idInput=document.getElementById('newsDeleteId');var nameBox=document.getElementById('newsDeleteName');var lastTrigger=null;
function openModal(button){lastTrigger=button;idInput.value=button.dataset.newsId||'';nameBox.textContent=button.dataset.newsTitle||'';modal.hidden=false;modal.setAttribute('aria-hidden','false');document.body.classList.add('admin-modal-open');var cancel=modal.querySelector('[data-modal-close]');if(cancel)cancel.focus();}
function closeModal(){modal.hidden=true;modal.setAttribute('aria-hidden','true');document.body.classList.remove('admin-modal-open');if(lastTrigger)lastTrigger.focus();}
document.querySelectorAll('.js-news-delete').forEach(function(button){button.addEventListener('click',function(){openModal(button);});});
modal.querySelectorAll('[data-modal-close]').forEach(function(button){button.addEventListener('click',closeModal);});
document.addEventListener('keydown',function(event){if(event.key==='Escape'&&!modal.hidden)closeModal();});
})();</script>
<?php admin_footer();
