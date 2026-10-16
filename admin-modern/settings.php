<?php
require __DIR__.'/includes/layout.php';

$footerSettings=[
    'FooterCopyright'=>['label'=>'Копирайт','default'=>'© 2003-2026, ЦИРИТАС'],
    'FooterEmail'=>['label'=>'EMail','default'=>''],
    'FooterPhone'=>['label'=>'Телефон','default'=>''],
    'FooterPostalAddress'=>['label'=>'Почтовый адрес','default'=>''],
];

$rows=[];
$footerValues=[];
foreach($footerSettings as $key=>$meta){
    $footerValues[$key]=(string)$meta['default'];
}

try{
    $all=db()->query('SELECT * FROM csSettings ORDER BY ID')->fetchAll();
    $seen=[];
    foreach($all as $row){
        $rawName=(string)($row['Name']??'');
        if(isset($footerSettings[$rawName])){
            $footerValues[$rawName]=a_legacy((string)($row['Value']??''));
            continue;
        }
        $key=a_legacy($rawName);
        if($key==='' || isset($seen[$key])) continue;
        $seen[$key]=true;
        $rows[]=$row;
    }
}catch(Throwable){}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $form=(string)($_POST['form']??'settings');

    if($form==='footer'){
        foreach($footerSettings as $key=>$meta){
            $value=(string)(($_POST['footer']??[])[$key]??'');
            $dbValue=admin_db_text($value);
            $q=db()->prepare('SELECT ID FROM csSettings WHERE Name=? ORDER BY ID LIMIT 1');
            $q->execute([$key]);
            $id=(int)($q->fetchColumn()?:0);
            if($id>0){
                db()->prepare('UPDATE csSettings SET Value=? WHERE ID=?')->execute([$dbValue,$id]);
            }else{
                db()->prepare('INSERT INTO csSettings (Name, Value) VALUES (?, ?)')->execute([$key,$dbValue]);
            }
        }
        admin_set_notice('Настройки футера сохранены.','success');
    }else{
        foreach((array)($_POST['value']??[]) as $id=>$value){
            db()->prepare('UPDATE csSettings SET Value=? WHERE ID=?')->execute([$value,(int)$id]);
        }
        admin_set_notice('Настройки сохранены.','success');
    }
    redirect('settings.php');
}

admin_header('Настройки');
?>
<div class="card">
    <form method="post">
        <input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>">
        <input type="hidden" name="form" value="settings">
        <h2 style="margin:0 0 20px">Общие настройки</h2>
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

<div class="card" style="margin-top:20px">
    <form method="post">
        <input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>">
        <input type="hidden" name="form" value="footer">
        <h2 style="margin:0 0 20px">Настройки футера</h2>
        <div class="grid" style="gap:14px">
            <?php foreach($footerSettings as $key=>$meta): ?>
                <div class="field">
                    <label class="label"><?=aesc((string)$meta['label'])?></label>
                    <input class="input" name="footer[<?=aesc($key)?>]" value="<?=aesc($footerValues[$key]??'')?>">
                </div>
            <?php endforeach; ?>
        </div>
        <div class="actions" style="margin-top:20px"><button class="btn primary">Сохранить</button></div>
    </form>
</div>
<?php admin_footer();
