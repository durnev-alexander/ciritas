<?php
require __DIR__.'/includes/layout.php';
require __DIR__.'/../app/order_compat.php';
require __DIR__.'/../app/cart.php';

$id=(int)($_GET['id']??0);$row=null;$cartRow=null;$items=[];$extra=null;
$statusColumn=order_first_column(['Status','OrderStatus']);
$processedColumn=order_first_column(['IsProcessed','Processed','IsComplete']);
$adminCommentColumn=order_first_column(['AdminComment','ManagerComment','CommentAdmin']);

if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();$updates=[];$params=[];
 if($statusColumn){$updates[]='`'.$statusColumn.'`=?';$params[]=order_db_text(trim((string)($_POST['status']??'')));}
 if($processedColumn){$updates[]='`'.$processedColumn.'`=?';$params[]=isset($_POST['processed'])?1:0;}
 if($adminCommentColumn){$updates[]='`'.$adminCommentColumn.'`=?';$params[]=order_db_text(trim((string)($_POST['admin_comment']??'')));}
 if($updates){$params[]=$id;db()->prepare('UPDATE csOrders SET '.implode(',',$updates).' WHERE ID=?')->execute($params);}redirect('order-view.php?id='.$id);
}

try{
 $q=db()->prepare('SELECT * FROM csOrders WHERE ID=?');$q->execute([$id]);$row=$q->fetch();
 if($row){$cq=db()->prepare('SELECT * FROM cart WHERE IsOrdered=? LIMIT 1');$cq->execute([$id]);$cartRow=$cq->fetch();if($cartRow){$items=cart_items((int)$cartRow['ID']);$extra=cart_extra_product((int)($cartRow['ExtraProduct']??0));}}
}catch(Throwable){}
admin_header('Заказ #'.$id);
?>
<div class="card">
<?php if(!$row): ?><p>Заказ не найден.</p><?php else: ?>
<div class="section-title"><div><h2 style="margin:0">№ <?=aesc((string)($row['OrderNumber']??$id))?></h2><div class="muted"><?php $d=(int)($row['OrderDate']??0);echo $d?date('d.m.Y H:i',$d):''; ?></div></div><strong><?=number_format((float)($row['TotalSum']??0),0,',',' ')?> ₽</strong></div>

<?php if($cartRow): ?><div class="grid" style="margin:20px 0"><div class="card"><div class="muted">Тип заказа</div><b><?=((int)($cartRow['zType']??0)===1)?'Физическое лицо':'Организация'?></b></div><div class="card"><div class="muted">Доставка</div><b><?=((int)($cartRow['Delivery']??0)===1)?'Курьерская — 1 000 ₽':'Почта РФ — бесплатно'?></b></div><div class="card"><div class="muted">Дополнительно</div><b><?=$extra?aesc(a_legacy((string)$extra['Name'])):'Не выбрано'?></b><?php if($extra): ?><div class="muted"><?=number_format((float)$extra['Price'],0,',',' ')?> ₽</div><?php endif; ?></div></div><?php endif; ?>

<?php if($items): ?><h3>Состав заказа</h3><div class="table-wrap"><table class="table"><thead><tr><th>Продукт</th><th>Лицензия</th><th>Цена</th><th>Кол-во</th><th>Сумма</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><?=aesc(a_legacy((string)$item['ProductName']))?></td><td><?=aesc(a_legacy((string)$item['Title']))?></td><td><?=number_format((float)$item['Price'],0,',',' ')?> ₽</td><td><?=(int)$item['Amount']?></td><td><?=number_format((float)$item['Price']*(int)$item['Amount'],0,',',' ')?> ₽</td></tr><?php endforeach; ?><?php if((int)($cartRow['Delivery']??0)===1): ?><tr><td colspan="4">Курьерская доставка</td><td>1 000 ₽</td></tr><?php endif; ?><?php if($extra): ?><tr><td colspan="4"><?=aesc(a_legacy((string)$extra['Name']))?></td><td><?=number_format((float)$extra['Price'],0,',',' ')?> ₽</td></tr><?php endif; ?></tbody></table></div><?php endif; ?>

<?php if($statusColumn||$processedColumn||$adminCommentColumn): ?><form method="post" style="margin:24px 0"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><div class="form-grid"><?php if($statusColumn): ?><div class="field"><label>Статус</label><input class="input" name="status" value="<?=aesc(a_legacy((string)($row[$statusColumn]??'')))?>"></div><?php endif; ?><?php if($processedColumn): ?><div class="field"><label>Обработка</label><label><input type="checkbox" name="processed" <?=!empty($row[$processedColumn])?'checked':''?>> Заказ обработан</label></div><?php endif; ?><?php if($adminCommentColumn): ?><div class="field full"><label>Комментарий менеджера</label><textarea class="textarea" rows="4" name="admin_comment"><?=aesc(a_legacy((string)($row[$adminCommentColumn]??'')))?></textarea></div><?php endif; ?></div><div class="actions" style="margin-top:16px"><button class="btn primary" type="submit">Сохранить состояние</button></div></form><?php endif; ?>

<h3>Реквизиты заказа</h3><div class="table-wrap"><table class="table"><tbody><?php foreach($row as $k=>$v): if(in_array($k,['ID','OrderNumber','OrderDate','TotalSum','ProductID','PriceID','Amount'],true))continue; ?><tr><th><?=aesc((string)$k)?></th><td><?=nl2br(aesc(a_legacy((string)$v)))?></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?><div class="actions" style="margin-top:16px"><a class="btn" href="orders.php">← К списку</a></div></div>
<?php admin_footer();
