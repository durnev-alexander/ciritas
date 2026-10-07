<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/cart.php';
require __DIR__.'/includes/layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['_cart_csrf'])) $_SESSION['_cart_csrf'] = bin2hex(random_bytes(24));
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals((string)$_SESSION['_cart_csrf'],(string)($_POST['_csrf']??''))){$error='Сессия корзины устарела. Обновите страницу.';}
    else{
        try{
            $action=(string)($_POST['action']??'');
            if($action==='add') cart_add_price((int)($_POST['price_id']??0),(int)($_POST['amount']??1));
            elseif($action==='update') cart_update_item((int)($_POST['item_id']??0),(int)($_POST['amount']??1));
            elseif($action==='remove') cart_remove_item((int)($_POST['item_id']??0));
            $_SESSION['_cart_csrf']=bin2hex(random_bytes(24));
            header('Location: cart.php');exit;
        }catch(Throwable $e){$error='Не удалось изменить корзину.';}
    }
}

$items=[];$total=0.0;
try{$items=cart_items();$total=cart_total();}catch(Throwable){$error=$error?:'Не удалось загрузить корзину.';}

render_header('Корзина');
?>
<section class="section"><div class="container"><div class="section-head"><div><h1>Корзина</h1><p class="lead">Проверьте выбранные лицензии перед оформлением заказа.</p></div></div>
<?php if($error): ?><div class="form-message error-box"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if(!$items): ?><div class="content-card"><h2>Корзина пуста</h2><p class="muted">Добавьте нужную лицензию со страницы продукта.</p><a class="btn btn-primary" href="products.php">Перейти к продуктам</a></div>
<?php else: ?><div class="content-card"><div class="table-responsive"><table class="cart-table"><thead><tr><th>Продукт</th><th>Лицензия</th><th>Цена</th><th>Количество</th><th>Сумма</th><th></th></tr></thead><tbody>
<?php foreach($items as $item): $sum=(float)$item['Price']*(int)$item['Amount']; ?><tr><td><?=htmlspecialchars(legacy((string)$item['ProductName']))?></td><td><?=htmlspecialchars(legacy((string)$item['Title']))?></td><td><?=number_format((float)$item['Price'],0,',',' ')?> ₽</td><td><form class="inline-form" method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_cart_csrf'])?>"><input type="hidden" name="action" value="update"><input type="hidden" name="item_id" value="<?=(int)$item['ID']?>"><input class="input qty-input" type="number" min="1" max="999" name="amount" value="<?=(int)$item['Amount']?>"><button class="btn" type="submit">Обновить</button></form></td><td><strong><?=number_format($sum,0,',',' ')?> ₽</strong></td><td><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_cart_csrf'])?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="item_id" value="<?=(int)$item['ID']?>"><button class="btn" type="submit">Удалить</button></form></td></tr><?php endforeach; ?>
</tbody></table></div><div class="cart-total"><span>Итого</span><strong><?=number_format($total,0,',',' ')?> ₽</strong></div><div class="product-actions"><a class="btn" href="products.php">Продолжить выбор</a><a class="btn btn-primary" href="order.php">Оформить заказ</a></div></div><?php endif; ?>
</div></section><?php render_footer();
