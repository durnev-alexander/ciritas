<?php

function order_notification_recipients(): array {
    $value = cfg('mail.order_to', '');
    if (is_array($value)) return array_values(array_filter(array_map('trim', $value)));
    return array_values(array_filter(array_map('trim', explode(',', (string)$value))));
}

function order_send_notification(array $order): bool {
    if (!cfg('mail.enabled', false)) return false;
    $recipients = order_notification_recipients();
    if (!$recipients) return false;

    $number = (string)($order['number'] ?? '');
    $subject = 'Новый заказ CIRITAS'.($number !== '' ? ' № '.$number : '');
    $lines = [
        'На сайте CIRITAS оформлен новый заказ.',
        '',
        'Номер: '.($number !== '' ? $number : '—'),
        'Сумма: '.number_format((float)($order['total'] ?? 0), 0, ',', ' ').' ₽',
        'Заказчик: '.trim((string)($order['organization'] ?? '')),
        'Контакт: '.trim((string)($order['contact'] ?? '')),
        'Телефон: '.trim((string)($order['phone'] ?? '')),
        'E-mail: '.trim((string)($order['email'] ?? '')),
    ];
    $body = implode("\r\n", $lines);

    $from = trim((string)cfg('mail.from', ''));
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    if ($from !== '') $headers[] = 'From: '.$from;

    $ok = true;
    foreach ($recipients as $recipient) {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) continue;
        if (!@mail($recipient, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, implode("\r\n", $headers))) $ok = false;
    }
    return $ok;
}
