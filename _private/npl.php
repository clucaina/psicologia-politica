<?php
declare(strict_types=1);
const PP_REVIEW_JOIN=' LEFT JOIN npl_reviews r ON r.observation_id=o.id';
const PP_PUBLISHED="r.status='published' AND BINARY r.reviewed_data=BINARY o.data AND trim(COALESCE(o.subgroup,''))<>'' AND trim(COALESCE(o.unit,''))<>'' AND o.value IS NOT NULL";
const PP_REVIEW_STATE="CASE WHEN BINARY r.reviewed_data=BINARY o.data THEN r.status ELSE 'review' END";
function pp_npl_unpack(array $r): array {return [...pp_parse($r['data']),'review'=>['status'=>$r['review_status']??'review','reviewed_at'=>$r['reviewed_at']??null,'evidence'=>pp_parse($r['evidence']??'{}',(object)[])]];}
function pp_npl_get(): never {
 pp_rate('npl-read','',120,300);$editorial=($_GET['editorial']??'')==='1';if($editorial)pp_require_role(['superadmin','admin_editor']);
 $scope=$editorial?'1=1':PP_PUBLISHED;$join=PP_REVIEW_JOIN;$indicator=$_GET['indicator']??'';
 if(!$indicator){
  $items=pp_all("SELECT i.*,count(o.id) AS observation_count FROM npl_indicators i LEFT JOIN (SELECT o.* FROM npl_observations o $join WHERE $scope) o ON o.indicator_id=i.id GROUP BY i.id ".($editorial?'':'HAVING count(o.id)>0').' ORDER BY i.domain,i.name');
  $sources=pp_all('SELECT * FROM npl_sources WHERE '.($editorial?'1=1':"id IN (SELECT DISTINCT o.source_id FROM npl_observations o $join WHERE $scope)"));
  $stats=pp_one("SELECT count(*) AS observations,count(DISTINCT o.indicator_id) AS active_indicators,count(DISTINCT o.source_id) AS active_sources,min(o.period) AS first_period,max(o.period) AS last_period FROM npl_observations o $join WHERE $scope");$archive=pp_one('SELECT count(*) AS observations FROM npl_observations');
  $imports=array_map(fn($r)=>['id'=>$r['id'],'sha256'=>$r['sha256'],'created_at'=>$r['created_at'],...pp_parse($r['data'])],pp_all("SELECT id,sha256,data,created_at FROM npl_imports ORDER BY CAST(json_unquote(json_extract(data,'$.batch_number')) AS SIGNED),created_at"));$latest=end($imports)?:[];
  $dataset=['label'=>$latest['label']??'NPL España','observations'=>$latest['observations']??$archive['observations'],'analytical_observations'=>$latest['analytical_observations']??$latest['observations']??$archive['observations'],'indicators'=>$latest['indicators']??count($items),'sources'=>$latest['sources']??count($sources)];
  foreach(['batches'=>'batch_log','domains'=>'domains','source_coverage'=>'source_coverage','audit_findings'=>'audit_findings','overlaps'=>'cross_source_overlaps','ingestion_targets'=>'ingestion_targets','deliveries'=>'delivery_lineage'] as $k=>$v)$dataset[$k]=$latest[$v]??[];
  $dataset['scope']=$latest['scope']??'';$dataset['sha256']=$latest['sha256']??'';$dataset['imports']=array_map(fn($r)=>array_intersect_key($r,array_flip(['id','label','batch_number','observations','indicators','sources','sha256'])),$imports);
  pp_reply(['api_version'=>1,'editorial'=>$editorial,'archive'=>$archive,'snapshot'=>$dataset['label'],'dataset'=>$dataset,'stats'=>$stats,'items'=>array_map(fn($r)=>[...pp_parse($r['data']),'id'=>$r['id'],'observation_count'=>$r['observation_count']],$items),'sources'=>(object)array_column(array_map(fn($r)=>['id'=>$r['id'],'data'=>pp_parse($r['data'])],$sources),'data','id')]);
 }
 if(!preg_match('/^[A-Za-z0-9_-]{1,100}$/',$indicator))throw new PPError(400,'Indicador no válido.');$record=pp_one('SELECT * FROM npl_indicators WHERE id=?',[$indicator]);if(!$record)throw new PPError(404,'Indicador no encontrado.');
 $offset=filter_var($_GET['offset']??0,FILTER_VALIDATE_INT);$limit=filter_var($_GET['limit']??50,FILTER_VALIDATE_INT);if($offset===false||$offset<0||$offset>100000||!in_array($limit,[25,50,100],true))throw new PPError(400,'Paginación no válida.');
 $clauses=['o.indicator_id=?',$scope];$args=[$indicator];
 foreach(['geo'=>['geo_code',30],'group'=>['subgroup',1500],'source'=>['source_id',100]] as $parameter=>[$column,$max])if(!empty($_GET[$parameter])){if(mb_strlen($_GET[$parameter])>$max)throw new PPError(400,'Filtro demasiado largo.');$clauses[]='o.'.$column.'=?';$args[]=$_GET[$parameter];}
 if(!empty($_GET['review'])){if(!$editorial||!in_array($_GET['review'],['review','published','rejected'],true))throw new PPError(400,'Filtro editorial no válido.');$clauses[]=PP_REVIEW_STATE.'=?';$args[]=$_GET['review'];}
 if(!empty($_GET['batch'])){if(!preg_match('/^T\d{2}(?:_[A-Z0-9_]{1,61})?$/',$_GET['batch']))throw new PPError(400,'Tanda no válida.');$clauses[]="json_unquote(json_extract(o.data,'$.batch_id'))=?";$args[]=$_GET['batch'];}
 if(!empty($_GET['method'])){if(strlen($_GET['method'])>500)throw new PPError(400,'Método demasiado largo.');$clauses[]="json_unquote(json_extract(o.data,'$.methodology_flag'))=?";$args[]=$_GET['method'];}
 foreach(['from'=>'>=','to'=>'<='] as $k=>$op)if(!empty($_GET[$k])){if(!preg_match('/^(19|20)\d{2}$/',$_GET[$k]))throw new PPError(400,'Indica el año con cuatro cifras.');$clauses[]='substr(o.period,1,4)'.$op.'?';$args[]=$_GET[$k];}
 if(!empty($_GET['from'])&&!empty($_GET['to'])&&$_GET['from']>$_GET['to'])throw new PPError(400,'El año inicial debe ser anterior al final.');
 $where=implode(' AND ',$clauses);$csv=($_GET['format']??'')==='csv';if(isset($_GET['format'])&&!$csv)throw new PPError(400,'Formato no válido.');$total=pp_one("SELECT count(*) AS total FROM npl_observations o $join WHERE $where",$args)['total'];if($csv&&$total>1000)throw new PPError(413,'Acota los filtros: la exportación admite hasta 1.000 filas.');
 $rows=pp_all('SELECT o.data,r.evidence,r.reviewed_at,'.PP_REVIEW_STATE." AS review_status FROM npl_observations o $join WHERE $where ORDER BY o.period DESC,o.geography,o.subgroup,o.id LIMIT ? OFFSET ?",[...$args,$csv?1000:$limit,$csv?0:$offset]);$observations=array_map('pp_npl_unpack',$rows);if($csv)pp_npl_csv($indicator,$observations);
 $geographies=pp_all("SELECT DISTINCT o.geo_code,o.geography FROM npl_observations o $join WHERE o.indicator_id=? AND $scope ORDER BY o.geography",[$indicator]);$groups=array_column(pp_all("SELECT DISTINCT o.subgroup FROM npl_observations o $join WHERE o.indicator_id=? AND $scope ORDER BY o.subgroup",[$indicator]),'subgroup');
 $options=[];foreach(['batches'=>'batch_id','methods'=>'methodology_flag'] as $k=>$field)$options[$k]=array_values(array_filter(array_column(pp_all("SELECT DISTINCT json_unquote(json_extract(o.data,'$.$field')) AS value FROM npl_observations o $join WHERE o.indicator_id=? AND $scope ORDER BY value",[$indicator]),'value')));
 pp_reply(['api_version'=>1,'editorial'=>$editorial,'indicator'=>pp_parse($record['data']),'rows'=>$observations,'total'=>$total,'geographies'=>$geographies,'groups'=>$groups,...$options,'limit'=>$limit,'offset'=>$offset]);
}
function pp_npl_csv(string $indicator,array $rows): never {
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="npl-'.$indicator.'.csv"');header('Cache-Control: no-store');
 $columns=explode(',','observation_id,indicator_id,period,geo_code,geography,subgroup,value,unit,source_id,source_url,retrieved_at,quality_flag,methodology_flag,dimensions,population,measurement_type,verified_at,citation,limits');
 $cell=function($v){$s=is_array($v)||is_object($v)?pp_json($v):(string)($v??'');if(!is_int($v)&&!is_float($v)&&preg_match('/^[\s\x00-\x1f]*[=+@-]/',$s))$s="'".$s;return '"'.str_replace('"','""',$s).'"';};echo "\xEF\xBB\xBF".implode(',',array_map($cell,$columns))."\r\n";
 foreach($rows as $r){$e=(array)$r['review']['evidence'];$r=[...$r,'population'=>$e['population']??'','measurement_type'=>$e['measurement_type']??'','verified_at'=>$r['review']['reviewed_at'],'citation'=>$e['citation']??'','limits'=>$e['limits']??''];echo implode(',',array_map(fn($k)=>$cell($r[$k]??''),$columns))."\r\n";}exit;
}
/** Require explicit source-table verification before publishing a batch imported from indexed PDF text. */
function pp_npl_source_verification_gate(array $original,array $request): void {
 $flag=(string)($original['source_quality_flag']??$original['quality_flag']??'');
 if(str_contains($flag,'PENDING_VISUAL_SOURCE_QA')){
  if(($request['source_verification_confirmed']??null)!==true||!array_key_exists('retrieved_at',$request))
   throw new PPError(400,'Comprueba visualmente la tabla oficial y confirma explícitamente la fecha real antes de publicar.');
 }
}
function pp_npl_edit(): never {
 $u=pp_require_role(['superadmin','admin_editor']);$p=pp_body();pp_rate('npl-edit',$u['email'],40,300);$reason=pp_clean($p['reason']??'',1000);if(mb_strlen($reason)<10)throw new PPError(400,'Explica el motivo y la fuente de la corrección.');
 pp_transaction(function()use($p,$u,$reason){
  $review=($p['action']??'')==='review';$isIndicator=($p['entity']??'')==='indicator';if($review&&$isIndicator)throw new PPError(400,'Solo las observaciones admiten revisión de publicación.');if(!$review&&!$isIndicator&&($p['entity']??'')!=='observation')throw new PPError(400,'Tipo de registro no válido.');$table=$isIndicator?'npl_indicators':'npl_observations';$key=(string)($p['id']??'');
  $before=pp_one("SELECT * FROM $table WHERE id=? FOR UPDATE",[$key]);if(!$before)throw new PPError(404,'Registro no encontrado.');$original=pp_parse($before['data']);if(!is_int($p['expected_revision']??null)||$p['expected_revision']!==($original['_revision']??0))throw new PPError(409,'El registro cambió. Recarga antes de editarlo.');
  if($review){
   if(!in_array($p['status']??'',['published','review','rejected'],true))throw new PPError(400,'Revisión no válida.');$e=[];foreach(['population','locator','limits','citation','series_key'] as $k)$e[$k]=pp_clean($p[$k]??'',2000);$e['source_url']=pp_url($p['source_url']??'');$e['measurement_type']=pp_clean($p['measurement_type']??'',50);
   // T19/T26 are historical prepared datasets without an explicit retrieval timestamp.
   // The reviewer must record a real verification date; never synthesize a historical retrieval date.
   $verificationDate=$p['retrieved_at']??($original['retrieved_at']??null);
   if($p['status']==='published'&&!is_string($verificationDate))throw new PPError(400,'Indica la fecha real de verificación de la fuente (AAAA-MM-DD).');
   if($p['status']==='published'){
    pp_npl_source_verification_gate($original,$p);
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$verificationDate,new DateTimeZone('UTC'));
    if(!$dt||$dt->format('Y-m-d')!==$verificationDate||$dt>new DateTimeImmutable('today',new DateTimeZone('UTC')))
      throw new PPError(400,'Fecha de verificación inválida o futura.');
    $original['retrieved_at']=$verificationDate;
    $original['retrieval_context']='human_source_verification';
    if(($p['source_verification_confirmed']??null)===true){$original['source_verified_at']=pp_now();$original['source_verified_by']=$u['email'];}
    $original['quality_flag']='REVIEWED';$original['import_review']='reviewed';
    $e['retrieved_at']=$verificationDate;
   }
   if($p['status']==='published'){
    foreach(['indicator_id','period','geo_code','geography','subgroup','unit','source_id','source_url','retrieved_at','methodology_flag'] as $k)if(!trim((string)($original[$k]??'')))throw new PPError(400,'Completa población, periodo, unidad, método y fuente.');
    if(!is_numeric($original['value']??null)||!pp_percent_value_valid($original))throw new PPError(400,'Valor no publicable.');if(!$e['source_url']||!in_array($e['measurement_type'],['survey_screening','survey','administrative_record','estimate','derived'],true))throw new PPError(400,'Documenta fuente y tipo de medida.');foreach(['population','locator','limits','citation','series_key'] as $k)if(mb_strlen($e[$k])<4)throw new PPError(400,'Documenta localizador, población, límites y cita.');
   }
   $original['_revision']=($original['_revision']??0)+1;$fields=['data'=>pp_json($original)];$prior=pp_one('SELECT * FROM npl_reviews WHERE observation_id=?',[$key]);$next=['observation_id'=>(int)$key,'status'=>$p['status'],'reviewed_data'=>$fields['data'],'evidence'=>pp_json($e),'reviewer_id'=>$u['email'],'reviewed_at'=>pp_now()];if($prior)pp_update('npl_reviews',array_diff_key($next,['observation_id'=>1]),'observation_id',$key);else pp_insert('npl_reviews',$next);pp_audit($u['email'],'npl_reviews',$key,'data-review',$prior,$next,$reason);
  }elseif($isIndicator){$name=pp_clean($p['name']??'',300);if(mb_strlen($name)<4)throw new PPError(400,'Completa el nombre del indicador.');$original['name']=$name;$original['comparability_notes']=pp_clean($p['comparability_notes']??'',4000);$original['_revision']=($original['_revision']??0)+1;$fields=['name'=>$name,'data'=>pp_json($original)];}
  else{
   $v=$p['value']??null;$unit=pp_clean($p['unit']??'',100);$url=pp_url($p['source_url']??'');if(!is_numeric($v)||!is_finite((float)$v)||abs((float)$v)>1e15||!$unit||!$url||!pp_percent_value_valid([...$original,'value'=>(float)$v,'unit'=>$unit]))throw new PPError(400,'Revisa valor, unidad y URL de la fuente.');$subgroup=array_key_exists('subgroup',$p)?pp_clean($p['subgroup'],1500):$before['subgroup'];$fields=['value'=>(float)$v,'unit'=>$unit,'subgroup'=>$subgroup];$original=[...$original,...$fields,'source_url'=>$url,'note'=>pp_clean($p['note']??'',4000),'methodology_flag'=>pp_clean($p['methodology_flag']??'',500),'_revision'=>($original['_revision']??0)+1];$fields['data']=pp_json($original);
  }
  pp_update($table,$fields,'id',$key);pp_audit($u['email'],$table,$key,$review?'review-snapshot':'data-correction',$before,[...$before,...$fields],$reason);
 });pp_reply(['ok'=>true,'status'=>$p['status']??null]);
}
function pp_analyze(string $text): array {
 $data=pp_parse(file_get_contents(__DIR__.'/text-rules.json'));$indicators=[];
 foreach($data['rules'] as $r){preg_match_all('~'.str_replace('~','\\~',$r['pattern']).'~iu',$text,$matches,PREG_OFFSET_CAPTURE);$spans=[];foreach(array_slice($matches[0],0,35) as [$match,$offset]){$start=strlen(mb_convert_encoding(substr($text,0,$offset),'UTF-16LE','UTF-8'))/2;$spans[]=['start'=>(int)$start,'end'=>(int)($start+strlen(mb_convert_encoding($match,'UTF-16LE','UTF-8'))/2),'text'=>$match];}$indicators[]=['code'=>$r['code'],'label'=>$r['label'],'status'=>$spans?'uncertain':'not_detected','confidence'=>$spans?0.55:0.25,'spans'=>$spans,'explanation'=>$r['explanation'],'alternative_reading'=>$r['alternative'],'limitations'=>$r['limit']];}
 return ['language'=>'es','indicators'=>$indicators,'summary'=>$data['summary'],'global_limitations'=>$data['global_limitations']];
}
function pp_analysis_sessions(): never {
 $u=pp_require_user();$method=$_SERVER['REQUEST_METHOD'];if($method==='GET')pp_reply(['sessions'=>pp_all('SELECT id,input_text,result,created_at FROM conflictometer_sessions WHERE user_id=? AND deleted_at IS NULL ORDER BY created_at DESC',[$u['email']])]);$p=pp_body();
 if($method==='DELETE'){pp_query('UPDATE conflictometer_sessions SET input_text=NULL,result=?,deleted_at=? WHERE id=? AND user_id=?',['{}',pp_now(),pp_clean($p['id']??'',60),$u['email']]);pp_reply(['ok'=>true]);}if($method!=='POST')throw new PPError(405,'Método no permitido.');pp_rate('save-analysis',$u['email'],20);if(($p['consent']??false)!==true)throw new PPError(400,'El guardado requiere una acción explícita.');$text=pp_clean($p['text']??'',12001);if(mb_strlen($text)<8||mb_strlen($text)>12000)throw new PPError(400,'Introduce entre 8 y 12.000 caracteres.');$key=pp_uuid();pp_insert('conflictometer_sessions',['id'=>$key,'user_id'=>$u['email'],'input_hash'=>hash('sha256',$text),'input_text'=>$text,'result'=>pp_json(pp_analyze($text))]);pp_reply(['ok'=>true,'id'=>$key]);
}
