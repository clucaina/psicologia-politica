<?php
declare(strict_types=1);
function pp_backup_tables(): array {
 $tables=array_keys(pp_parse(file_get_contents(__DIR__.'/schema.json')));
 // Optional schema-migration tables are part of the mutable database state.
 foreach(['hp_schema_migrations','hp_npl_batches','hp_npl_batch_items','hp_news_candidates'] as $table){
  if(pp_one('SHOW TABLES LIKE ?',[$table]))$tables[]=$table;
 }
 return $tables;
}
function pp_backup_make(string $actor): string {
 set_time_limit(300);$dir=__DIR__.'/backups';if(!is_dir($dir))mkdir($dir,0700,true);$job=$dir.'/'.pp_uuid();mkdir($job,0700);mkdir($job.'/data',0700);
 $tables=pp_backup_tables();$manifest=['format'=>'psicologiapolitica-hostinger-1','created_at'=>pp_now(),'tables'=>[],'files'=>[]];$sql=fopen($job.'/database.sql','wb');fwrite($sql,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
 $db=pp_db();$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();
 try{
  foreach($tables as $table){$create=pp_one('SHOW CREATE TABLE `'.$table.'`')['Create Table'];fwrite($sql,$create.";\n");}
  fwrite($sql,"START TRANSACTION;\n");$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
  foreach($tables as $table){
   $f=fopen($job.'/data/'.$table.'.jsonl','wb');$count=0;$statement=$db->query('SELECT * FROM `'.$table.'`');
   while($row=$statement->fetch()){
    fwrite($f,pp_json($row)."\n");$count++;
    $values=array_map(fn($v)=>$v===null?'NULL':(is_int($v)||is_float($v)?(string)$v:($v===''?"''":"CONVERT(X'".bin2hex($v)."' USING utf8mb4)")),array_values($row));
    fwrite($sql,'INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',$values).");\n");
   }
   $statement->closeCursor();fclose($f);$manifest['tables'][$table]=['rows'=>$count,'sha256'=>hash_file('sha256',$job.'/data/'.$table.'.jsonl')];
  }
  $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);$db->commit();fwrite($sql,"COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n");fclose($sql);
 }catch(Throwable $e){$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);if($db->inTransaction())$db->rollBack();fclose($sql);throw $e;}
 $zipPath=$job.'.zip';$zip=new ZipArchive();if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Backup archive failed');
 $zip->addFile($job.'/database.sql','database.sql');foreach($tables as $t)$zip->addFile($job.'/data/'.$t.'.jsonl','data/'.$t.'.jsonl');
 $root=dirname(__DIR__);$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
 foreach($files as $file){if(!$file->isFile()||$file->isLink())continue;$relative=substr($file->getPathname(),strlen($root)+1);if(preg_match('~^_private/(?:config\.php|sessions/|cache/|backups/|installed\.lock)~',$relative))continue;$manifest['files'][$relative]=hash_file('sha256',$file->getPathname());$zip->addFile($file->getPathname(),'files/'.$relative);}
 $zip->addFromString('manifest.json',pp_json($manifest));$zip->addFromString('RESTAURAR.txt',"Copia privada: contiene hashes de cuentas y datos personales.\nDesde /mantenimiento puedes restaurar base de datos y archivos subidos.\nPara recuperar el código completo en un hosting nuevo, sube el contenido de files/ a public_html, configura _private/config.php e importa database.sql en una base VACÍA.\nNo subas este ZIP completo a public_html.\n");$zip->close();
 foreach($tables as $t)unlink($job.'/data/'.$t.'.jsonl');rmdir($job.'/data');unlink($job.'/database.sql');rmdir($job);
 pp_audit($actor,'backup',basename($zipPath),'export',null,['tables'=>count($tables)],'Copia completa de base y archivos; configuración secreta excluida.');return $zipPath;
}
function pp_backup_auth(): array {
 $u=pp_require_role(['superadmin']);pp_csrf();pp_rate('backup',$u['email'],6,3600);
 if(!pp_verify_password($u['email'],(string)($_POST['password']??'')))throw new PPError(403,'Confirma tu contraseña actual.');return $u;
}
function pp_backup_download(): never {
 if($_SERVER['REQUEST_METHOD']!=='POST')pp_redirect('/mantenimiento');$u=pp_backup_auth();$path=pp_backup_make($u['email']);header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="PSICOLOGIAPOLITICA_BACKUP_'.gmdate('Ymd_His').'.zip"');header('Cache-Control: no-store');header('Content-Length: '.filesize($path));readfile($path);unlink($path);exit;
}
function pp_backup_restore(string $path,string $actor): void {
 set_time_limit(300);$zip=new ZipArchive();if($zip->open($path)!==true)throw new PPError(400,'La copia no es un ZIP válido.');
 $manifest=pp_parse($zip->getFromName('manifest.json'),null);$tables=pp_backup_tables();if(!is_array($manifest)||($manifest['format']??'')!=='psicologiapolitica-hostinger-1'||array_keys($manifest['tables']??[])!==$tables)throw new PPError(400,'Esta copia no corresponde a esta versión de la web.');
 // Validate everything before deleting any row. Never extract archive paths.
 $stage=__DIR__.'/backups/restore-'.pp_uuid();mkdir($stage,0700,true);mkdir($stage.'/uploads',0700);
 try{
  foreach($tables as $t){$stream=$zip->getStream('data/'.$t.'.jsonl');if(!$stream)throw new PPError(400,'Copia incompleta.');$f=fopen($stage.'/'.$t.'.jsonl','wb');stream_copy_to_stream($stream,$f);fclose($stream);fclose($f);if(!hash_equals($manifest['tables'][$t]['sha256'],hash_file('sha256',$stage.'/'.$t.'.jsonl')))throw new PPError(400,'La copia está dañada.');}
  foreach($manifest['files'] as $relative=>$sha){
   if(!str_starts_with($relative,'uploads/')||$relative==='uploads/.htaccess')continue;
   if(!preg_match('~^uploads/[a-f0-9-]{36}\.(jpg|png|webp|avif|pdf|doc|docx)$~',$relative))throw new PPError(400,'Archivo de subida no permitido.');$bytes=$zip->getFromName('files/'.$relative);if(!is_string($bytes)||strlen($bytes)>12*1024*1024||!hash_equals($sha,hash('sha256',$bytes)))throw new PPError(400,'Archivo de subida dañado.');file_put_contents($stage.'/'.$relative,$bytes);
  }
  $db=pp_db();$db->exec('SET FOREIGN_KEY_CHECKS=0');$db->beginTransaction();
  try{
   foreach(array_reverse($tables) as $t)$db->exec('DELETE FROM `'.$t.'`');
   foreach($tables as $t){
    $columns=array_column(pp_all('SHOW COLUMNS FROM `'.$t.'`'),'Field');$statement=$db->prepare('INSERT INTO `'.$t.'` (`'.implode('`,`',$columns).'`) VALUES('.implode(',',array_fill(0,count($columns),'?')).')');$f=fopen($stage.'/'.$t.'.jsonl','rb');$count=0;
    while(($line=fgets($f))!==false){$row=pp_parse($line,null);if(!is_array($row)||array_keys($row)!==$columns)throw new PPError(400,'Estructura de tabla no válida.');$statement->execute(array_values($row));$count++;}fclose($f);if($count!==$manifest['tables'][$t]['rows'])throw new PPError(400,'Número de filas incorrecto.');
   }
   // Revoke restored sessions; the operator must sign in again.
   pp_query('UPDATE hp_sessions SET revoked_at=?',[pp_now()]);
   foreach(glob($stage.'/uploads/*') as $file){$dest=dirname(__DIR__).'/uploads/'.basename($file);if(is_file($dest)&&hash_file('sha256',$dest)!==hash_file('sha256',$file))throw new PPError(409,'Existe un archivo distinto con el mismo nombre. Conserva ambas copias antes de restaurar.');if(!is_file($dest)&&!copy($file,$dest))throw new RuntimeException('Could not restore upload');}
   pp_audit($actor,'backup','restore','restore',null,['created_at'=>$manifest['created_at']],'Restauración verificada; sesiones revocadas.');$db->commit();
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}finally{$db->exec('SET FOREIGN_KEY_CHECKS=1');}
 }finally{$zip->close();foreach(glob($stage.'/uploads/*') as $f)unlink($f);rmdir($stage.'/uploads');foreach(glob($stage.'/*') as $f)if(is_file($f))unlink($f);rmdir($stage);}
}
function pp_maintenance(): never {
 $u=pp_require_role(['superadmin']);$message='';
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $u=pp_backup_auth();$action=$_POST['action']??'';
  if($action==='backup'){$path=pp_backup_make($u['email']);header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="PSICOLOGIAPOLITICA_BACKUP_'.gmdate('Ymd_His').'.zip"');header('Cache-Control: no-store');header('Content-Length: '.filesize($path));readfile($path);unlink($path);exit;}
  if($action==='restore'){
   if(($_POST['confirmation']??'')!=='RESTAURAR'||($_FILES['backup']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($_FILES['backup']['tmp_name']))throw new PPError(400,'Selecciona la copia y escribe RESTAURAR para confirmar.');
   // Always create an undo copy before changing the database.
   $undo=pp_backup_make($u['email']);pp_backup_restore($_FILES['backup']['tmp_name'],$u['email']);$_SESSION=[];$message='Restauración completada. Entra de nuevo con una cuenta de la copia. Se ha conservado una copia previa de seguridad en el servidor.';
  }
 }
 header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header('X-Robots-Tag: noindex');
 ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Copias de seguridad</title><link rel="stylesheet" href="/assets/site.css"></head><body><main class="pp-shell reading-body"><a href="/admin">Volver a Redacción</a><h1>Copias de seguridad y restauración</h1><?php if($message):?><p role="status"><?=pp_escape($message)?></p><a href="/acceso">Entrar</a><?php else:?><h2>Guardar una copia completa</h2><p>Descarga la base de datos y los archivos. Conserva el ZIP fuera del hosting: contiene datos privados.</p><form method="post" class="editor-form"><input type="hidden" name="_csrf" value="<?=pp_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="backup"><label>Tu contraseña actual<input type="password" name="password" required autocomplete="current-password"></label><button class="button button-dark">Descargar copia completa</button></form><h2>Restaurar una copia</h2><p>Se sustituirán los datos actuales por los de la copia elegida y se recuperarán sus archivos subidos. El código de la web se conserva. Guarda primero una copia actual.</p><form method="post" enctype="multipart/form-data" class="editor-form"><input type="hidden" name="_csrf" value="<?=pp_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="restore"><label>Copia ZIP<input name="backup" type="file" accept=".zip" required></label><label>Tu contraseña actual<input type="password" name="password" required autocomplete="current-password"></label><label>Escribe RESTAURAR<input name="confirmation" required pattern="RESTAURAR" autocomplete="off"></label><button class="button button-dark">Restaurar esta copia</button></form><?php endif;?></main></body></html><?php exit;
}
