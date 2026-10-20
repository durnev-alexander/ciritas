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

function cart_sort_column(string $table): ?string {
    try {
        $cols=db()->query('SHOW COLUMNS FROM `'.str_replace('`','',$table).'`')->fetchAll();
        $names=[];
        foreach($cols as $col){
            $name=(string)($col['Field']??'');
            if($name!=='') $names[]=$name;
        }
        foreach(['OrderIndex','SortOrder','SortIndex','Position','OrderNum','OrderID','Sort','Order'] as $candidate){
            foreach($names as $name){
                if(strcasecmp($name,$candidate)===0) return $name;
            }
        }
        foreach($names as $name){
            if(in_array(strtolower($name),['id','softgroupid','softproductid'],true)) continue;
            if(preg_match('/(order|sort|position|index)/i',$name)) return $name;
        }
    } catch(Throwable) {}
    return null;
}

function cart_order_part(string $alias, ?string $column, string $fallback): string {
    if($column!==null) return $alias.'.`'.str_replace('`','',$column).'`, '.$alias.'.ID';
    return $fallback==='ID' ? $alias.'.ID' : $alias.'.`'.str_replace('`','',$fallback).'`, '.$alias.'.ID';
}

function cart_catalog_licenses(): array {
    try {
        $groupOrder=cart_sort_column('csSoftGroups');
        $productOrder=cart_sort_column('csSoftProducts');
        $priceOrder=cart_sort_column('csSoftPrices');
        $order=cart_order_part('g',$groupOrder,'Name').', '.cart_order_part('p',$productOrder,'Name').', '.cart_order_part('pr',$priceOrder,'ID');
        $sql='SELECT pr.*, p.Name AS ProductName, p.OrderAllow FROM csSoftPrices pr JOIN csSoftProducts p ON p.ID=pr.SoftProductID LEFT JOIN csSoftGroups g ON g.ID=p.SoftGroupID WHERE p.IsActive=1 ORDER BY '.$order;
        return db()->query($sql)->fetchAll();
    } catch(Throwable) {
        return [];
    }
}

function cart_render_summary(array $items, float $total): string {
    ob_start();
    if(!$items): ?>
<div class="content-card cart-content-card"><h2>Корзина пуста</h2><p class="muted">Добавьте нужную лицензию из списка ниже.</p></div>
<?php else: ?>
<div class="content-card cart-content-card"><div class="cart-list">
<?php foreach($items as $item): $sum=(float)$item['Price']*(int)$item['Amount']; ?>
<article class="cart-item-row">
    <div class="cart-field cart-product">
        <div class="cart-field-label">Продукт</div>
        <div class="cart-field-value cart-product-value"><div class="cart-product-line"><strong><?=htmlspecialchars(legacy((string)$item['ProductName']))?></strong><span class="cart-license-inline"><?=htmlspecialchars(legacy((string)$item['Title']))?></span></div></div>
    </div>
    <div class="cart-metrics">
        <div class="cart-field cart-price"><div class="cart-field-label">Цена</div><div class="cart-field-value"><?=number_format((float)$item['Price'],0,',',' ')?> ₽</div></div>
        <div class="cart-field cart-quantity">
            <div class="cart-field-label">Количество</div>
            <div class="cart-field-value"><form class="cart-qty-control" method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_cart_csrf'])?>"><input type="hidden" name="action" value="update"><input type="hidden" name="item_id" value="<?=(int)$item['ID']?>"><?php if((int)$item['Amount']<=1): ?><button class="cart-qty-btn cart-qty-remove-btn" type="submit" name="remove" value="1" title="Удалить из корзины" aria-label="Удалить из корзины"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg></button><?php else: ?><button class="cart-qty-btn" type="submit" name="delta" value="-1" aria-label="Уменьшить количество">−</button><?php endif; ?><input class="cart-qty-input" type="number" min="1" max="999" name="amount" value="<?=(int)$item['Amount']?>" aria-label="Количество"><button class="cart-qty-btn" type="submit" name="delta" value="1" aria-label="Увеличить количество">+</button></form></div>
        </div>
        <div class="cart-field cart-sum"><div class="cart-field-label">Сумма</div><div class="cart-field-value"><strong><?=number_format($sum,0,',',' ')?> ₽</strong></div></div>
    </div>
</article>
<?php endforeach; ?>
</div><div class="cart-total"><span>Итого</span><strong><?=number_format($total,0,',',' ')?> ₽</strong></div><div class="product-actions cart-actions"><a class="btn btn-primary" href="order.php">Оформить заказ</a></div></div>
<?php endif;
    return (string)ob_get_clean();
}

