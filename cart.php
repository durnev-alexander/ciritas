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
<?php else: ?><div class="content-card cart-content-card"><div class="cart-table-wrap"><table class="cart-table"><colgroup><col class="cart-col-product"><col class="cart-col-price"><col class="cart-col-qty"><col class="cart-col-sum"><col class="cart-col-remove"></colgroup><thead><tr><th>Продукт</th><th>Цена</th><th>Количество</th><th>Сумма</th><th aria-label="Удаление"></th></tr></thead><tbody>
<?php foreach($items as $item): $sum=(float)$item['Price']*(int)$item['Amount']; ?><tr class="cart-item-row">
<td class="cart-product" data-label="Продукт"><strong><?=htmlspecialchars(legacy((string)$item['ProductName']))?></strong><span class="cart-license-inline"><?=htmlspecialchars(legacy((string)$item['Title']))?></span></td>
<td class="cart-price" data-label="Цена"><?=number_format((float)$item['Price'],0,',',' ')?> ₽</td>
<td class="cart-quantity" data-label="Количество"><form class="cart-qty-control" method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_cart_csrf'])?>"><input type="hidden" name="action" value="update"><input type="hidden" name="item_id" value="<?=(int)$item['ID']?>"><button class="cart-qty-btn" type="submit" name="delta" value="-1" aria-label="Уменьшить количество">−</button><input class="cart-qty-input" type="number" min="1" max="999" name="amount" value="<?=(int)$item['Amount']?>" aria-label="Количество"><button class="cart-qty-btn" type="submit" name="delta" value="1" aria-label="Увеличить количество">+</button></form></td>
<td class="cart-sum" data-label="Сумма"><strong><?=number_format($sum,0,',',' ')?> ₽</strong></td>
<td class="cart-remove" data-label=""><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_cart_csrf'])?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="item_id" value="<?=(int)$item['ID']?>"><button class="cart-remove-btn" type="submit" title="Удалить из корзины" aria-label="Удалить из корзины"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg></button></form></td>
</tr><?php endforeach; ?>
</tbody></table></div><div class="cart-total"><span>Итого</span><strong><?=number_format($total,0,',',' ')?> ₽</strong></div><div class="product-actions cart-actions"><a class="btn" href="products.php">Продолжить выбор</a><a class="btn btn-primary" href="order.php">Оформить заказ</a></div></div><?php endif; ?>
</div></section>
<style>
.cart-content-card h2{margin:0 0 6px}.cart-content-card>p.muted{margin:0 0 18px}.cart-table-wrap{overflow:visible}.cart-table{width:100%;min-width:0;table-layout:fixed;border-collapse:collapse}.cart-col-product{width:42%}.cart-col-price{width:13%}.cart-col-qty{width:20%}.cart-col-sum{width:15%}.cart-col-remove{width:10%}.cart-table th,.cart-table td{padding:15px 12px;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}.cart-table th{font-size:13px;color:var(--muted);font-weight:600}.cart-product{overflow-wrap:anywhere}.cart-license-inline{display:block;margin-top:5px;color:var(--muted);font-size:14px;line-height:1.35}.cart-price,.cart-sum{white-space:nowrap}.cart-qty-control{display:inline-grid;grid-template-columns:38px 54px 38px;align-items:stretch;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:var(--input)}.cart-qty-btn{border:0;background:var(--soft);color:var(--text);font:inherit;font-size:20px;line-height:1;cursor:pointer;min-height:40px}.cart-qty-btn:hover{background:var(--soft-2);color:var(--navy)}.cart-qty-input{width:54px;min-width:0;border:0;border-left:1px solid var(--line);border-right:1px solid var(--line);border-radius:0;background:var(--input);color:var(--text);font:inherit;text-align:center;padding:0 4px;outline:0;-moz-appearance:textfield}.cart-qty-input::-webkit-outer-spin-button,.cart-qty-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}.cart-remove{text-align:right!important}.cart-remove form{display:flex;justify-content:flex-end}.cart-remove-btn{width:40px;height:40px;padding:0;display:grid;place-items:center;border:1px solid #f4c4c4;border-radius:10px;background:#fff3f3;color:#d92d20;cursor:pointer}.cart-remove-btn:hover{background:#ffe6e6;border-color:#eea5a5}.cart-remove-btn svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}:root[data-theme="dark"] .cart-remove-btn{background:#341b20;border-color:#66313b;color:#ff8f9a}:root[data-theme="dark"] .cart-remove-btn:hover{background:#442129}.cart-actions{justify-content:space-between}.cart-actions .btn{min-width:180px}.cart-total{border-top:0}
@media(max-width:900px){.cart-col-product{width:44%}.cart-col-price{width:12%}.cart-col-qty{width:20%}.cart-col-sum{width:14%}.cart-col-remove{width:10%}.cart-table th,.cart-table td{padding-left:9px;padding-right:9px}.cart-qty-control{grid-template-columns:34px 48px 34px}.cart-qty-input{width:48px}}
@media(max-width:800px){.cart-content-card{padding:18px;background:transparent;border:0;box-shadow:none}.cart-table,.cart-table tbody{display:block}.cart-table colgroup,.cart-table thead{display:none}.cart-table tr.cart-item-row{display:grid;grid-template-columns:minmax(300px,1fr) minmax(82px,max-content) minmax(116px,max-content) minmax(90px,max-content) auto;grid-template-areas:"product price quantity sum remove";align-items:center;gap:14px;margin-bottom:14px;padding:16px;background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:0 6px 20px var(--shadow)}.cart-table td{display:block;padding:0;border:0}.cart-product{grid-area:product;min-width:300px;font-size:16px;align-self:center}.cart-price{grid-area:price;align-self:center}.cart-quantity{grid-area:quantity;align-self:center}.cart-sum{grid-area:sum;align-self:center}.cart-remove{grid-area:remove!important;justify-self:end;align-self:center}.cart-product,.cart-price,.cart-quantity,.cart-sum{margin:0!important;padding:0!important;border:0!important;text-align:left!important}.cart-product::before,.cart-price::before,.cart-quantity::before,.cart-sum::before{content:attr(data-label);display:block;margin-bottom:7px;color:var(--muted);font-size:12px;font-weight:500;text-align:left}.cart-price,.cart-quantity,.cart-sum{justify-self:end}.cart-remove form{justify-content:flex-end}.cart-qty-control{display:grid;width:max-content;grid-template-columns:34px 48px 34px}.cart-qty-input{width:48px}.cart-total{margin-top:4px;padding:17px 16px;background:var(--soft);border:1px solid var(--line);border-radius:14px}.cart-actions{margin-bottom:0}.cart-actions .btn{flex:1;min-width:0}.cart-table-wrap{overflow:visible}}
@media(max-width:760px){.cart-table tr.cart-item-row{grid-template-columns:minmax(300px,1fr) minmax(82px,max-content) minmax(116px,max-content) auto;grid-template-areas:"product price quantity remove" "sum sum sum sum"}.cart-sum{width:100%;justify-self:stretch!important;align-self:stretch;margin-top:4px!important;padding-top:10px!important;border-top:1px solid var(--line)!important;text-align:left!important}}
@media(max-width:670px){.cart-table tr.cart-item-row{grid-template-columns:minmax(300px,1fr) minmax(82px,max-content) auto;grid-template-areas:"product price remove" "quantity quantity quantity" "sum sum sum"}.cart-quantity,.cart-sum{width:100%;justify-self:stretch!important;align-self:stretch;margin-top:4px!important;padding-top:10px!important;border-top:1px solid var(--line)!important;text-align:left!important}.cart-actions{flex-direction:column}.cart-actions .btn{width:100%}}
@media(max-width:540px){.cart-table tr.cart-item-row{grid-template-columns:minmax(300px,1fr) auto;grid-template-areas:"product remove" "price price" "quantity quantity" "sum sum"}.cart-price,.cart-quantity,.cart-sum{width:100%;justify-self:stretch!important;align-self:stretch;margin-top:4px!important;padding-top:10px!important;border-top:1px solid var(--line)!important;text-align:left!important}.cart-price::before,.cart-quantity::before,.cart-sum::before{margin-bottom:6px}.cart-remove{align-self:center}.cart-total strong{font-size:25px}}
@media(max-width:390px){.cart-table tr.cart-item-row{grid-template-columns:minmax(0,1fr) auto}.cart-product{min-width:0}}
</style>
<?php render_footer();
