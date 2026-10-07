<?php
require __DIR__.'/includes/layout.php';
$rows=[];
try{$rows=db()->query('SELECT * FROM csOrders ORDER BY OrderDate DESC, ID DESC LIMIT 200')->fetchAll();}catch(Throwable){}
admin_header('Заказы');
?>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Заказ</th><th>Дата</th><th>Клиент</th><th>Сумма</th><th>Телефон / E-mail</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r):
$date=(int)($r['OrderDate']??0);
$client=trim((string)($r['Organization']??''));
if($client==='')$client=trim((string)($r['LastName']??'').' '.(string)($r['FirstName']??'').' '.(string)($r['MiddleName']??''));
?>
<tr><td><b>№ <?=aesc((string)($r['OrderNumber']??$r['ID']))?></b><div class="help">ID <?=aesc((string)$r['ID'])?></div></td><td><?=$date>0?aesc(date('d.m.Y H:i',$date)):''?></td><td><?=aesc(a_legacy($client))?></td><td><?=number_format((float)($r['TotalSum']??0),2,',',' ')?> ₽</td><td><?=aesc(a_legacy(trim((string)($r['Phone']??'').' '.(string)($r['Email']??''))))?></td><td><a class="btn" href="order-view.php?id=<?=(int)$r['ID']?>">Открыть</a></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php admin_footer();
