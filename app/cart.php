<?php

function cart_cookie_options(): array {
    return [
        'expires' => time() + 60 * 60 * 24 * 5,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function cart_current(): ?array {
    $id = (int)($_COOKIE['cartID'] ?? 0);
    $code = (string)($_COOKIE['cartCode'] ?? '');
    if ($id < 1 || $code === '') return null;
    $q = db()->prepare('SELECT * FROM cart WHERE ID=? AND Code=? AND (IsOrdered IS NULL OR IsOrdered=0) LIMIT 1');
    $q->execute([$id, $code]);
    return $q->fetch() ?: null;
}

function cart_create(): array {
    $code = bin2hex(random_bytes(8));
    db()->prepare('INSERT INTO cart (zDate, Code) VALUES (?, ?)')->execute([time(), $code]);
    $id = (int)db()->lastInsertId();
    setcookie('cartID', (string)$id, cart_cookie_options());
    setcookie('cartCode', $code, cart_cookie_options());
    $_COOKIE['cartID'] = (string)$id;
    $_COOKIE['cartCode'] = $code;
    return ['ID' => $id, 'Code' => $code];
}

function cart_get_or_create(): array {
    return cart_current() ?: cart_create();
}

function cart_add_price(int $priceId, int $amount = 1): void {
    if ($priceId < 1) throw new InvalidArgumentException('Не выбрана лицензия.');
    $q = db()->prepare('SELECT ID, SoftProductID FROM csSoftPrices WHERE ID=? LIMIT 1');
    $q->execute([$priceId]);
    $price = $q->fetch();
    if (!$price) throw new RuntimeException('Вариант лицензии не найден.');
    $cart = cart_get_or_create();
    $q = db()->prepare('SELECT ID FROM cart_items WHERE CartID=? AND PriceID=? LIMIT 1');
    $q->execute([(int)$cart['ID'], $priceId]);
    $itemId = (int)($q->fetchColumn() ?: 0);
    $amount = max(1, min(999, $amount));
    if ($itemId) {
        db()->prepare('UPDATE cart_items SET Amount=Amount+? WHERE ID=? AND CartID=?')->execute([$amount, $itemId, (int)$cart['ID']]);
    } else {
        db()->prepare('INSERT INTO cart_items (CartID, ProductID, PriceID, Amount) VALUES (?, ?, ?, ?)')->execute([(int)$cart['ID'], (int)$price['SoftProductID'], $priceId, $amount]);
    }
}

function cart_update_item(int $itemId, int $amount): void {
    $cart = cart_current();
    if (!$cart) return;
    if ($amount <= 0) {
        db()->prepare('DELETE FROM cart_items WHERE ID=? AND CartID=?')->execute([$itemId, (int)$cart['ID']]);
        return;
    }
    $amount = min(999, $amount);
    db()->prepare('UPDATE cart_items SET Amount=? WHERE ID=? AND CartID=?')->execute([$amount, $itemId, (int)$cart['ID']]);
}

function cart_remove_item(int $itemId): void {
    cart_update_item($itemId, 0);
}

function cart_items(?int $cartId = null): array {
    if ($cartId === null) {
        $cart = cart_current();
        if (!$cart) return [];
        $cartId = (int)$cart['ID'];
    }
    $sql = 'SELECT ci.ID,ci.CartID,ci.ProductID,ci.PriceID,ci.Amount,p.Title,p.Price,sp.Name AS ProductName FROM cart_items ci JOIN csSoftPrices p ON p.ID=ci.PriceID JOIN csSoftProducts sp ON sp.ID=ci.ProductID WHERE ci.CartID=? ORDER BY sp.Name,p.Title';
    $q = db()->prepare($sql);
    $q->execute([$cartId]);
    return $q->fetchAll();
}

function cart_total(?int $cartId = null): float {
    $total = 0.0;
    foreach (cart_items($cartId) as $item) $total += (float)$item['Price'] * (int)$item['Amount'];
    return $total;
}

function cart_clear_cookies(): void {
    $options = cart_cookie_options();
    $options['expires'] = time() - 3600;
    setcookie('cartID', '', $options);
    setcookie('cartCode', '', $options);
    unset($_COOKIE['cartID'], $_COOKIE['cartCode']);
}
