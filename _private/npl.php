<?php
declare(strict_types=1);
const PP_REVIEW_JOIN=' LEFT JOIN npl_reviews r ON r.observation_id=o.id';
// A staged batch can never become public merely by setting review.status in SQL.
// Legacy rows without batch_id retain their historical publication predicate.
// SQL and PHP must require the same human source-verification evidence.
// A pending import/review, pending source QA or unresolved source conflict is
// never public solely because an old review.status says published.
const PP_SOURCE_CONFLICT_RESOLVED="CASE WHEN JSON_VALID(r.evidence) THEN ("
    ."CHAR_LENGTH(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.evidence,'$.source_conflict_resolution')),'')))>=60 AND "
    ."JSON_UNQUOTE(JSON_EXTRACT(r.evidence,'$.source_conflict_resolution_url')) LIKE 'https://%'"
    .") ELSE 0 END";
/** Fail-closed SQL publication gate for the INE correction announced on
 * 2026-10-01. Old ECV 2025 observations must be re-reviewed after the notice,
 * even when a historical review still says published. Does not assume that
 * the special modules actually changed: reviewers can document unaffected scope.
 * This predicate is shared by the public API and release diagnostics. */
const PP_ECV2025_REVISION_CURRENT="(NOT ("
    ."UPPER(TRIM(COALESCE(o.source_id,'')))='INE' AND o.period='2025' AND "
    ."UPPER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.dataset_code')),'')) LIKE 'ECV2025%'"
    .") OR ("
    ."r.reviewed_at>='2026-10-01 00:00:00' AND JSON_VALID(r.evidence) AND "
    ."COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.evidence,'$.revision_notice_date')),'')='2026-10-01' AND "
    ."COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.evidence,'$.retrieved_at')),'')>='2026-10-01' AND "
    ."CHAR_LENGTH(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.evidence,'$.revision_review_note')),'')))>=60 AND "
    ."(LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.evidence,'$.revision_reference_url')),'')) LIKE 'https://www.ine.es/%' OR "
    ."LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.evidence,'$.revision_reference_url')),'')) LIKE 'https://ine.es/%') AND "
    ."(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.evidence,'$.revision_review_result')),'') IN "
    ."('unchanged_after_table_comparison','corrected_value_reconciled') OR ("
    ."UPPER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.dataset_code')),'')) LIKE '%MODULE%' AND "
    ."COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.evidence,'$.revision_review_result')),'')='notice_scope_checked_not_affected'))"
    ."))";
const PP_STAGED_VERIFIED="(("
    ."JSON_EXTRACT(o.data,'$.batch_id') IS NULL AND "
    ."JSON_EXTRACT(o.data,'$.import_review') IS NULL AND "
    ."LOCATE('PENDING_VISUAL_SOURCE_QA',COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.source_quality_flag')),''))=0 AND "
    ."LOCATE('PENDING_HUMAN_SOURCE_QA',COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.source_quality_flag')),''))=0 AND "
    ."LOCATE('PENDING_VISUAL_SOURCE_QA',COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.quality_flag')),''))=0 AND "
    ."LOCATE('PENDING_HUMAN_SOURCE_QA',COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.quality_flag')),''))=0 AND "
    ."LOWER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.source_conflict_flag')),''))) IN ('','false','0','null')"
    .") OR ("
    ."NULLIF(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.source_verified_at')),'') IS NOT NULL AND "
    ."NULLIF(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.source_verified_by')),'') IS NOT NULL))"
    ." AND (LOWER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.source_conflict_flag')),''))) IN ('','false','0','null') OR (".PP_SOURCE_CONFLICT_RESOLVED."))"
    ." AND ".PP_ECV2025_REVISION_CURRENT;
const PP_PUBLISHED="r.status='published' AND BINARY r.reviewed_data=BINARY o.data AND trim(COALESCE(o.subgroup,''))<>'' AND trim(COALESCE(o.unit,''))<>'' AND o.value IS NOT NULL AND ".PP_STAGED_VERIFIED;
const PP_REVIEW_STATE="CASE WHEN BINARY r.reviewed_data=BINARY o.data THEN r.status ELSE 'review' END";
/** Public NPL output is an explicit scientific-data projection, never a dump of
 * arbitrary review JSON. The editor retains the original document and audit trail.
 * This prevents future form fields (emails, internal discussion, verification notes)
 * from silently becoming public when a reviewed row is published. */
