<?php
require __DIR__.'/bootstrap.php';
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $u=(string)($_POST['user']??''); $p=(string)($_POST['pass']??'');
    if ($adminUser!=='' && $adminHash!=='' && hash_equals($adminUser,$u) && password_verify($p,$adminHash)) {
        $_SESSION['ciritas_admin']=true; redirect('index.php');
    }
    $error='Неверное имя пользователя или пароль';
}
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Вход — ЦИРИТАС</title><script>(function(){try{var t=localStorage.getItem('ciritas_theme');document.documentElement.dataset.theme=t==='dark'?'dark':'light'}catch(e){document.documentElement.dataset.theme='light'}})();</script><link rel="stylesheet" href="assets/css/admin.css"><link rel="stylesheet" href="assets/css/button-unify.css"></head><body><div class="login-wrap"><div class="login-card"><div class="login-brand"><img class="login-brand-logo admin-logo-light" src="../assets/img/ciritas-logo-light.png" alt="ЦИРИТАС"><img class="login-brand-logo admin-logo-dark" src="../assets/img/ciritas-logo-dark.png" alt="ЦИРИТАС"></div><p class="muted login-subtitle">Вход в административную панель</p><?php if($error): ?><div class="flash"><?=aesc($error)?></div><?php endif; ?><form method="post" class="login-form"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><div class="field"><label class="label" for="admin-user">Имя пользователя</label><input id="admin-user" class="input" name="user" autocomplete="username" required></div><div class="field"><label class="label" for="admin-pass">Пароль</label><input id="admin-pass" class="input" type="password" name="pass" autocomplete="current-password" required></div><button class="btn login-submit" type="submit">Войти</button></form></div></div></body></html>