if($_SERVER['REQUEST_METHOD']==='GET'){
    $ajax=(string)($_GET['ajax']??'');
    if($ajax==='state'){
        header('Content-Type: application/json; charset=utf-8');
        try{echo json_encode(cart_ajax_payload(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(Throwable){http_response_code(500);echo json_encode(['ok'=>false,'message'=>'Не удалось загрузить корзину.'],JSON_UNESCAPED_UNICODE);}
        exit;
    }
    if($ajax==='summary'){
        header('Content-Type: text/html; charset=utf-8');
        try{echo cart_render_summary(cart_items(),cart_total());}catch(Throwable){http_response_code(500);echo '<div class="form-message error-box">Не удалось обновить корзину.</div>';}
        exit;
    }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $isAjax=(string)($_POST['_ajax']??'')==='1';
    if(!hash_equals((string)$_SESSION['_cart_csrf'],(string)($_POST['_csrf']??''))){$error='Сессия корзины устарела. Обновите страницу.';}
    else{
        try{
            $action=isset($_POST['remove'])?'remove':(string)($_POST['action']??'');
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
        }catch(Throwable){$error='Не удалось изменить корзину.';}
    }
    if($isAjax){header('Content-Type: application/json; charset=utf-8');http_response_code(400);echo json_encode(['ok'=>false,'message'=>$error,'csrf'=>(string)$_SESSION['_cart_csrf']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
}

$items=[];$total=0.0;$catalog=[];
try{$items=cart_items();$total=cart_total();}catch(Throwable){$error=$error?:'Не удалось загрузить корзину.';}
$catalog=cart_catalog_licenses();

render_header('Корзина');
?>
<section class="section public-compact-section cart-public-section"><div class="container"><div class="section-head"><div><h1>Корзина</h1><p class="muted">Выбранные лицензии для оформления заказа</p></div></div>
<?php if($error): ?><div class="form-message error-box"><?=htmlspecialchars($error)?></div><?php endif; ?>
<div data-cart-summary><?=cart_render_summary($items,$total)?></div>
<?php if($catalog): ?><div class="content-card cart-license-catalog"><h2>Продукты и лицензии</h2><div class="license-list">
<?php foreach($catalog as $price): $title=legacy((string)($price['Title']??$price['Name']??'Лицензия')); ?>
<div class="license-row"><div><div><strong><?=htmlspecialchars(legacy((string)$price['ProductName']))?></strong> <?=htmlspecialchars($title)?></div><div class="muted"><?=number_format((float)($price['Price']??0),0,',',' ')?> ₽</div></div><?php if((int)($price['OrderAllow']??1)===1): ?><form class="inline-form" method="post" action="cart.php"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_cart_csrf'])?>"><input type="hidden" name="action" value="add"><input type="hidden" name="price_id" value="<?=(int)$price['ID']?>"><input class="input qty-input" type="number" name="amount" min="1" max="999" value="1"><button class="btn btn-primary" type="submit">В корзину</button></form><?php endif; ?></div>
<?php endforeach; ?>
</div></div><?php endif; ?>
</div></section>
<style>
.cart-content-card{padding:0;background:transparent;border:0;box-shadow:none}.cart-content-card h2{margin:0 0 6px}.cart-content-card>p.muted{margin:0 0 18px}.cart-list{display:grid;gap:14px}.cart-item-row{display:flex;flex-wrap:wrap;align-items:flex-start;column-gap:18px;row-gap:8px;padding:16px;background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:0 6px 20px var(--shadow)}.cart-field{display:grid;grid-template-rows:18px minmax(40px,1fr);align-content:stretch;min-width:0;padding:0}.cart-field-label{font-size:12px;line-height:18px;color:var(--muted);font-weight:500;white-space:nowrap}.cart-field-value{display:flex;align-items:center;min-height:40px;line-height:1.35}.cart-product{flex:1 1 300px;min-width:300px}.cart-product-value{display:flex;align-items:center;white-space:normal;min-height:40px}.cart-product-line{line-height:1.35;overflow-wrap:anywhere}.cart-product strong{display:inline;overflow-wrap:anywhere}.cart-license-inline{display:inline;margin-left:8px;color:var(--muted);font-size:14px;line-height:1.35;overflow-wrap:anywhere}.cart-metrics{flex:0 0 auto;display:grid;grid-template-columns:100px 118px 110px;column-gap:18px;align-items:start;max-width:100%}.cart-price,.cart-sum{white-space:nowrap}.cart-qty-control{display:inline-grid;grid-template-columns:34px 48px 34px;align-items:stretch;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:var(--input)}.cart-qty-btn{border:0;background:var(--soft);color:var(--text);font:inherit;font-size:20px;line-height:1;cursor:pointer;min-height:40px}.cart-qty-btn:hover{background:var(--soft-2);color:var(--navy)}.cart-qty-input{width:48px;min-width:0;border:0;border-left:1px solid var(--line);border-right:1px solid var(--line);border-radius:0;background:var(--input);color:var(--text);font:inherit;text-align:center;padding:0 4px;outline:0;-moz-appearance:textfield}.cart-qty-input::-webkit-outer-spin-button,.cart-qty-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}.cart-qty-remove-btn{display:grid;place-items:center;background:#fff3f3;color:#d92d20}.cart-qty-remove-btn:hover{background:#ffe6e6;color:#d92d20}.cart-qty-remove-btn svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}:root[data-theme="dark"] .cart-qty-remove-btn{background:#341b20;color:#ff8f9a}:root[data-theme="dark"] .cart-qty-remove-btn:hover{background:#442129;color:#ff8f9a}.cart-total{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:14px;padding:17px 16px;background:var(--soft);border:1px solid var(--line);border-radius:14px}.cart-total span{margin-right:auto;text-align:left}.cart-total strong{margin-left:auto;text-align:right;white-space:nowrap}.cart-actions{display:flex;flex-wrap:nowrap;align-items:center;justify-content:flex-end;gap:12px}.cart-actions .btn{flex:0 0 180px;min-width:180px;max-width:180px;white-space:nowrap}.cart-license-catalog{margin-top:24px}.cart-license-catalog h2{margin-top:0}.cart-license-catalog .license-row>div:first-child{min-width:0}
@media(max-width:800px){.cart-item-row{display:grid;grid-template-columns:minmax(0,1fr);row-gap:4px}.cart-product{min-width:0;width:100%}.cart-metrics{justify-self:start}.cart-actions{margin-bottom:0}.cart-license-catalog .license-row{align-items:flex-start}}
@media(max-width:620px){.cart-product{min-width:0;width:100%}}
@media(max-width:420px){.cart-item-row{padding:14px 12px;row-gap:4px}.cart-metrics{width:100%;grid-template-columns:minmax(0,1fr) 116px minmax(0,1fr);column-gap:8px}.cart-price,.cart-sum{min-width:0}.cart-total strong{font-size:25px}.cart-actions .btn{flex:0 0 180px;min-width:180px;max-width:180px}}
</style>
<?php render_footer();
