<?php
declare(strict_types=1);
// Local SMTP client; no third-party email service or remote JS dependency.
function pp_smtp(string $recipient,string $subject,string $body): void {
    $m=pp_config()['mail'];$from=pp_email($m['from']??'');pp_email($recipient);
    if(preg_match('/[\r\n]/',$subject))throw new RuntimeException('Invalid mail subject');
    $mode=$m['transport']??'smtp';
    // Used only by isolated acceptance tests; never enabled in delivery config.
    if($mode==='test'&&PHP_SAPI==='cli-server'&&parse_url(pp_origin(),PHP_URL_HOST)==='127.0.0.1'&&pp_config()['debug']===true){
        file_put_contents(__DIR__.'/cache/mail-test.jsonl',pp_json(compact('recipient','subject','body'))."\n",FILE_APPEND|LOCK_EX);return;
    }
    if($mode!=='smtp')throw new RuntimeException('SMTP required');
    $host=$m['host']??'';$port=(int)($m['port']??465);$encryption=$m['encryption']??'ssl';
    if(!preg_match('/^[a-z0-9.-]+$/i',$host)||!in_array($encryption,['ssl','tls'],true))throw new RuntimeException('Invalid SMTP config');
    $context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host,'allow_self_signed'=>false]]);
    $socket=stream_socket_client(($encryption==='ssl'?'ssl://':'tcp://').$host.':'.$port,$errno,$error,12,STREAM_CLIENT_CONNECT,$context);
    if(!$socket)throw new RuntimeException('SMTP unavailable');stream_set_timeout($socket,12);
    $expect=function(array $codes)use($socket){$line='';do{$line=fgets($socket,4096);if($line===false)throw new RuntimeException('SMTP closed');}while(isset($line[3])&&$line[3]==='-');if(!in_array((int)substr($line,0,3),$codes,true))throw new RuntimeException('SMTP rejected operation');};
    $send=function(string $command,array $codes)use($socket,$expect){fwrite($socket,$command."\r\n");$expect($codes);};
    try{
        $expect([220]);$send('EHLO '.(parse_url(pp_origin(),PHP_URL_HOST)?:'localhost'),[250]);
        if($encryption==='tls'){$send('STARTTLS',[220]);if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('TLS unavailable');$send('EHLO '.parse_url(pp_origin(),PHP_URL_HOST),[250]);}
        if(!empty($m['username'])){$send('AUTH LOGIN',[334]);$send(base64_encode($m['username']),[334]);$send(base64_encode($m['password']),[235]);}
        $send('MAIL FROM:<'.$from.'>',[250]);$send('RCPT TO:<'.$recipient.'>',[250,251]);$send('DATA',[354]);
        $name='=?UTF-8?B?'.base64_encode($m['from_name']??'Psicología Política').'?=';
        $headers='Date: '.gmdate('r')."\r\n".'Message-ID: <'.bin2hex(random_bytes(16)).'@'.parse_url(pp_origin(),PHP_URL_HOST).">\r\nFrom: $name <$from>\r\nTo: <$recipient>\r\nSubject: =?UTF-8?B?".base64_encode($subject)."?=\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        fwrite($socket,$headers.chunk_split(base64_encode($body),76,"\r\n").".\r\n");$expect([250]);$send('QUIT',[221]);
    }finally{fclose($socket);}
}
function pp_mail(string $email,string $subject,string $body,bool $required=true): void {
    $id=pp_uuid();pp_insert('hp_mail_queue',['id'=>$id,'recipient'=>$email,'subject'=>$subject,'body'=>$body]);
    try{pp_smtp($email,$subject,$body);pp_update('hp_mail_queue',['status'=>'sent','attempts'=>1,'body'=>'','sent_at'=>pp_now()],'id',$id);}
    catch(Throwable $e){pp_update('hp_mail_queue',['status'=>'failed','attempts'=>1,'body'=>''],'id',$id);if($required)throw new PPError(503,'No se pudo enviar el correo. Contacta con la administración o vuelve a intentarlo más tarde.');}
}
