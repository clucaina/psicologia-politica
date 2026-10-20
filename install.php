<?php
declare(strict_types=1);
require __DIR__.'/_private/core.php';
header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
$message='';$complete=false;$ready=false;
try{
 $config=pp_config();pp_session();$key=$config['setup_key']??'';
 if(strlen($key)<32||str_contains($key,'CAMBIA_ESTA'))throw new PPError(503,'Completa config.php y sustituye setup_key por una clave aleatoria propia de al menos 32 caracteres.');
 if(pp_one("SELECT id FROM hp_settings WHERE id='installed'"))throw new PPError(403,'La instalación ya está cerrada. Entra desde Acceder.');
 $counts=pp_parse(file_get_contents(__DIR__.'/_private/migration-counts.json'));
 foreach($counts as $table=>$expected){$actual=pp_one('SELECT count(*) AS n FROM `'.$table.'`')['n'];if($actual<$expected)throw new PPError(503,'Importa la base completa antes de instalar. Falta información en '.$table.'.');}
 foreach(['pdo_mysql','mbstring','gd','zip','fileinfo','openssl'] as $extension)if(!extension_loaded($extension))throw new PPError(503,'Activa la extensión PHP '.$extension.' desde hPanel.');
 if(!is_writable(__DIR__.'/_private')||!is_writable(__DIR__.'/uploads'))throw new PPError(503,'La carpeta _private y uploads deben poder escribirse desde PHP.');$ready=true;
 if($_SERVER['REQUEST_METHOD']==='POST'){
  pp_csrf();pp_rate('installer','',6,900);if(!hash_equals($key,(string)($_POST['setup_key']??'')))throw new PPError(403,'La clave de instalación no coincide con config.php.');
  $email=pp_email($_POST['email']??'');$password=(string)($_POST['password']??'');pp_password($password);$name=pp_clean($_POST['display_name']??'',60);if(mb_strlen($name)<2)throw new PPError(400,'Escribe tu nombre público.');
  pp_transaction(function()use($email,$password,$name){
   // Unique key makes two concurrent installations mutually exclusive.
   pp_insert('hp_settings',['id'=>'installed','value'=>pp_now()]);$profile=pp_one('SELECT email FROM reader_profiles WHERE email=? FOR UPDATE',[$email]);
   if(!$profile)pp_insert('reader_profiles',['email'=>$email,'display_name'=>$name,'user_id'=>'email:'.$email,'email_verified'=>1]);else pp_update('reader_profiles',['display_name'=>$name,'status'=>'active','email_verified'=>1],'email',$email);
   pp_set_password($email,$password);pp_query("INSERT INTO user_roles(email,role) VALUES(?,'superadmin') ON DUPLICATE KEY UPDATE role='superadmin'",[$email]);pp_audit($email,'installation','hostinger','install',null,['status'=>'complete'],'Cuenta inicial creada por quien conoce la clave privada de configuración.');
  });file_put_contents(__DIR__.'/_private/installed.lock',pp_now());pp_login($email);$complete=true;$message='Instalación completada. Tu cuenta administradora está activa.';
 }
}catch(Throwable $e){$message=$e instanceof PPError?$e->getMessage():'No se pudo completar la instalación. Comprueba la conexión MySQL y la importación del SQL.';http_response_code($e instanceof PPError?$e->status:503);}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar Psicología Política</title><link rel="stylesheet" href="/assets/site.css"></head><body><main class="pp-shell reading-body"><span class="kicker">Psicología Política</span><h1>Activa tu web</h1><p>La base de datos ya contiene el contenido y las cuentas del equipo. Este paso crea tu acceso de administración.</p><?php if($message):?><p class="notice" role="status"><?=pp_escape($message)?></p><?php endif;if($complete):?><p><a class="button button-dark" href="/cuenta">Entrar en mi cuenta</a> · <a href="/">Ver la web</a></p><?php elseif($ready):?><form method="post" class="editor-form"><input type="hidden" name="_csrf" value="<?=pp_escape($_SESSION['csrf'])?>"><label>Clave de instalación de config.php<input type="password" name="setup_key" required minlength="32" autocomplete="off"></label><label>Tu nombre público<input name="display_name" required minlength="2" maxlength="60" autocomplete="name"></label><label>Tu email de administración<input type="email" name="email" required autocomplete="email"></label><label>Tu nueva contraseña<input type="password" name="password" required minlength="12" maxlength="128" autocomplete="new-password"></label><p>Usa al menos 12 caracteres con letras y números. Guarda esta contraseña en un lugar seguro.</p><button class="button button-dark">Crear mi cuenta y activar la web</button></form><?php else:?><p><a href="/acceso">Acceder</a></p><?php endif;?></main></body></html>