function pp_npl_public_scrub(mixed $value): mixed {
 if(is_array($value)){
   $safe=[];
   foreach($value as $key=>$child){
     if(is_string($key)&&preg_match('/(?:email|reviewer|internal|private|secret|password|token|source_verified_by)/i',$key))continue;
     $safe[$key]=pp_npl_public_scrub($child);
   }
   return $safe;
 }
 if(is_string($value))return preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i','[correo oculto]',$value);
 return $value;
}
function pp_npl_public_projection(array $data,array $evidence): array {
 $fields=['indicator_id','period','measurement_period_start','measurement_period_end','reference_date','geo_code','geography','geography_level','subgroup',
   'value','unit','source_id','source_url','secondary_source_url','source_locator',
   'source_updated','retrieved_at','dataset_code','methodology_flag','methodology_note',
   'measurement_type','measurement_window_type','measure_definition','definition_version','population',
   'denominator','dimensions','frequency','limits','citation','survey_question',
   'derivation','signed_percent','percentage_semantics','quality_flag','source_quality_flag',
   'source_conflict_flag','publication_policy','human_source_verification',
   'source_extraction','batch_id','data_kind'];
 $public=array_intersect_key($data,array_flip($fields));
 // A reviewer may correct public methodology without changing the source row.
 // Only these fields are suitable for anonymous readers.
 $evidenceFields=['source_url','population','measurement_type','measurement_window_type','frequency','citation',
   'limits','retrieved_at','locator','source_locator','denominator','definition_version',
   'measurement_period_start','measurement_period_end','reference_date','series_key',
   'revision_review_result','revision_reference_url','revision_notice_date'];
 return [pp_npl_public_scrub($public),pp_npl_public_scrub(array_intersect_key($evidence,array_flip($evidenceFields)))];
}
/** Public catalog metadata is a whitelist too. The database stores editorial
 * source/indicator documents and can acquire private notes in future migrations.
 * Anonymous clients only need scientific definitions and source provenance.
 */
function pp_npl_public_indicator(array $indicator): array {
    $fields=['indicator_id','id','name','label_es','domain','subdomain','description',
      'unit','unit_default','source_id','source_url','dataset_code','period_start',
      'period_end','geography_scope','subgroup_dimensions','frequency','population',
      'denominator','measurement_type','measurement_window_type','measure_definition','definition_version',
      'methodology_flag','methodology_note','comparability_notes','percentage_semantics',
      'quality_grade','source_quality_flag','status','dimensions','limits',
      'observation_count','series_key','periodicity','geography_level'];
    return pp_npl_public_scrub(array_intersect_key($indicator,array_flip($fields)));
}
function pp_npl_public_source(array $source): array {
    $fields=['id','name','label','title','organization','publisher','url',
      'source_url','homepage','dataset_url','description','type','source_type',
      'country','geography_scope','methodology','methodology_note','access_date',
      'retrieved_at','license','citation','updated_at','frequency'];
    return pp_npl_public_scrub(array_intersect_key($source,array_flip($fields)));
}
function pp_npl_unpack(array $r,bool $editorial=false): array {
 // The canonical DB ID is needed by the editor and is retained for API compatibility.
 $data=pp_parse($r['data']);if(!is_array($data))$data=[];
 $evidence=pp_parse($r['evidence']??'{}');if(!is_array($evidence))$evidence=[];
 if(!$editorial){
  [$data,$evidence]=pp_npl_public_projection($data,$evidence);
  // A human reviewer may correct population, measurement type and provenance in
  // npl_reviews.evidence. The public NPL list/CSV must expose the SAME approved
  // metadata as Conflictometro analytics, rather than stale imported fields.
  // Only a narrow scientific whitelist is merged; internal notes stay excluded.
  foreach(['source_url'=>'source_url','population'=>'population',
    'measurement_type'=>'measurement_type','frequency'=>'frequency','citation'=>'citation',
    'limits'=>'limits','retrieved_at'=>'retrieved_at','locator'=>'source_locator',
    'denominator'=>'denominator','definition_version'=>'definition_version',
    'measurement_period_start'=>'measurement_period_start','measurement_period_end'=>'measurement_period_end','reference_date'=>'reference_date'] as $reviewKey=>$publicKey){
   $verified=$evidence[$reviewKey]??null;
   if(is_string($verified)&&trim($verified)!=='')$data[$publicKey]=$verified;
  }
 }
 // The legacy React editor posts row.id, while newer consumers use
 // observation_id. Both must be the canonical DB primary key, never an
 // imported JSON id (which may be absent or deliberately inconsistent).
 $canonicalId=(string)($r['observation_id']??'');
 return [...$data,...($editorial?['id'=>$canonicalId]:[]),'observation_id'=>$canonicalId,
     'review'=>['status'=>$r['review_status']??'review','reviewed_at'=>$r['reviewed_at']??null,
         'evidence'=>$evidence]];
}
/** Public coverage is NOT the size of the historical NPL archive. A machine-readable
 * distinction prevents clients from presenting 50,031 imported rows as 50,031
 * published/reviewed measurements. The ratio is descriptive, not a quality score. */
