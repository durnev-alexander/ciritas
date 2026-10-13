<?php
require __DIR__.'/includes/layout.php';
$rows=[];
try{$rows=db()->query('SELECT * FROM csOrders ORDER BY OrderDate DESC, ID DESC LIMIT 200')->fetchAll();}catch(Throwable){}
function order_plain_text(string $value): string {
    $value=a_legacy($value);
    $value=html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8');
    $value=strip_tags($value);
    $value=preg_replace('/[\s\x{00A0}]+/u',' ',trim($value))??trim($value);
    return trim($value);
}
admin_header('Заказы');
?>
<div class="card">
    <div class="toolbar">
        <div><h2>Список заказов</h2><div class="help">Полный список заказов через сайт за все время.</div></div>
        <div class="admin-products-summary">Всего: <?=count($rows)?></div>
    </div>
    <div class="table-wrap"><table class="table orders-table"><colgroup><col style="width:16%"><col style="width:18%"><col style="width:27%"><col style="width:12%"><col style="width:17%"><col style="width:10%"></colgroup><thead><tr><th>Заказ</th><th>Дата</th><th>Клиент</th><th>Сумма</th><th>Телефон / E-mail</th><th>Действия</th></tr></thead><tbody>
<?php foreach($rows as $r):
$date=(int)($r['OrderDate']??0);
$client=trim((string)($r['Organization']??''));
if($client==='')$client=trim((string)($r['LastName']??'').' '.(string)($r['FirstName']??'').' '.(string)($r['MiddleName']??''));
$client=order_plain_text($client);
$contact=order_plain_text(trim((string)($r['Phone']??'').' '.(string)($r['Email']??'')));
?>
<tr><td><b>№ <?=aesc((string)($r['OrderNumber']??$r['ID']))?></b><div class="help">ID <?=aesc((string)$r['ID'])?></div></td><td><?=$date>0?aesc(date('d.m.Y H:i',$date)):''?></td><td><?=aesc($client)?></td><td><?=number_format((float)($r['TotalSum']??0),2,',',' ')?> ₽</td><td><?=aesc($contact)?></td><td><a class="btn" href="order-view.php?id=<?=(int)$r['ID']?>">Открыть</a></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php admin_footer();
