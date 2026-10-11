<?php
require __DIR__.'/includes/layout.php';

$error='';

function groups_admin_sort_column(string $table): ?string {
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

function groups_admin_normalize_order(string $column): array {
    $safe='`'.str_replace('`','',$column).'`';
    $rows=db()->query('SELECT ID,'.$safe.' SortValue FROM csSoftGroups ORDER BY '.$safe.',ID')->fetchAll();
    $update=db()->prepare('UPDATE csSoftGroups SET '.$safe.'=? WHERE ID=?');
    $pos=10;
    foreach($rows as &$row){
        $row['SortValue']=$pos;
        $update->execute([$pos,(int)$row['ID']]);
        $pos+=10;
    }
    unset($row);
    return $rows;
}

$groupSortColumn=groups_admin_sort_column('csSoftGroups');

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['group_action'])){
    csrf_check();
    try{
        $action=(string)($_POST['group_action']??'');
        $id=(int)($_POST['id']??0);
        if(($action==='up'||$action==='down')&&$id){
            if($groupSortColumn===null) throw new RuntimeException('В таблице групп не найдено поле индекса сортировки.');
            $rows=groups_admin_normalize_order($groupSortColumn);
            $index=null;
            foreach($rows as $i=>$row){if((int)$row['ID']===$id){$index=$i;break;}}
            if($index!==null){
                $other=$action==='up'?$index-1:$index+1;
                if(isset($rows[$other])){
                    $a=$rows[$index];$b=$rows[$other];
                    $safe='`'.str_replace('`','',$groupSortColumn).'`';
                    $q=db()->prepare('UPDATE csSoftGroups SET '.$safe.'=? WHERE ID=?');
                    $q->execute([(int)$b['SortValue'],(int)$a['ID']]);
                    $q->execute([(int)$a['SortValue'],(int)$b['ID']]);
                }
            }
            redirect('groups.php');
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_id'])){
    csrf_check();
    try{
        $id=(int)($_POST['delete_id']??0);
        if($id<1) throw new RuntimeException('Группа не найдена.');

        $q=db()->prepare('SELECT COUNT(*) FROM csSoftProducts WHERE SoftGroupID=?');
        $q->execute([$id]);
        if((int)$q->fetchColumn()>0){
            throw new RuntimeException('Удаление запрещено: в группе есть продукты.');
        }

        $q=db()->prepare('DELETE FROM csSoftGroups WHERE ID=?');
        $q->execute([$id]);
        if($groupSortColumn!==null) groups_admin_normalize_order($groupSortColumn);
        redirect('groups.php?deleted=1');
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

$order=$groupSortColumn!==null?'g.`'.str_replace('`','',$groupSortColumn).'`,g.ID':'g.Name,g.ID';
$rows=db()->query('SELECT g.*,COUNT(p.ID) ProductCount FROM csSoftGroups g LEFT JOIN csSoftProducts p ON p.SoftGroupID=g.ID GROUP BY g.ID ORDER BY '.$order)->fetchAll();
admin_header('Группы');
?>
<?php if(!empty($_GET['deleted'])) admin_notice('Группа удалена.','success'); ?>
<?php if($error) admin_notice($error,'error'); ?>
<div class="card">
    <div class="toolbar">
        <div><h2>Служебный справочник</h2><div class="help">Группы используются только в админке. Порядок групп определяет их сортировку на публичной странице продуктов.</div></div>
        <div class="admin-products-summary">Всего: <?=count($rows)?></div>
    </div>
    <div class="toolbar groups-filter-row">
        <input id="groupSearch" class="input" type="search" placeholder="Поиск по названию группы..." style="flex:1;min-width:0">
        <a class="btn primary" href="group-edit.php">+ Добавить группу</a>
    </div>
    <style>.groups-actions-cell .actions{justify-content:flex-end}</style>
    <div class="table-wrap">
        <table class="table products-table" id="groupsTable">
            <thead><tr><th>Группа</th><th>Описание</th><th>Продуктов</th><th>Статус</th><th>Сортировка</th><th class="products-actions-head">Действия</th></tr></thead>
            <tbody>
            <?php foreach($rows as $i=>$r): $productCount=(int)$r['ProductCount']; $name=a_legacy((string)$r['Name']); ?>
                <tr data-name="<?=aesc($name)?>">
                    <td><b><?=aesc($name)?></b></td>
                    <td><?=aesc(a_legacy($r['Description']??''))?></td>
                    <td><?=$productCount?></td>
                    <td><span class="badge <?=$r['IsActive']?'on':'off'?>"><?=$r['IsActive']?'Активна':'Скрыта'?></span></td>
                    <td>
                        <?php if($groupSortColumn!==null): ?>
                        <form method="post" class="actions">
                            <input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>">
                            <input type="hidden" name="id" value="<?=(int)$r['ID']?>">
                            <button class="btn sort-btn" name="group_action" value="up" <?=$i===0?'disabled':''?> title="Выше">↑</button>
                            <button class="btn sort-btn" name="group_action" value="down" <?=$i===count($rows)-1?'disabled':''?> title="Ниже">↓</button>
                        </form>
                        <?php else: ?><span class="muted">Нет поля сортировки</span><?php endif; ?>
                    </td>
                    <td class="products-actions-cell groups-actions-cell">
                        <div class="actions">
                            <a class="btn" href="group-edit.php?id=<?=(int)$r['ID']?>">Изменить</a>
                            <?php if($productCount>0): ?>
                                <button class="btn" type="button" disabled title="Удаление недоступно: в группе есть продукты">Удалить</button>
                            <?php else: ?>
                                <button class="btn js-group-delete" type="button" data-group-id="<?=(int)$r['ID']?>" data-group-name="<?=aesc($name)?>">Удалить</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="groupDeleteModal" class="admin-modal" hidden aria-hidden="true">
    <div class="admin-modal-backdrop" data-modal-close></div>
    <div class="admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="groupDeleteTitle">
        <h2 id="groupDeleteTitle">Подтверждение удаления</h2>
        <p>Удалить группу? Это действие нельзя отменить.<span id="groupDeleteName" class="admin-modal-product"></span></p>
        <form method="post" class="admin-modal-actions">
            <input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>">
            <input type="hidden" id="groupDeleteId" name="delete_id" value="">
            <button class="btn" type="button" data-modal-close>Отмена</button>
            <button class="btn" type="submit">Удалить</button>
        </form>
    </div>
</div>

<script>(function(){
var search=document.getElementById('groupSearch');
var rows=[].slice.call(document.querySelectorAll('#groupsTable tbody tr'));
function lower(v){return (v||'').toLocaleLowerCase('ru-RU')}
function filter(){var q=lower((search.value||'').trim());rows.forEach(function(row){var ok=!q||lower(row.dataset.name||'').indexOf(q)!==-1;row.style.display=ok?'':'none';});}
if(search)search.addEventListener('input',filter);

var modal=document.getElementById('groupDeleteModal');
var idInput=document.getElementById('groupDeleteId');
var nameBox=document.getElementById('groupDeleteName');
var lastTrigger=null;
function openModal(button){
    lastTrigger=button;
    idInput.value=button.dataset.groupId||'';
    nameBox.textContent=button.dataset.groupName||'';
    modal.hidden=false;
    modal.setAttribute('aria-hidden','false');
    document.body.classList.add('admin-modal-open');
    var cancel=modal.querySelector('[data-modal-close]');
    if(cancel) cancel.focus();
}
function closeModal(){
    modal.hidden=true;
    modal.setAttribute('aria-hidden','true');
    document.body.classList.remove('admin-modal-open');
    if(lastTrigger) lastTrigger.focus();
}
document.querySelectorAll('.js-group-delete').forEach(function(button){
    button.addEventListener('click',function(){openModal(button);});
});
modal.querySelectorAll('[data-modal-close]').forEach(function(button){button.addEventListener('click',closeModal);});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!modal.hidden) closeModal();});
})();</script>
<?php admin_footer();