function pp_npl_availability(array $archive,array $stats): array {
 $archived=max(0,(int)($archive['observations']??0));
 $published=max(0,(int)($stats['observations']??0));
 return ['archived_observations'=>$archived,
  'published_reviewed_observations'=>$published,
  'not_publicly_available'=>max(0,$archived-$published),
  'published_fraction'=>$archived>0?round($published/$archived,6):null,
  'published_indicators'=>max(0,(int)($stats['active_indicators']??0)),
  'published_sources'=>max(0,(int)($stats['active_sources']??0)),
  'archive_is_not_published_coverage'=>true,
  'warning'=>'Las observaciones del archivo no equivalen a datos publicados y revisados; sin dato no significa cero.'];
}
/** The historical import ledger is not a public review-notes API. A public
 * dataset summary may describe archive size and published coverage, but it must
 * not serialize arbitrary audit_findings, delivery paths, editor discussions or
 * operational ingestion targets from future import manifests. */
function pp_npl_public_dataset(array $dataset): array {
 $fields=['label','observations','analytical_observations','indicators','sources',
   'domains','source_coverage','scope','sha256'];
 $safe=array_intersect_key($dataset,array_flip($fields));
 // These are descriptive summaries; scrub email addresses and nested internal keys.
 return pp_npl_public_scrub($safe);
}
/** Preserve negative numeric measurements as numbers while preventing CSV
 * formula execution, including leading control/whitespace before = + - @.
 * A quoted negative string is numeric data, not a spreadsheet formula. */
function pp_npl_csv_cell(mixed $value): string {
 $s=is_array($value)||is_object($value)?pp_json($value):(string)($value??'');
 $numeric=is_int($value)||is_float($value)||(is_string($value)&&preg_match('/^-?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/D',$value));
 if(!$numeric&&preg_match('/^[\p{Z}\p{Cc}\p{Cf}\x{FEFF}]*[=+@-]/u',$s))$s="'".$s;
 return '"'.str_replace('"','""',$s).'"';
}
/** Prefer a non-empty approved reviewer correction, then the public observation
 * metadata; never replace a reviewed value with an empty evidence field. */
