<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/cart.php';
require __DIR__.'/includes/layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['_cart_csrf'])) $_SESSION['_cart_csrf'] = bin2hex(random_bytes(24));
$error='';

function cart_ajax_payload(): array {
    $items=[];
    foreach(cart_items() as $item){
        $items[(string)(int)$item['PriceID']]=[
            'item_id'=>(int)$item['ID'],
            'price_id'=>(int)$item['PriceID'],
            'amount'=>(int)$item['Amount'],
        ];
    }
    return ['ok'=>true,'csrf'=>(string)$_SESSION['_cart_csrf'],'items'=>$items,'total'=>cart_total()];
}

if($_SERVER['REQUEST_METHOD']==='GET' && (string)($_GET['ajax']??'')==='state'){
    header('Content-Type: application/json; charset=utf-8');
    try{echo json_encode(cart_ajax_payload(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(Throwable){http_response_code(500);echo json_encode(['ok'=>false,'message'=>'Не удалось загрузить корзину.'],JSON_UNESCAPED_UNICODE);}
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $isAjax=(string)($_POST['_ajax']??'')==='1';
    if(!hash_equals((string)$_SESSION['_cart_csrf'],(string)($_POST['_csrf']??''))){$error='Сессия корзины устарела. Обновите страницу.';}
    else{
        try{
            $action=(string)($_POST['action']??'');
            if($action==='add') cart_add_price((int)($_POST['price_id']??0),(int)($_POST['amount']??1));
            elseif($action==='update'){
                $amount=(int)($_POST['amount']??1);
                $delta=(int)($_POST['delta']??0);
                if($delta!==0) $amount+=$delta;
                $amount=max(1,min(999,$amount));
                cart_update_item((int)($_POST['item_id']??0),$amount);
            }
            elseif($action==='remove') cart_remove_item((int)($_POST['item_id']??0));
            if(!$isAjax)$_SESSION['_cart_csrf']=bin2hex(random_bytes(24));
            if($isAjax){header('Content-Type: application/json; charset=utf-8');echo json_encode(cart_ajax_payload(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
            header('Location: cart.php');exit;
        }catch(Throwable $e){$error='Не удалось изменить корзину.';}
    }
    if($isAjax){header('Content-Type: application/json; charset=utf-8');http_response_code(400);echo json_encode(['ok'=>false,'message'=>$error,'csrf'=>(string)$_SESSION['_cart_csrf']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
}

$items=[];$total=0.0;
try{$items=cart_items();$total=cart_total();}catch(Throwable){$error=$error?:'Не удалось загрузить корзину.';}

render_header('Корзина');
?>
<section class="section public-compact-section cart-public-section"><div class="container"><div class="section-head"><div><h1>Корзина</h1><p class="muted">Проверьте выбранные лицензии перед оформлением заказа.</p></div></div>
<?php if($error): ?><div class="form-message error-box"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if(!$items): ?><div class="content-card cart-content-card"><h2>Корзина пуста</h2><p class="muted">Добавьте нужную лицензию со страницы продукта.</p><a class="btn btn-primary" href="products.php">Перейти к продуктам</a></div>
<?php else: ?><div class="content-card cart-content-card"><div class="cart-list">
<?php foreach($items as $item): $sum=(float)$item['Price']*(int)$item['Amount']; ?>
<article class="cart-item-row">
    <div class="cart-info-wrap">
        <div class="cart-field cart-product">
            <div class="cart-field-label">Продукт</div>
            <div class="cart-field-value cart-product-value"><div class="cart-product-line"><strong><?=htmlspecialchars(legacy((string)$item['ProductName']))?></strong><span class="cart-license-inline"><?=htmlspecialchars(legacy((string)$item['Title']))?></span></div></div>
        </div>
        <div class="cart-field cart-price">
            <div class="cart-field-label">Цена</div>
            <div class="cart-field-value"><?=number_format((float)$item['Price'],0,',',' ')?> ₽</div>
        </div>
        <div class="cart-field cart-quantity">
            <div class="cart-field-label">Количество</div>
            <div class="cart-field-value"><form class="cart-qty-control" method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_cart_csrf'])?>"><input type="hidden" name="action" value="update"><input type="hidden" name="item_id" value="<?=(int)$item['ID']?>"><button class="cart-qty-btn" type="submit" name="delta" value="-1" aria-label="Уменьшить количество">−</button><input class="cart-qty-input" type="number" min="1" max="999" name="amount" value="<?=(int)$item['Amount']?>" aria-label="Количество"><button class="cart-qty-btn" type="submit" name="delta" value="1" aria-label="Увеличить количество">+</button></form></div>
        </div>
        <div class="cart-field cart-sum">
            <div class="cart-field-label">Сумма</div>
            <div class="cart-field-value"><strong><?=number_format($sum,0,',',' ')?> ₽</strong></div>
        </div>
    </div>
    <div class="cart-remove"><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_cart_csrf'])?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="item_id" value="<?=(int)$item['ID']?>"><button class="cart-remove-btn" type="submit" title="Удалить из корзины" aria-label="Удалить из корзины"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg></button></form></div>
</article>
<?php endforeach; ?>
</div><div class="cart-total"><span>Итого</span><strong><?=number_format($total,0,',',' ')?> ₽</strong></div><div class="product-actions cart-actions"><a class="btn" href="products.php">Продолжить выбор</a><a class="btn btn-primary" href="order.php">Оформить заказ</a></div></div><?php endif; ?>
</div></section>
<style>
.cart-content-card h2{margin:0 0 6px}.cart-content-card>p.muted{margin:0 0 18px}.cart-list{display:grid;gap:14px}.cart-item-row{display:grid;grid-template-columns:minmax(0,1fr) 40px;gap:14px;align-items:stretch;padding:16px;background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:0 6px 20px var(--shadow)}.cart-info-wrap{min-width:0;display:flex;flex-wrap:wrap;align-items:stretch;column-gap:24px;row-gap:14px}.cart-field{display:grid;grid-template-rows:18px minmax(40px,1fr);align-content:stretch;min-width:0;padding:0}.cart-field-label{font-size:12px;line-height:18px;color:var(--muted);font-weight:500;white-space:nowrap}.cart-field-value{display:flex;align-items:center;min-height:40px;line-height:1.35}.cart-product{flex:1 1 300px;min-width:300px}.cart-product-value{display:flex;align-items:center;white-space:normal;min-height:40px}.cart-product-line{line-height:1.35;overflow-wrap:anywhere}.cart-product strong{display:inline;overflow-wrap:anywhere}.cart-license-inline{display:inline;margin-left:8px;color:var(--muted);font-size:14px;line-height:1.35;overflow-wrap:anywhere}.cart-price{flex:0 0 110px}.cart-quantity{flex:0 0 132px}.cart-sum{flex:0 0 120px}.cart-price,.cart-sum{white-space:nowrap}.cart-remove{display:flex;align-self:stretch;min-width:40px}.cart-remove form{display:flex;flex:1;align-self:stretch}.cart-qty-control{display:inline-grid;grid-template-columns:38px 54px 38px;align-items:stretch;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:var(--input)}.cart-qty-btn{border:0;background:var(--soft);color:var(--text);font:inherit;font-size:20px;line-height:1;cursor:pointer;min-height:40px}.cart-qty-btn:hover{background:var(--soft-2);color:var(--navy)}.cart-qty-input{width:54px;min-width:0;border:0;border-left:1px solid var(--line);border-right:1px solid var(--line);border-radius:0;background:var(--input);color:var(--text);font:inherit;text-align:center;padding:0 4px;outline:0;-moz-appearance:textfield}.cart-qty-input::-webkit-outer-spin-button,.cart-qty-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}.cart-remove-btn{width:40px;height:100%;min-height:40px;padding:0;display:grid;place-items:center;border:1px solid #f4c4c4;border-radius:10px;background:#fff3f3;color:#d92d20;cursor:pointer}.cart-remove-btn:hover{background:#ffe6e6;border-color:#eea5a5}.cart-remove-btn svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}:root[data-theme="dark"] .cart-remove-btn{background:#341b20;border-color:#66313b;color:#ff8f9a}:root[data-theme="dark"] .cart-remove-btn:hover{background:#442129}.cart-actions{justify-content:space-between}.cart-actions .btn{min-width:180px}.cart-total{border-top:0;margin-top:14px}
@media(max-width:900px){.cart-info-wrap{column-gap:18px;row-gap:14px}.cart-price{flex-basis:100px}.cart-quantity{flex-basis:118px}.cart-sum{flex-basis:110px}.cart-qty-control{grid-template-columns:34px 48px 34px}.cart-qty-input{width:48px}}
@media(max-width:800px){.cart-content-card{padding:18px;background:transparent;border:0;box-shadow:none}.cart-table-wrap{overflow:visible}.cart-actions{margin-bottom:0}.cart-actions .btn{flex:1;min-width:0}.cart-total{padding:17px 16px;background:var(--soft);border:1px solid var(--line);border-radius:14px}}
@media(max-width:620px){.cart-info-wrap{column-gap:14px;row-gap:14px}.cart-product{flex-basis:300px;min-width:min(300px,100%)}.cart-actions{flex-direction:column}.cart-actions .btn{width:100%}}
@media(max-width:420px){.cart-item-row{padding:14px 12px;gap:10px}.cart-info-wrap{column-gap:12px;row-gap:14px}.cart-price,.cart-quantity,.cart-sum{flex:1 0 100%}.cart-total strong{font-size:25px}}
</style>
<?php render_footer();
