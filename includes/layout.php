<?php
function render_header(string $title='ЦИРИТАС'): void { ?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($title)?> — ЦИРИТАС</title><script>(function(){try{var t=localStorage.getItem('ciritas_theme');document.documentElement.dataset.theme=t==='dark'?'dark':'light'}catch(e){document.documentElement.dataset.theme='light'}})();</script><link rel="stylesheet" href="assets/css/site.css"><link rel="stylesheet" href="assets/css/cart-inline.css"><link rel="stylesheet" href="assets/css/public-compact.css"><link rel="stylesheet" href="assets/css/public-tweaks.css"></head><body><header class="site-header"><div class="container nav"><a class="brand" href="index.php"><img class="brand-logo brand-logo-light" src="assets/img/ciritas-logo-light.png" alt="ЦИРИТАС"><img class="brand-logo brand-logo-dark" src="assets/img/ciritas-logo-dark.png" alt="ЦИРИТАС"></a><div class="nav-right"><nav><a href="products.php">Продукты</a><a href="news.php">Новости</a><a href="support.php">Поддержка</a><a href="faq.php">ЧАВО</a><a href="contacts.php">Контакты</a><a href="cart.php">Корзина</a></nav><div class="theme-switch" role="group" aria-label="Тема оформления"><button class="theme-button" type="button" data-theme-value="light" title="Светлая тема" aria-label="Светлая тема"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.41M17.66 6.34l1.41-1.41"></path></svg></button><button class="theme-button" type="button" data-theme-value="dark" title="Тёмная тема" aria-label="Тёмная тема"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg></button></div></div></div></header><main><?php }
function render_footer(): void {
    $settings=[
        'FooterCopyright'=>'© 2003-'.date('Y').', ЦИРИТАС',
        'FooterEmail'=>'',
        'FooterPhone'=>'',
        'FooterPostalAddress'=>'',
    ];
    try{
        $q=db()->prepare("SELECT Name, Value FROM csSettings WHERE Name IN ('FooterCopyright','FooterEmail','FooterPhone','FooterPostalAddress') ORDER BY ID");
        $q->execute();
        foreach($q->fetchAll() as $row){
            $name=(string)($row['Name']??'');
            if(array_key_exists($name,$settings)) $settings[$name]=legacy((string)($row['Value']??''));
        }
    }catch(Throwable){}
    $copyright=htmlspecialchars($settings['FooterCopyright'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $address=htmlspecialchars($settings['FooterPostalAddress'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $phone=htmlspecialchars($settings['FooterPhone'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $email=htmlspecialchars($settings['FooterEmail'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    ?></main><footer class="site-footer"><div class="container site-footer-row"><div class="site-footer-copy"><?=$copyright?></div><div class="site-footer-contact"><span class="site-footer-address">Почтовый адрес: <?=$address?>,</span> <span class="site-footer-phone">тел.: <?=$phone?>,</span> <span class="site-footer-email">email: <?=$email?></span></div></div></footer><script src="assets/js/site.js"></script></body></html><?php }
