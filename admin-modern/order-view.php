<?php
require __DIR__.'/includes/layout.php';
require __DIR__.'/../app/order_compat.php';

$id=(int)($_GET['id']??0);
$row=null;
$statusColumn=order_first_column(['Status','OrderStatus']);
$processedColumn=order_first_column(['IsProcessed','Processed','IsComplete']);
$adminCommentColumn=order_first_column(['AdminComment','ManagerComment','CommentAdmin']);

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $updates=[];
    $params=[];
    if($statusColumn){$updates[]='`'.$statusColumn.'`=?';$params[]=order_db_text(trim((string)($_POST['status']??'')));}
    if($processedColumn){$updates[]='`'.$processedColumn.'`=?';$params[]=isset($_POST['processed'])?1:0;}
    if($adminCommentColumn){$updates[]='`'.$adminCommentColumn.'`=?';$params[]=order_db_text(trim((string)($_POST['admin_comment']??'')));}
    if($updates){
        $params[]=$id;
        db()->prepare('UPDATE csOrders SET '.implode(',',$updates).' WHERE ID=?')->execute($params);
    }
    redirect('order-view.php?id='.$id);
}

try{$q=db()->prepare('SELECT * FROM csOrders WHERE ID=?');$q->execute([$id]);$row=$q->fetch();}catch(Throwable){}
admin_header('Заказ #'.$id);
?>
<div class="card">
<?php if(!$row): ?>
<p>Заказ не найден.</p>
<?php else: ?>
<?php if($statusColumn || $processedColumn || $adminCommentColumn): ?>
<form method="post" style="margin-bottom:24px">
<input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>">
<div class="form-grid">
<?php if($statusColumn): ?><div class="field"><label>Статус</label><input class="input" name="status" value="<?=aesc(a_legacy((string)($row[$statusColumn]??'')))?>"></div><?php endif; ?>
<?php if($processedColumn): ?><div class="field"><label>Обработка</label><label><input type="checkbox" name="processed" <?=!empty($row[$processedColumn])?'checked':''?>> Заказ обработан</label></div><?php endif; ?>
<?php if($adminCommentColumn): ?><div class="field full"><label>Комментарий менеджера</label><textarea class="textarea" rows="4" name="admin_comment"><?=aesc(a_legacy((string)($row[$adminCommentColumn]??'')))?></textarea></div><?php endif; ?>
</div>
<div class="actions" style="margin-top:16px"><button class="btn primary" type="submit">Сохранить состояние</button></div>
</form>
<?php endif; ?>

<div class="table-wrap"><table class="table"><tbody>
<?php foreach($row as $k=>$v): ?><tr><th><?=aesc((string)$k)?></th><td><?=nl2br(aesc(a_legacy((string)$v)))?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<div class="actions" style="margin-top:16px"><a class="btn" href="orders.php">← К списку</a></div>
</div>
<?php admin_footer();
