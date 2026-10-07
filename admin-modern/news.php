<?php
require __DIR__.'/includes/layout.php';
$rows=[];$error='';
try{$rows=db()->query('SELECT * FROM csNews ORDER BY NewsDate DESC,ID DESC')->fetchAll();}catch(Throwable){$error='Не удалось загрузить новости.';}
admin_header('Новости');
?>
<div class="card">
<div class="section-title"><div><h2 style="margin:0">Новости</h2><p class="muted">Создание и редактирование новостей сайта.</p></div><a class="btn primary" href="news-edit.php">+ Добавить новость</a></div>
<?php if(isset($_GET['saved'])): ?><div class="alert success">Новость сохранена.</div><?php endif; ?>
<?php if($error): ?><div class="alert error"><?=aesc($error)?></div><?php endif; ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Дата</th><th>Заголовок</th><th>Статус</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=aesc(a_legacy((string)($r['NewsDate']??'')))?></td><td><?=aesc(a_legacy((string)($r['Title']??'')))?></td><td><span class="badge <?=!empty($r['IsPublish'])?'on':'off'?>"><?=!empty($r['IsPublish'])?'Опубликована':'Скрыта'?></span></td><td><a class="btn" href="news-edit.php?id=<?=(int)$r['ID']?>">Редактировать</a></td></tr><?php endforeach; ?>
</tbody></table></div>
</div>
<?php admin_footer();
