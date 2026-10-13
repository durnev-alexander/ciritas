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
<style>
.orders-table{table-layout:auto}
.orders-table .order-number,.orders-table .order-date{width:1%;white-space:nowrap}
.orders-table .order-client{width:auto}
.orders-table .order-sum{width:1%;white-space:nowrap}
.orders-table .order-contact{width:17%}
.orders-table .order-actions{width:1%;white-space:nowrap}
</style>
<div class="card">
    <div class="toolbar">
        <div><h2>Список заказов</h2><div class="help">Полный список заказов через сайт за все время.</div></div>
        <div class="admin-products-summary">Всего: <?=count($rows)?></div>
    </div>
    <div class="table-wrap"><table class="table orders-table"><thead><tr><th class="order-number">Заказ</th><th class="order-date">Дата</th><th class="order-client">Клиент</th><th class="order-sum">Сумма</th><th class="order-contact">Телефон / E-mail</th><th class="order-actions">Действия</th></tr></thead><tbody>
<?php foreach($rows as $r):
$date=(int)($r['OrderDate']??0);
$client=trim((string)($r['Organization']??''));
if($client==='')$client=trim((string)($r['LastName']??'').' '.(string)($r['FirstName']??'').' '.(string)($r['MiddleName']??''));
$client=order_plain_text($client);
$contact=order_plain_text(trim((string)($r['Phone']??'').' '.(string)($r['Email']??'')));
?>
<tr><td class="order-number"><b>№ <?=aesc((string)($r['OrderNumber']??$r['ID']))?></b><div class="help">ID <?=aesc((string)$r['ID'])?></div></td><td class="order-date"><?=$date>0?aesc(date('d.m.Y H:i',$date)):''?></td><td class="order-client"><?=aesc($client)?></td><td class="order-sum"><?=number_format((float)($r['TotalSum']??0),2,',',' ')?> ₽</td><td class="order-contact"><?=aesc($contact)?></td><td class="order-actions"><a class="btn" href="order-view.php?id=<?=(int)$r['ID']?>">Открыть</a></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php admin_footer();