function pp_npl_csv_metadata(array $row): array {
 $e=(array)($row['review']['evidence']??[]);
 foreach(['population','measurement_type','citation','limits'] as $key){
   $approved=$e[$key]??null;
   if(is_string($approved)&&trim($approved)!=='')$row[$key]=$approved;
   else $row[$key]=$row[$key]??'';
 }
 $row['verified_at']=$row['review']['reviewed_at']??null;
 return $row;
}
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
  if(!$editorial)$dataset=pp_npl_public_dataset($dataset);
  pp_reply(['api_version'=>1,'editorial'=>$editorial,'archive'=>$archive,'availability'=>pp_npl_availability($archive,$stats),'snapshot'=>$dataset['label'],'dataset'=>$dataset,'stats'=>$stats,'items'=>array_map(fn($r)=>$editorial?[...pp_parse($r['data']),'id'=>$r['id'],'observation_count'=>$r['observation_count']]:pp_npl_public_indicator([...pp_parse($r['data']),'id'=>$r['id'],'observation_count'=>$r['observation_count']]),$items),'sources'=>(object)array_column(array_map(fn($r)=>['id'=>$r['id'],'data'=>$editorial?pp_parse($r['data']):pp_npl_public_source([...pp_parse($r['data']),'id'=>$r['id']])],$sources),'data','id')]);
 }
 if(!preg_match('/^[A-Za-z0-9_-]{1,100}$/',$indicator))throw new PPError(400,'Indicador no válido.');$record=pp_one('SELECT * FROM npl_indicators WHERE id=?',[$indicator]);if(!$record)throw new PPError(404,'Indicador no encontrado.');
 $offset=filter_var($_GET['offset']??0,FILTER_VALIDATE_INT);$limit=filter_var($_GET['limit']??50,FILTER_VALIDATE_INT);if($offset===false||$offset<0||$offset>100000||!in_array($limit,[25,50,100],true))throw new PPError(400,'Paginación no válida.');
 $clauses=['o.indicator_id=?',$scope];$args=[$indicator];
 foreach(['geo'=>['geo_code',30],'group'=>['subgroup',1500],'source'=>['source_id',100]] as $parameter=>[$column,$max])if(!empty($_GET[$parameter])){if(mb_strlen($_GET[$parameter])>$max)throw new PPError(400,'Filtro demasiado largo.');$clauses[]='o.'.$column.'=?';$args[]=$_GET[$parameter];}
 if(!empty($_GET['review'])){if(!$editorial||!in_array($_GET['review'],['review','published','rejected'],true))throw new PPError(400,'Filtro editorial no válido.');$clauses[]=PP_REVIEW_STATE.'=?';$args[]=$_GET['review'];}
 if(!empty($_GET['batch'])){if(!preg_match('/^T[1-9][0-9]{1,3}(?:_[A-Z0-9_]{3,61})?$/D',$_GET['batch']))throw new PPError(400,'Tanda no válida.');$clauses[]="json_unquote(json_extract(o.data,'$.batch_id'))=?";$args[]=$_GET['batch'];}
 if(!empty($_GET['method'])){if(strlen($_GET['method'])>500)throw new PPError(400,'Método demasiado largo.');$clauses[]="json_unquote(json_extract(o.data,'$.methodology_flag'))=?";$args[]=$_GET['method'];}
 foreach(['from'=>'>=','to'=>'<='] as $k=>$op)if(!empty($_GET[$k])){if(!preg_match('/^(19|20)\d{2}$/',$_GET[$k]))throw new PPError(400,'Indica el año con cuatro cifras.');$clauses[]='substr(o.period,1,4)'.$op.'?';$args[]=$_GET[$k];}
 if(!empty($_GET['from'])&&!empty($_GET['to'])&&$_GET['from']>$_GET['to'])throw new PPError(400,'El año inicial debe ser anterior al final.');
 $where=implode(' AND ',$clauses);$csv=($_GET['format']??'')==='csv';if(isset($_GET['format'])&&!$csv)throw new PPError(400,'Formato no válido.');$total=pp_one("SELECT count(*) AS total FROM npl_observations o $join WHERE $where",$args)['total'];if($csv&&$total>1000)throw new PPError(413,'Acota los filtros: la exportación admite hasta 1.000 filas.');
 $rows=pp_all('SELECT o.id AS observation_id,o.data,r.evidence,r.reviewed_at,'.PP_REVIEW_STATE." AS review_status FROM npl_observations o $join WHERE $where ORDER BY o.period DESC,o.geography,o.subgroup,o.id LIMIT ? OFFSET ?",[...$args,$csv?1000:$limit,$csv?0:$offset]);$observations=array_map(fn($r)=>pp_npl_unpack($r,$editorial),$rows);if($csv)pp_npl_csv($indicator,$observations);
 $geographies=pp_all("SELECT DISTINCT o.geo_code,o.geography FROM npl_observations o $join WHERE o.indicator_id=? AND $scope ORDER BY o.geography",[$indicator]);$groups=array_column(pp_all("SELECT DISTINCT o.subgroup FROM npl_observations o $join WHERE o.indicator_id=? AND $scope ORDER BY o.subgroup",[$indicator]),'subgroup');
 $options=[];foreach(['batches'=>'batch_id','methods'=>'methodology_flag'] as $k=>$field)$options[$k]=array_values(array_filter(array_column(pp_all("SELECT DISTINCT json_unquote(json_extract(o.data,'$.$field')) AS value FROM npl_observations o $join WHERE o.indicator_id=? AND $scope ORDER BY value",[$indicator]),'value')));
 pp_reply(['api_version'=>1,'editorial'=>$editorial,'indicator'=>$editorial?pp_parse($record['data']):pp_npl_public_indicator([...pp_parse($record['data']),'id'=>$record['id']]),'rows'=>$observations,'total'=>$total,'geographies'=>$geographies,'groups'=>$groups,...$options,'limit'=>$limit,'offset'=>$offset]);
}
function pp_npl_csv(string $indicator,array $rows): never {
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="npl-'.$indicator.'.csv"');header('Cache-Control: no-store');
 $columns=explode(',','observation_id,indicator_id,period,geo_code,geography,subgroup,value,unit,source_id,source_url,retrieved_at,quality_flag,methodology_flag,dimensions,population,measurement_type,verified_at,citation,limits,denominator,definition_version,measurement_period_start,measurement_period_end,source_locator');
 $cell='pp_npl_csv_cell';echo "\xEF\xBB\xBF".implode(',',array_map($cell,$columns))."\r\n";
 foreach($rows as $r){$r=pp_npl_csv_metadata($r);echo implode(',',array_map(fn($k)=>$cell($r[$k]??''),$columns))."\r\n";}exit;
}
/** A prepared label (including VERIFIED) is not a human verification. Every staged
 *  observation must be checked against the original source before publication.
 *  Legacy published rows remain readable; this gate applies to new review actions.
 */
