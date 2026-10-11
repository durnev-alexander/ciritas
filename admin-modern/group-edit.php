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

function group_admin_sort_column(string $table): ?string {
    try{
        $cols=db()->query('SHOW COLUMNS FROM `'.str_replace('`','',$table).'`')->fetchAll();
        $names=[];
        foreach($cols as $col){$name=(string)($col['Field']??'');if($name!=='')$names[]=$name;}
        foreach(['OrderIndex','SortOrder','SortIndex','Position','OrderNum','OrderID','Sort','Order'] as $candidate){
            foreach($names as $name){if(strcasecmp($name,$candidate)===0)return $name;}
        }
        foreach($names as $name){
            if(in_array(strtolower($name),['id','softgroupid','softproductid'],true))continue;
            if(preg_match('/(order|sort|position|index)/i',$name))return $name;
        }
    }catch(Throwable){}
    return null;
}

function group_admin_normalize_products(int $groupId,string $column): array {
    $safe='`'.str_replace('`','',$column).'`';
    $q=db()->prepare('SELECT ID,'.$safe.' SortValue FROM csSoftProducts WHERE SoftGroupID=? ORDER BY '.$safe.',ID');
    $q->execute([$groupId]);
    $rows=$q->fetchAll();
    $update=db()->prepare('UPDATE csSoftProducts SET '.$safe.'=? WHERE ID=? AND SoftGroupID=?');
    $pos=10;
    foreach($rows as &$item){
        $item['SortValue']=$pos;
        $update->execute([$pos,(int)$item['ID'],$groupId]);
        $pos+=10;
    }
    unset($item);
    return $rows;
}

$groupSortColumn=group_admin_sort_column('csSoftGroups');
$productSortColumn=group_admin_sort_column('csSoftProducts');

if($id){
    $q=db()->prepare('SELECT * FROM csSoftGroups WHERE ID=?');
    $q->execute([$id]);
    $row=$q->fetch()?:$row;
}

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='product_sort'){
    csrf_check();
    try{
        if($id<1) throw new RuntimeException('Сначала сохраните группу.');
        if($productSortColumn===null) throw new RuntimeException('В таблице продуктов не найдено поле индекса сортировки.');
        $productId=(int)($_POST['product_id']??0);
        $direction=(string)($_POST['direction']??'');
        if($productId<1 || !in_array($direction,['up','down'],true)) throw new RuntimeException('Некорректная команда сортировки.');

        $items=group_admin_normalize_products($id,$productSortColumn);
        $index=null;
        foreach($items as $i=>$item){if((int)$item['ID']===$productId){$index=$i;break;}}
        if($index!==null){
            $other=$direction==='up'?$index-1:$index+1;
            if(isset($items[$other])){
                $a=$items[$index];$b=$items[$other];
                $safe='`'.str_replace('`','',$productSortColumn).'`';
                $q=db()->prepare('UPDATE csSoftProducts SET '.$safe.'=? WHERE ID=? AND SoftGroupID=?');
                $q->execute([(int)$b['SortValue'],(int)$a['ID'],$id]);
                $q->execute([(int)$a['SortValue'],(int)$b['ID'],$id]);
            }
        }
        redirect('group-edit.php?id='.$id.'#group-products');
    }catch(Throwable $e){$error=$e->getMessage();}
}

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')!=='product_sort'){
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

        $isNew=$id<1;
        if($id){
            db()->prepare('UPDATE csSoftGroups SET Name=?,Description=?,IsActive=? WHERE ID=?')->execute([$name,$description,$isActive,$id]);
        }else{
            if($groupSortColumn!==null){
                $safe='`'.str_replace('`','',$groupSortColumn).'`';
                $max=(int)db()->query('SELECT COALESCE(MAX('.$safe.'),0) FROM csSoftGroups')->fetchColumn();
                db()->prepare('INSERT INTO csSoftGroups (Name,Description,IsActive,'.$safe.') VALUES (?,?,?,?)')->execute([$name,$description,$isActive,$max+10]);
            }else{
                db()->prepare('INSERT INTO csSoftGroups (Name,Description,IsActive) VALUES (?,?,?)')->execute([$name,$description,$isActive]);
            }
        }
        redirect('groups.php'.($isNew?'?added=1':''));
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

