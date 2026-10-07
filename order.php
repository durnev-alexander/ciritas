<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/order_compat.php';
require __DIR__.'/includes/layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['_order_csrf'])) $_SESSION['_order_csrf'] = bin2hex(random_bytes(24));

$products = [];
$prices = [];
$error = '';
$successId = 0;
$selectedProduct = (int)($_GET['product'] ?? $_POST['product_id'] ?? 0);
$selectedPrice = (int)($_POST['price_id'] ?? 0);

try {
    $products = db()->query('SELECT ID,Name,ShortDescr FROM csSoftProducts WHERE IsActive=1 ORDER BY Name')->fetchAll();
    if ($selectedProduct > 0) {
        $q = db()->prepare('SELECT * FROM csSoftPrices WHERE SoftProductID=? ORDER BY ID');
        $q->execute([$selectedProduct]);
        $prices = $q->fetchAll();
    }
} catch (Throwable) {
    $error = 'Не удалось загрузить список продуктов. Проверьте подключение к базе данных.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $token = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['_order_csrf'], $token)) {
        $error = 'Сессия формы устарела. Обновите страницу и повторите отправку.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $company = trim((string)($_POST['company'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $comment = trim((string)($_POST['comment'] ?? ''));

        if ($selectedProduct < 1 || $name === '' || ($email === '' && $phone === '')) {
            $error = 'Заполните продукт, имя и хотя бы один способ связи: телефон или E-mail.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Укажите корректный E-mail.';
        } else {
            try {
                $productName = '';
                foreach ($products as $product) {
                    if ((int)$product['ID'] === $selectedProduct) {
                        $productName = legacy($product['Name']);
                        break;
                    }
                }
                if ($productName === '') throw new RuntimeException('Выбранный продукт не найден.');

                $priceTitle = '';
                if ($selectedPrice > 0) {
                    $pq = db()->prepare('SELECT * FROM csSoftPrices WHERE ID=? AND SoftProductID=? LIMIT 1');
                    $pq->execute([$selectedPrice, $selectedProduct]);
                    $priceRow = $pq->fetch();
                    if ($priceRow) $priceTitle = legacy((string)($priceRow['Title'] ?? $priceRow['Name'] ?? $priceRow['Price'] ?? ''));
                }

                $details = 'Продукт: '.$productName;
                if ($priceTitle !== '') $details .= "\nВариант: ".$priceTitle;
                if ($comment !== '') $details .= "\nКомментарий: ".$comment;

                $values = [];
                foreach ([
                    ['Name','FIO','CustomerName'],
                    ['Company','Organization','CompanyName'],
                    ['Email','EMail','Mail'],
                    ['Phone','Telephone'],
                    ['Comment','Comments','Description','OrderText'],
                    ['SoftProductID','ProductID'],
                    ['SoftPriceID','PriceID'],
                    ['OrderDate','Date','CreatedAt','CreateDate'],
                ] as $group) {
                    $column = order_first_column($group);
                    if (!$column) continue;
                    $first = $group[0];
                    $value = match ($first) {
                        'Name' => $name,
                        'Company' => $company,
                        'Email' => $email,
                        'Phone' => $phone,
                        'Comment' => $details,
                        'SoftProductID' => $selectedProduct,
                        'SoftPriceID' => $selectedPrice ?: null,
                        'OrderDate' => date('Y-m-d H:i:s'),
                        default => '',
                    };
                    if (is_string($value)) $value = order_db_text($value);
                    $values[$column] = $value;
                }

                $successId = order_insert($values);
                $_SESSION['_order_csrf'] = bin2hex(random_bytes(24));
            } catch (Throwable $e) {
                $error = 'Не удалось сохранить заказ в существующей базе. Проверьте структуру таблицы csOrders и настройки подключения.';
            }
        }
    }
}

render_header('Оформление заказа');
?>
<section class="section"><div class="container order-layout">
<div>
<h1>Оформление заказа</h1>
<p class="lead">Выберите программу и оставьте контактные данные. Заказ будет зарегистрирован в существующей системе CIRITAS.</p>
<?php if ($successId): ?>
<div class="content-card success-box"><h2>Заказ принят</h2><p>Номер заказа: <b>#<?=htmlspecialchars((string)$successId)?></b>. Мы свяжемся с вами по указанным контактам.</p><a class="btn btn-primary" href="products.php">Вернуться к продуктам</a></div>
<?php else: ?>
<?php if ($error): ?><div class="form-message error-box"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form class="content-card order-form" method="post" action="order.php">
<input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_order_csrf'])?>">
<label class="field"><span>Продукт *</span><select class="input" name="product_id" required onchange="this.form.submit()"><option value="">Выберите продукт</option><?php foreach($products as $p): ?><option value="<?=(int)$p['ID']?>" <?=$selectedProduct===(int)$p['ID']?'selected':''?>><?=htmlspecialchars(legacy($p['Name']))?></option><?php endforeach; ?></select></label>
<?php if ($selectedProduct && $prices): ?><label class="field"><span>Вариант / лицензия</span><select class="input" name="price_id"><option value="">Не выбран</option><?php foreach($prices as $price): $label=legacy((string)($price['Title']??$price['Name']??'Вариант')); if(isset($price['Price']) && $price['Price']!=='') $label.=' — '.legacy((string)$price['Price']); ?><option value="<?=(int)$price['ID']?>" <?=$selectedPrice===(int)$price['ID']?'selected':''?>><?=htmlspecialchars($label)?></option><?php endforeach; ?></select></label><?php endif; ?>
<div class="form-grid-public">
<label class="field"><span>Имя / ФИО *</span><input class="input" name="name" required maxlength="160" value="<?=htmlspecialchars((string)($_POST['name']??''))?>"></label>
<label class="field"><span>Организация</span><input class="input" name="company" maxlength="200" value="<?=htmlspecialchars((string)($_POST['company']??''))?>"></label>
<label class="field"><span>E-mail</span><input class="input" type="email" name="email" maxlength="200" value="<?=htmlspecialchars((string)($_POST['email']??''))?>"></label>
<label class="field"><span>Телефон</span><input class="input" name="phone" maxlength="80" value="<?=htmlspecialchars((string)($_POST['phone']??''))?>"></label>
</div>
<label class="field"><span>Комментарий</span><textarea class="input" name="comment" rows="6" maxlength="3000"><?=htmlspecialchars((string)($_POST['comment']??''))?></textarea></label>
<p class="muted">Укажите телефон или E-mail, чтобы мы могли связаться с вами.</p>
<button class="btn btn-primary" type="submit">Оформить заказ</button>
</form>
<?php endif; ?>
</div>
<aside class="content-card order-note"><h2>Что будет дальше</h2><p>Заказ сохраняется в действующей базе сайта. Сотрудник сможет открыть его в новой административной панели и продолжить обработку.</p><p class="muted">Оплата на этой форме не производится.</p></aside>
</div></section>
<?php render_footer();