/** NPL scientific evidence must use a traceable HTTPS source, not a mixed-content
 * HTTP link, a credential-bearing URL, or a scheme-relative address. */
function pp_npl_https_source_url(mixed $url): bool {
 if(!is_string($url)||strlen($url)>2000||preg_match('/%(?:0a|0d)/i',$url))return false;
 $parts=parse_url($url);
 return is_array($parts)&&strtolower((string)($parts['scheme']??''))==='https'
  &&is_string($parts['host']??null)&&$parts['host']!==''
  &&!isset($parts['user'])&&!isset($parts['pass'])
  &&filter_var($url,FILTER_VALIDATE_URL)!==false;
}
function pp_npl_requires_source_verification(array $original): bool {
    $flag=(string)($original['source_quality_flag']??$original['quality_flag']??'');
    $secondary=(string)($original['quality_flag']??'');
    $conflict=$original['source_conflict_flag']??false;
    $unresolved=!in_array(strtolower(trim((string)$conflict)),['','false','0','null'],true);
    return !empty($original['batch_id'])||array_key_exists('import_review',$original)
        ||str_contains($flag,'PENDING_VISUAL_SOURCE_QA')
        ||str_contains($flag,'PENDING_HUMAN_SOURCE_QA')
        ||str_contains($secondary,'PENDING_VISUAL_SOURCE_QA')
        ||str_contains($secondary,'PENDING_HUMAN_SOURCE_QA')||$unresolved;
}
function pp_npl_source_conflict_present(array $original): bool {
 return !in_array(strtolower(trim((string)($original['source_conflict_flag']??''))),['','false','0','null'],true);
}
/** INEbase explicitly announced a correction of 2025 ECV data on 2026-10-01.
 * The notice does NOT prove that each press-release cell changed. For annual
 * 2025 ECV records, demand a documented post-notice table comparison rather
 * than silently treating an earlier machine check as final human verification.
 * For special modules, the reviewer must establish whether the correction
 * applies; this does NOT assume that the modules were corrected. */
