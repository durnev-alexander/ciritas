<?php
require __DIR__.'/includes/layout.php';
require dirname(__DIR__).'/app/home_products.php';

home_products_ensure_schema();
$error='';

function products_admin_normalize_home_order(): void {
    $rows=db()->query('SELECT ID FROM csHomeProducts ORDER BY SortOrder, ID')->fetchAll();
    $q=db()->prepare('UPDATE csHomeProducts SET SortOrder=? WHERE ID=?');
    $pos=10;
    foreach($rows as $row){$q->execute([$pos,(int)$row['ID']]);$pos+=10;}
}

function products_admin_related_count(string $table,int $productId): int {
    $allowed=['csSoftDownloads','csSoftPrices','csSoftShots','csHomeProducts','csOrders'];
    if(!in_array($table,$allowed,true)) return 0;
    try{
        $column=$table==='csHomeProducts'?'SoftProductID':'SoftProductID';
        if($table==='csOrders') $column='ProductID';
        $q=db()->prepare('SELECT COUNT(*) FROM `'.$table.'` WHERE `'.$column.'`=?');
        $q->execute([$productId]);
        return (int)$q->fetchColumn();
    }catch(Throwable){return 0;}
}

function products_admin_delete_blocked(array $row): bool {
    foreach(['ShortDescr','About','Features','PriceDescr'] as $field){
        if(array_key_exists($field,$row) && trim(a_legacy((string)$row[$field]))!=='') return true;
    }
    $id=(int)($row['ID']??0);
    if($id<1) return true;
    foreach(['csSoftDownloads','csSoftPrices','csSoftShots','csHomeProducts','csOrders'] as $table){
        if(products_admin_related_count($table,$id)>0) return true;
    }
    return false;
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['product_action'])){
    csrf_check();
    try{
        $action=(string)($_POST['product_action']??'');
        $id=(int)($_POST['id']??0);
        if($action==='delete'&&$id){
            $q=db()->prepare('SELECT * FROM csSoftProducts WHERE ID=?');
            $q->execute([$id]);
            $product=$q->fetch();
            if(!$product) throw new RuntimeException('Продукт не найден.');
            if(products_admin_delete_blocked($product)) throw new RuntimeException('Удаление запрещено: у продукта есть описание, ресурсы или связанные данные.');
            db()->prepare('DELETE FROM csSoftProducts WHERE ID=?')->execute([$id]);
            redirect('products.php?deleted=1');
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['home_action'])){
    csrf_check();
    try{
        $action=(string)($_POST['home_action']??'');
        $id=(int)($_POST['id']??0);
        if($action==='add'){
            $productId=(int)($_POST['product_id']??0);
            $check=db()->prepare('SELECT ID FROM csSoftProducts WHERE ID=?');
            $check->execute([$productId]);
            if(!$check->fetch()) throw new RuntimeException('Выбранная программа не найдена.');
            $max=(int)db()->query('SELECT COALESCE(MAX(SortOrder),0) FROM csHomeProducts')->fetchColumn();
            db()->prepare('INSERT IGNORE INTO csHomeProducts (SoftProductID,SortOrder) VALUES (?,?)')->execute([$productId,$max+10]);
        }elseif($action==='remove'&&$id){
            db()->prepare('DELETE FROM csHomeProducts WHERE ID=?')->execute([$id]);
            products_admin_normalize_home_order();
        }elseif(($action==='up'||$action==='down')&&$id){
            $homeRows=db()->query('SELECT ID,SortOrder FROM csHomeProducts ORDER BY SortOrder,ID')->fetchAll();
            $index=null;
            foreach($homeRows as $i=>$row){if((int)$row['ID']===$id){$index=$i;break;}}
            if($index!==null){
                $other=$action==='up'?$index-1:$index+1;
                if(isset($homeRows[$other])){
                    $a=$homeRows[$index];$b=$homeRows[$other];
                    $q=db()->prepare('UPDATE csHomeProducts SET SortOrder=? WHERE ID=?');
                    $q->execute([(int)$b['SortOrder'],(int)$a['ID']]);
                    $q->execute([(int)$a['SortOrder'],(int)$b['ID']]);
                    products_admin_normalize_home_order();
                }
            }
        }
        redirect('products.php#home-products');
    }catch(Throwable $e){$error=$e->getMessage();}
}

$order=column_exists('csSoftProducts','OrderIndex')?'p.OrderIndex,p.ID':'p.ID DESC';
$rows=db()->query('SELECT p.*,g.Name GroupName FROM csSoftProducts p LEFT JOIN csSoftGroups g ON g.ID=p.SoftGroupID ORDER BY '.$order)->fetchAll();
$current=db()->query('SELECT h.ID,h.SoftProductID,h.SortOrder,p.Name,p.IsActive FROM csHomeProducts h JOIN csSoftProducts p ON p.ID=h.SoftProductID ORDER BY h.SortOrder,h.ID')->fetchAll();
$available=db()->query('SELECT p.ID,p.Name,p.IsActive FROM csSoftProducts p LEFT JOIN csHomeProducts h ON h.SoftProductID=p.ID WHERE h.ID IS NULL ORDER BY p.Name')->fetchAll();
$groups=[];
foreach($rows as $r){$g=trim(a_legacy((string)($r['GroupName']??'')));if($g!==''&&!in_array($g,$groups,true))$groups[]=$g;}
sort($groups,SORT_NATURAL|SORT_FLAG_CASE);

admin_header('Продукты');
?>
<?php if(!empty($_GET['deleted'])) admin_notice('Продукт удалён.','success'); ?>
<?php if($error) admin_notice($error,'error'); ?>
<div class="card">
<div class="toolbar">
    <div><h2>Список программ</h2><div class="help">Все программы из базы данных.</div></div>
    <div class="admin-products-summary">Всего: <?=count($rows)?></div>
</div>
<div class="toolbar products-filter-row">
    <select id="productGroupFilter" class="select products-group-filter"><option value="">Все группы</option><?php foreach($groups as $g): ?><option value="<?=aesc($g)?>"><?=aesc($g)?></option><?php endforeach; ?></select>
    <input id="productSearch" class="input products-search" type="search" placeholder="Поиск по названию...">
    <a class="btn primary products-add-button" href="product-edit.php">+ Добавить продукт</a>
</div>
<div class="table-wrap"><table class="table" id="productsTable"><thead><tr><?php if(column_exists('csSoftProducts','OrderIndex')): ?><th>Порядок</th><?php endif; ?><th>Продукт</th><th>Группа</th><th>Статус</th><?php if(column_exists('csSoftProducts','OrderAllow')): ?><th>Заказ</th><?php endif; ?><th>Действия</th></tr></thead><tbody>
<?php foreach($rows as $r): $name=a_legacy((string)$r['Name']);$group=a_legacy((string)($r['GroupName']??''));$deleteBlocked=products_admin_delete_blocked($r); ?><tr data-name="<?=aesc($name)?>" data-group="<?=aesc($group)?>"><?php if(array_key_exists('OrderIndex',$r)): ?><td><?=(int)$r['OrderIndex']?></td><?php endif; ?><td><b><?=aesc($name)?></b><div class="help"><?=aesc(a_legacy($r['ShortDescr']??''))?></div></td><td><?=aesc($group)?></td><td><span class="badge <?=$r['IsActive']?'on':'off'?>"><?=$r['IsActive']?'Активен':'Скрыт'?></span></td><?php if(array_key_exists('OrderAllow',$r)): ?><td><span class="badge <?=$r['OrderAllow']?'on':'off'?>"><?=$r['OrderAllow']?'Разрешён':'Запрещён'?></span></td><?php endif; ?><td><div class="actions"><a class="btn" href="product-edit.php?id=<?=$r['ID']?>">Изменить</a><a class="btn" href="product-assets.php?id=<?=$r['ID']?>">Ресурсы</a><?php if($deleteBlocked): ?><button class="btn" type="button" disabled title="Удаление недоступно: у продукта есть данные или ресурсы">Удалить</button><?php else: ?><button class="btn js-product-delete" type="button" data-product-id="<?=(int)$r['ID']?>" data-product-name="<?=aesc($name)?>">Удалить</button><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div>
</div>

<div id="home-products" class="card products-tools-card">
<div class="section-title"><div><h2>Продукты на главной</h2><div class="help">Состав и порядок программ, которые показываются на главной странице публичного сайта.</div></div></div>
<?php if($current): ?><div class="home-product-list">
<?php foreach($current as $i=>$row): ?><div class="home-product-row"><div class="home-product-position"><?=($i+1)?></div><div class="home-product-name"><strong><?=aesc(a_legacy($row['Name']))?></strong><?php if(!(int)$row['IsActive']): ?><span class="badge off">Неактивен</span><?php endif; ?></div><div class="actions"><form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$row['ID']?>"><button class="btn sort-btn" name="home_action" value="up" <?=$i===0?'disabled':''?> title="Выше">↑</button><button class="btn sort-btn" name="home_action" value="down" <?=$i===count($current)-1?'disabled':''?> title="Ниже">↓</button><button class="btn danger" name="home_action" value="remove">Убрать</button></form></div></div><?php endforeach; ?>
</div><?php else: ?><p class="muted">Список пуст.</p><?php endif; ?>
<div class="products-add-home"><h3>Добавить программу на главную</h3><?php if($available): ?><form method="post" class="toolbar products-add-home-form"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><select class="select" name="product_id" required><?php foreach($available as $row): ?><option value="<?=(int)$row['ID']?>"><?=aesc(a_legacy($row['Name']))?><?=!(int)$row['IsActive']?' — неактивна':''?></option><?php endforeach; ?></select><button class="btn primary" name="home_action" value="add">Добавить</button></form><?php else: ?><p class="muted">Все программы уже добавлены.</p><?php endif; ?></div>
</div>

<div id="productDeleteModal" class="admin-modal" hidden aria-hidden="true">
    <div class="admin-modal-backdrop" data-modal-close></div>
    <div class="admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="productDeleteTitle">
        <h2 id="productDeleteTitle">Подтверждение удаления</h2>
        <p>Удалить продукт? Это действие нельзя отменить.<span id="productDeleteName" class="admin-modal-product"></span></p>
        <form method="post" class="admin-modal-actions">
            <input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>">
            <input type="hidden" name="product_action" value="delete">
            <input type="hidden" id="productDeleteId" name="id" value="">
            <button class="btn" type="button" data-modal-close>Отмена</button>
            <button class="btn" type="submit">Удалить</button>
        </form>
    </div>
</div>
<script>(function(){
var search=document.getElementById('productSearch');var group=document.getElementById('productGroupFilter');var rows=[].slice.call(document.querySelectorAll('#productsTable tbody tr'));
function lower(v){return (v||'').toLocaleLowerCase('ru-RU')}
function filter(){var q=lower((search.value||'').trim());var g=lower(group.value||'');rows.forEach(function(row){var okName=!q||lower(row.dataset.name||'').indexOf(q)!==-1;var okGroup=!g||lower(row.dataset.group||'')===g;row.style.display=okName&&okGroup?'':'none';});}
search.addEventListener('input',filter);group.addEventListener('change',filter);
var modal=document.getElementById('productDeleteModal');var idInput=document.getElementById('productDeleteId');var nameBox=document.getElementById('productDeleteName');var lastTrigger=null;
function openModal(button){lastTrigger=button;idInput.value=button.dataset.productId||'';nameBox.textContent=button.dataset.productName||'';modal.hidden=false;modal.setAttribute('aria-hidden','false');document.body.classList.add('admin-modal-open');var cancel=modal.querySelector('[data-modal-close]');if(cancel)cancel.focus();}
function closeModal(){modal.hidden=true;modal.setAttribute('aria-hidden','true');document.body.classList.remove('admin-modal-open');if(lastTrigger)lastTrigger.focus();}
document.querySelectorAll('.js-product-delete').forEach(function(button){button.addEventListener('click',function(){openModal(button);});});
modal.querySelectorAll('[data-modal-close]').forEach(function(button){button.addEventListener('click',closeModal);});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!modal.hidden)closeModal();});
})();</script>
<?php admin_footer();
