<?php
declare(strict_types=1);
function pp_worldbank(): never {
 $country=$_GET['country']??'';$indicator=$_GET['indicator']??'';$period=$_GET['date']??'';
 if(!preg_match('/^(all|[A-Z]{3})$/',$country)||!preg_match('/^[A-Z][A-Z0-9_.]{2,60}$/',$indicator)||!preg_match('/^\d{4}(:\d{4})?$/',$period))throw new PPError(400,'Consulta de datos no válida.');$years=array_map('intval',explode(':',$period));if(min($years)<1960||max($years)>(int)gmdate('Y')||$years[0]>($years[1]??$years[0]))throw new PPError(400,'Periodo fuera de cobertura.');
 $key=hash('sha256',$country.':'.$indicator.':'.$period);$dir=__DIR__.'/cache/worldbank';if(!is_dir($dir))mkdir($dir,0700,true);$path=$dir.'/'.$key.'.json';
 if(!is_file($path)||filemtime($path)<time()-21600){
  pp_rate('worldbank',$country.':'.$indicator,90,300);$url='https://api.worldbank.org/v2/country/'.$country.'/indicator/'.$indicator.'?'.http_build_query(['date'=>$period,'format'=>'json','per_page'=>400]);
  $ctx=stream_context_create(['http'=>['timeout'=>12,'header'=>"Accept: application/json\r\nUser-Agent: PsicologiaPolitica/1.0\r\n"],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);$bytes=@file_get_contents($url,false,$ctx);$data=$bytes?json_decode($bytes,true):null;
  if(is_array($data))file_put_contents($path,$bytes,LOCK_EX);elseif(!is_file($path))throw new PPError(503,'La fuente no está disponible. No se han estimado valores.');else header('X-Data-Stale: true');
 }
 header('Content-Type: application/json; charset=utf-8');header('Cache-Control: public, max-age=21600');header('X-Data-Source: World Bank');readfile($path);exit;
}
function pp_api(string $path): never {
 require_once __DIR__.'/uploads.php';$method=$_SERVER['REQUEST_METHOD'];
 if(preg_match('~^/api/auth/([a-z-]+)$~',$path,$m)){require_once __DIR__.'/auth.php';pp_auth($m[1]);}
 if(preg_match('~^/api/media/([^/]+)$~',$path,$m)&&$method==='GET')pp_media($m[1]);
 if(in_array($path,['/api/conflictometer/spain','/api/v1/npl'],true)){if($method==='GET')pp_npl_get();if($method==='POST')pp_npl_edit();throw new PPError(405,'Método no permitido.');}
 if($path==='/api/v1/npl/coverage'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';pp_analytics_coverage();}
 if($path==='/api/v1/npl/compare'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';pp_analytics_compare();}
 if($path==='/api/v1/npl/correlation'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';pp_analytics_correlation();}
 if($path==='/api/v1/npl/series'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';pp_analytics_series();}
 if($path==='/api/v1/npl/rank'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';pp_analytics_rank();}
 if($path==='/api/v1/npl/ask'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';pp_analytics_ask();}
 if(in_array($path,['/api/v1/text/analyze','/api/v1/text/taxonomy'],true)){require_once __DIR__.'/text_analysis_v2.php';pp_text_v2_api($path);}
 if($path==='/api/conflictometer/data'&&$method==='GET')pp_worldbank();
 if($path==='/api/conflictometer/sessions')pp_analysis_sessions();
 if($path==='/api/comments')pp_comment_api();
 if($path==='/api/comments/like'&&in_array($method,['POST','DELETE'],true)){pp_forum('reaction',[...pp_body(),'reaction'=>'useful','remove'=>$method==='DELETE']);}
 if(preg_match('~^/api/forum/([a-z]+)$~',$path,$m)&&$method==='POST')pp_forum($m[1]);
 if(preg_match('~^/api/account/([a-z]+)$~',$path,$m))pp_account($m[1]);
 if($path==='/api/contact'&&$method==='POST')pp_contact();if($path==='/api/pulse')pp_pulse();
 if($path==='/api/panel/uploads'&&$method==='POST'){$u=pp_require_role(['superadmin','admin_editor']);pp_csrf();pp_rate('upload',$u['email'],24);if(!isset($_FILES['file']))throw new PPError(400,'Selecciona un archivo.');pp_reply(pp_upload($_FILES['file'],$u['email'],pp_clean($_POST['alt']??'',240)));}
 if($path==='/api/admin/npl-batches'&&$method==='POST'){require_once __DIR__.'/npl_batch.php';pp_npl_batch_api();}
 if($path==='/api/admin/schema-migrations'&&$method==='POST'){require_once __DIR__.'/migrations.php';pp_migration_api();}
 if($path==='/api/admin/news-ingest'&&$method==='POST'){require_once __DIR__.'/news_ingest.php';pp_news_ingest_api();}
 if($path==='/api/admin/backup'){require_once __DIR__.'/backup.php';pp_backup_download();}
 if(preg_match('~^/api/admin/([a-z]+)$~',$path,$m)&&$method==='POST'){require_once __DIR__.'/editorial.php';pp_admin($m[1]);}
 if($path==='/api/editor/posts'&&$method==='GET'){require_once __DIR__.'/editorial.php';pp_author_get();}
 if($path==='/api/editor/posts'&&$method==='POST'){require_once __DIR__.'/editorial.php';pp_author_post();}
 if(preg_match('~^/api/panel/(posts|comments|contacts)$~',$path,$m)&&in_array($method,['POST','PATCH'],true)){require_once __DIR__.'/editorial.php';$p=pp_body();if($m[1]==='posts')$p=[...$p,'source_url'=>$p['sourceUrl']??$p['source_url']??null,'cover_image_key'=>$p['coverImageKey']??$p['cover_image_key']??null,'cover_image_alt'=>$p['coverImageAlt']??$p['cover_image_alt']??null];pp_admin($m[1]==='posts'?'content':$m[1],$p);}
 throw new PPError(404,'Ruta no encontrada.');
}
