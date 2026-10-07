<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/order_compat.php';
require __DIR__.'/includes/layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['_order_csrf'])) $_SESSION['_order_csrf'] = bin2hex(random_bytes(24));

$products=[];$prices=[];$error='';$successId=0;$successNumber=0;
$selectedProduct=(int)($_GET['product']??$_POST['product_id']??0);
$selectedPrice=(int)($_POST['price_id']??0);

try{
    $products=db()->query('SELECT ID,Name,ShortDescr FROM csSoftProducts WHERE IsActive=1 AND OrderAllow=1 ORDER BY Name')->fetchAll();
    if($selectedProduct>0){$q=db()->prepare('SELECT * FROM csSoftPrices WHERE SoftProductID=? ORDER BY Title');$q->execute([$selectedProduct]);$prices=$q->fetchAll();}
}catch(Throwable){$error='Не удалось загрузить каталог для оформления заказа.';}

if($_SERVER['REQUEST_METHOD']==='POST' && $error===''){
    if(!hash_equals((string)$_SESSION['_order_csrf'],(string)($_POST['_csrf']??''))){$error='Сессия формы устарела. Обновите страницу и повторите отправку.';}
    else{
        $organization=trim((string)($_POST['organization']??''));
        $lastname=trim((string)($_POST['lastname']??''));$firstname=trim((string)($_POST['firstname']??''));$middlename=trim((string)($_POST['middlename']??''));
        $postaddr=trim((string)($_POST['postaddr']??''));$email=trim((string)($_POST['email']??''));$phone=trim((string)($_POST['phone']??''));
        $amount=max(1,min(999,(int)($_POST['amount']??1)));
        if($selectedProduct<1){$error='Выберите продукт.';}
        elseif($prices && $selectedPrice<1){$error='Выберите вариант лицензии.';}
        elseif($organization==='' && ($lastname===''||$firstname==='')){$error='Укажите организацию или фамилию и имя заказчика.';}
        elseif($postaddr===''){$error='Укажите почтовый адрес.';}
        elseif($email===''&&$phone===''){$error='Укажите телефон или E-mail.';}
        elseif($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)){$error='Укажите корректный E-mail.';}
        else{
            try{
                $price=0.0;
                if($selectedPrice>0){$pq=db()->prepare('SELECT Price FROM csSoftPrices WHERE ID=? AND SoftProductID=? LIMIT 1');$pq->execute([$selectedPrice,$selectedProduct]);$priceRow=$pq->fetch();if(!$priceRow)throw new RuntimeException('Price not found');$price=(float)$priceRow['Price'];}
                $successNumber=order_next_number();
                $textFields=['Organization','LegalAddress','PostAddress','INN','KPP','OKPO','OKONH','BIK','Bank','RAccount','KAccount','Fax','Phone','Email','LastName','FirstName','MiddleName','WhereFrom','Comments'];
                $values=[
                    'OrderNumber'=>$successNumber,
                    'OrderDate'=>time(),
                    'ProductID'=>$selectedProduct,
                    'PriceID'=>$selectedPrice,
                    'Amount'=>$amount,
                    'TotalSum'=>$price*$amount,
                    'Organization'=>$organization,
                    'LegalAddress'=>trim((string)($_POST['legaladdr']??'')),
                    'PostAddress'=>$postaddr,
                    'INN'=>trim((string)($_POST['inn']??'')),
                    'KPP'=>trim((string)($_POST['kpp']??'')),
                    'OKPO'=>trim((string)($_POST['okpo']??'')),
                    'OKONH'=>trim((string)($_POST['okonh']??'')),
                    'BIK'=>trim((string)($_POST['bik']??'')),
                    'Bank'=>trim((string)($_POST['bank']??'')),
                    'RAccount'=>trim((string)($_POST['raccount']??'')),
                    'KAccount'=>trim((string)($_POST['kaccount']??'')),
                    'Fax'=>trim((string)($_POST['fax']??'')),
                    'Phone'=>$phone,
                    'Email'=>$email,
                    'LastName'=>$lastname,
                    'FirstName'=>$firstname,
                    'MiddleName'=>$middlename,
                    'WhereFrom'=>trim((string)($_POST['wherefrom']??'')),
                    'Comments'=>trim((string)($_POST['comments']??'')),
                ];
                foreach($textFields as $field)$values[$field]=order_db_text((string)$values[$field]);
                $successId=order_insert($values);
                if($successNumber<1)$successNumber=$successId;
                $_SESSION['_order_csrf']=bin2hex(random_bytes(24));
            }catch(Throwable){$error='Не удалось сохранить заказ. Проверьте подключение к базе и структуру csOrders.';}
        }
    }
}

