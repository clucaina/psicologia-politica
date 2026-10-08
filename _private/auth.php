<?php
declare(strict_types=1);
require_once __DIR__.'/mail.php';
function pp_issue_code(string $email,string $purpose): void {
    pp_query('DELETE FROM hp_email_tokens WHERE email=? AND purpose=?',[$email,$purpose]);
    $code=(string)random_int(10000000,99999999);
    pp_insert('hp_email_tokens',['token_hash'=>hash('sha256',$email.':'.$purpose.':'.$code),'email'=>$email,'purpose'=>$purpose,'expires_at'=>time()+1200]);
    $label=$purpose==='signup'?'verificar tu cuenta':'recuperar tu acceso';
    pp_mail($email,'Tu código de Psicología Política',"Has solicitado $label.\n\nTu código es: $code\n\nCaduca en 20 minutos y solo se puede usar una vez.\nAbre ".pp_origin().'/acceso?modo='.($purpose==='signup'?'verify':'reset')." e introduce tu email y este código.\nSi no has solicitado esta operación, ignora este mensaje. Nunca compartas el código.");
}
function pp_consume_code(string $email,string $purpose,mixed $code): void {
    $hash=hash('sha256',$email.':'.$purpose.':'.pp_clean($code,20));
    $row=pp_one('SELECT * FROM hp_email_tokens WHERE token_hash=? AND email=? AND purpose=? AND used_at IS NULL AND expires_at>? FOR UPDATE',[$hash,$email,$purpose,time()]);
    if(!$row)throw new PPError(400,'El código no es válido o ha caducado.');
    pp_update('hp_email_tokens',['used_at'=>pp_now()],'token_hash',$hash);
}
function pp_auth(string $action): never {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new PPError(405,'Método no permitido.');
    $p=pp_body();
    if(in_array($action,['logout','revoke'],true)){
        $u=pp_user();if($u){if($action==='revoke')pp_revoke($u['email']);else pp_query('UPDATE hp_sessions SET revoked_at=? WHERE token_hash=?',[pp_now(),hash('sha256',$_SESSION['auth_token']??'')]);}
        $_SESSION=[];session_regenerate_id(true);pp_reply(['ok'=>true]);
    }
    if($action==='change-password'){
        $u=pp_require_user();pp_rate('change-password',$u['email'],5,900);
        $current=$p['current_password']??'';$next=$p['new_password']??'';
        if(!is_string($current)||!is_string($next)||!pp_verify_password($u['email'],$current))throw new PPError(400,'La contraseña actual no es correcta.');
        if(hash_equals($current,$next))throw new PPError(400,'Elige una contraseña distinta de la actual.');
        pp_transaction(function()use($u,$next){pp_set_password($u['email'],$next);pp_revoke($u['email']);pp_audit($u['email'],'account',$u['email'],'password_change',null,null,'Contraseña actualizada; no se registran secretos.');});
        pp_login($u['email']);pp_reply(['ok'=>true,'csrf'=>$_SESSION['csrf'],'message'=>'Contraseña actualizada. Las demás sesiones se han cerrado.']);
    }
    $email=pp_email($p['email']??'');pp_rate('auth-'.$action,$email,8,900);
    $password=is_string($p['password']??null)?$p['password']:'';
    if(strlen($password)>128)throw new PPError(400,'Contraseña demasiado larga.');
    if($action==='login'){
        if(!pp_verify_password($email,$password))throw new PPError(400,'No se pudo verificar el email o la contraseña.');
        pp_login($email);pp_reply(['ok'=>true,'csrf'=>$_SESSION['csrf'],'mustChangePassword'=>pp_user()['mustChangePassword']]);
    }
    if($action==='signup'){
        if(!pp_config()['registration_enabled'])throw new PPError(403,'El registro está cerrado temporalmente.');
        if(($p['consent']??false)!==true)throw new PPError(400,'Acepta la información de privacidad.');pp_password($password);
        $existing=pp_one('SELECT * FROM reader_profiles WHERE email=?',[$email]);
        if(!$existing||(!$existing['email_verified']&&$existing['status']==='active')){
            pp_transaction(function()use($email,$password,$p,$existing){
                if(!$existing)pp_insert('reader_profiles',['email'=>$email,'display_name'=>pp_clean($p['displayName']??'',60)?:'Lector/a','user_id'=>'email:'.$email]);
                pp_query("INSERT IGNORE INTO user_roles(email,role) VALUES(?,'registered_user')",[$email]);
                pp_set_password($email,$password);
            });pp_issue_code($email,'signup');
        }
        pp_reply(['ok'=>true,'message'=>'Si el registro procede, recibirás un código de verificación por email.']);
    }
    if($action==='recover'){
        if(pp_one("SELECT email FROM reader_profiles WHERE email=? AND status='active'",[$email]))pp_issue_code($email,'recovery');
        pp_reply(['ok'=>true,'message'=>'Si existe una cuenta, recibirás las instrucciones de recuperación.']);
    }
    if($action==='verify'){
        pp_transaction(function()use($email,$p){pp_consume_code($email,'signup',$p['code']??'');pp_query('UPDATE reader_profiles SET email_verified=1 WHERE email=?',[$email]);});
        pp_login($email);pp_reply(['ok'=>true,'csrf'=>$_SESSION['csrf'],'mustChangePassword'=>pp_user()['mustChangePassword']]);
    }
    if($action==='reset'){
        pp_password($password);pp_transaction(function()use($email,$p,$password){pp_consume_code($email,'recovery',$p['code']??'');pp_set_password($email,$password);pp_query('UPDATE reader_profiles SET email_verified=1 WHERE email=?',[$email]);pp_revoke($email);});
        pp_reply(['ok'=>true,'message'=>'Contraseña cambiada. Entra con tu nueva contraseña.']);
    }
    throw new PPError(404,'Acción no encontrada.');
}
