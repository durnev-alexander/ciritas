<?php
require __DIR__.'/includes/layout.php';

$error='';
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
        redirect('groups.php?deleted=1');
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

$rows=db()->query('SELECT g.*,COUNT(p.ID) ProductCount FROM csSoftGroups g LEFT JOIN csSoftProducts p ON p.SoftGroupID=g.ID GROUP BY g.ID ORDER BY g.Name')->fetchAll();
admin_header('Группы');
?>
<?php if(!empty($_GET['deleted'])) admin_notice('Группа удалена.','success'); ?>
<?php if($error) admin_notice($error,'error'); ?>
<div class="card">
    <div class="toolbar">
        <div><b>Служебный справочник</b><div class="help">Группы используются только в админке.</div></div>
        <a class="btn primary" href="group-edit.php">+ Добавить группу</a>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Группа</th><th>Описание</th><th>Продуктов</th><th>Статус</th><th>Действия</th></tr></thead>
            <tbody>
            <?php foreach($rows as $r): $productCount=(int)$r['ProductCount']; $name=a_legacy((string)$r['Name']); ?>
                <tr>
                    <td><b><?=aesc($name)?></b></td>
                    <td><?=aesc(a_legacy($r['Description']??''))?></td>
                    <td><?=$productCount?></td>
                    <td><span class="badge <?=$r['IsActive']?'on':'off'?>"><?=$r['IsActive']?'Активна':'Скрыта'?></span></td>
                    <td>
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
