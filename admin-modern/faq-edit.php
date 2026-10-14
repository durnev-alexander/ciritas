<?php
require __DIR__.'/includes/layout.php';

$id=(int)($_GET['id']??$_POST['id']??0);
$error='';
$topics=[];
try{$topics=db()->query('SELECT * FROM faq_category ORDER BY OrderIndex,ID')->fetchAll();}catch(Throwable $e){$error='Не удалось загрузить темы ЧАВО.';}

function faq_edit_db_text(string $value): string {
    $charset=strtolower((string)cfg('db.charset','latin1'));
    if($value===''||str_contains($charset,'utf8')) return $value;
    $converted=@iconv('UTF-8','Windows-1251//TRANSLIT',$value);
    return $converted!==false?$converted:$value;
}
function faq_edit_normalize(int $categoryId): void {
    $q=db()->prepare('SELECT ID FROM faq WHERE CategoryID=? ORDER BY OrderIndex,ID');$q->execute([$categoryId]);$rows=$q->fetchAll();
    $u=db()->prepare('UPDATE faq SET OrderIndex=? WHERE ID=? AND CategoryID=?');$pos=10;foreach($rows as $row){$u->execute([$pos,(int)$row['ID'],$categoryId]);$pos+=10;}
}
function faq_edit_next_order(int $categoryId): int {
    $q=db()->prepare('SELECT MAX(OrderIndex) FROM faq WHERE CategoryID=?');$q->execute([$categoryId]);return ((int)$q->fetchColumn())+10;
}

$row=['CategoryID'=>(int)($topics[0]['ID']??0),'Question'=>'','Answer'=>'','IsActive'=>0,'OrderIndex'=>0];
if($id>0){
    try{$q=db()->prepare('SELECT * FROM faq WHERE ID=?');$q->execute([$id]);$found=$q->fetch();if($found)$row=$found;else$error='ЧАВО не найдено.';}catch(Throwable){$error='Не удалось загрузить ЧАВО.';}
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $categoryId=(int)($_POST['category_id']??0);
    $question=trim((string)($_POST['question']??''));
    $answer=(string)($_POST['answer']??'');
    $active=isset($_POST['active'])?1:0;
    if($categoryId<1){$error='Укажите тему ЧАВО.';}
    elseif($question===''){$error='Укажите вопрос.';}
    else{
        try{
            $q=db()->prepare('SELECT COUNT(*) FROM faq_category WHERE ID=?');$q->execute([$categoryId]);if((int)$q->fetchColumn()<1)throw new RuntimeException('Выбранная тема не найдена.');
            $isNew=$id<1;
            if($isNew){
                $order=faq_edit_next_order($categoryId);
                db()->prepare('INSERT INTO faq (CategoryID,Question,Answer,OrderIndex,IsActive) VALUES (?,?,?,?,?)')->execute([$categoryId,faq_edit_db_text($question),faq_edit_db_text($answer),$order,$active]);
                $id=(int)db()->lastInsertId();
            }else{
                $oldQ=db()->prepare('SELECT CategoryID,OrderIndex FROM faq WHERE ID=?');$oldQ->execute([$id]);$old=$oldQ->fetch();if(!$old)throw new RuntimeException('ЧАВО не найдено.');
                $oldCategory=(int)$old['CategoryID'];$order=(int)$old['OrderIndex'];
                if($oldCategory!==$categoryId)$order=faq_edit_next_order($categoryId);
                db()->prepare('UPDATE faq SET CategoryID=?,Question=?,Answer=?,OrderIndex=?,IsActive=? WHERE ID=?')->execute([$categoryId,faq_edit_db_text($question),faq_edit_db_text($answer),$order,$active,$id]);
                if($oldCategory!==$categoryId){faq_edit_normalize($oldCategory);faq_edit_normalize($categoryId);}
            }
            admin_set_notice($isNew?'ЧАВО добавлено.':'ЧАВО изменено.','success');
            redirect('faq.php');
        }catch(Throwable $e){$error=$e->getMessage();}
    }
    $row=['CategoryID'=>$categoryId,'Question'=>$question,'Answer'=>$answer,'IsActive'=>$active,'OrderIndex'=>$row['OrderIndex']??0];
}

admin_header($id?'Редактирование ЧАВО':'Новое ЧАВО');
if($error)admin_notice($error,'error');
?>
<div class="card">
<form method="post">
<input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>">
<div class="form-grid">
<div class="field full"><label for="faqCategory">Тема</label><select class="select" id="faqCategory" name="category_id" required><?php foreach($topics as $topic): ?><option value="<?=(int)$topic['ID']?>" <?=(int)($row['CategoryID']??0)===(int)$topic['ID']?'selected':''?>><?=aesc(a_legacy((string)($topic['Title']??'')))?></option><?php endforeach; ?></select></div>
<div class="field full faq-question-active-row"><div class="faq-question-field"><label for="faqQuestion">Вопрос</label><input class="input" id="faqQuestion" name="question" required value="<?=aesc(a_legacy((string)($row['Question']??'')))?>"></div><label class="faq-active-field"><input type="checkbox" name="active" <?=!empty($row['IsActive'])?'checked':''?>> Активно</label></div>
<div class="field full"><label for="faqAnswer">Ответ (HTML)</label><textarea class="textarea html-editor" id="faqAnswer" rows="16" name="answer"><?=aesc(a_legacy((string)($row['Answer']??'')))?></textarea></div>
</div>
<div class="actions"><button class="btn primary" type="submit">Сохранить</button><a class="btn" href="faq.php">Отмена</a></div>
</form>
</div>
<style>
.faq-question-active-row{display:flex;align-items:flex-end;gap:18px}
.faq-question-field{display:flex;flex:1;flex-direction:column;gap:6px;min-width:0}
.faq-active-field{display:flex;align-items:center;gap:8px;min-height:38px;margin-bottom:1px;white-space:nowrap}
</style>
<script src="assets/js/html-editor.js"></script>
<?php admin_footer();
