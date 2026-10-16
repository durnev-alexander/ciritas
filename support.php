<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/mailer.php';
require __DIR__.'/includes/layout.php';

if(session_status()!==PHP_SESSION_ACTIVE) session_start();
if(empty($_SESSION['_support_csrf'])) $_SESSION['_support_csrf']=bin2hex(random_bytes(24));

function public_page_html(?string $value): string {
    $html=legacy($value??'');
    if($html==='') return '';
    for($i=0;$i<2 && (str_contains($html,'&lt;')||str_contains($html,'&gt;'));$i++){
        $decoded=html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8');
        if($decoded===$html) break;
        $html=$decoded;
    }
    return $html;
}

function support_page_html(string $pageName): string {
    try{
        $q=db()->prepare('SELECT Content FROM csPages WHERE PageName=? ORDER BY ID LIMIT 1');
        $q->execute([$pageName]);
        $row=$q->fetch();
        return $row?public_page_html($row['Content']??''):'';
    }catch(Throwable){
        return '';
    }
}

function support_next_letter_num(): int {
    $pdo=db();
    $pdo->beginTransaction();
    try{
        $q=$pdo->prepare('SELECT ID,Value FROM csSettings WHERE Name=? ORDER BY ID LIMIT 1 FOR UPDATE');
        $q->execute(['LastLetterNum']);
        $row=$q->fetch();
        if($row){
            $next=max(0,(int)$row['Value'])+1;
            $u=$pdo->prepare('UPDATE csSettings SET Value=? WHERE ID=?');
            $u->execute([$next,(int)$row['ID']]);
        }else{
            $next=1;
            $i=$pdo->prepare('INSERT INTO csSettings (Name,Value) VALUES (?,?)');
            $i->execute(['LastLetterNum',$next]);
        }
        $pdo->commit();
        return $next;
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

$sentTicket=0;
$sentMessageHtml='';
$formError='';
$form=[
    'name'=>trim((string)($_POST['name']??'')),
    'email'=>trim((string)($_POST['email']??'')),
    'topic'=>(string)($_POST['topic']??'Вопрос'),
    'message'=>trim((string)($_POST['message']??'')),
];
$allowedTopics=['Вопрос','Ошибка','Предложение','Важно'];
if(!in_array($form['topic'],$allowedTopics,true)) $form['topic']='Вопрос';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!hash_equals((string)$_SESSION['_support_csrf'],(string)($_POST['_csrf']??''))) throw new RuntimeException('Сессия формы устарела. Обновите страницу и повторите отправку.');
        if(trim((string)($_POST['website']??''))!=='') throw new RuntimeException('Не удалось отправить обращение.');
        if($form['name']==='') throw new RuntimeException('Укажите имя.');
        if(!filter_var($form['email'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Укажите корректный электронный адрес.');
        if($form['message']==='') throw new RuntimeException('Введите текст обращения.');
        if(strlen($form['message'])>10000) throw new RuntimeException('Текст обращения слишком большой.');

        $ticket=support_next_letter_num();
        $to=trim((string)cfg('mail.support_to','support@ciritas.ru')) ?: 'support@ciritas.ru';
        $sentAt=date('d.m.Y H:i:s');
        $ip=trim((string)($_SERVER['REMOTE_ADDR']??''));
        $userAgent=trim((string)($_SERVER['HTTP_USER_AGENT']??''));

        $supportSubject='Обращение в техподдержку № '.$ticket.' — '.$form['topic'];
        $supportBody="Обращение в службу технической поддержки № {$ticket}\n"
            ."Дата и время: {$sentAt}\n"
            ."Тема: {$form['topic']}\n"
            ."Имя: {$form['name']}\n"
            ."E-mail для ответа: {$form['email']}\n"
            .($ip!==''?"IP: {$ip}\n":'')
            .($userAgent!==''?"Браузер: {$userAgent}\n":'')
            ."\nТекст обращения:\n{$form['message']}\n";

        $copySubject='Копия обращения в техподдержку ЦИРИТАС № '.$ticket;
        $copyBody="Здравствуйте, {$form['name']}!\n\n"
            ."От вашего имени направлено следующее обращение в службу технической поддержки компании ЦИРИТАС:\n\n"
            ."Номер обращения: {$ticket}\n"
            ."Дата и время: {$sentAt}\n"
            ."Тема: {$form['topic']}\n\n"
            ."Текст обращения:\n{$form['message']}\n\n"
            ."После рассмотрения обращения ответ будет направлен на этот электронный адрес.\n\n"
            ."С Уважением, служба тех.поддержки компании ЦИРИТАС\n";

        mailer_send($to,$supportSubject,$supportBody,$form['email']);
        mailer_send($form['email'],$copySubject,$copyBody,$to);

        $sentTicket=$ticket;
        $template=support_page_html('SupportMessage');
        if($template===''){
            $template='Обращение в службу технической поддержки № [LetterNum] отправлено, после выявления проблемы на указанный электронный ящик вам будет дан ответ.<br>С Уважением, служба тех.поддержки компании ЦИРИТАС';
        }
        $sentMessageHtml=str_replace('[LetterNum]',htmlspecialchars((string)$ticket,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),$template);
        $_SESSION['_support_csrf']=bin2hex(random_bytes(24));
        $form=['name'=>'','email'=>'','topic'=>'Вопрос','message'=>''];
    }catch(Throwable $e){
        $formError=$e->getMessage();
    }
}

render_header('Поддержка');
?>
<section class="section public-compact-section"><div class="container">
<div class="section-head"><div><h1>Поддержка</h1><p class="muted">Информация о технической поддержке</p></div></div>
<?php
$html=support_page_html('Support');
if($html!==''){
    echo '<div class="content-card support-info-card"><h2>Техническая поддержка в приложениях</h2><p class="muted">Используйте встроенный в приложение модуль обращения в техподдержку</p><div class="public-page-html">'.$html.'</div></div>';
}else{
    echo '<div class="content-card support-info-card"><h2>Техническая поддержка в приложениях</h2><p class="muted">Используйте встроенный в приложение модуль обращения в техподдержку</p><p>Свяжитесь с нами для получения технической поддержки.</p></div>';
}
?>

<div class="content-card support-request-card">
    <h2>Обращение в техническую поддержку</h2>
    <?php if($sentTicket>0): ?>
        <div class="support-success public-page-html" role="status"><?=$sentMessageHtml?></div>
    <?php else: ?>
        <p class="muted">Опишите вопрос или проблему. Ответ будет направлен на указанный электронный адрес.</p>
        <?php if($formError!==''): ?><div class="form-message error-box"><?=htmlspecialchars($formError,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></div><?php endif; ?>
        <form class="support-form" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?=htmlspecialchars((string)$_SESSION['_support_csrf'],ENT_QUOTES)?>">
            <div class="support-honeypot" aria-hidden="true"><label>Сайт<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="form-grid-public">
                <label class="field">Имя<input class="input" type="text" name="name" maxlength="150" required value="<?=htmlspecialchars($form['name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"></label>
                <label class="field">Эл. ящик<input class="input" type="email" name="email" maxlength="254" required value="<?=htmlspecialchars($form['email'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"></label>
            </div>
            <label class="field">Тема<select class="input" name="topic"><?php foreach($allowedTopics as $topic): ?><option value="<?=htmlspecialchars($topic,ENT_QUOTES)?>" <?=$form['topic']===$topic?'selected':''?>><?=htmlspecialchars($topic,ENT_QUOTES)?></option><?php endforeach; ?></select></label>
            <label class="field">Сообщение<textarea class="input support-message" name="message" rows="8" maxlength="10000" required><?=htmlspecialchars($form['message'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></textarea></label>
            <div class="support-actions"><button class="btn btn-primary" type="submit">Отправить</button></div>
        </form>
    <?php endif; ?>
</div>
</div></section>
<style>
.support-info-card h2,.support-request-card h2{margin-top:0}.support-info-card>.muted,.support-request-card>.muted{margin-top:-6px}.support-form{display:grid;gap:18px}.support-message{resize:vertical;min-height:150px}.support-actions{display:flex;justify-content:flex-start}.support-honeypot{position:absolute!important;left:-10000px!important;width:1px!important;height:1px!important;overflow:hidden!important}.support-success{padding:18px 20px;border:1px solid #86d6a6;border-radius:14px;background:#eefbf3;color:#17663a;line-height:1.65;font-weight:600}:root[data-theme="dark"] .support-success{background:#123022;color:#91e4b9;border-color:#2d7950}
</style>
<?php render_footer();
