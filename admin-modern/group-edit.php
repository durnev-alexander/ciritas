<?php
require __DIR__.'/includes/layout.php';

$id=(int)($_GET['id']??0);
$row=['Name'=>'','Description'=>'','IsActive'=>1];
$error='';

function group_admin_name_key(string $value): string {
    $value=trim(preg_replace('/\s+/u',' ',$value)??$value);
    if(function_exists('mb_strtolower')) return mb_strtolower($value,'UTF-8');
    return strtr(strtolower($value),[
        'А'=>'а','Б'=>'б','В'=>'в','Г'=>'г','Д'=>'д','Е'=>'е','Ё'=>'ё','Ж'=>'ж','З'=>'з','И'=>'и','Й'=>'й','К'=>'к','Л'=>'л','М'=>'м','Н'=>'н','О'=>'о','П'=>'п','Р'=>'р','С'=>'с','Т'=>'т','У'=>'у','Ф'=>'ф','Х'=>'х','Ц'=>'ц','Ч'=>'ч','Ш'=>'ш','Щ'=>'щ','Ъ'=>'ъ','Ы'=>'ы','Ь'=>'ь','Э'=>'э','Ю'=>'ю','Я'=>'я'
    ]);
}

if($id){
    $q=db()->prepare('SELECT * FROM csSoftGroups WHERE ID=?');
    $q->execute([$id]);
    $row=$q->fetch()?:$row;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();

    $postedRow=[
        'Name'=>(string)($_POST['name']??''),
        'Description'=>(string)($_POST['description']??''),
        'IsActive'=>isset($_POST['active'])?1:0,
    ];
    $row=array_merge($row,$postedRow);

    $name=trim((string)$postedRow['Name']);
    $description=(string)$postedRow['Description'];
    $isActive=(int)$postedRow['IsActive'];

    try{
        if($name==='') throw new RuntimeException('Укажите название группы.');

        $q=db()->prepare('SELECT ID,Name FROM csSoftGroups'.($id?' WHERE ID<>?':''));
        $q->execute($id?[$id]:[]);
        $nameKey=group_admin_name_key(a_legacy($name));
        foreach($q->fetchAll() as $existing){
            if(group_admin_name_key(a_legacy((string)($existing['Name']??'')))===$nameKey){
                throw new RuntimeException($id
                    ? 'Сохранение запрещено: группа с таким названием уже существует.'
                    : 'Добавление запрещено: группа с таким названием уже существует.');
            }
        }

        $data=[$name,$description,$isActive];
        if($id){
            $data[]=$id;
            db()->prepare('UPDATE csSoftGroups SET Name=?,Description=?,IsActive=? WHERE ID=?')->execute($data);
        }else{
            db()->prepare('INSERT INTO csSoftGroups (Name,Description,IsActive) VALUES (?,?,?)')->execute($data);
        }
        redirect('groups.php');
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

admin_header($id?'Редактирование группы':'Новая группа');
?>
<?php if($error) admin_notice($error,'error'); ?>
<div class="card"><form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><div class="form-grid"><div class="field"><label class="label">Название</label><input class="input" name="name" maxlength="127" required<?=$id?'':' autofocus'?> value="<?=aesc(a_legacy($row['Name']))?>"></div><div class="field"><label class="label">Статус</label><label class="checkbox"><input type="checkbox" name="active" <?=$row['IsActive']?'checked':''?>> Показывать как активную</label></div><div class="field full"><label class="label">Описание</label><textarea class="textarea" name="description" rows="5" maxlength="255"><?=aesc(a_legacy($row['Description']??''))?></textarea></div></div><div class="actions" style="margin-top:20px"><button class="btn primary">Сохранить</button><a class="btn" href="groups.php">Отмена</a></div></form></div><?php admin_footer();
