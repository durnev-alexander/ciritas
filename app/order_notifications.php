<?php
require_once __DIR__.'/mailer.php';

function order_notification_recipients(): array {
    $value = cfg('mail.order_to', '');
    if (is_array($value)) {
        $recipients = array_values(array_filter(array_map('trim', $value)));
    } else {
        $recipients = array_values(array_filter(array_map('trim', explode(',', (string)$value))));
    }

    if (!$recipients) {
        $fallback = trim((string)cfg('mail.support_to', ''));
        if ($fallback !== '') $recipients = [$fallback];
    }

    return $recipients;
}

function order_send_notification(array $order): bool {
    if (!cfg('mail.enabled', false)) return false;
    $recipients = order_notification_recipients();
    if (!$recipients) return false;

    $number = (string)($order['number'] ?? '');
    $subject = 'Новый заказ ЦИРИТАС'.($number !== '' ? ' № '.$number : '');
    $lines = [
        'На сайте ЦИРИТАС оформлен новый заказ.',
        '',
        'Номер: '.($number !== '' ? $number : '—'),
        'Сумма: '.number_format((float)($order['total'] ?? 0), 0, ',', ' ').' ₽',
        'Заказчик: '.trim((string)($order['organization'] ?? '')),
        'Контакт: '.trim((string)($order['contact'] ?? '')),
        'Телефон: '.trim((string)($order['phone'] ?? '')),
        'E-mail: '.trim((string)($order['email'] ?? '')),
    ];
    $body = implode("\n", $lines);
    $replyTo = trim((string)($order['email'] ?? ''));
    if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $replyTo = null;

    try {
        foreach ($recipients as $recipient) {
            $recipient = trim((string)$recipient);
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) continue;
            mailer_send($recipient, $subject, $body, $replyTo);
        }
        return true;
    } catch (Throwable) {
        return false;
    }
}