render_header('Оформление заказа');
function ov(string $name): string{return htmlspecialchars((string)($_POST[$name]??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?>
<section class="section"><div class="container order-layout"><div>
<h1>Оформление заказа</h1><p class="lead">Новая форма использует существующую таблицу заказов CIRITAS и сохраняет совместимость со старой системой.</p>
<?php if($successId): ?><div class="content-card success-box"><h2>Заказ принят</h2><p>Номер заказа: <b>№ <?=htmlspecialchars((string)$successNumber)?></b>.</p><a class="btn btn-primary" href="products.php">Вернуться к продуктам</a></div>
<?php else: ?>
<?php if($error): ?><div class="form-message error-box"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form class="content-card order-form" method="post" action="order.php<?= $selectedProduct?'?product='.$selectedProduct:'' ?>">
<input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_order_csrf'])?>">
<label class="field"><span>Продукт *</span><select class="input" name="product_id" required onchange="window.location='order.php?product='+this.value"><option value="">Выберите продукт</option><?php foreach($products as $p): ?><option value="<?=(int)$p['ID']?>" <?=$selectedProduct===(int)$p['ID']?'selected':''?>><?=htmlspecialchars(legacy($p['Name']))?></option><?php endforeach; ?></select></label>
<?php if($selectedProduct&&$prices): ?><label class="field"><span>Вариант / лицензия *</span><select class="input" name="price_id" required><option value="">Выберите вариант</option><?php foreach($prices as $price): $label=legacy((string)($price['Title']??'Лицензия')).' — '.number_format((float)($price['Price']??0),0,',',' ').' ₽'; ?><option value="<?=(int)$price['ID']?>" <?=$selectedPrice===(int)$price['ID']?'selected':''?>><?=htmlspecialchars($label)?></option><?php endforeach; ?></select></label><?php endif; ?>
<label class="field"><span>Количество *</span><input class="input" type="number" min="1" max="999" name="amount" value="<?=ov('amount')?:'1'?>"></label>
<h2>Заказчик</h2><div class="form-grid-public">
<label class="field"><span>Организация</span><input class="input" name="organization" maxlength="255" value="<?=ov('organization')?>"></label>
<label class="field"><span>Почтовый адрес *</span><input class="input" name="postaddr" maxlength="255" required value="<?=ov('postaddr')?>"></label>
<label class="field"><span>Фамилия</span><input class="input" name="lastname" maxlength="50" value="<?=ov('lastname')?>"></label>
<label class="field"><span>Имя</span><input class="input" name="firstname" maxlength="50" value="<?=ov('firstname')?>"></label>
<label class="field"><span>Отчество</span><input class="input" name="middlename" maxlength="50" value="<?=ov('middlename')?>"></label>
<label class="field"><span>Телефон</span><input class="input" name="phone" maxlength="50" value="<?=ov('phone')?>"></label>
<label class="field"><span>E-mail</span><input class="input" type="email" name="email" maxlength="50" value="<?=ov('email')?>"></label>
<label class="field"><span>Как узнали о нас</span><input class="input" name="wherefrom" maxlength="255" value="<?=ov('wherefrom')?>"></label>
</div>
<details><summary>Реквизиты организации</summary><div class="form-grid-public" style="margin-top:16px">
<label class="field"><span>Юридический адрес</span><input class="input" name="legaladdr" maxlength="255" value="<?=ov('legaladdr')?>"></label><label class="field"><span>ИНН</span><input class="input" name="inn" maxlength="50" value="<?=ov('inn')?>"></label><label class="field"><span>КПП</span><input class="input" name="kpp" maxlength="50" value="<?=ov('kpp')?>"></label><label class="field"><span>ОКПО</span><input class="input" name="okpo" maxlength="50" value="<?=ov('okpo')?>"></label><label class="field"><span>ОКОНХ</span><input class="input" name="okonh" maxlength="50" value="<?=ov('okonh')?>"></label><label class="field"><span>БИК</span><input class="input" name="bik" maxlength="50" value="<?=ov('bik')?>"></label><label class="field"><span>Банк</span><input class="input" name="bank" maxlength="255" value="<?=ov('bank')?>"></label><label class="field"><span>Расчётный счёт</span><input class="input" name="raccount" maxlength="50" value="<?=ov('raccount')?>"></label><label class="field"><span>Корреспондентский счёт</span><input class="input" name="kaccount" maxlength="50" value="<?=ov('kaccount')?>"></label><label class="field"><span>Факс</span><input class="input" name="fax" maxlength="50" value="<?=ov('fax')?>"></label>
</div></details>
<label class="field"><span>Комментарий</span><textarea class="input" name="comments" rows="5"><?=ov('comments')?></textarea></label>
<p class="muted">Нужно указать организацию либо имя заказчика, а также телефон или E-mail.</p><button class="btn btn-primary" type="submit">Оформить заказ</button>
</form><?php endif; ?></div>
<aside class="content-card order-note"><h2>Совместимость</h2><p>Заказ сохраняется в существующей <code>csOrders</code>: номер заказа, продукт, лицензия, количество, сумма и реквизиты.</p><p class="muted">Платёжные данные на сайте не запрашиваются.</p></aside>
</div></section><?php render_footer();
