<?php
declare(strict_types=1);

// Independent UTF-8 connection: the legacy content connection uses latin1.
function product_seo_db(): PDO {
    static $pdo=null;
    if($pdo instanceof PDO) return $pdo;
    $dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',cfg('db.host'),(int)cfg('db.port',3306),cfg('db.name'));
    $pdo=new PDO($dsn,(string)cfg('db.user'),(string)cfg('db.password'),[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    return $pdo;
}

function product_seo_ensure_schema(): void {
    product_seo_db()->exec("CREATE TABLE IF NOT EXISTS csProductSeo (
        ProductID INT NOT NULL PRIMARY KEY,
        SeoTitle VARCHAR(255) NOT NULL DEFAULT '',
        SeoDescription VARCHAR(500) NOT NULL DEFAULT '',
        SeoKeywords TEXT NULL,
        IsIndexable TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function product_seo_find(int $productId):array {
    if($productId<1)return [];
    try {
        $q=product_seo_db()->prepare('SELECT SeoTitle,SeoDescription,SeoKeywords,IsIndexable FROM csProductSeo WHERE ProductID=?');
        $q->execute([$productId]);
        return $q->fetch()?:[];
    }catch(Throwable){return [];}
}

function product_seo_save(int $productId,array $values):void {
    $q=product_seo_db()->prepare('INSERT INTO csProductSeo (ProductID,SeoTitle,SeoDescription,SeoKeywords,IsIndexable)
        VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE
        SeoTitle=VALUES(SeoTitle),SeoDescription=VALUES(SeoDescription),
        SeoKeywords=VALUES(SeoKeywords),IsIndexable=VALUES(IsIndexable)');
    $q->execute([
        $productId,
        trim((string)($values['SeoTitle']??'')),
        trim((string)($values['SeoDescription']??'')),
        trim((string)($values['SeoKeywords']??'')),
        !empty($values['IsIndexable'])?1:0,
    ]);
}

function product_seo_hidden_ids():array {
    try {
        $ids=product_seo_db()->query('SELECT ProductID FROM csProductSeo WHERE IsIndexable=0')->fetchAll(PDO::FETCH_COLUMN);
        return array_fill_keys(array_map('intval',$ids),true);
    }catch(Throwable){return [];}
}
