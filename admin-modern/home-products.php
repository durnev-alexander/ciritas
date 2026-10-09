<?php
require __DIR__.'/includes/layout.php';
require dirname(__DIR__).'/app/home_products.php';

home_products_ensure_schema();
$error='';

function home_admin_normalize_order(): void {
    $rows=db()->query('SELECT ID FROM csHomeProducts ORDER BY SortOrder, ID')->fetchAll();
    $q=db()->prepare('UPDATE csHomeProducts SET SortOrder=? WHERE ID=?');
    $pos=10;
    foreach($rows as $row){$q->execute([$pos,(int)$row['ID']]);$pos+=10;}
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    try{
        $action=(string)($_POST['action']??'');
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
            home_admin_normalize_order();
        }elseif(($action==='up'||$action==='down')&&$id){
            $rows=db()->query('SELECT ID,SortOrder FROM csHomeProducts ORDER BY SortOrder,ID')->fetchAll();
            $index=null;
            foreach($rows as $i=>$row){if((int)$row['ID']===$id){$index=$i;break;}}
            if($index!==null){
                $other=$action==='up'?$index-1:$index+1;
                if(isset($rows[$other])){
                    $a=$rows[$index];$b=$rows[$other];
                    $q=db()->prepare('UPDATE csHomeProducts SET SortOrder=? WHERE ID=?');
                    $q->execute([(int)$b['SortOrder'],(int)$a['ID']]);
                    $q->execute([(int)$a['SortOrder'],(int)$b['ID']]);
                    home_admin_normalize_order();
                }
            }
        }
        redirect('home-products.php');
    }catch(Throwable $e){$error=$e->getMessage();}
}

$current=db()->query('SELECT h.ID,h.SoftProductID,h.SortOrder,p.Name,p.IsActive FROM csHomeProducts h JOIN csSoftProducts p ON p.ID=h.SoftProductID ORDER BY h.SortOrder,h.ID')->fetchAll();
$available=db()->query('SELECT p.ID,p.Name,p.IsActive FROM csSoftProducts p LEFT JOIN csHomeProducts h ON h.SoftProductID=p.ID WHERE h.ID IS NULL ORDER BY p.Name')->fetchAll();

admin_header('Продукты на главной');
?>
<?php if($error): ?><div class="flash"><?=aesc($error)?></div><?php endif; ?>
<div class="card">
<div class="section-title"><div><h2>Состав и порядок</h2><div class="help">На главной странице показываются только программы из этого списка. Порядок меняется кнопками вверх/вниз.</div></div></div>
<?php if($current): ?><div class="home-product-list">
<?php foreach($current as $i=>$row): ?><div class="home-product-row"><div class="home-product-position"><?=($i+1)?></div><div class="home-product-name"><strong><?=aesc(a_legacy($row['Name']))?></strong><?php if(!(int)$row['IsActive']): ?><span class="badge off">Неактивен</span><?php endif; ?></div><div class="actions"><form method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$row['ID']?>"><button class="btn" name="action" value="up" <?=$i===0?'disabled':''?> title="Выше">↑</button><button class="btn" name="action" value="down" <?=$i===count($current)-1?'disabled':''?> title="Ниже">↓</button><button class="btn danger" name="action" value="remove">Убрать</button></form></div></div><?php endforeach; ?>
</div><?php else: ?><p class="muted">Список пуст.</p><?php endif; ?>
</div>
<div class="card" style="margin-top:18px"><h2>Добавить программу</h2><?php if($available): ?><form method="post" class="toolbar" style="justify-content:flex-start"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="action" value="add"><select class="select" name="product_id" required style="max-width:520px"><?php foreach($available as $row): ?><option value="<?=(int)$row['ID']?>"><?=aesc(a_legacy($row['Name']))?><?=!(int)$row['IsActive']?' — неактивна':''?></option><?php endforeach; ?></select><button class="btn primary">Добавить</button></form><?php else: ?><p class="muted">Все программы уже добавлены.</p><?php endif; ?></div>
<?php admin_footer();