function pp_npl_ecv2025_revision_recheck_required(array $o): bool {
    // A source can be a press-release HTML/PDF or a direct INEbase table.
    // Do not let a changed official URL format bypass the 2026-10-01 notice.
    if(strtoupper(trim((string)($o['source_id']??'')))!=='INE'||(string)($o['period']??'')!=='2025')return false;
    $dataset=strtoupper(trim((string)($o['dataset_code']??'')));
    if(!str_starts_with($dataset,'ECV2025'))return false;
    $url=(string)($o['source_url']??'');
    $parts=parse_url($url);
    $host=strtolower((string)($parts['host']??''));
    return in_array($host,['ine.es','www.ine.es'],true)
        &&strtolower((string)($parts['scheme']??''))==='https'
        &&!isset($parts['user'])&&!isset($parts['pass']);
}
function pp_npl_ecv2025_revision_gate(array $original,array $request): void {
    if(!pp_npl_ecv2025_revision_recheck_required($original))return;
    $date=$request['retrieved_at']??null;
    $result=$request['revision_review_result']??null;
    $url=$request['revision_reference_url']??null;
    $note=$request['revision_review_note']??null;
    $host=is_string($url)?strtolower((string)(parse_url($url,PHP_URL_HOST)??'')):'';
    $today=new DateTimeImmutable('today',new DateTimeZone('Europe/Madrid'));
    $checkedDate=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Europe/Madrid')):false;
    $dateValid=$checkedDate&&$checkedDate->format('Y-m-d')===$date&&$date>='2026-10-01'&&$checkedDate<=$today;
    $module=str_contains(strtoupper((string)($original['dataset_code']??'')),'MODULE');
    $resultValid=in_array($result,['unchanged_after_table_comparison','corrected_value_reconciled'],true)
        ||($module&&$result==='notice_scope_checked_not_affected');
    if(($request['source_verification_confirmed']??null)!==true)
        throw new PPError(400,'ECV 2025: confirma la verificación humana de la fuente primaria antes de publicar.');
    if(!$dateValid||($request['source_revision_checked']??null)!==true||!$resultValid||
        !pp_npl_https_source_url($url)||!in_array($host,['ine.es','www.ine.es'],true)||
        !is_string($note)||(function_exists('mb_strlen')?mb_strlen(trim($note)):strlen(trim($note)))<60)
        throw new PPError(409,'ECV 2025: coteja el aviso de 01/10/2026 y la tabla oficial. En módulos especiales, documenta primero si el aviso les afecta; no supongas que cambiaron. Indica resultado, URL oficial, fecha válida y 60 caracteres de justificación.');
}
function pp_npl_source_verification_gate(array $original,array $request): void {
 // Mandatory even for pre-existing legacy ECV observations without batch_id.
 // Otherwise a 2025 row published before the INE correction could be republished
 // without documenting the official revision.
 pp_npl_ecv2025_revision_gate($original,$request);
 if(!pp_npl_requires_source_verification($original))return;
 if(($request['source_verification_confirmed']??null)!==true)
     throw new PPError(400,'Verifica personalmente la cifra y su población en la fuente primaria antes de publicar. La etiqueta VERIFIED de una tanda no es verificación humana.');
 if(!is_string($request['retrieved_at']??null)||trim($request['retrieved_at'])==='')
     throw new PPError(400,'Indica explícitamente la fecha real de consulta de la fuente. No reutilices la fecha importada.');
 if(pp_npl_source_conflict_present($original)){
    $resolution=$request['source_conflict_resolution']??null;
    $url=$request['source_conflict_resolution_url']??null;
    $parts=is_string($url)?parse_url($url):false;
    $urlValid=is_string($url)&&filter_var($url,FILTER_VALIDATE_URL)&&is_array($parts)
        &&strtolower((string)($parts['scheme']??''))==='https'&&!empty($parts['host'])
        &&!isset($parts['user'])&&!isset($parts['pass']);
    if(!is_string($resolution)||(function_exists('mb_strlen')?mb_strlen(trim($resolution)):strlen(trim($resolution)))<60||!$urlValid)
        throw new PPError(409,'Existe una discrepancia entre fuentes. Documenta al menos 60 caracteres de conciliación y una URL primaria que justifique la decisión.');
 }
}
/** A reviewer can supply missing scientific descriptors without changing the archived
 * observation. Never manufacture a frequency or denominator from the year/unit.
 * The approved evidence is public metadata, not a substitute for checking sources. */
