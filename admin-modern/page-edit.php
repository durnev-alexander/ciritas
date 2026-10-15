<?php
require __DIR__.'/includes/layout.php';

function page_edit_db_text(string $value): string {
    $charset=strtolower((string)cfg('db.charset','latin1'));
    if($value==='' || str_contains($charset,'utf8')) return $value;
    $converted=@iconv('UTF-8','Windows-1251//TRANSLIT',$value);
    return $converted!==false?$converted:$value;
}

$id=(int)($_GET['id']??0);
$q=db()->prepare('SELECT * FROM csPages WHERE ID=?');
$q->execute([$id]);
$r=$q->fetch();
if(!$r){http_response_code(404);exit('Page not found');}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $content=(string)($_POST['content']??'');
    db()->prepare('UPDATE csPages SET Content=? WHERE ID=?')->execute([page_edit_db_text($content),$id]);
    admin_set_notice('Страница сохранена.','success');
    redirect('page-edit.php?id='.$id);
}

admin_header('Страница: '.a_legacy((string)$r['PageName']));
?>
<div class="card">
    <form method="post">
        <input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>">
        <div class="field"><label class="label">Название</label><input class="input" value="<?=aesc(a_legacy((string)$r['PageName']))?>" disabled></div>
        <div style="height:14px"></div>
        <div class="field"><label class="label">Содержимое (HTML)</label><textarea class="textarea html-editor" name="content" rows="18"><?=aesc(a_legacy((string)$r['Content']))?></textarea></div>
        <div class="actions" style="margin-top:20px"><button class="btn primary">Сохранить</button><a class="btn" href="pages.php">Отмена</a></div>
    </form>
</div>
<script src="assets/js/html-editor.js"></script>
<?php admin_footer();
