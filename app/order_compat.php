<?php

function order_columns(): array {
    static $columns;
    if (is_array($columns)) return $columns;
    $columns = [];
    try {
        foreach (db()->query('SHOW COLUMNS FROM csOrders')->fetchAll() as $row) {
            $columns[(string)$row['Field']] = $row;
        }
    } catch (Throwable) {
        // Keep an empty set; callers can show a configuration error safely.
    }
    return $columns;
}

function order_has_column(string $name): bool {
    return isset(order_columns()[$name]);
}

function order_first_column(array $candidates): ?string {
    foreach ($candidates as $name) {
        if (order_has_column($name)) return $name;
    }
    return null;
}

function order_db_text(string $value): string {
    $charset = strtolower((string)cfg('db.charset', 'latin1'));
    if ($value === '' || str_contains($charset, 'utf8')) return $value;
    return mb_convert_encoding($value, 'Windows-1251', 'UTF-8');
}

function order_insert(array $values): int {
    $columns = order_columns();
    $data = [];
    foreach ($values as $column => $value) {
        if (isset($columns[$column])) $data[$column] = $value;
    }
    if (!$data) throw new RuntimeException('Не удалось сопоставить поля заказа со схемой csOrders.');

    $names = array_keys($data);
    $quoted = array_map(static fn(string $name): string => '`'.$name.'`', $names);
    $sql = 'INSERT INTO csOrders ('.implode(',', $quoted).') VALUES ('.implode(',', array_fill(0, count($names), '?')).')';
    db()->prepare($sql)->execute(array_values($data));
    return (int)db()->lastInsertId();
}