function pp_npl_review_scientific_metadata(array $input,array $original): array {
 $out=[];
 foreach(['denominator'=>1000,'definition_version'=>200,'frequency'=>20,
          'measurement_period_start'=>10,'measurement_period_end'=>10] as $field=>$limit){
  if(!array_key_exists($field,$input))continue;
  if(!is_string($input[$field]))throw new PPError(422,'Metadato científico no textual: '.$field);
  $value=trim($input[$field]);
  if(mb_strlen($value)>$limit)throw new PPError(422,'Metadato científico demasiado largo: '.$field);
  if($value!=='')$out[$field]=$value;
 }
 $frequency=$out['frequency']??($original['frequency']??null);
 $period=(string)($original['period']??'');
 if($frequency!==null&&$frequency!==''){
  if(!in_array($frequency,['annual','yearly','quarterly','monthly'],true))throw new PPError(422,'Frecuencia estadística no reconocida.');
  $pattern=match($frequency){'annual','yearly'=>'/^(19|20)[0-9]{2}$/D',
      'quarterly'=>'/^(19|20)[0-9]{2}-Q[1-4]$/D',
      'monthly'=>'/^(19|20)[0-9]{2}-(0[1-9]|1[0-2])$/D'};
  if(!preg_match($pattern,$period))throw new PPError(422,'Frecuencia incompatible con el periodo publicado.');
 }
 $start=$out['measurement_period_start']??($original['measurement_period_start']??null);
 $end=$out['measurement_period_end']??($original['measurement_period_end']??null);
 if(($start===null||$start==='')!==($end===null||$end===''))
   throw new PPError(422,'El intervalo requiere fecha inicial y final.');
 if(is_string($start)&&$start!==''){
  foreach([$start,$end] as $date){
   if(!is_string($date)||!preg_match('/^(19|20)[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$date))throw new PPError(422,'Fecha científica inválida.');
   $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
   if(!$dt||$dt->format('Y-m-d')!==$date)throw new PPError(422,'Fecha científica inexistente.');
  }
  if($start>$end||substr($start,0,4)!==substr($period,0,4)||substr($end,0,4)!==substr($period,0,4))
    throw new PPError(422,'El intervalo no corresponde al periodo declarado.');
  if($frequency==='quarterly'){
   $quarter=(int)substr($period,-1);
   $firstMonth=($quarter-1)*3+1;$lastMonth=$firstMonth+2;
   if((int)substr($start,5,2)<$firstMonth||(int)substr($end,5,2)>$lastMonth)
     throw new PPError(422,'El intervalo invade otro trimestre.');
  }
  if($frequency==='monthly'&&
     (substr($start,0,7)!==$period||substr($end,0,7)!==$period))
      throw new PPError(422,'El intervalo invade otro mes.');
 }
 return $out;
}
/** Publication requires comparable metadata, not merely a human status flag.
 * Values remain individually visible when older records lack these descriptors. */
function pp_npl_review_publication_metadata_gate(array $original,array $e): void {
 $frequency=$e['frequency']??($original['frequency']??'');
 if(!in_array($frequency,['annual','yearly','quarterly','monthly'],true))
    throw new PPError(422,'Documenta la frecuencia real de medición antes de publicar.');
 $denominator=$e['denominator']??($original['denominator']??'');
 $unit=strtolower(trim((string)($original['unit']??'')));
 $type=strtolower(trim((string)($e['measurement_type']??($original['measurement_type']??''))));
 $requiresDenominator=(bool)preg_match('/[%‰]|\b(?:por|per)\s+(?:cada\s+)?(?:[0-9][0-9.,]*|mil|mill[oó]n(?:es)?)\b|\/\s*[0-9][0-9.,]*/iu',$unit)
   ||str_contains($type,'rate')||str_contains($type,'ratio')||str_contains($type,'prevalence')||str_contains($type,'percentage');
 if($requiresDenominator&&trim((string)$denominator)==='')
    throw new PPError(422,'Documenta el denominador de la tasa antes de publicar.');
}
function pp_npl_edit(): never {
 $u=pp_require_role(['superadmin','admin_editor']);$p=pp_body();pp_rate('npl-edit',$u['email'],40,300);$reason=pp_clean($p['reason']??'',1000);if(mb_strlen($reason)<10)throw new PPError(400,'Explica el motivo y la fuente de la corrección.');
 pp_transaction(function()use($p,$u,$reason){
  $review=($p['action']??'')==='review';$isIndicator=($p['entity']??'')==='indicator';if($review&&$isIndicator)throw new PPError(400,'Solo las observaciones admiten revisión de publicación.');if(!$review&&!$isIndicator&&($p['entity']??'')!=='observation')throw new PPError(400,'Tipo de registro no válido.');$table=$isIndicator?'npl_indicators':'npl_observations';$key=(string)($p['id']??'');
  $before=pp_one("SELECT * FROM $table WHERE id=? FOR UPDATE",[$key]);if(!$before)throw new PPError(404,'Registro no encontrado.');$original=pp_parse($before['data']);if(!is_int($p['expected_revision']??null)||$p['expected_revision']!==($original['_revision']??0))throw new PPError(409,'El registro cambió. Recarga antes de editarlo.');
  if($review){
   if(!in_array($p['status']??'',['published','review','rejected'],true))throw new PPError(400,'Revisión no válida.');$e=[];foreach(['population','locator','limits','citation','series_key'] as $k)$e[$k]=pp_clean($p[$k]??'',2000);$e['source_url']=pp_url($p['source_url']??'');$e['measurement_type']=pp_clean($p['measurement_type']??'',50);
   $e=[...$e,...pp_npl_review_scientific_metadata($p,$original)];
   $e['source_conflict_resolution']=pp_clean($p['source_conflict_resolution']??'',2000);
   $e['source_conflict_resolution_url']=pp_url($p['source_conflict_resolution_url']??'');
   // Preserve the 2026-10-01 INE correction reconciliation in the review audit.
   if(pp_npl_ecv2025_revision_recheck_required($original)){
    $e['revision_review_result']=pp_clean($p['revision_review_result']??'',80);
    $e['revision_reference_url']=pp_url($p['revision_reference_url']??'');
    $e['revision_review_note']=pp_clean($p['revision_review_note']??'',2000);
    $e['revision_notice_date']='2026-10-01';
   }
   // T19/T26 are historical prepared datasets without an explicit retrieval timestamp.
   // The reviewer must record a real verification date; never synthesize a historical retrieval date.
   $verificationDate=$p['retrieved_at']??($original['retrieved_at']??null);
   if($p['status']==='published'&&!is_string($verificationDate))throw new PPError(400,'Indica la fecha real de verificación de la fuente (AAAA-MM-DD).');
   if($p['status']==='published'){
    $stagedForVerification=pp_npl_requires_source_verification($original);
    pp_npl_source_verification_gate($original,$p);
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$verificationDate,new DateTimeZone('Europe/Madrid'));
    if(!$dt||$dt->format('Y-m-d')!==$verificationDate||$dt>new DateTimeImmutable('today',new DateTimeZone('Europe/Madrid')))
      throw new PPError(400,'Fecha de verificación inválida o futura.');
    $original['retrieved_at']=$verificationDate;
    $original['retrieval_context']='human_source_verification';
    if(($p['source_verification_confirmed']??null)===true){$original['source_verified_at']=pp_now();$original['source_verified_by']=$u['email'];}
    $original['quality_flag']='REVIEWED';
    // Preserve legacy publication semantics: adding import_review to a legacy row
    // without a source stamp would make it disappear from the public API.
    if($stagedForVerification)$original['import_review']='reviewed';
    $e['retrieved_at']=$verificationDate;
   }
   if($p['status']==='published'){
    pp_npl_review_publication_metadata_gate($original,$e);
    foreach(['indicator_id','period','geo_code','geography','subgroup','unit','source_id','source_url','retrieved_at','methodology_flag'] as $k)if(!trim((string)($original[$k]??'')))throw new PPError(400,'Completa población, periodo, unidad, método y fuente.');
    if(!is_numeric($original['value']??null)||!pp_percent_value_valid($original))throw new PPError(400,'Valor no publicable.');if(!pp_npl_https_source_url($e['source_url'])||!pp_npl_https_source_url($original['source_url']??null)||!in_array($e['measurement_type'],['survey_screening','survey','administrative_record','estimate','derived'],true))throw new PPError(400,'Documenta fuente HTTPS y tipo de medida.');foreach(['population','locator','limits','citation','series_key'] as $k)if(mb_strlen($e[$k])<4)throw new PPError(400,'Documenta localizador, población, límites y cita.');
   }
   $original['_revision']=($original['_revision']??0)+1;$fields=['data'=>pp_json($original)];$prior=pp_one('SELECT * FROM npl_reviews WHERE observation_id=?',[$key]);$next=['observation_id'=>(int)$key,'status'=>$p['status'],'reviewed_data'=>$fields['data'],'evidence'=>pp_json($e),'reviewer_id'=>$u['email'],'reviewed_at'=>pp_now()];if($prior)pp_update('npl_reviews',array_diff_key($next,['observation_id'=>1]),'observation_id',$key);else pp_insert('npl_reviews',$next);pp_audit($u['email'],'npl_reviews',$key,'data-review',$prior,$next,$reason);
  }elseif($isIndicator){$name=pp_clean($p['name']??'',300);if(mb_strlen($name)<4)throw new PPError(400,'Completa el nombre del indicador.');$original['name']=$name;$original['comparability_notes']=pp_clean($p['comparability_notes']??'',4000);$original['_revision']=($original['_revision']??0)+1;$fields=['name'=>$name,'data'=>pp_json($original)];}
  else{
   $v=$p['value']??null;$unit=pp_clean($p['unit']??'',100);$url=pp_url($p['source_url']??'');if(!is_numeric($v)||!is_finite((float)$v)||abs((float)$v)>1e15||!$unit||!pp_npl_https_source_url($url)||!pp_percent_value_valid([...$original,'value'=>(float)$v,'unit'=>$unit]))throw new PPError(400,'Revisa valor, unidad y URL de la fuente.');$subgroup=array_key_exists('subgroup',$p)?pp_clean($p['subgroup'],1500):$before['subgroup'];$fields=['value'=>(float)$v,'unit'=>$unit,'subgroup'=>$subgroup];$original=[...$original,...$fields,'source_url'=>$url,'note'=>pp_clean($p['note']??'',4000),'methodology_flag'=>pp_clean($p['methodology_flag']??'',500),'_revision'=>($original['_revision']??0)+1];$fields['data']=pp_json($original);
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
