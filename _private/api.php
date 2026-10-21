<?php
declare(strict_types=1);
/** World Bank v2: complete, bounded series only. Missing != zero; never cache
 * an HTTP error, a partial page sequence, or a mid-query source revision.
 * Pagination contract: https://datahelpdesk.worldbank.org/knowledgebase/articles/898581
 */
const PP_WB_PAGE_SIZE = 400;
const PP_WB_MAX_PAGES = 12; // 4,800 cells; fail explicitly for larger queries.
const PP_WB_MAX_CACHE_BYTES = 8388608;
function pp_worldbank_row_valid(mixed $row,?int $startYear,?int $endYear,?string $indicatorId): bool {
 if(!is_array($row)||!isset($row['date'],$row['indicator'],$row['country'])||!array_key_exists('value',$row))return false;
 if(!preg_match('/^(19|20)[0-9]{2}$/D',(string)$row['date'])||(int)$row['date']>(int)gmdate('Y'))return false;
 if(!is_array($row['indicator'])||!is_array($row['country'])||!is_string($row['indicator']['id']??null)||($row['indicator']['id']??'')===''||!is_string($row['country']['id']??null)||($row['country']['id']??'')==='')return false;
 if(($startYear!==null&&(int)$row['date']<$startYear)||($endYear!==null&&(int)$row['date']>$endYear))return false;
 if($indicatorId!==null&&($row['indicator']['id']??null)!==$indicatorId)return false;
 if($row['value']!==null&&(!is_numeric($row['value'])||!is_finite((float)$row['value'])))return false;
 return true;
}
function pp_worldbank_valid_page(mixed $payload,int $page,?int $startYear=null,?int $endYear=null,?string $indicatorId=null): bool {
 if(!is_array($payload)||count($payload)!==2||!array_is_list($payload))return false;
 [$meta,$rows]=$payload;
 if(!is_array($meta)||!is_array($rows)||!array_is_list($rows))return false;
 foreach(['page','pages','total','per_page'] as $field)
  if(!isset($meta[$field])||!is_int($meta[$field]))return false;
 if($meta['page']!==$page||$meta['pages']<1||$meta['pages']>PP_WB_MAX_PAGES||$meta['total']<1||
    $meta['total']>PP_WB_MAX_PAGES*PP_WB_PAGE_SIZE||$meta['per_page']<1||$meta['per_page']>PP_WB_PAGE_SIZE||
    count($rows)<1||count($rows)>$meta['per_page']||$meta['pages']!==intdiv($meta['total']+$meta['per_page']-1,$meta['per_page']))return false;
 $expected=min($meta['per_page'],$meta['total']-($page-1)*$meta['per_page']);
 if(count($rows)!==$expected)return false;
 foreach($rows as $row)if(!pp_worldbank_row_valid($row,$startYear,$endYear,$indicatorId))return false;
 return true;
}
/** Public/cache response shape is the historical [metadata, rows] structure,
 * normalized to a complete single-page snapshot after validated aggregation. */
function pp_worldbank_valid_payload(mixed $payload,?int $startYear=null,?int $endYear=null,?string $indicatorId=null): bool {
 if(!is_array($payload)||count($payload)!==2||!array_is_list($payload))return false;
 [$meta,$rows]=$payload;
 if(!is_array($meta)||!is_array($rows)||!array_is_list($rows)||!isset($meta['page'],$meta['pages'],$meta['total'],$meta['per_page']))return false;
 if(!is_int($meta['page'])||!is_int($meta['pages'])||!is_int($meta['total'])||!is_int($meta['per_page'])||
    $meta['page']!==1||$meta['pages']!==1||$meta['total']<1||$meta['total']>PP_WB_MAX_PAGES*PP_WB_PAGE_SIZE||
    $meta['per_page']<$meta['total']||$meta['per_page']>PP_WB_MAX_PAGES*PP_WB_PAGE_SIZE||count($rows)!==$meta['total'])return false;
 $seen=[];
 foreach($rows as $row){
  if(!pp_worldbank_row_valid($row,$startYear,$endYear,$indicatorId))return false;
  $key=(string)$row['country']['id'].'|'.(string)$row['date'].'|'.(string)($row['indicator']['id']??'');
  if(isset($seen[$key]))return false;
  $seen[$key]=true;
 }
 return true;
}
/** $fetchPage(page, perPage) returns a decoded upstream response or throws.
 * Nothing is returned or cached unless EVERY page is coherent and complete. */
