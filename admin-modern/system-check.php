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

[$mailReady,$mailDetails]=order_notification_check();
$add('Уведомления о заказах',$mailReady,$mailDetails);

function order_notification_check():array{
    if(!cfg('mail.enabled',false)) return [true,'Отключены — для прототипа это допустимо'];

    $to=cfg('mail.order_to','');
    if(is_array($to)) $recipients=array_values(array_filter(array_map('trim',$to)));
    else $recipients=array_values(array_filter(array_map('trim',explode(',',(string)$to))));
    if(!$recipients){
        $fallback=trim((string)cfg('mail.support_to',''));
        if($fallback!=='') $recipients=[$fallback];
    }

    $validRecipient=false;
    foreach($recipients as $recipient){
        if(filter_var($recipient,FILTER_VALIDATE_EMAIL)){$validRecipient=true;break;}
    }
    if(!$validRecipient) return [false,'Не указан корректный адрес для уведомлений о заказах'];

    $from=trim((string)cfg('mail.from',''));
    if(!filter_var($from,FILTER_VALIDATE_EMAIL)) return [false,'Не указан корректный mail.from'];

    $transport=strtolower(trim((string)cfg('mail.transport','smtp')));
    if($transport==='mail'||$transport==='php'){
        return [true,'Почтовый сервис хостинга; получатель: '.$recipients[0]];
    }

    $host=trim((string)cfg('mail.host',''));
    $port=(int)cfg('mail.port',587);
    if($host===''||$port<1) return [false,'Для SMTP не заполнены host/port'];
    return [true,'SMTP '.$host.':'.$port.'; получатель: '.$recipients[0]];
}

admin_header('Проверка системы');
$failed=count(array_filter($checks,fn($c)=>!$c['ok']));
?>
<div class="card">
<div class="section-title"><div><h2 style="margin:0">Готовность к размещению</h2><p class="muted">Проверка окружения и обязательных зависимостей без изменения данных.</p></div><span class="badge <?=$failed?'off':'on'?>"><?=$failed?'Есть проблемы':'Готово'?></span></div>
<div class="table-wrap"><table class="table"><thead><tr><th>Проверка</th><th>Статус</th><th>Подробности</th></tr></thead><tbody><?php foreach($checks as $check): ?><tr><td><?=aesc($check['name'])?></td><td><span class="badge <?=$check['ok']?'on':'off'?>"><?=$check['ok']?'OK':'Ошибка'?></span></td><td><?=aesc($check['details'])?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php admin_footer();
