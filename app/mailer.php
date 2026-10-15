<?php

function mailer_read_response($socket): array {
    $lines=[];$code=0;
    while(($line=fgets($socket,515))!==false){
        $lines[]=$line;
        if(preg_match('/^(\d{3})([ -])/', $line, $m)){
            $code=(int)$m[1];
            if($m[2]===' ') break;
        }
    }
    return [$code,implode('',$lines)];
}

function mailer_expect($socket,array $ok): void {
    [$code,$text]=mailer_read_response($socket);
    if(!in_array($code,$ok,true)) throw new RuntimeException('SMTP error '.$code.': '.trim($text));
}

function mailer_command($socket,string $command,array $ok): void {
    fwrite($socket,$command."\r\n");
    mailer_expect($socket,$ok);
}

function mailer_encode_header(string $value): string {
    return '=?UTF-8?B?'.base64_encode($value).'?=';
}

function mailer_send_smtp(string $to,string $subject,string $body,?string $replyTo=null): void {
    if(!cfg('mail.enabled',false)) throw new RuntimeException('Отправка почты отключена в конфигурации.');
    $host=trim((string)cfg('mail.host',''));
    $port=(int)cfg('mail.port',587);
    $encryption=strtolower(trim((string)cfg('mail.encryption','tls')));
    $username=(string)cfg('mail.username','');
    $password=(string)cfg('mail.password','');
    $from=trim((string)cfg('mail.from',''));
    $fromName=trim((string)cfg('mail.from_name','ЦИРИТАС'));
    $timeout=max(5,(int)cfg('mail.timeout',15));
    if($host===''||$port<1||$from==='') throw new RuntimeException('Не заполнены параметры почтового сервера.');

    $remote=($encryption==='ssl'?'ssl://':'').$host.':'.$port;
    $socket=@stream_socket_client($remote,$errno,$errstr,$timeout,STREAM_CLIENT_CONNECT);
    if(!$socket) throw new RuntimeException('Не удалось подключиться к почтовому серверу: '.$errstr);
    stream_set_timeout($socket,$timeout);
    try{
        mailer_expect($socket,[220]);
        $hostname=preg_replace('/[^a-z0-9.-]/i','',(string)($_SERVER['SERVER_NAME']??'localhost')) ?: 'localhost';
        mailer_command($socket,'EHLO '.$hostname,[250]);
        if($encryption==='tls'){
            mailer_command($socket,'STARTTLS',[220]);
            $crypto=stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if($crypto!==true) throw new RuntimeException('Не удалось включить TLS для SMTP.');
            mailer_command($socket,'EHLO '.$hostname,[250]);
        }
        if($username!==''){
            mailer_command($socket,'AUTH LOGIN',[334]);
            mailer_command($socket,base64_encode($username),[334]);
            mailer_command($socket,base64_encode($password),[235]);
        }
        mailer_command($socket,'MAIL FROM:<'.$from.'>',[250]);
        mailer_command($socket,'RCPT TO:<'.$to.'>',[250,251]);
        mailer_command($socket,'DATA',[354]);

        $headers=[
            'Date: '.date(DATE_RFC2822),
            'From: '.mailer_encode_header($fromName).' <'.$from.'>',
            'To: <'.$to.'>',
            'Subject: '.mailer_encode_header($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $headers[]='Reply-To: <'.$replyTo.'>';
        $payload=implode("\r\n",$headers)."\r\n\r\n".str_replace(["\r\n","\r"],"\n",$body);
        $payload=str_replace("\n.","\n..",$payload);
        $payload=str_replace("\n","\r\n",$payload);
        fwrite($socket,$payload."\r\n.\r\n");
        mailer_expect($socket,[250]);
        @fwrite($socket,"QUIT\r\n");
    } finally {
        fclose($socket);
    }
}
