<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/order_compat.php';
require __DIR__.'/app/cart.php';
require __DIR__.'/includes/layout.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['_order_csrf']))$_SESSION['_order_csrf']=bin2hex(random_bytes(24));
$error='';$successId=0;$successNumber=0;$items=[];$cart=null;$total=0.0;
try{$cart=cart_current();$items=cart_items();$total=cart_total();}catch(Throwable){$error='Не удалось загрузить корзину.';}
if(!$items && !$error){header('Location: cart.php');exit;}

if($_SERVER['REQUEST_METHOD']==='POST' && $error===''){
 if(!hash_equals((string)$_SESSION['_order_csrf'],(string)($_POST['_csrf']??'')))$error='Сессия формы устарела. Обновите страницу.';
 else{
  $organization=trim((string)($_POST['organization']??''));$lastname=trim((string)($_POST['lastname']??''));$firstname=trim((string)($_POST['firstname']??''));$middlename=trim((string)($_POST['middlename']??''));$postaddr=trim((string)($_POST['postaddr']??''));$email=trim((string)($_POST['email']??''));$phone=trim((string)($_POST['phone']??''));
  if($organization===''&&($lastname===''||$firstname===''))$error='Укажите организацию или фамилию и имя заказчика.';
  elseif($postaddr==='')$error='Укажите почтовый адрес.';
  elseif($email===''&&$phone==='')$error='Укажите телефон или E-mail.';
  elseif($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Укажите корректный E-mail.';
  else{
   try{
    $successNumber=order_next_number();
    $values=['OrderNumber'=>$successNumber,'OrderDate'=>time(),'ProductID'=>0,'PriceID'=>0,'Amount'=>0,'TotalSum'=>$total,'Organization'=>$organization,'LegalAddress'=>trim((string)($_POST['legaladdr']??'')),'PostAddress'=>$postaddr,'INN'=>trim((string)($_POST['inn']??'')),'KPP'=>trim((string)($_POST['kpp']??'')),'OKPO'=>trim((string)($_POST['okpo']??'')),'OKONH'=>trim((string)($_POST['okonh']??'')),'BIK'=>trim((string)($_POST['bik']??'')),'Bank'=>trim((string)($_POST['bank']??'')),'RAccount'=>trim((string)($_POST['raccount']??'')),'KAccount'=>trim((string)($_POST['kaccount']??'')),'Fax'=>trim((string)($_POST['fax']??'')),'Phone'=>$phone,'Email'=>$email,'LastName'=>$lastname,'FirstName'=>$firstname,'MiddleName'=>$middlename,'WhereFrom'=>trim((string)($_POST['wherefrom']??'')),'Comments'=>trim((string)($_POST['comments']??''))];
    foreach(['Organization','LegalAddress','PostAddress','INN','KPP','OKPO','OKONH','BIK','Bank','RAccount','KAccount','Fax','Phone','Email','LastName','FirstName','MiddleName','WhereFrom','Comments'] as $f)$values[$f]=order_db_text((string)$values[$f]);
    db()->beginTransaction();$successId=order_insert($values);if($successNumber<1)$successNumber=$successId;
    if(!$cart)throw new RuntimeException('Cart not found');
    db()->prepare('UPDATE cart SET IsOrdered=? WHERE ID=? AND Code=?')->execute([$successId,(int)$cart['ID'],(string)$cart['Code']]);db()->commit();
    cart_clear_cookies();$_SESSION['_order_csrf']=bin2hex(random_bytes(24));
   }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$error='Не удалось сохранить заказ. Проверьте подключение к базе.';}
  }
 }
}
function ov(string $n):string{return htmlspecialchars((string)($_POST[$n]??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
render_header('Оформление заказа');
?>
<section class="section"><div class="container order-layout"><div><h1>Оформление заказа</h1><p class="lead">Все позиции корзины будут оформлены одним заказом.</p>
<?php if($successId): ?><div class="content-card success-box"><h2>Заказ принят</h2><p>Номер заказа: <b>№ <?=htmlspecialchars((string)$successNumber)?></b>.</p><a class="btn btn-primary" href="products.php">Вернуться к продуктам</a></div><?php else: ?>
<?php if($error): ?><div class="form-message error-box"><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="content-card"><h2>Состав заказа</h2><?php foreach($items as $i): ?><div class="license-row"><span><?=htmlspecialchars(legacy((string)$i['ProductName']))?> — <?=htmlspecialchars(legacy((string)$i['Title']))?> × <?=(int)$i['Amount']?></span><strong><?=number_format((float)$i['Price']*(int)$i['Amount'],0,',',' ')?> ₽</strong></div><?php endforeach; ?><div class="cart-total"><span>Итого</span><strong><?=number_format($total,0,',',' ')?> ₽</strong></div><a href="cart.php">← Изменить корзину</a></div>
<form class="content-card order-form" method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_order_csrf'])?>"><h2>Заказчик</h2><div class="form-grid-public"><label class="field"><span>Организация</span><input class="input" name="organization" maxlength="255" value="<?=ov('organization')?>"></label><label class="field"><span>Почтовый адрес *</span><input class="input" name="postaddr" required maxlength="255" value="<?=ov('postaddr')?>"></label><label class="field"><span>Фамилия</span><input class="input" name="lastname" maxlength="50" value="<?=ov('lastname')?>"></label><label class="field"><span>Имя</span><input class="input" name="firstname" maxlength="50" value="<?=ov('firstname')?>"></label><label class="field"><span>Отчество</span><input class="input" name="middlename" maxlength="50" value="<?=ov('middlename')?>"></label><label class="field"><span>Телефон</span><input class="input" name="phone" maxlength="50" value="<?=ov('phone')?>"></label><label class="field"><span>E-mail</span><input class="input" type="email" name="email" maxlength="100" value="<?=ov('email')?>"></label><label class="field"><span>Как узнали о нас</span><input class="input" name="wherefrom" maxlength="255" value="<?=ov('wherefrom')?>"></label></div><details><summary>Реквизиты организации</summary><div class="form-grid-public" style="margin-top:16px"><label class="field"><span>Юридический адрес</span><input class="input" name="legaladdr" value="<?=ov('legaladdr')?>"></label><label class="field"><span>ИНН</span><input class="input" name="inn" value="<?=ov('inn')?>"></label><label class="field"><span>КПП</span><input class="input" name="kpp" value="<?=ov('kpp')?>"></label><label class="field"><span>ОКПО</span><input class="input" name="okpo" value="<?=ov('okpo')?>"></label><label class="field"><span>ОКОНХ</span><input class="input" name="okonh" value="<?=ov('okonh')?>"></label><label class="field"><span>БИК</span><input class="input" name="bik" value="<?=ov('bik')?>"></label><label class="field"><span>Банк</span><input class="input" name="bank" value="<?=ov('bank')?>"></label><label class="field"><span>Расчётный счёт</span><input class="input" name="raccount" value="<?=ov('raccount')?>"></label><label class="field"><span>Корреспондентский счёт</span><input class="input" name="kaccount" value="<?=ov('kaccount')?>"></label><label class="field"><span>Факс</span><input class="input" name="fax" value="<?=ov('fax')?>"></label></div></details><label class="field"><span>Комментарий</span><textarea class="input" name="comments" rows="5"><?=ov('comments')?></textarea></label><button class="btn btn-primary" type="submit">Оформить заказ</button></form><?php endif; ?></div><aside class="content-card order-note"><h2>О заказе</h2><p>Состав сохраняется в старых таблицах <code>cart</code> и <code>cart_items</code>, а сам заказ — в <code>csOrders</code>.</p></aside></div></section><?php render_footer();
