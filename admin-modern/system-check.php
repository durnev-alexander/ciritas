<?php
require __DIR__.'/includes/layout.php';


$siteError='';$websites=[];
try {
 db()->exec("CREATE TABLE IF NOT EXISTS csSystemCheckSites (ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, Url VARCHAR(500) NOT NULL, OrderIndex INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Throwable $e) {$siteError='Не удалось создать таблицу для сайтов. Проверьте права базы данных.';}

function site_rows():array{return db()->query('SELECT ID,Url,OrderIndex FROM csSystemCheckSites ORDER BY OrderIndex,ID')->fetchAll();}
function site_url(string $raw):string {
 $raw=trim($raw);
 if($raw===''||strlen($raw)>500||preg_match('/[\x00-\x20\x7f]/',$raw))throw new RuntimeException('Введите корректный адрес сайта.');
 if(!preg_match('~^https?://~i',$raw))$raw='https://'.$raw;
 $parts=parse_url($raw);
 if(!is_array($parts)||!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment']))throw new RuntimeException('Разрешены только адреса HTTP и HTTPS без авторизации.');
 $host=(string)$parts['host'];$scheme=strtolower((string)$parts['scheme']);
 if(!preg_match('/^[a-z0-9.-]+$/i',$host)||strlen($host)>253||str_contains($host,'..')||!str_contains($host,'.'))throw new RuntimeException('Укажите публичное доменное имя сайта.');
 $port=(int)($parts['port']??($scheme==='https'?443:80));
 if($port!==($scheme==='https'?443:80))throw new RuntimeException('Разрешены только стандартные порты HTTP/HTTPS.');
 return $raw;
}
function site_reorder(array $ids):void {
 $q=db()->prepare('UPDATE csSystemCheckSites SET OrderIndex=? WHERE ID=?');
 foreach($ids as $i=>$id)$q->execute([($i+1)*10,(int)$id]);
}
function site_ping(string $value):array {
 if(!function_exists('curl_init'))return [false,'PHP cURL не установлен'];
 try{$url=site_url($value);}catch(Throwable $e){return [false,$e->getMessage()];}
 $p=parse_url($url);$host=strtolower((string)$p['host']);$scheme=strtolower((string)$p['scheme']);$port=$scheme==='https'?443:80;
 $ips=@gethostbynamel($host);
 if(!$ips)return [false,'Не удалось определить IP-адрес'];
 $ip='';
 foreach($ips as $candidate)if(filter_var($candidate,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)){$ip=$candidate;break;}
 if($ip==='')return [false,'Публичный IPv4-адрес не найден'];
 $ch=curl_init($url);if($ch===false)return [false,'Не удалось начать проверку'];
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_NOBODY=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>4,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_RESOLVE=>[$host.':'.$port.':'.$ip],CURLOPT_USERAGENT=>'CIRITAS-System-Check/1.0']);
 if(defined('CURLOPT_PROTOCOLS_STR'))curl_setopt($ch,CURLOPT_PROTOCOLS_STR,$scheme);
 else curl_setopt($ch,CURLOPT_PROTOCOLS,$scheme==='https'?CURLPROTO_HTTPS:CURLPROTO_HTTP);
 curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
 // Any HTTP response demonstrates availability, even 403 or 404.
 return $status>0?[true,'HTTP '.$status]:[false,$error?:'Нет ответа'];
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['site_action'])){
 csrf_check();
 try {
  if($siteError!=='')throw new RuntimeException($siteError);
  $action=(string)$_POST['site_action'];$id=(int)($_POST['site_id']??0);
  if($action==='add'){
   $url=site_url((string)($_POST['site_url']??''));$rows=site_rows();$position=max(1,min(count($rows)+1,(int)($_POST['position']??count($rows)+1)));
   $q=db()->prepare('INSERT INTO csSystemCheckSites (Url,OrderIndex) VALUES (?,?)');$q->execute([$url,(count($rows)+1)*10]);
   $new=(int)db()->lastInsertId();$ids=array_map('intval',array_column($rows,'ID'));array_splice($ids,$position-1,0,[$new]);site_reorder($ids);
   admin_set_notice('Сайт добавлен.');
  }elseif($action==='save'&&$id>0){
   $url=site_url((string)($_POST['site_url']??''));db()->prepare('UPDATE csSystemCheckSites SET Url=? WHERE ID=?')->execute([$url,$id]);admin_set_notice('Сайт сохранён.');
  }elseif(($action==='up'||$action==='down')&&$id>0){
   $ids=array_map('intval',array_column(site_rows(),'ID'));$i=array_search($id,$ids,true);$j=$action==='up'?$i-1:$i+1;
   if($i!==false&&isset($ids[$j])){[$ids[$i],$ids[$j]]=[$ids[$j],$ids[$i]];site_reorder($ids);}
  }elseif($action==='delete'&&$id>0){
   db()->prepare('DELETE FROM csSystemCheckSites WHERE ID=?')->execute([$id]);site_reorder(array_map('intval',array_column(site_rows(),'ID')));admin_set_notice('Сайт удалён.');
  }else throw new RuntimeException('Неизвестное действие.');
  redirect('system-check.php');
 }catch(Throwable $e){$siteError=$e->getMessage();}
}
if($siteError==='')try{
 $websites=site_rows();
 foreach($websites as &$site){[$site['online'],$site['detail']]=site_ping((string)$site['Url']);}unset($site);
}catch(Throwable $e){$siteError='Не удалось прочитать список сайтов.';}

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
<div class="section-title"><div><h2 style="margin:0">Готовность к эксплуатации</h2><p class="muted">Проверка окружения и обязательных зависимостей без изменения данных.</p></div><span class="badge <?=$failed?'off':'on'?>"><?=$failed?'Есть проблемы':'Готово'?></span></div>
<div class="table-wrap"><table class="table"><thead><tr><th>Проверка</th><th>Статус</th><th>Подробности</th></tr></thead><tbody><?php foreach($checks as $check): ?><tr><td><?=aesc($check['name'])?></td><td><span class="badge <?=$check['ok']?'on':'off'?>"><?=$check['ok']?'OK':'Ошибка'?></span></td><td><?=aesc($check['details'])?></td></tr><?php endforeach; ?></tbody></table></div>
</div>

