<?php
require __DIR__.'/includes/layout.php';
require __DIR__.'/../app/site_settings.php';

$error='';
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_home_news_count'){
    csrf_check();
    try{
        $count=max(0,min(50,(int)($_POST['home_news_count']??3)));
        site_setting_set('HomeNewsCount',(string)$count);
        redirect('news.php?setting_saved=1');
    }catch(Throwable $e){$error='Не удалось сохранить количество новостей на главной: '.$e->getMessage();}
}

$rows=[];
try{$rows=db()->query('SELECT * FROM csNews ORDER BY NewsDate DESC,ID DESC')->fetchAll();}catch(Throwable){$error=$error?:'Не удалось загрузить новости.';}
$homeNewsCount=home_news_count();
admin_header('Новости');
?>
<div class="card" style="margin-bottom:18px">
<form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="save_home_news_count">
<div class="section-title"><div><h2 style="margin:0">Новости на главной</h2><p class="muted">Количество последних опубликованных новостей, выводимых под списком программ. Значение 0 отключает блок.</p></div></div>
<div class="actions" style="justify-content:flex-start;margin-top:14px"><input class="input" style="width:130px" type="number" name="home_news_count" min="0" max="50" value="<?=$homeNewsCount?>"><button class="btn primary">Сохранить</button></div>
<?php if(isset($_GET['setting_saved'])): ?><div class="alert success" style="margin-top:14px">Настройка главной страницы сохранена.</div><?php endif; ?>
</form></div>

<div class="card">
<div class="section-title"><div><h2 style="margin:0">Новости</h2><p class="muted">Создание и редактирование новостей сайта.</p></div><a class="btn primary" href="news-edit.php">+ Добавить новость</a></div>
<?php if(isset($_GET['saved'])): ?><div class="alert success">Новость сохранена.</div><?php endif; ?>
<?php if($error): ?><div class="alert error"><?=aesc($error)?></div><?php endif; ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Дата</th><th>Заголовок</th><th>Статус</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=aesc(a_legacy((string)($r['NewsDate']??'')))?></td><td><?=aesc(a_legacy((string)($r['Title']??'')))?></td><td><span class="badge <?=!empty($r['IsPublish'])?'on':'off'?>"><?=!empty($r['IsPublish'])?'Опубликована':'Скрыта'?></span></td><td><a class="btn" href="news-edit.php?id=<?=(int)$r['ID']?>">Редактировать</a></td></tr><?php endforeach; ?>
</tbody></table></div>
</div>
<?php admin_footer();