function pp_worldbank_collect_pages(callable $fetchPage,?int $startYear=null,?int $endYear=null,?string $indicatorId=null): array {
 $first=$fetchPage(1,PP_WB_PAGE_SIZE);
 if(!pp_worldbank_valid_page($first,1,$startYear,$endYear,$indicatorId))
    throw new RuntimeException('World Bank: primera página inválida o consulta demasiado extensa.');
 $meta=$first[0];$rows=$first[1];$seen=[];
 foreach($rows as $row)$seen[$row['country']['id'].'|'.$row['date'].'|'.$row['indicator']['id']]=true;
 for($page=2;$page<=$meta['pages'];$page++){
  $part=$fetchPage($page,PP_WB_PAGE_SIZE);
  if(!pp_worldbank_valid_page($part,$page,$startYear,$endYear,$indicatorId))throw new RuntimeException('World Bank: página incompleta o inválida.');
  $m=$part[0];
  foreach(['pages','per_page','total','sourceid','lastupdated'] as $field)
   if(($m[$field]??null)!==($meta[$field]??null))throw new RuntimeException('World Bank: la fuente cambió durante la consulta.');
  foreach($part[1] as $row){
   $key=$row['country']['id'].'|'.$row['date'].'|'.$row['indicator']['id'];
   if(isset($seen[$key]))throw new RuntimeException('World Bank: celda duplicada entre páginas.');
   $seen[$key]=true;$rows[]=$row;
  }
 }
 if(count($rows)!==$meta['total'])throw new RuntimeException('World Bank: cobertura incompleta.');
 $meta['page']=1;$meta['pages']=1;$meta['per_page']=count($rows);
 $complete=[$meta,$rows];
 if(!pp_worldbank_valid_payload($complete,$startYear,$endYear,$indicatorId))throw new RuntimeException('World Bank: validación final fallida.');
 return $complete;
}
function pp_worldbank_http_page(string $country,string $indicator,string $period,int $page,int $perPage): array {
 $url='https://api.worldbank.org/v2/country/'.$country.'/indicator/'.$indicator.'?'.http_build_query([
  'date'=>$period,'format'=>'json','per_page'=>$perPage,'page'=>$page]);
 $ctx=stream_context_create(['http'=>['timeout'=>12,'ignore_errors'=>true,
  'header'=>"Accept: application/json\r\nUser-Agent: PsicologiaPolitica/1.0\r\n"],
  'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
 $bytes=@file_get_contents($url,false,$ctx,0,2000001);
 $headers=$http_response_header??[];$lastStatus='';
 foreach($headers as $line)if(preg_match('~^HTTP/[0-9.]+ [0-9]{3}(?: |$)~',$line))$lastStatus=$line;
 if(!preg_match('~^HTTP/[0-9.]+ 200(?: |$)~',$lastStatus)||!is_string($bytes)||strlen($bytes)>2000000)
  throw new RuntimeException('World Bank: error HTTP o respuesta excesiva.');
 $decoded=json_decode($bytes,true,64);
 if(!is_array($decoded))throw new RuntimeException('World Bank: JSON inválido.');
 return $decoded;
}
function pp_worldbank(): never {
 $country=$_GET['country']??'';$indicator=$_GET['indicator']??'';$period=$_GET['date']??'';
 if(!is_string($country)||!is_string($indicator)||!is_string($period)||
    !preg_match('/^(all|[A-Z]{3})$/D',$country)||!preg_match('/^[A-Z][A-Z0-9_.]{2,60}$/D',$indicator)||
    !preg_match('/^\d{4}(:\d{4})?$/D',$period))throw new PPError(400,'Consulta de datos no válida.');
 $years=array_map('intval',explode(':',$period));
 if(min($years)<1960||max($years)>(int)gmdate('Y')||$years[0]>($years[1]??$years[0]))throw new PPError(400,'Periodo fuera de cobertura.');
 $key=hash('sha256',$country.':'.$indicator.':'.$period);$dir=__DIR__.'/cache/worldbank';
 if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new PPError(503,'Caché temporal no disponible.');
 $path=$dir.'/'.$key.'.json';$stale=false;
 if(!is_file($path)||filemtime($path)<time()-21600){
  pp_rate('worldbank',$country.':'.$indicator,90,300);
  try{
   $data=pp_worldbank_collect_pages(static fn(int $page,int $size)=>pp_worldbank_http_page($country,$indicator,$period,$page,$size),
     $years[0],$years[1]??$years[0],$indicator);
   $bytes=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
   if(strlen($bytes)>PP_WB_MAX_CACHE_BYTES)throw new RuntimeException('Serie demasiado grande para la caché.');
   $temp=tempnam($dir,'wb_');
   if($temp===false||file_put_contents($temp,$bytes,LOCK_EX)===false||!rename($temp,$path)){
    if(is_string($temp)&&is_file($temp))@unlink($temp);
    throw new RuntimeException('No se pudo conservar la respuesta completa.');
   }
   @chmod($path,0600);
  }catch(Throwable $e){
   if(!is_file($path))throw new PPError(503,'La fuente no está disponible o supera los límites seguros. No se han estimado valores.');
   $stale=true;
  }
 }
 $cached=@file_get_contents($path);
 if(!is_string($cached)||strlen($cached)>PP_WB_MAX_CACHE_BYTES||
    !pp_worldbank_valid_payload(json_decode($cached,true,64),$years[0],$years[1]??$years[0],$indicator))
    throw new PPError(503,'La caché de la fuente no contiene una serie estadística completa y válida.');
 if($stale){header('X-Data-Stale: true');header('Cache-Control: no-store');}
 else header('Cache-Control: public, max-age=21600');
 header('Content-Type: application/json; charset=utf-8');header('X-Data-Source: World Bank');echo $cached;exit;
}
function pp_api(string $path): never {
 require_once __DIR__.'/uploads.php';$method=$_SERVER['REQUEST_METHOD'];
 if($path==='/api/v1/status'&&$method==='GET')pp_public_status();
 if($path==='/api/admin/runtime-diagnostics'&&$method==='GET')pp_admin_runtime_diagnostics();
 if(preg_match('~^/api/auth/([a-z-]+)$~',$path,$m)){require_once __DIR__.'/auth.php';pp_auth($m[1]);}
 if(preg_match('~^/api/media/([^/]+)$~',$path,$m)&&$method==='GET')pp_media($m[1]);
 if(in_array($path,['/api/conflictometer/spain','/api/v1/npl'],true)){if($method==='GET')pp_npl_get();if($method==='POST')pp_npl_edit();throw new PPError(405,'Método no permitido.');}
 if($path==='/api/v1/npl/coverage'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';pp_analytics_coverage();}
 if($path==='/api/admin/npl-release-diagnostics'&&$method==='GET'){require_once __DIR__.'/npl_release.php';pp_release_diagnostics();}
 if($path==='/api/v1/npl/compare'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';pp_analytics_compare();}
 if($path==='/api/v1/npl/correlation'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';pp_analytics_correlation();}
 if($path==='/api/v1/npl/series'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';pp_analytics_series();}
 if($path==='/api/v1/npl/quarterly'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';require_once __DIR__.'/npl_quarterly.php';pp_quarterly_api();}
 if($path==='/api/v1/npl/rank'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';pp_analytics_rank();}
 if($path==='/api/v1/npl/ask'&&$method==='GET'){require_once __DIR__.'/npl_analytics.php';require_once __DIR__.'/npl_quarterly.php';pp_analytics_ask();}
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