<div class="card" style="margin-top:18px">
<div class="section-title" style="margin-bottom:16px"><div><h2 style="margin:0">Работоспособность сайтов</h2><div class="help">Проверка доступности отделных сайтов для программ</div></div><a class="btn" href="system-check.php?refresh=1">Обновить</a></div>
<?php if($siteError!==''): ?><div class="form-message error-box"><?=aesc($siteError)?></div><?php endif; ?>
<div class="table-wrap"><table class="table site-check-table"><thead><tr><th class="site-check-name">Имя сайта</th><th class="site-check-status">Активность</th><th class="site-check-sort">Сортировка</th><th class="site-check-actions">Действия</th></tr></thead><tbody>
<?php foreach($websites as $i=>$site): $fid='site_form_'.(int)$site['ID']; ?>
<tr>
<td class="site-check-name"><form id="<?=aesc($fid)?>" method="post" action="system-check.php"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="site_id" value="<?=(int)$site['ID']?>"></form><input form="<?=aesc($fid)?>" class="input" name="site_url" type="text" maxlength="500" required value="<?=aesc((string)$site['Url'])?>"></td>
<td class="site-check-status"><span class="site-check-<?=$site['online']?'on':'off'?>" title="<?=aesc((string)$site['detail'])?>"><?=$site['online']?'Активен':'Не активен'?></span></td>
<td class="site-check-sort"><div class="actions"><button class="btn sort-btn" form="<?=aesc($fid)?>" name="site_action" value="up" formnovalidate <?=$i===0?'disabled':''?> title="Выше">↑</button><button class="btn sort-btn" form="<?=aesc($fid)?>" name="site_action" value="down" formnovalidate <?=$i===count($websites)-1?'disabled':''?> title="Ниже">↓</button></div></td>
<td class="site-check-actions"><div class="actions"><button class="btn" form="<?=aesc($fid)?>" name="site_action" value="save">Сохранить</button><button class="btn danger js-site-delete" type="button" data-id="<?=(int)$site['ID']?>" data-name="<?=aesc((string)$site['Url'])?>">Удалить</button></div></td>
</tr>
<?php endforeach; ?>
<tr><td class="site-check-name"><form id="siteAddForm" method="post" action="system-check.php"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input class="input" name="site_url" type="text" required maxlength="500" placeholder="https://example.com"></form></td>
<td class="site-check-status"><span class="muted">—</span></td>
<td class="site-check-sort"><input form="siteAddForm" class="input site-check-position" name="position" type="number" min="1" max="<?=count($websites)+1?>" value="<?=count($websites)+1?>" title="Место в списке"></td>
<td class="site-check-actions"><div class="actions"><button form="siteAddForm" class="btn primary" name="site_action" value="add" type="submit">Добавить</button></div></td></tr>
</tbody></table></div></div>
<div class="admin-modal" id="siteDeleteModal" hidden aria-hidden="true"><div class="admin-modal-backdrop" data-site-close></div><div class="admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="siteDeleteTitle"><h2 id="siteDeleteTitle">Подтверждение удаления</h2><p>Удалить сайт из списка проверок?<span class="admin-modal-product" id="siteDeleteName"></span></p><form class="admin-modal-actions" method="post"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="site_action" value="delete"><input type="hidden" name="site_id" id="siteDeleteId"><button type="button" class="btn" data-site-close>Отмена</button><button type="submit" class="btn">Удалить</button></form></div></div>
<style>
.site-check-table{table-layout:auto}
.site-check-table .site-check-name{width:100%;min-width:260px}
.site-check-table .site-check-status,.site-check-table .site-check-sort,.site-check-table .site-check-actions{width:1%;white-space:nowrap}
.site-check-table .actions{flex-wrap:nowrap}
.site-check-table .site-check-position{min-width:76px;width:100%;text-align:center}
.site-check-table .site-check-on{color:#238a49;font-weight:600}
.site-check-table .site-check-off{color:#db4b4b;font-weight:600}
.site-check-table tbody:has(tr:nth-child(2)) tr:last-child .site-check-actions .actions,.site-check-table tbody:has(tr:nth-child(2)) tr:last-child .site-check-actions .btn{width:100%}
</style>
<script>(function(){
var modal=document.getElementById('siteDeleteModal'),name=document.getElementById('siteDeleteName'),id=document.getElementById('siteDeleteId'),trigger=null;
function close(){modal.hidden=true;modal.setAttribute('aria-hidden','true');document.body.classList.remove('admin-modal-open');if(trigger)trigger.focus();}
document.querySelectorAll('.js-site-delete').forEach(function(button){button.addEventListener('click',function(){trigger=button;name.textContent=button.dataset.name||'';id.value=button.dataset.id||'';modal.hidden=false;modal.setAttribute('aria-hidden','false');document.body.classList.add('admin-modal-open');modal.querySelector('[data-site-close]').focus();});});
modal.querySelectorAll('[data-site-close]').forEach(function(button){button.addEventListener('click',close);});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!modal.hidden)close();});
})();</script>

<?php admin_footer();
