<?php

function home_products_ensure_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    db()->exec("CREATE TABLE IF NOT EXISTS csHomeProducts (
        ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
        SoftProductID INT NOT NULL,
        SortOrder INT NOT NULL DEFAULT 0,
        PRIMARY KEY (ID),
        UNIQUE KEY uq_csHomeProducts_product (SoftProductID),
        KEY ix_csHomeProducts_sort (SortOrder, ID)
    ) ENGINE=InnoDB");

    $count = (int)db()->query('SELECT COUNT(*) FROM csHomeProducts')->fetchColumn();
    if ($count > 0) return;

    $order = 'ID DESC';
    try {
        $cols = db()->query('SHOW COLUMNS FROM csSoftProducts')->fetchAll();
        foreach ($cols as $col) {
            if (($col['Field'] ?? '') === 'OrderIndex') {
                $order = 'OrderIndex, Name';
                break;
            }
        }
    } catch (Throwable) {}

    $rows = db()->query('SELECT ID FROM csSoftProducts WHERE IsActive=1 ORDER BY '.$order.' LIMIT 6')->fetchAll();
    $insert = db()->prepare('INSERT IGNORE INTO csHomeProducts (SoftProductID, SortOrder) VALUES (?, ?)');
    $pos = 10;
    foreach ($rows as $row) {
        $insert->execute([(int)$row['ID'], $pos]);
        $pos += 10;
    }
}

function home_products_rows(): array {
    home_products_ensure_schema();
    $sql = 'SELECT p.* FROM csHomeProducts h JOIN csSoftProducts p ON p.ID=h.SoftProductID WHERE p.IsActive=1 ORDER BY h.SortOrder, h.ID';
    return db()->query($sql)->fetchAll();
}
