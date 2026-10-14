<?php
require __DIR__.'/includes/layout.php';

$error='';

function faq_db_text(string $value): string {
    $charset=strtolower((string)cfg('db.charset','latin1'));
    if($value===''||str_contains($charset,'utf8')) return $value;
    $converted=@iconv('UTF-8','Windows-1251//TRANSLIT',$value);
    return $converted!==false?$converted:$value;
}
function faq_topic_rows(): array {
    $q=db()->query('SELECT c.*, (SELECT COUNT(*) FROM faq f WHERE f.CategoryID=c.ID) FaqCount FROM faq_category c ORDER BY c.OrderIndex,c.ID');
    return $q->fetchAll();
}
function faq_question_rows(): array {
    $q=db()->query('SELECT f.*,c.Title CategoryTitle,c.OrderIndex CategoryOrder FROM faq f LEFT JOIN faq_category c ON c.ID=f.CategoryID ORDER BY c.OrderIndex,c.ID,f.OrderIndex,f.ID');
    return $q->fetchAll();
}
function faq_normalize_topics(): array {
    $rows=db()->query('SELECT ID FROM faq_category ORDER BY OrderIndex,ID')->fetchAll();
    $q=db()->prepare('UPDATE faq_category SET OrderIndex=? WHERE ID=?');
    $pos=10;foreach($rows as &$row){$row['OrderIndex']=$pos;$q->execute([$pos,(int)$row['ID']]);$pos+=10;}unset($row);return $rows;
}
function faq_normalize_questions(int $categoryId): array {
    $q=db()->prepare('SELECT ID FROM faq WHERE CategoryID=? ORDER BY OrderIndex,ID');$q->execute([$categoryId]);$rows=$q->fetchAll();
    $u=db()->prepare('UPDATE faq SET OrderIndex=? WHERE ID=? AND CategoryID=?');
    $pos=10;foreach($rows as &$row){$row['OrderIndex']=$pos;$u->execute([$pos,(int)$row['ID'],$categoryId]);$pos+=10;}unset($row);return $rows;
}
function faq_move_topic(int $id,bool $up): void {
    $rows=faq_normalize_topics();$index=null;foreach($rows as $i=>$row){if((int)$row['ID']===$id){$index=$i;break;}}if($index===null)return;
    $other=$up?$index-1:$index+1;if(!isset($rows[$other]))return;
    $q=db()->prepare('UPDATE faq_category SET OrderIndex=? WHERE ID=?');
    $q->execute([(int)$rows[$other]['OrderIndex'],$id]);$q->execute([(int)$rows[$index]['OrderIndex'],(int)$rows[$other]['ID']]);
}
function faq_move_question(int $id,bool $up): void {
    $q=db()->prepare('SELECT CategoryID FROM faq WHERE ID=?');$q->execute([$id]);$categoryId=(int)$q->fetchColumn();if($categoryId<1)return;
    $rows=faq_normalize_questions($categoryId);$index=null;foreach($rows as $i=>$row){if((int)$row['ID']===$id){$index=$i;break;}}if($index===null)return;
    $other=$up?$index-1:$index+1;if(!isset($rows[$other]))return;
    $u=db()->prepare('UPDATE faq SET OrderIndex=? WHERE ID=? AND CategoryID=?');
    $u->execute([(int)$rows[$other]['OrderIndex'],$id,$categoryId]);$u->execute([(int)$rows[$index]['OrderIndex'],(int)$rows[$other]['ID'],$categoryId]);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $action=(string)($_POST['action']??'');
    try{
        if($action==='topic_add'){
            $title=trim((string)($_POST['title']??''));if($title==='')throw new RuntimeException('Укажите название темы.');
            $rows=faq_normalize_topics();$position=(int)($_POST['position']??0);$max=count($rows)+1;if($position<1)$position=$max;if($position>$max)$position=$max;
            $u=db()->prepare('UPDATE faq_category SET OrderIndex=? WHERE ID=?');foreach($rows as $i=>$row){$place=$i+1;$u->execute([($place>=$position?$place+1:$place)*10,(int)$row['ID']]);}
            db()->prepare('INSERT INTO faq_category (Title,OrderIndex,IsActive) VALUES (?,?,?)')->execute([faq_db_text($title),$position*10,isset($_POST['active'])?1:0]);
            admin_set_notice('Тема ЧАВО добавлена.','success');redirect('faq.php');
        }
        if($action==='topic_save'){
            $id=(int)($_POST['id']??0);$title=trim((string)($_POST['title']??''));if($id<1)throw new RuntimeException('Тема не найдена.');if($title==='')throw new RuntimeException('Укажите название темы.');
            db()->prepare('UPDATE faq_category SET Title=?,IsActive=? WHERE ID=?')->execute([faq_db_text($title),isset($_POST['active'])?1:0,$id]);
            admin_set_notice('Тема ЧАВО сохранена.','success');redirect('faq.php');
        }
        if(($action==='topic_up'||$action==='topic_down')){
            $id=(int)($_POST['id']??0);if($id)faq_move_topic($id,$action==='topic_up');redirect('faq.php');
        }
        if($action==='topic_delete'){
            $id=(int)($_POST['id']??0);if($id<1)throw new RuntimeException('Тема не найдена.');
            $q=db()->prepare('SELECT COUNT(*) FROM faq WHERE CategoryID=?');$q->execute([$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Нельзя удалить тему, пока в ней есть ЧАВО.');
            db()->prepare('DELETE FROM faq_category WHERE ID=?')->execute([$id]);faq_normalize_topics();admin_set_notice('Тема ЧАВО удалена.','success');redirect('faq.php');
        }
        if(($action==='faq_up'||$action==='faq_down')){
            $id=(int)($_POST['id']??0);if($id)faq_move_question($id,$action==='faq_up');redirect('faq.php');
        }
        if($action==='faq_delete'){
            $id=(int)($_POST['id']??0);if($id<1)throw new RuntimeException('ЧАВО не найдено.');
            $q=db()->prepare('SELECT CategoryID FROM faq WHERE ID=?');$q->execute([$id]);$categoryId=(int)$q->fetchColumn();
            db()->prepare('DELETE FROM faq WHERE ID=?')->execute([$id]);if($categoryId>0)faq_normalize_questions($categoryId);admin_set_notice('ЧАВО удалено.','success');redirect('faq.php');
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}

$topics=[];$faqs=[];
try{$topics=faq_topic_rows();$faqs=faq_question_rows();}catch(Throwable $e){$error=$error?:'Не удалось загрузить ЧАВО: '.$e->getMessage();}
admin_header('FAQ');
if($error)admin_notice($error,'error');
?>
<style>
.faq-topics-table,.faq-items-table{table-layout:auto}
.faq-topics-table .faq-topic-title,.faq-items-table .faq-question{width:100%}
.faq-topics-table .faq-topic-active,.faq-topics-table .faq-topic-sort,.faq-topics-table .faq-topic-actions,.faq-items-table .faq-topic-name,.faq-items-table .faq-active,.faq-items-table .faq-sort,.faq-items-table .faq-actions{width:1%;white-space:nowrap}
.faq-topics-table .actions,.faq-items-table .actions{flex-wrap:nowrap;justify-content:flex-start}
.faq-topic-position{width:76px;text-align:center}
.faq-filter-row .input{flex:1;min-width:220px}
</style>

<div class="card" style="margin-bottom:18px">
<div class="toolbar">
    <div><h2>Темы ЧАВО</h2><div class="help">Темы объединяют связанные вопросы и задают порядок их показа на сайте.</div></div>
    <div class="admin-products-summary">Всего: <?=count($topics)?></div>
</div>
<div class="toolbar faq-filter-row"><input id="faqTopicSearch" class="input" type="search" placeholder="Поиск по названию темы..."></div>
<div class="table-wrap"><table class="table faq-topics-table" id="faqTopicsTable"><thead><tr><th class="faq-topic-title">Тема</th><th class="faq-topic-active">Активна</th><th class="faq-topic-sort">Сортировка</th><th class="faq-topic-actions">Действия</th></tr></thead><tbody>
<?php foreach($topics as $i=>$topic): $title=a_legacy((string)($topic['Title']??''));$used=(int)($topic['FaqCount']??0)>0; ?>
<tr data-title="<?=aesc($title)?>"><form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$topic['ID']?>"><td class="faq-topic-title"><input class="input" name="title" value="<?=aesc($title)?>"></td><td class="faq-topic-active"><input type="checkbox" name="active" <?=!empty($topic['IsActive'])?'checked':''?>></td><td class="faq-topic-sort"><div class="actions"><button class="btn sort-btn" name="action" value="topic_up" <?=$i===0?'disabled':''?> title="Выше">↑</button><button class="btn sort-btn" name="action" value="topic_down" <?=$i===count($topics)-1?'disabled':''?> title="Ниже">↓</button></div></td><td class="faq-topic-actions"><div class="actions"><button class="btn" name="action" value="topic_save">Сохранить</button><button class="btn danger js-topic-delete" type="button" data-id="<?=(int)$topic['ID']?>" data-title="<?=aesc($title)?>" <?=$used?'disabled title="Нельзя удалить непустую тему"':''?>>Удалить</button></div></td></form></tr>
<?php endforeach; ?>
<tr><form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="topic_add"><td class="faq-topic-title"><input class="input" name="title" placeholder="Название новой темы"></td><td class="faq-topic-active"><input type="checkbox" name="active" checked></td><td class="faq-topic-sort"><input class="input faq-topic-position" type="number" name="position" min="1" max="<?=count($topics)+1?>" value="<?=count($topics)+1?>" title="Место темы"></td><td class="faq-topic-actions"><div class="actions"><button class="btn primary">Добавить</button></div></td></form></tr>
</tbody></table></div>
</div>

<div class="card">
<div class="toolbar">
    <div><h2>ЧАВО</h2><div class="help">Все частые вопросы сайта. Порядок вопросов задаётся внутри каждой темы.</div></div>
    <div class="admin-products-summary">Всего: <?=count($faqs)?></div>
</div>
<div class="toolbar faq-filter-row"><input id="faqSearch" class="input" type="search" placeholder="Поиск по вопросу или теме..."><a class="btn primary" href="faq-edit.php">+ Добавить ЧАВО</a></div>
<div class="table-wrap"><table class="table faq-items-table" id="faqItemsTable"><thead><tr><th class="faq-topic-name">Тема</th><th class="faq-question">Вопрос</th><th class="faq-active">Активность</th><th class="faq-sort">Сортировка</th><th class="faq-actions">Действия</th></tr></thead><tbody>
<?php $categoryPositions=[];$categoryCounts=[];foreach($faqs as $row){$cid=(int)($row['CategoryID']??0);$categoryCounts[$cid]=($categoryCounts[$cid]??0)+1;}foreach($faqs as $row): $cid=(int)($row['CategoryID']??0);$categoryPositions[$cid]=($categoryPositions[$cid]??0)+1;$pos=$categoryPositions[$cid];$count=$categoryCounts[$cid]??1;$question=a_legacy((string)($row['Question']??''));$topic=a_legacy((string)($row['CategoryTitle']??'')); ?>
<tr data-search="<?=aesc($topic.' '.$question)?>"><td class="faq-topic-name"><?=aesc($topic)?></td><td class="faq-question"><strong><?=aesc($question)?></strong></td><td class="faq-active"><span class="badge <?=!empty($row['IsActive'])?'on':'off'?>"><?=!empty($row['IsActive'])?'Активно':'Скрыто'?></span></td><td class="faq-sort"><form method="post" class="actions"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$row['ID']?>"><button class="btn sort-btn" name="action" value="faq_up" <?=$pos===1?'disabled':''?> title="Выше">↑</button><button class="btn sort-btn" name="action" value="faq_down" <?=$pos===$count?'disabled':''?> title="Ниже">↓</button></form></td><td class="faq-actions"><div class="actions"><a class="btn" href="faq-edit.php?id=<?=(int)$row['ID']?>">Изменить</a><button class="btn danger js-faq-delete" type="button" data-id="<?=(int)$row['ID']?>" data-title="<?=aesc($question)?>">Удалить</button></div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</div>

<div id="faqDeleteModal" class="admin-modal" hidden aria-hidden="true"><div class="admin-modal-backdrop" data-modal-close></div><div class="admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="faqDeleteTitle"><h2 id="faqDeleteTitle">Подтверждение удаления</h2><p><span id="faqDeleteQuestion">Удалить запись?</span><span id="faqDeleteName" class="admin-modal-product"></span></p><form method="post" class="admin-modal-actions"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" id="faqDeleteAction" name="action"><input type="hidden" id="faqDeleteId" name="id"><button class="btn" type="button" data-modal-close>Отмена</button><button class="btn" type="submit">Удалить</button></form></div></div>
<script>(function(){
function bindFilter(inputId,tableId,attr){var input=document.getElementById(inputId);if(!input)return;var rows=[].slice.call(document.querySelectorAll('#'+tableId+' tbody tr[data-'+attr+']'));function lower(v){return(v||'').toLocaleLowerCase('ru-RU')}input.addEventListener('input',function(){var q=lower(input.value.trim());rows.forEach(function(row){row.style.display=!q||lower(row.dataset[attr]||'').indexOf(q)!==-1?'':'none';});});}
bindFilter('faqTopicSearch','faqTopicsTable','title');bindFilter('faqSearch','faqItemsTable','search');
var modal=document.getElementById('faqDeleteModal'),action=document.getElementById('faqDeleteAction'),id=document.getElementById('faqDeleteId'),question=document.getElementById('faqDeleteQuestion'),name=document.getElementById('faqDeleteName'),last=null;
function openModal(button,type){last=button;action.value=type==='topic'?'topic_delete':'faq_delete';id.value=button.dataset.id||'';question.textContent=type==='topic'?'Удалить тему ЧАВО?':'Удалить ЧАВО?';name.textContent=button.dataset.title||'';modal.hidden=false;modal.setAttribute('aria-hidden','false');document.body.classList.add('admin-modal-open');var cancel=modal.querySelector('[data-modal-close]');if(cancel)cancel.focus();}
function closeModal(){modal.hidden=true;modal.setAttribute('aria-hidden','true');document.body.classList.remove('admin-modal-open');if(last)last.focus();}
document.querySelectorAll('.js-topic-delete:not(:disabled)').forEach(function(b){b.addEventListener('click',function(){openModal(b,'topic');});});document.querySelectorAll('.js-faq-delete').forEach(function(b){b.addEventListener('click',function(){openModal(b,'faq');});});modal.querySelectorAll('[data-modal-close]').forEach(function(b){b.addEventListener('click',closeModal);});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!modal.hidden)closeModal();});
})();</script>
<?php admin_footer();
