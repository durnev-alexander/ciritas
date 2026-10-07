<?php
require __DIR__.'/includes/layout.php';

$checks=[];
$add=function(string $name,bool $ok,string $details='')use(&$checks):void{$checks[]=['name'=>$name,'ok'=>$ok,'details'=>$details];};
$add('PHP 8.1+',version_compare(PHP_VERSION,'8.1.0','>='),PHP_VERSION);
$add('PDO',extension_loaded('pdo'));
$add('PDO MySQL',extension_loaded('pdo_mysql'));
$add('mbstring',extension_loaded('mbstring'));

try{db()->query('SELECT 1');$add('Подключение к MySQL',true);}catch(Throwable $e){$add('Подключение к MySQL',false,'Проверьте config/local.php');}
foreach(['csSoftProducts','csSoftPrices','csOrders','cart','cart_items','csSettings'] as $table){
    try{db()->query('SELECT 1 FROM `'.$table.'` LIMIT 1');$add('Таблица '.$table,true);}catch(Throwable){$add('Таблица '.$table,false,'Таблица недоступна');}
}
$uploads=(string)cfg('site.uploads_path','');
$photos=(string)cfg('site.photos_path','');
$add('Каталог Uploads',$uploads!==''&&is_dir($uploads),$uploads);
$add('Каталог photos',$photos!==''&&is_dir($photos),$photos);
$add('Уведомления о заказах',!cfg('mail.enabled',false)||order_notification_ready(),cfg('mail.enabled',false)?'Включены':'Отключены — для прототипа это допустимо');

function order_notification_ready():bool{
    $to=cfg('mail.order_to','');
    $from=(string)cfg('mail.from','');
    if(is_array($to))$to=implode(',',array_filter($to));
    return trim((string)$to)!==''&&trim($from)!=='';
}

admin_header('Проверка системы');
$failed=count(array_filter($checks,fn($c)=>!$c['ok']));
?>
<div class="card">
<div class="section-title"><div><h2 style="margin:0">Готовность к размещению</h2><p class="muted">Проверка окружения и обязательных зависимостей без изменения данных.</p></div><span class="badge <?=$failed?'off':'on'?>"><?=$failed?'Есть проблемы':'Готово'?></span></div>
<div class="table-wrap"><table class="table"><thead><tr><th>Проверка</th><th>Статус</th><th>Подробности</th></tr></thead><tbody><?php foreach($checks as $check): ?><tr><td><?=aesc($check['name'])?></td><td><span class="badge <?=$check['ok']?'on':'off'?>"><?=$check['ok']?'OK':'Ошибка'?></span></td><td><?=aesc($check['details'])?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php admin_footer();