$groupProducts=[];
if($id){
    try{
        $order=$productSortColumn!==null?'`'.str_replace('`','',$productSortColumn).'`,ID':'Name,ID';
        $q=db()->prepare('SELECT ID,Name,IsActive'.($productSortColumn!==null?',`'.str_replace('`','',$productSortColumn).'` SortValue':'').' FROM csSoftProducts WHERE SoftGroupID=? ORDER BY '.$order);
        $q->execute([$id]);
        $groupProducts=$q->fetchAll();
    }catch(Throwable $e){
        if($error==='')$error='Не удалось загрузить список программ группы.';
    }
}

admin_header($id?'Редактирование группы':'Новая группа');
?>
<?php if($error) admin_notice($error,'error'); ?>
<div class="card"><form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><div class="form-grid"><div class="field"><label class="label">Название</label><input class="input" name="name" maxlength="127" required<?=$id?'':' autofocus'?> value="<?=aesc(a_legacy($row['Name']))?>"></div><div class="field"><label class="label">Статус</label><label class="checkbox"><input type="checkbox" name="active" <?=$row['IsActive']?'checked':''?>> Показывать как активную</label></div><div class="field full"><label class="label">Описание</label><textarea class="textarea" name="description" rows="5" maxlength="255"><?=aesc(a_legacy($row['Description']??''))?></textarea></div></div><div class="actions" style="margin-top:20px"><button class="btn primary">Сохранить</button><a class="btn" href="groups.php">Отмена</a></div></form></div>

<?php if($id): ?>
<div id="group-products" class="card" style="margin-top:18px">
    <div class="section-title" style="margin-bottom:16px"><div><h2>Программы группы</h2><div class="help">Программы показаны в порядке их индекса сортировки. Стрелки меняют этот индекс внутри текущей группы.</div></div><div class="admin-products-summary">Всего: <?=count($groupProducts)?></div></div>
    <?php if($groupProducts): ?>
    <style>
        .group-products-table{width:100%;table-layout:auto}
        .group-products-table th{text-align:left}
        .group-products-table .group-product-name{width:100%}
        .group-products-table .group-product-status,
        .group-products-table .group-product-sort{width:1%;white-space:nowrap}
        .group-products-table .group-product-sort .actions{flex-wrap:nowrap}
    </style>
    <div class="table-wrap"><table class="table group-products-table"><thead><tr><th class="group-product-name">Программа</th><th class="group-product-status">Статус</th><th class="group-product-sort">Сортировка</th></tr></thead><tbody>
    <?php foreach($groupProducts as $i=>$product): ?>
        <tr>
            <td class="group-product-name"><b><?=aesc(a_legacy((string)$product['Name']))?></b></td>
            <td class="group-product-status"><span class="badge <?=$product['IsActive']?'on':'off'?>"><?=$product['IsActive']?'Активна':'Скрыта'?></span></td>
            <td class="group-product-sort">
                <?php if($productSortColumn!==null): ?>
                <form method="post" class="actions">
                    <input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>">
                    <input type="hidden" name="action" value="product_sort">
                    <input type="hidden" name="product_id" value="<?=(int)$product['ID']?>">
                    <button class="btn sort-btn" name="direction" value="up" <?=$i===0?'disabled':''?> title="Выше">↑</button>
                    <button class="btn sort-btn" name="direction" value="down" <?=$i===count($groupProducts)-1?'disabled':''?> title="Ниже">↓</button>
                </form>
                <?php else: ?><span class="muted">Нет поля сортировки</span><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php else: ?><p class="muted">В этой группе пока нет программ.</p><?php endif; ?>
</div>
<?php endif; ?>
<?php admin_footer();
