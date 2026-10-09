<?php

function site_setting_get(string $name, string $default=''): string {
    try {
        $q=db()->prepare('SELECT Value FROM csSettings WHERE Name=? LIMIT 1');
        $q->execute([$name]);
        $row=$q->fetch();
        if($row && array_key_exists('Value',$row)) return (string)$row['Value'];
    } catch(Throwable) {}
    return $default;
}

function site_setting_set(string $name, string $value): void {
    $q=db()->prepare('SELECT ID FROM csSettings WHERE Name=? LIMIT 1');
    $q->execute([$name]);
    $row=$q->fetch();
    if($row){
        db()->prepare('UPDATE csSettings SET Value=? WHERE ID=?')->execute([$value,(int)$row['ID']]);
        return;
    }
    db()->prepare('INSERT INTO csSettings (Name,Value) VALUES (?,?)')->execute([$name,$value]);
}

function home_news_count(): int {
    $raw=site_setting_get('HomeNewsCount','3');
    $count=(int)$raw;
    return max(0,min(50,$count));
}
