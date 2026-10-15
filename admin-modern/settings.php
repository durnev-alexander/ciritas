<?php
require __DIR__.'/includes/layout.php';

$rows=[];
try{
    $all=db()->query('SELECT * FROM csSettings ORDER BY ID')->fetchAll();
    $seen=[];
    foreach($all as $row){
        $key=a_legacy((string)($row['Name']??''));
        if($key==='' || isset($seen[$key])) continue;
        $seen[$key]=true;
        $rows[]=$row;
    }
}catch(Throwable){}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    foreach((array)($_POST['value']??[]) as $id=>$value){
        db()->prepare('UPDATE csSettings SET Value=? WHERE ID=?')->execute([$value,(int)$id]);
    }
    admin_set_notice('Настройки сохранены.','success');
    redirect('settings.php');
}

admin_header('Настройки');
?>
<div class="card">
    <form method="post">
        <input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>">
        <div class="grid" style="gap:14px">
            <?php foreach($rows as $r): ?>
                <div class="field">
                    <label class="label"><?=aesc(a_legacy((string)$r['Name']))?></label>
                    <input class="input" name="value[<?=(int)$r['ID']?>]" value="<?=aesc(a_legacy((string)$r['Value']))?>">
                </div>
            <?php endforeach; ?>
        </div>
        <div class="actions" style="margin-top:20px"><button class="btn primary">Сохранить</button></div>
    </form>
</div>
<?php admin_footer();
