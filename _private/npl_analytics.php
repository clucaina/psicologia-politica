<?php
declare(strict_types=1);
require_once __DIR__.'/npl_catalog.php';
/** Public, reviewed-only comparative analytics; no synthetic geography or causal scores. */
function pp_analytics_id(string $value): string {if(!preg_match('/^[A-Za-z0-9_-]{2,100}$/D',$value))throw new PPError(400,'Indicador inválido.');return $value;}
function pp_analytics_current_year(): int {return (int)(new DateTimeImmutable('now',new DateTimeZone('Europe/Madrid')))->format('Y');}
function pp_analytics_period(string $value): string {
    if(!preg_match('/^(19|20)[0-9]{2}$/D',$value)|| (int)$value>pp_analytics_current_year())
        throw new PPError(400,'Año inválido o futuro.');
    return $value;
}
function pp_analytics_geos(): array {
    $geo=json_decode(file_get_contents(__DIR__.'/../geo/ccaa-code-map.json'),true);
    return array_values($geo['by_cod_ccaa']??[]);
}
function pp_analytics_rows(string $indicator,string $period,string $subgroup,array $geos): array {
    if(!$geos)return ['rows'=>[],'ambiguous'=>[]];
    $sql='SELECT o.geo_code,o.geography,o.period,o.value,o.unit,o.source_id,o.subgroup,o.data,r.evidence,r.reviewed_at FROM npl_observations o '.PP_REVIEW_JOIN.' WHERE '.PP_PUBLISHED.' AND o.indicator_id=? AND o.period=? AND o.subgroup=? AND o.geo_code IN ('.implode(',',array_fill(0,count($geos),'?')).') ORDER BY o.geo_code,o.id';
    $rows=pp_all($sql,[$indicator,$period,$subgroup,...$geos]);
    $byGeo=[];$ambiguous=[];
    foreach($rows as $row){$geo=$row['geo_code'];if(isset($byGeo[$geo])){$ambiguous[$geo]=true;continue;}$byGeo[$geo]=$row;}
    foreach(array_keys($ambiguous) as $geo)unset($byGeo[$geo]);
    return ['rows'=>$byGeo,'ambiguous'=>array_keys($ambiguous)];
}
/** Contract of one annual availability cell. Counts refer to reviewed records,
 * not to unreviewed archive rows. A singleton NULL is explicitly unavailable. */
function pp_analytics_coverage_cell(array $row,int $possible): array {
    $territories=max(0,(int)($row['territories']??0));
    $raw=max(0,(int)($row['raw_territories']??0));
    $rows=max(0,(int)($row['row_count']??0));
    $ambiguous=max(0,(int)($row['ambiguous_territories']??0));
    $missing=max(0,(int)($row['missing_value_territories']??0));
    if($territories+$ambiguous+$missing>$raw||$territories>$possible)
        throw new PPError(503,'Cobertura inconsistente: revisar las observaciones publicadas.');
    return ['group'=>(string)$row['subgroup'],'year'=>(string)$row['period'],
        'territories'=>$territories,'possible'=>$possible,
        'ambiguous_rows'=>max(0,$rows-$raw),'ambiguous_territories'=>$ambiguous,
        'missing_value_territories'=>$missing];
}
/** Lightweight published-only availability index. A missing territory is not a zero. */
function pp_analytics_coverage(): never {
    pp_rate('npl-coverage','',60,300);
    $indicator=pp_analytics_id((string)($_GET['indicator']??''));
    $geos=[...pp_analytics_geos(),'ES'];
    // A territory with two conflicting reviewed rows is NOT an available, comparable cell.
    // Page the aggregate instead of failing once an indicator has >1,000
    // year/subgroup combinations. One extra row detects the next page without hiding it.
    $raw=$_GET['offset']??'0';
    if(!is_string($raw)&&!is_int($raw))throw new PPError(400,'Desplazamiento anual inválido.');
    $raw=(string)$raw;
    if(!preg_match('/^(0|[1-9][0-9]{0,5})$/D',$raw)||(int)$raw>20000)
        throw new PPError(400,'Desplazamiento anual fuera del límite seguro.');
    $offset=(int)$raw;$pageSize=pp_catalog_page_size($_GET['page_size']??null,200);
    // A published review row may still contain SQL NULL. It is not coverage:
    // exactly one reviewed, *measured* value is required per territory/cell.
    $sql='SELECT period,subgroup,SUM(CASE WHEN geo_rows=1 AND measured_rows=1 THEN 1 ELSE 0 END) AS territories, '
       .'COUNT(*) AS raw_territories,SUM(geo_rows) AS row_count, '
       .'SUM(CASE WHEN geo_rows>1 THEN 1 ELSE 0 END) AS ambiguous_territories, '
       .'SUM(CASE WHEN geo_rows=1 AND measured_rows=0 THEN 1 ELSE 0 END) AS missing_value_territories '
       .'FROM (SELECT o.period,o.subgroup,o.geo_code,COUNT(*) AS geo_rows, '
       .'SUM(CASE WHEN o.value IS NOT NULL THEN 1 ELSE 0 END) AS measured_rows '
       .'FROM npl_observations o '.PP_REVIEW_JOIN.' WHERE '.PP_PUBLISHED
       .' AND o.indicator_id=? AND o.period REGEXP ? AND o.period<=? AND o.geo_code IN ('
       .implode(',',array_fill(0,count($geos),'?')).') GROUP BY o.period,o.subgroup,o.geo_code) AS coverage_by_geo '
       .'GROUP BY period,subgroup ORDER BY period DESC,subgroup ASC LIMIT ? OFFSET ?';
    $params=[$indicator,'^(19|20)[0-9]{2}$',(string)pp_analytics_current_year(),...$geos];
    $stampSql='SELECT COUNT(*) AS row_count,MAX(r.reviewed_at) AS max_review, '
       .'COALESCE(SUM(o.id),0) AS id_sum, '
       ."COALESCE(SUM(CRC32(CONCAT_WS('|',o.id,o.period,o.subgroup,o.geo_code,COALESCE(o.value,'NULL'),COALESCE(o.data,''),COALESCE(r.reviewed_data,''),COALESCE(r.status,''),COALESCE(r.reviewed_at,'')))),0) AS row_checksum "
       .'FROM npl_observations o '.PP_REVIEW_JOIN.' WHERE '.PP_PUBLISHED
       .' AND o.indicator_id=? AND o.period REGEXP ? AND o.period<=? AND o.geo_code IN ('
       .implode(',',array_fill(0,count($geos),'?')).')';
    [$rows,$snapshot]=pp_catalog_consistent(function()use($stampSql,$sql,$params,$pageSize,$offset){
        $snapshot=pp_catalog_token(pp_one($stampSql,$params)??[]);
        pp_catalog_check_snapshot($_GET['snapshot']??null,$snapshot,$offset);
        return [pp_all($sql,[...$params,$pageSize+1,$offset]),$snapshot];
    });
    $hasMore=count($rows)>$pageSize;
    if($hasMore)array_pop($rows);
    $groups=[];$years=[];$cells=[];
    foreach($rows as $row){
        $group=(string)$row['subgroup'];$year=(string)$row['period'];
        $groups[$group]=true;$years[$year]=true;
        $cells[]=pp_analytics_coverage_cell($row,count($geos));
    }
    pp_reply(['indicator'=>$indicator,'groups'=>array_keys($groups),'years'=>array_keys($years),
        'coverage'=>$cells,'offset'=>$offset,'page_size'=>$pageSize,'snapshot'=>$snapshot,'has_more'=>$hasMore,
        'next_offset'=>$hasMore?$offset+count($rows):null,'truncated'=>$hasMore,
        'methodology'=>'Cobertura anual revisada con huella de cambio entre páginas; si cambia la publicación, se exige reiniciar. Solo territorios con exactamente un registro publicado y valor medido (incluido cero); NULL no cuenta como cobertura y los duplicados se excluyen. La huella no sustituye auditoría científica.']);
}
/** Keep the documentary source *family*, not merely its host, when comparing
 * successive official editions. The year may change; the source path, table
 * and query must not silently change to an unrelated dataset on that host.
 * Conservative by design: changed URLs without a clear year token require
 * human reconciliation rather than an automatic longitudinal chart. */
function pp_analytics_source_family(string $url,string $period): string {
    $parts=parse_url($url);
    if(!$parts||strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))return $url;
    $year=substr($period,0,4);
    $normalize=function(string $part)use($year): string {
        if(!preg_match('/^(19|20)[0-9]{2}$/D',$year))return $part;
        return preg_replace('/(?<![0-9])'.preg_quote($year,'/').'(?![0-9])/','[year]',$part);
    };
    $path=$normalize((string)($parts['path']??'/'));
    $query=isset($parts['query'])?'?'.$normalize($parts['query']):'';
    $port=isset($parts['port'])?':'.$parts['port']:'';
    return strtolower($parts['host']).$port.$path.$query;
}
/** Rates and percentages without a defined denominator can be shown as
 * individual observations, but never treated as ready for comparisons. */
function pp_analytics_denominator_required(array $point): bool {
    $unit=strtolower(trim((string)($point['unit']??'')));
    $type=strtolower(trim((string)($point['measurement_type']??'')));
    return (bool)preg_match('/[%‰]|\b(?:por|per)\s+(?:cada\s+)?(?:[0-9][0-9.,]*|mil|mill[oó]n(?:es)?)\b|\/\s*[0-9][0-9.,]*/iu',$unit) ||
           str_contains($type,'rate')||str_contains($type,'ratio')||
           str_contains($type,'prevalence')||str_contains($type,'percentage');
}
/** Measurement fingerprint: equal percentages may refer to different populations or concepts. */
/** Do not label a comparison valid when both records omit the same population definition. */
function pp_analytics_metadata_complete(array $point): bool {
    foreach(['unit','source_id','source_url','population','measurement_type','period','reviewed_at'] as $key)
        if(!is_string($point[$key]??null)||trim($point[$key])==='')return false;
    if(pp_analytics_denominator_required($point) && (!is_string($point['denominator']??null)||trim($point['denominator'])===''))return false;
    // Explicit frequency is required for comparative use. A year label alone
    // cannot prove whether a source covers a calendar year, quarter or month.
    // Legacy rows remain individually visible but cannot drive ranks, maps,
    // correlations or connected time series until a reviewer documents it.
    $frequency=strtolower(trim((string)($point['frequency']??'')));
    $period=(string)($point['period']??'');
    if(!in_array($frequency,['annual','yearly','quarterly','monthly'],true))return false;
    if(in_array($frequency,['annual','yearly'],true)&&!preg_match('/^(19|20)[0-9]{2}$/D',$period))return false;
    if($frequency==='quarterly'&&!preg_match('/^(19|20)[0-9]{2}-Q[1-4]$/D',$period))return false;
    if($frequency==='monthly'&&!preg_match('/^(19|20)[0-9]{2}-(0[1-9]|1[0-2])$/D',$period))return false;
    // A point-in-time stock cannot masquerade as an interval observation.
    // Apply this to reviewed legacy rows too, not just to new batch imports.
    $reference=$point['reference_date']??null;
    $hasReference=is_string($reference)&&trim($reference)!=='';
    if($reference!==null&&!$hasReference)return false;
    if($hasReference){
        if(!preg_match('/^(19|20)[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$reference))return false;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$reference);
        if(!$date||$date->format('Y-m-d')!==$reference)return false;
        if($reference>(new DateTimeImmutable('now',new DateTimeZone('Europe/Madrid')))->format('Y-m-d'))return false;
    }
    $ecp=str_contains(strtoupper((string)($point['dataset_code']??'')),'ECP');
    if($ecp){
        if($frequency!=='quarterly'||!$hasReference||!preg_match('/^((?:19|20)[0-9]{2})-Q([1-4])$/D',$period,$m))return false;
        $expected=(int)$m[2]===4?sprintf('%04d-01-01',(int)$m[1]+1):sprintf('%04d-%02d-01',(int)$m[1],(int)$m[2]*3+1);
        if($reference!==$expected)return false;
    }

    $url=$point['source_url'];$parts=parse_url($url);
    if(!$parts||strtolower($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))return false;
    // A calendar year is not the same observation window as January-June.
    // For partial-year and cumulative series the exact bounds are mandatory.
    $start=$point['measurement_period_start']??null;
    $end=$point['measurement_period_end']??null;
    $hasStart=is_string($start)&&trim($start)!=='';
    $hasEnd=is_string($end)&&trim($end)!=='';
    $cumulative=str_contains(strtoupper((string)($point['methodology_flag']??'')),'CUMULATIVE_JAN_JUN');
    if($hasStart!==$hasEnd||($cumulative&&!$hasStart))return false;
    if($hasStart){
        foreach([$start,$end] as $date){
            if(!preg_match('/^(19|20)[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$date))return false;
            $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if(!$parsed||$parsed->format('Y-m-d')!==$date)return false;
        }
        if($start>$end||substr($start,0,4)!==substr((string)$point['period'],0,4)||
           substr($end,0,4)!==substr((string)$point['period'],0,4))return false;
        if($cumulative&&(substr($start,5)!=='01-01'||substr($end,5)!=='06-30'))return false;
    }
    return true;
}
/** Exact windows for same-year comparisons; year-neutral month/day windows for
 * longitudinal comparisons, so 2025 H1 and 2026 H1 remain comparable while
 * H1 and full-year observations never become the same series. */
function pp_analytics_signature(array $p,bool $yearNeutral=false): array {
    $keys=['unit','source_id','source_url','dataset_code','methodology_flag','measurement_type',
        'percentage_semantics','population','denominator','definition_version','data_kind','frequency','measurement_window_type',
        'measurement_period_start','measurement_period_end','reference_date'];
    $signature=array_map(fn($key)=>$p[$key]??null,$keys);
    if($yearNeutral){
        foreach(['measurement_period_start','measurement_period_end','reference_date'] as $key){
            $index=array_search($key,$keys,true);
            $v=$signature[$index];
            if(is_string($v)&&preg_match('/^(?:19|20)[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$v))
                $signature[$index]=substr($v,5);
        }
        // Official annual releases can change the year in the path, but a
        // different publication on the same host must NEVER be assumed equal.
        $urlIndex=array_search('source_url',$keys,true);
        $url=$signature[$urlIndex];
        if(is_string($url))$signature[$urlIndex]=pp_analytics_source_family($url,(string)($p['period']??''));
        $datasetIndex=array_search('dataset_code',$keys,true);
        $dataset=$signature[$datasetIndex];
        $year=substr((string)($p['period']??''),0,4);
        if(is_string($dataset)&&preg_match('/^(19|20)[0-9]{2}$/D',$year)&&str_ends_with($dataset,$year))
            $signature[$datasetIndex]=substr($dataset,0,-4).'[year]';
    }
    return $signature;
}
/** Compatibility is about measurement, not about magnitude. Null is missing, zero is valid. */
function pp_analytics_compatibility(array $points): array {
    $available=array_values(array_filter($points,fn($p)=>$p!==null));
    $signatures=[];
    foreach($available as $p){
        $signature=pp_analytics_signature($p);
        $signatures[pp_json($signature)]=true;
    }
    $missing=count($points)-count($available);
    $complete=$missing===0;
    $homogeneous=count($signatures)<=1;
    $metadataComplete=count($available)>0&&count(array_filter($available,'pp_analytics_metadata_complete'))===count($available);
    $enough=count($available)>=2;
    return ['complete'=>$complete,'homogeneous'=>$homogeneous,'metadata_complete'=>$metadataComplete,
        'comparable'=>$complete&&$homogeneous&&$metadataComplete&&$enough,
        'available'=>count($available),'requested'=>count($points),
        'reason'=>!$complete?'missing_data':(!$homogeneous?'incompatible_source_unit_or_methodology':(!$metadataComplete?'missing_measurement_metadata':(!$enough?'insufficient_territories':'ok')))];
}
function pp_analytics_point(?array $r): ?array {
    if(!$r||!array_key_exists('value',$r)||$r['value']===null||!is_numeric($r['value'])||!is_finite((float)$r['value']))return null;
    $data=pp_parse($r['data']);$evidence=pp_parse($r['evidence']??'{}');
    if(!is_array($data))$data=[];if(!is_array($evidence))$evidence=[];
    // Reviewers record the validated population, measurement type, citation and limits in
    // npl_reviews.evidence. Without this merge the public map silently loses the QA metadata.
    $reviewed=function(string $key,?string $original=null)use($evidence,$data): mixed {
        $v=$evidence[$key]??null;
        return is_string($v)&&trim($v)!==''?$v:($data[$original??$key]??null);
    };
    return ['value'=>(float)$r['value'],'unit'=>$r['unit'],'source_id'=>$r['source_id'],
        'source_url'=>$reviewed('source_url'),'period'=>$r['period'],
        'methodology_flag'=>$data['methodology_flag']??null,'dataset_code'=>$data['dataset_code']??null,
        'data_kind'=>$data['data_kind']??null,'subgroup'=>$r['subgroup']??($data['subgroup']??null),
        'measurement_period_start'=>$reviewed('measurement_period_start'),
        'measurement_period_end'=>$reviewed('measurement_period_end'),
        'reference_date'=>$reviewed('reference_date'),
        'measurement_type'=>$reviewed('measurement_type'),'measurement_window_type'=>$reviewed('measurement_window_type'),'percentage_semantics'=>$data['percentage_semantics']??null,'population'=>$reviewed('population'),
        'denominator'=>$reviewed('denominator'),'definition_version'=>$reviewed('definition_version'),
        'source_updated'=>$data['source_updated']??null,'frequency'=>$reviewed('frequency'),'source_locator'=>$reviewed('locator','source_locator'),
        'citation'=>$reviewed('citation'),'limits'=>$reviewed('limits'),
        'retrieved_at'=>$reviewed('retrieved_at'),'reviewed_at'=>$r['reviewed_at'],
        'geography'=>$r['geography'],'geo_code'=>$r['geo_code']];
}
function pp_analytics_ranks(array $values): array {
    $indexed=[];foreach($values as $i=>$v)$indexed[]=['index'=>$i,'value'=>(float)$v];usort($indexed,fn($a,$b)=>$a['value']<=>$b['value']);
    $ranks=array_fill(0,count($values),0.0);$n=count($values);
    for($i=0;$i<$n;){$j=$i+1;while($j<$n&&$indexed[$j]['value']===$indexed[$i]['value'])$j++;$rank=($i+1+$j)/2.0;for($k=$i;$k<$j;$k++)$ranks[$indexed[$k]['index']]=$rank;$i=$j;}
    return $ranks;
}
/** Scale before centering: values near PHP_FLOAT_MAX or subnormal values must
 * not overflow/underflow the sum-of-squares calculation. The output is a
 * descriptive coefficient, not a significance test. */
function pp_analytics_pearson(array $x,array $y): ?float {
    $n=count($x);if($n<2||$n!==count($y))return null;
    $sx=0.0;$sy=0.0;
    foreach($x as $v){if(!is_numeric($v)||!is_finite((float)$v))return null;$sx=max($sx,abs((float)$v));}
    foreach($y as $v){if(!is_numeric($v)||!is_finite((float)$v))return null;$sy=max($sy,abs((float)$v));}
    if($sx===0.0||$sy===0.0)return null;
    $ax=array_map(static fn($v)=>(float)$v/$sx,$x);
    $ay=array_map(static fn($v)=>(float)$v/$sy,$y);
    $mx=array_sum($ax)/$n;$my=array_sum($ay)/$n;
    $cross=0.0;$vx=0.0;$vy=0.0;
    for($i=0;$i<$n;$i++){
        $dx=$ax[$i]-$mx;$dy=$ay[$i]-$my;
        $cross+=$dx*$dy;$vx+=$dx*$dx;$vy+=$dy*$dy;
    }
    if($vx<=0.0||$vy<=0.0)return null;
    return max(-1.0,min(1.0,($cross/sqrt($vx))/sqrt($vy)));
}
/** Leave-one-territory-out is a sensitivity diagnostic, NOT a p-value,
 * confidence interval or correction for spatial dependence. */
function pp_analytics_leave_one_out(array $x,array $y,array $territories): array {
    $n=count($x);
    if($n<8||$n!==count($y)||$n!==count($territories))
        return ['status'=>'insufficient_coverage','n'=>$n];
    $full=pp_analytics_pearson($x,$y);
    if($full===null)return ['status'=>'constant_series','n'=>$n];
    $byTerritory=[];
    foreach($territories as $i=>$territory){
        $xx=$x;$yy=$y;array_splice($xx,$i,1);array_splice($yy,$i,1);
        $r=pp_analytics_pearson($xx,$yy);
        if($r===null)return ['status'=>'unstable_constant_subsample','n'=>$n,'omitted_territory'=>$territory];
        $byTerritory[$territory]=$r;
    }
    $min=min($byTerritory);$max=max($byTerritory);$influential=null;$delta=-1.0;
    foreach($byTerritory as $geo=>$r){$d=abs($r-$full);if($d>$delta){$delta=$d;$influential=$geo;}}
    $signFlip=($full>0.0&&$min<0.0)||($full<0.0&&$max>0.0);
    return ['status'=>'ok','n'=>$n,'min_r'=>$min,'max_r'=>$max,
        'max_absolute_change'=>$delta,'most_influential_territory'=>$influential,
        'sign_flip'=>$signFlip,'fragility_warning'=>$signFlip||$delta>0.30,
        'method'=>'Eliminar un territorio por vez; intervalo descriptivo de sensibilidad, NO intervalo de confianza ni prueba de significación.'];
}
/** Spearman sensitivity MUST recompute ranks after each omission. Reusing ranks
 * from the full sample changes the coefficient when ties are present; it can
 * create spurious fragility warnings or conceal an influential territory.
 * This is a descriptive leave-one-out diagnostic, not an inference test. */
function pp_analytics_spearman(array $x,array $y): ?float {
    if(count($x)!==count($y)||count($x)<2)return null;
    foreach([...$x,...$y] as $value)
        if(!is_numeric($value)||!is_finite((float)$value))return null;
    return pp_analytics_pearson(pp_analytics_ranks($x),pp_analytics_ranks($y));
}
function pp_analytics_spearman_leave_one_out(array $x,array $y,array $territories): array {
    $n=count($x);
    if($n<8||$n!==count($y)||$n!==count($territories))
        return ['status'=>'insufficient_coverage','n'=>$n];
    $full=pp_analytics_spearman($x,$y);
    if($full===null)return ['status'=>'constant_series','n'=>$n];
    $byTerritory=[];
    foreach($territories as $i=>$territory){
        $xx=$x;$yy=$y;array_splice($xx,$i,1);array_splice($yy,$i,1);
        $rho=pp_analytics_spearman($xx,$yy);
        if($rho===null)return ['status'=>'unstable_constant_subsample','n'=>$n,'omitted_territory'=>$territory];
        $byTerritory[$territory]=$rho;
    }
    $min=min($byTerritory);$max=max($byTerritory);$influential=null;$delta=-1.0;
    foreach($byTerritory as $geo=>$rho){$d=abs($rho-$full);if($d>$delta){$delta=$d;$influential=$geo;}}
    $signFlip=($full>0.0&&$min<0.0)||($full<0.0&&$max>0.0);
    return ['status'=>'ok','n'=>$n,'min_r'=>$min,'max_r'=>$max,
        'max_absolute_change'=>$delta,'most_influential_territory'=>$influential,
        'sign_flip'=>$signFlip,'fragility_warning'=>$signFlip||$delta>0.30,
        'method'=>'Spearman: recalcular rangos y empates tras excluir cada territorio; NO es intervalo de confianza ni prueba de significación.'];
}
function pp_analytics_compare(): never {
    pp_rate('npl-compare','',80,300);
    $indicator=pp_analytics_id((string)($_GET['indicator']??''));$period=pp_analytics_period((string)($_GET['period']??''));
    $group=pp_clean($_GET['group']??'',200);if($group==='')throw new PPError(400,'Selecciona subgrupo.');
    $geos=explode(',',(string)($_GET['geos']??''));$allowed=[...pp_analytics_geos(),'ES'];
    if(count($geos)<1||count($geos)>20||count(array_unique($geos))!==count($geos)||array_diff($geos,$allowed))throw new PPError(400,'Selecciona territorios válidos sin repetir.');
    $data=pp_analytics_rows($indicator,$period,$group,$geos);$points=[];
    foreach($geos as $geo)$points[$geo]=pp_analytics_point($data['rows'][$geo]??null);
    $compat=pp_analytics_compatibility($points);
    pp_reply(['indicator'=>$indicator,'period'=>$period,'subgroup'=>$group,'points'=>$points,'ambiguous'=>$data['ambiguous'],
        'comparability'=>$compat,'methodology'=>'Solo registros publicados y revisados. null = sin dato; 0 = cero observado. Nunca se sustituye un dato territorial por el nacional. Si difieren fuente, unidad o metodología, los valores se muestran pero no se comparan numéricamente.']);
}
/** Correlating two territorial indicators requires the same observation window.
 * An H1 cumulative police count and an annual poverty rate are not simultaneous
 * measurements merely because both display "2026". Do not infer unknown bounds.
 * Different populations are permitted across variables, but reported explicitly.
 */
function pp_analytics_cross_axis_window(array $x,array $y): array {
    $frequencyX=strtolower(trim((string)($x['frequency']??'')));
    $frequencyY=strtolower(trim((string)($y['frequency']??'')));
    if($frequencyX==='yearly')$frequencyX='annual';
    if($frequencyY==='yearly')$frequencyY='annual';
    $startX=$x['measurement_period_start']??null;$endX=$x['measurement_period_end']??null;
    $startY=$y['measurement_period_start']??null;$endY=$y['measurement_period_end']??null;
    $boundedX=is_string($startX)&&$startX!==''&&is_string($endX)&&$endX!=='';
    $boundedY=is_string($startY)&&$startY!==''&&is_string($endY)&&$endY!=='';
    $referenceX=trim((string)($x['reference_date']??''));
    $referenceY=trim((string)($y['reference_date']??''));
    // Same nominal quarter/year does NOT make a population stock at one date
    // temporally interchangeable with a quarterly flow or survey prevalence.
    $windowTypeX=strtolower(trim((string)($x['measurement_window_type']??'')));
    $windowTypeY=strtolower(trim((string)($y['measurement_window_type']??'')));
    // Rolling survey lookbacks, calendar-year totals and point-in-time
    // measurements must not be silently treated as the same annual window.
    if($windowTypeX!==$windowTypeY)
        return ['compatible'=>false,'reason'=>'incompatible_measurement_window_type'];
    if(($referenceX==='')!==($referenceY===''))
        return ['compatible'=>false,'reason'=>'incompatible_temporal_basis'];
    if($referenceX!==''&&$referenceX!==$referenceY)
        return ['compatible'=>false,'reason'=>'incompatible_reference_dates'];
    if($frequencyX!==$frequencyY || $boundedX!==$boundedY ||
       ($boundedX&&($startX!==$startY||$endX!==$endY)))
        return ['compatible'=>false,'reason'=>'incompatible_time_windows'];
    // A legacy record without bounds cannot silently override an explicit
    // cumulative-period flag on another indicator.
    $cumulativeX=str_contains(strtoupper((string)($x['methodology_flag']??'')),'CUMULATIVE_');
    $cumulativeY=str_contains(strtoupper((string)($y['methodology_flag']??'')),'CUMULATIVE_');
    if($cumulativeX!==$cumulativeY)return ['compatible'=>false,'reason'=>'incompatible_time_windows'];
    return ['compatible'=>true,'reason'=>'ok',
        'frequency'=>$frequencyX,
        'start'=>$boundedX?$startX:null,'end'=>$boundedX?$endX:null,
        'temporal_basis'=>$referenceX!==''?'stock_at_reference_date':($boundedX?'bounded_interval':'declared_frequency'),
        'reference_date'=>$referenceX!==''?$referenceX:null,
        'measurement_window_type'=>$windowTypeX!==''?$windowTypeX:null];
}
/** Geographic coverage is not population representativeness. Even all 19
 * NUTS2 territories are ecological units, not a probability sample of people.
 * A partial 8/19 correlation may be exploratory but must never be labelled
 * "Spain-wide" or a representative national estimate. */
function pp_analytics_correlation_coverage(int $n,int $possible): array {
    if($n<0||$possible<1||$n>$possible)throw new PPError(503,'Cobertura territorial imposible.');
    return ['matched_territories'=>$n,'possible_territories'=>$possible,
        'fraction'=>round($n/$possible,6),'complete'=>$n===$possible,
        'population_representative'=>false,
        'warning'=>$n<$possible?'Cobertura territorial parcial: no extrapolar el coeficiente a las comunidades sin ambas mediciones.':'Cobertura territorial completa, pero correlación ecológica sin ponderación poblacional; no equivale a una estimación individual representativa.'];
}
/** Correlation gate: validate numerical values BEFORE casting and require traceable measurement metadata.
 * A missing value is never a zero; 0 is a valid measured value. No implicit imputation. */
function pp_analytics_correlation_quality(array $left,array $right,array $geos): array {
    $vx=[];$vy=[];$matched=[];$excluded=[];$metadataMissing=[];$leftPoints=[];$rightPoints=[];
    foreach($geos as $geo){
        $a=pp_analytics_point($left[$geo]??null);
        $b=pp_analytics_point($right[$geo]??null);
        if($a===null||$b===null){$excluded[]=$geo;continue;}
        $matched[]=$geo;$vx[]=$a['value'];$vy[]=$b['value'];$leftPoints[]=$a;$rightPoints[]=$b;
        foreach(['unit','source_id','source_url','population','measurement_type','period','reviewed_at'] as $key){
            foreach(['x'=>$a,'y'=>$b] as $axis=>$point){
                if(!is_string($point[$key]??null)||trim($point[$key])==='')$metadataMissing[$axis.':'.$key]=true;
            }
        }
        foreach(['x'=>$a,'y'=>$b] as $axis=>$point)if(!pp_analytics_metadata_complete($point))$metadataMissing[$axis.':metadata_or_source_url']=true;
    }
    $n=count($matched);
    $coverage=pp_analytics_correlation_coverage($n,count($geos));
    if($n<8)return ['status'=>'insufficient_coverage','n'=>$n,'min_n'=>8,'territories'=>$matched,'excluded'=>$excluded,'coverage'=>$coverage];
    if($metadataMissing)return ['status'=>'insufficient_metadata','n'=>$n,'territories'=>$matched,'excluded'=>$excluded,
        'missing_fields'=>array_keys($metadataMissing),'coverage'=>$coverage,'warning'=>'No se calcula correlación hasta verificar población, tipo de medida, fuente y fecha de revisión.'];
    $xSignatures=array_unique(array_map(fn($p)=>pp_json(pp_analytics_signature($p)),$leftPoints));
    $ySignatures=array_unique(array_map(fn($p)=>pp_json(pp_analytics_signature($p)),$rightPoints));
    if(count($xSignatures)!==1||count($ySignatures)!==1)
        return ['status'=>'incompatible_measurements','n'=>$n,'territories'=>$matched,'excluded'=>$excluded,
            'coverage'=>$coverage,'warning'=>'Series heterogéneas en unidad, fuente, población, definición o metodología.'];
    $crossWindow=pp_analytics_cross_axis_window($leftPoints[0],$rightPoints[0]);
    if(!$crossWindow['compatible'])return ['status'=>'incompatible_time_windows','n'=>$n,
        'territories'=>$matched,'excluded'=>$excluded,'coverage'=>$coverage,
        'warning'=>'Las dos variables no comparten fecha de referencia, tipo temporal (stock frente a intervalo), frecuencia o ventana documentada. No se correlacionan automáticamente mediciones incompatibles.'];
    $pearson=pp_analytics_pearson($vx,$vy);
    $spearman=pp_analytics_spearman($vx,$vy);
    $sensitivity=$pearson!==null?pp_analytics_leave_one_out($vx,$vy,$matched):['status'=>'constant_series','n'=>$n];
    $spearmanSensitivity=$spearman!==null?pp_analytics_spearman_leave_one_out($vx,$vy,$matched):['status'=>'constant_series','n'=>$n];
    $paired=[];
    foreach($matched as $i=>$geo)$paired[]=['geo_code'=>$geo,'x'=>$vx[$i],'y'=>$vy[$i]];

    return ['status'=>$pearson===null||$spearman===null?'constant_series':'ok',
        'n'=>$n,'territories'=>$matched,'excluded'=>$excluded,'coverage'=>$coverage,'pearson_r'=>$pearson,'spearman_rho'=>$spearman,
        'leave_one_territory_out'=>$sensitivity,'spearman_leave_one_out'=>$spearmanSensitivity,
        'paired_observations'=>$paired,'measurement_window'=>$crossWindow,

        'sources'=>['x'=>array_intersect_key($leftPoints[0],array_flip(['unit','source_id','dataset_code','population','measurement_type','measurement_window_type','reference_date','source_url','methodology_flag'])),
                    'y'=>array_intersect_key($rightPoints[0],array_flip(['unit','source_id','dataset_code','population','measurement_type','measurement_window_type','reference_date','source_url','methodology_flag']))]];
}
function pp_analytics_correlation(): never {
    pp_rate('npl-correlation','',30,300);
    $xid=pp_analytics_id((string)($_GET['x']??''));$yid=pp_analytics_id((string)($_GET['y']??''));
    if($xid===$yid)throw new PPError(400,'Selecciona dos indicadores distintos.');
    $period=pp_analytics_period((string)($_GET['period']??''));$gx=pp_clean($_GET['group_x']??'',200);$gy=pp_clean($_GET['group_y']??'',200);
    if(!$gx||!$gy)throw new PPError(400,'Indica ambos subgrupos.');
    $geos=pp_analytics_geos();$left=pp_analytics_rows($xid,$period,$gx,$geos);$right=pp_analytics_rows($yid,$period,$gy,$geos);
    $quality=pp_analytics_correlation_quality($left['rows'],$right['rows'],$geos);
    pp_reply(['x'=>$xid,'y'=>$yid,'period'=>$period,'subgroups'=>[$gx,$gy],...$quality,
        'ambiguous'=>array_values(array_unique([...$left['ambiguous'],...$right['ambiguous']])),
        'limitations'=>['Correlación transversal descriptiva, no causalidad.',
            'No ajusta factores de confusión ni dependencia espacial.',
            'No establece significación estadística ni efectos individuales.',
            'Solo territorios con ambos datos revisados del mismo año y metadatos explícitos.']]);
}

/** Build explicit drawable runs: a chart must never connect missing years,
 * unreviewed metadata, or incompatible definitions with an invented line.
 * Returned years are labels, not interpolated observations. A measured zero is valid.
 * This is a pure helper so it can be tested without MariaDB or a browser. */
function pp_analytics_series_segments(array $points): array {
    $runs=[];$current=[];$missing=[];$blocked=[];$breaks=[];$isolated=[];
    $previousYear=null;$previousSignature=null;
    $flush=static function()use(&$runs,&$current,&$isolated): void {
        if(count($current)>=2)$runs[]=$current;
        elseif(count($current)===1)$isolated[]=$current[0];
        $current=[];
    };
    foreach($points as $year=>$point){
        $year=(string)$year;
        if(!preg_match('/^(19|20)[0-9]{2}$/D',$year))throw new PPError(422,'Año de serie inválido.');
        if($point===null){$flush();$missing[]=$year;$previousYear=null;$previousSignature=null;continue;}
        if(!pp_analytics_metadata_complete($point)){
            $flush();$blocked[]=$year;$previousYear=null;$previousSignature=null;continue;
        }
        $signature=pp_json(pp_analytics_signature($point,true));
        if($previousYear!==null && ((int)$year!==(int)$previousYear+1 || $signature!==$previousSignature)){
            if($signature!==$previousSignature)$breaks[]=['from'=>$previousYear,'to'=>$year,'reason'=>'incompatible_measurement'];
            $flush();
        }
        $current[]=$year;$previousYear=$year;$previousSignature=$signature;
    }
    $flush();
    return ['drawable_segments'=>$runs,'isolated_years'=>$isolated,'missing_years'=>$missing,
        'metadata_blocked_years'=>$blocked,'methodology_breaks'=>$breaks,
        'drawable'=>count($runs)>0,'continuous'=>count($runs)===1&&count($missing)===0&&count($blocked)===0&&count($breaks)===0&&count($isolated)===0,
        'methodology'=>'Solo se unen años consecutivos con metadatos completos y definiciones homogéneas. Los huecos no se interpolan.'];
}
/** Published-only annual series; every point carries its own provenance. */
function pp_analytics_series(): never {
    pp_rate('npl-series','',50,300);
    $indicator=pp_analytics_id((string)($_GET['indicator']??''));
    $group=pp_clean($_GET['group']??'',200);if($group==='')throw new PPError(400,'Selecciona subgrupo.');
    $from=(int)pp_analytics_period((string)($_GET['from']??''));
    $to=(int)pp_analytics_period((string)($_GET['to']??''));
    if($from>$to||$to-$from>29||$to>pp_analytics_current_year())throw new PPError(400,'Intervalo anual inválido (máximo 30 años).');
    $geos=explode(',',(string)($_GET['geos']??''));$allowed=[...pp_analytics_geos(),'ES'];
    if(count($geos)<1||count($geos)>5||count(array_unique($geos))!==count($geos)||array_diff($geos,$allowed))throw new PPError(400,'Selecciona entre uno y cinco territorios válidos.');
    $years=array_map('strval',range($from,$to));
    $sql='SELECT o.geo_code,o.geography,o.period,o.value,o.unit,o.source_id,o.subgroup,o.data,r.evidence,r.reviewed_at FROM npl_observations o '.PP_REVIEW_JOIN.
         ' WHERE '.PP_PUBLISHED.' AND o.indicator_id=? AND o.subgroup=? AND o.geo_code IN ('.implode(',',array_fill(0,count($geos),'?')).')'.
         ' AND o.period IN ('.implode(',',array_fill(0,count($years),'?')).') ORDER BY o.geo_code,o.period,o.id';
    $rows=pp_all($sql,[$indicator,$group,...$geos,...$years]);
    $found=[];$ambiguous=[];
    foreach($rows as $row){$key=$row['geo_code'].'|'.$row['period'];if(isset($found[$key])){$ambiguous[$key]=true;continue;}$found[$key]=$row;}
    foreach(array_keys($ambiguous) as $key)unset($found[$key]);
    $series=[];$incompatible=[];
    foreach($geos as $geo){
        $points=[];$metadata=[];
        foreach($years as $year){$key=$geo.'|'.$year;$point=pp_analytics_point($found[$key]??null);$points[$year]=$point;
            if($point)$metadata[$year]=pp_analytics_signature($point,true);
        }
        $distinct=array_unique(array_map('json_encode',array_values($metadata)));
        $renderPlan=pp_analytics_series_segments($points);
        if(count($distinct)>1){$incompatible[]=$geo; /* Incompatible releases are separated, never joined. */
            $series[$geo]=['points'=>$points,'comparable'=>false,...$renderPlan];
        }else $series[$geo]=['points'=>$points,'comparable'=>count($metadata)>1&&count(array_filter($points,fn($p)=>$p!==null&&!pp_analytics_metadata_complete($p)))===0,...$renderPlan];
    }
    // Across-territory comparisons must share a measurement signature as well.
    $allSignatures=[];
    foreach($series as $seriesData)foreach($seriesData['points'] as $point){
        if($point===null)continue;
        $allSignatures[pp_json(pp_analytics_signature($point,true))]=true;
    }
    $crossComparable=count($allSignatures)<=1;
    $allPoints=[];foreach($series as $seriesData)foreach($seriesData['points'] as $point)if($point!==null)$allPoints[]=$point;
    $metadataComplete=count($allPoints)>0&&count(array_filter($allPoints,'pp_analytics_metadata_complete'))===count($allPoints);
    $crossComparable=$crossComparable&&$metadataComplete;
    pp_reply(['indicator'=>$indicator,'subgroup'=>$group,'from'=>$from,'to'=>$to,'series'=>$series,'ambiguous'=>array_keys($ambiguous),
        'incompatible_series'=>$incompatible,'cross_territory_comparable'=>$crossComparable,'metadata_complete'=>$metadataComplete,
        'comparison_warning'=>$crossComparable?null:'No superponer series de territorios con distinta fuente, unidad o metodología.',
        'methodology'=>'Solo datos revisados. null = sin dato, 0 = cero real. No se interpolan años. Fuentes/unidades/metodologías diferentes impiden dibujar una serie continua o comparar territorios.']);
}
/** Strict numerical ties for rankings. A tolerance such as 1e-12 can
 * merge two distinct low-prevalence observations into an invented tie. Values
 * have already been converted to finite floats by pp_analytics_point().
 * This helper is pure and never changes the measured values. */
function pp_analytics_assign_ranks(array $sorted): array {
    $previous=null;$rank=0;
    foreach($sorted as $i=>&$row){
        $value=$row['value']??null;
        if(!is_float($value)||!is_finite($value))
            throw new PPError(422,'Valor de ranking inválido.');
        if($previous===null||$value!==$previous)$rank=$i+1;
        $row['rank']=$rank;$previous=$value;
    }
    unset($row);
    return $sorted;
}
/** Ranking describes magnitude, not which region is politically or socially 'better'. */
function pp_analytics_rank(): never {
    pp_rate('npl-rank','',40,300);
    $indicator=pp_analytics_id((string)($_GET['indicator']??''));
    $period=pp_analytics_period((string)($_GET['period']??''));
    $group=pp_clean($_GET['group']??'',200);if($group==='')throw new PPError(400,'Selecciona subgrupo.');
    $direction=(string)($_GET['direction']??'desc');if(!in_array($direction,['asc','desc'],true))throw new PPError(400,'Orden inválido.');
    $geos=pp_analytics_geos();$data=pp_analytics_rows($indicator,$period,$group,$geos);
    $valid=[];$missing=[];$signatures=[];
    foreach($geos as $geo){$point=pp_analytics_point($data['rows'][$geo]??null);
        if(!$point){$missing[]=$geo;continue;}
        $signatures[pp_json(pp_analytics_signature($point))]=true;
        $valid[]=$point;
    }
    if(count($valid)<2)throw new PPError(422,'Ranking no disponible: se necesitan al menos dos territorios con datos revisados.');
    if(count($signatures)>1||count(array_filter($valid,fn($p)=>!pp_analytics_metadata_complete($p)))>0)throw new PPError(422,'Ranking bloqueado: faltan metadatos de población/medición o hay fuentes, unidades o metodologías incompatibles.');
    usort($valid,fn($a,$b)=>($direction==='asc'?1:-1)*($a['value']<=>$b['value'])?:strcmp($a['geo_code'],$b['geo_code']));
    $valid=pp_analytics_assign_ranks($valid);
    pp_reply(['indicator'=>$indicator,'period'=>$period,'subgroup'=>$group,'direction'=>$direction,'rows'=>$valid,'missing'=>$missing,
        'ambiguous'=>$data['ambiguous'],'coverage'=>['available'=>count($valid),'possible'=>count($geos)],
        'methodology'=>'Ranking descriptivo de magnitud. El orden no implica que mayor o menor sea mejor. Sin imputación ni equivalencia entre ausencia y cero.']);
}
function pp_analytics_normalize(string $text): string {
    $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text);
    return trim(preg_replace('/\s+/',' ',preg_replace('/[^a-z0-9]+/',' ',strtolower($ascii?:$text))));
}
/** Parse exactly stated quarters; never infer one from a year or today's date. */
function pp_analytics_question_quarters(string $q): array {
    $quarters=[];
    if(preg_match_all('/\b((?:19|20)\d{2})\s*[- ]?q([1-4])\b/u',$q,$m,PREG_SET_ORDER))
        foreach($m as $hit)$quarters[]=$hit[1].'-Q'.$hit[2];
    if(preg_match_all('/\b(primer|primero|segundo|tercer|tercero|cuarto) trimestre (?:de |del )?((?:19|20)\d{2})\b/u',$q,$m,PREG_SET_ORDER))
        foreach($m as $hit){$n=['primer'=>1,'primero'=>1,'segundo'=>2,'tercer'=>3,'tercero'=>3,'cuarto'=>4][$hit[1]];$quarters[]=$hit[2].'-Q'.$n;}
    return array_values(array_unique($quarters));
}
/** ECP estimates resident households at quarterly reference dates. It does not
 * count housing supply, rental households, empty dwellings or household need.
 * Only Spain is prepared for this indicator; never synthesize a CCAA breakdown. */
function pp_analytics_ecp_intent(string $q): array {
    if(preg_match('/\b(?:alquiler(?:es)?|arrendamiento|viviendas? vacias?|viviendas? ofertadas?|viviendas? construidas?|hogares? sin vivienda|desahucios?|precio(?:s)? de vivienda)\b/u',$q))
        return ['status'=>'incompatible_measurement','hint'=>'La ECP estima hogares residentes; no cuenta viviendas vacías, alquileres, desahucios ni oferta residencial.'];
    $quarters=pp_analytics_question_quarters($q);
    if(count($quarters)!==1)return ['status'=>'quarter_required','hint'=>'Indica un trimestre exacto de la ECP, por ejemplo 2026-Q2.'];
    try{$period=pp_quarterly_period($quarters[0]);}catch(PPError){return ['status'=>'future_or_invalid_period','hint'=>'El trimestre solicitado no ha comenzado o es inválido.'];}
    if(!preg_match('/\bespana\b/u',$q))return ['status'=>'territory_required','hint'=>'La serie preparada de hogares ECP solo contiene el total nacional de España.'];
    if(preg_match('/\b(?:andalucia|madrid|cataluna|catalunya|valencia|valenciana|pais vasco|euskadi|canarias|castilla|extremadura|murcia|galicia|asturias|cantabria|navarra|la rioja|aragon|baleares|ceuta|melilla)\b/u',$q))
        return ['status'=>'unsupported_geography_level','hint'=>'La tanda ECP preparada no dispone de cifras territoriales verificadas; no se reparte el total de España entre CCAA.'];
    return ['status'=>'ok_ecp','indicator'=>'INE_ECP_HOGARES_ESTIMADOS_TRIMESTRAL','period'=>$period,
        'subgroup'=>'Total hogares','geos'=>['ES'],'kind'=>'single','measurement'=>'households_estimated'];
}
/** Quarterly IPV question routing. Never substitutes an annual value, rent or
 * absolute sale price for a quarterly change in the INE purchase-price index.
 * The parser only chooses a dataset; the DB query still requires PP_PUBLISHED. */
function pp_analytics_ipv_intent(string $q): array {
    if($ambiguity=pp_analytics_ambiguous_capital($q))return $ambiguity;
    if(preg_match('/\b(?:alquiler|arrendamiento|precio por metro cuadrado|precio medio en euros)\b/u',$q))
        return ['status'=>'incompatible_measurement','hint'=>'El IPV mide variaciones del índice de compraventa, no alquiler ni precios absolutos.'];
    $annual=(bool)preg_match('/\b(?:interanual|variacion anual|tasa anual|respecto al mismo trimestre)\b/u',$q);
    $quarterly=(bool)preg_match('/\b(?:intertrimestral|variacion trimestral|tasa trimestral|respecto al trimestre anterior)\b/u',$q);
    if($annual===$quarterly)return ['status'=>'measurement_definition_required',
        'hint'=>'Indica variación interanual o variación trimestral del IPV; son mediciones distintas.'];
    $new=(bool)preg_match('/\b(?:vivienda nueva|obra nueva)\b/u',$q);
    $used=(bool)preg_match('/\b(?:segunda mano|vivienda usada)\b/u',$q);
    if($new&&$used)return ['status'=>'unsupported_subgroup','hint'=>'Consulta un tipo de vivienda cada vez.'];
    $prefix='INE_IPV_'.($annual?'YOY':'QOQ').'_'.($new?'NEW':($used?'EXISTING':'TOTAL')).'_PCT';
    $subgroup=$new?'Vivienda nueva':($used?'Vivienda de segunda mano':'Total vivienda');
    $quarters=pp_analytics_question_quarters($q);
    if(count($quarters)!==1)return ['status'=>'quarter_required','hint'=>'Indica un único trimestre, por ejemplo, 2026-Q2 o segundo trimestre de 2026.'];
    try{$period=pp_quarterly_period($quarters[0]);}catch(PPError){return ['status'=>'future_or_invalid_period','hint'=>'El trimestre solicitado no existe o todavía no ha comenzado.'];}
    $aliases=[
      'ES'=>['espana'], 'ES61'=>['andalucia'], 'ES30'=>['madrid'],
      'ES51'=>['cataluna','catalunya'], 'ES52'=>['valencia','comunidad valenciana','comunitat valenciana'],
      'ES21'=>['pais vasco','euskadi'], 'ES70'=>['canarias'],
      'ES41'=>['castilla leon','castilla y leon'], 'ES42'=>['castilla la mancha','castilla de la mancha'],
      'ES43'=>['extremadura'], 'ES62'=>['murcia','region de murcia'],
      'ES11'=>['galicia'], 'ES12'=>['asturias'], 'ES13'=>['cantabria'],
      'ES22'=>['navarra'], 'ES23'=>['la rioja'], 'ES24'=>['aragon'],
      'ES53'=>['baleares','illes balears','islas baleares'], 'ES63'=>['ceuta'], 'ES64'=>['melilla']
    ];
    $geos=[];
    foreach($aliases as $code=>$names)foreach($names as $name)
        if(str_contains(' '.$q.' ',' '.$name.' ')){$geos[$code]=true;break;}
    $geos=array_keys($geos);
    if(count($geos)>1&&in_array('ES',$geos,true)&&!preg_match('/\b(?:compara|comparar|comparacion|frente a|versus|contra|respecto a|vs)\b/u',$q))
        $geos=array_values(array_diff($geos,['ES']));
    if(!$geos)return ['status'=>'territory_required','hint'=>'Indica España o una comunidad/ciudad autónoma.'];
    if(count($geos)>2)return ['status'=>'too_many_territories','hint'=>'Se admiten como máximo dos territorios.'];
    if(count($geos)===1&&preg_match('/\b(?:compara|comparar|comparacion|frente a|versus|contra|respecto a|vs)\b/u',$q))
        return ['status'=>'comparison_requires_two_territories','hint'=>'Indica explícitamente ambos territorios.'];
    return ['status'=>'ok_quarterly','indicator'=>$prefix,'period'=>$period,'subgroup'=>$subgroup,
        'geos'=>$geos,'kind'=>count($geos)===2?'comparison':'single'];
}
/** A bare regional capital is not proof that the user meant the CCAA.
 * Never turn a municipal question into a regional answer by accident.
 * The caller must state the administrative level explicitly for Madrid/Murcia/Valencia.
 * Input is already normalized to lower-case ASCII. This helper is pure. */
function pp_analytics_ambiguous_capital(string $q): ?array {
    $cases=[
        'madrid'=>['/\b(?:comunidad(?:\s+autonoma)?\s+de\s+madrid|ccaa\s+de\s+madrid)\b/u','Comunidad de Madrid'],
        'murcia'=>['/\b(?:region\s+de\s+murcia|comunidad(?:\s+autonoma)?\s+de\s+murcia|ccaa\s+de\s+murcia)\b/u','Región de Murcia'],
        'valencia'=>['/\b(?:comunidad\s+valenciana|comunitat\s+valenciana|ccaa\s+valenciana)\b/u','Comunitat Valenciana'],
    ];
    foreach($cases as $name=>[$explicit,$label]){
        if(preg_match('/\b'.preg_quote($name,'/').'\b/u',$q)&&!preg_match($explicit,$q))
            return ['status'=>'ambiguous_geography_level','ambiguous_name'=>$name,
                'hint'=>'Especifica «'.$label.'» para consultar datos autonómicos. La capital, la provincia y la comunidad no son la misma unidad estadística.'];
    }
    return null;
}
/** Strict, explainable question routing. Unsupported/ambiguous queries are not guessed. */
function pp_analytics_ask_intent(string $question): array {
    $q=pp_analytics_normalize($question);
    // CCAA are not provinces, municipalities or city districts. An otherwise
    // matching name (Madrid, Valencia, Murcia) must not silently change level.
    // Ceuta and Melilla are explicitly represented as autonomous cities.
    if(preg_match('/\b(?:provincia|municipio|ciudad|capital|distrito|barrio) de (?!ceuta\b|melilla\b)[a-z]/u',$q))
        return ['status'=>'unsupported_geography_level',
            'hint'=>'Los datos disponibles corresponden a España o comunidades/ciudades autónomas, no a provincias, municipios, capitales, distritos ni barrios.'];
    // A place-name followed by a local administrative level is just as ambiguous
    // as "municipio de Madrid". Reject it before matching CCAA aliases; otherwise
    // "Valencia ciudad" silently becomes Comunitat Valenciana. Autonomous cities
    // Ceuta/Melilla remain valid when explicitly named as cities.
    if(preg_match('/\b(?:madrid|valencia|murcia|barcelona|sevilla|zaragoza|malaga|bilbao|oviedo|santander|valladolid|toledo|merida|pamplona|logrono|palma|vitoria|donostia|san sebastian|alicante|castellon|granada|cordoba|cadiz|huelva|almeria|jaen|salamanca|burgos|leon|cuenca|guadalajara|albacete|ciudad real)\s+(?:capital|ciudad|municipio|provincia|distrito|barrio|area metropolitana)\b/u',$q)
       ||preg_match('/\b(?:provincia|municipio|distrito|barrio|comarca|area metropolitana|capital)\s+(?:de|del|en)\s+[a-z]/u',$q))
        return ['status'=>'unsupported_geography_level',
            'hint'=>'No se equiparan provincias, capitales, municipios, comarcas o distritos con comunidades autónomas. Especifica la comunidad autónoma.'];
    // Before resolving an alias, demand an explicit administrative level for
    // capitals whose names are also used for provinces and autonomous regions.
    if($ambiguity=pp_analytics_ambiguous_capital($q))return $ambiguity;
    // Reject requests for a causal explanation; this API can only retrieve reviewed observations.
    if(preg_match('/\b(?:causa|causan|causo|causaron|provoca|provocan|provoco|provocaron|por que|a que se debe|demuestra|demuestran|explica|explican|culpa|culpable|responsable de)\b/u',$q))
        return ['status'=>'causal_question_not_supported','hint'=>'Los indicadores describen mediciones, no demuestran causas ni responsabilidades.'];
    // Route only explicit INE price-index questions to the quarterly dataset.
    // A generic question about housing prices is too broad to answer as IPV.
    if(preg_match('/\b(?:ipv|indice de precios de vivienda|indice de precios de la vivienda)\b/u',$q))
        return pp_analytics_ipv_intent($q);
    if(preg_match('/\b(?:ecp|encuesta continua de poblacion)\b/u',$q)&&preg_match('/\bhogares?\b/u',$q))
        return pp_analytics_ecp_intent($q);
    $indicator=null;$group='total';
    $choices=[
        'ecv_arope_pct'=>['arope','pobreza o exclusion'],
        'EUROSTAT_AROPE_PERSONAS_HOGARES_HIJOS_PCT'=>['arope de hogares con hijos','arope en hogares con hijos','arope hogares con hijos','arope de personas en hogares con hijos'],
        'ecv_riesgo_pobreza_pct'=>['riesgo de pobreza','tasa de pobreza'],
        'ecv_carencia_material_social_severa_pct'=>['carencia material y social severa','carencia severa'],
        'ecv_baja_intensidad_empleo_pct'=>['baja intensidad laboral','baja intensidad en el empleo'],
        'EAES_SALARIO_ANUAL_BRUTO'=>['salario medio','salario bruto','ganancia media anual','salarios medios'],
        'EPA_ABANDONO_TEMPRANO_18_24_PCT'=>['abandono educativo temprano','abandono escolar temprano','abandono temprano','abandono escolar'],
        'EPA_TASA_EMPLEO_25_64_PCT'=>['tasa de empleo de 25 a 64','empleo de 25 a 64','tasa de empleo'],
        'EPA_TASA_PARO_25_64_PCT'=>['tasa de paro','tasa de desempleo','desempleo de 25 a 64'],
        'PEW_SOCIAL_DEMOCRACY_BAD_PCT'=>['redes perjudican a la democracia','redes sociales perjudican a la democracia'],
        'PEW_SOCIAL_DEMOCRACY_GOOD_PCT'=>['redes benefician a la democracia','redes sociales benefician a la democracia'],
        'PEW_SOCIAL_DIVISION_MORE_PCT'=>['redes aumentan la division politica','division politica por redes'],
        'INE_ECV_HOGARES_ESPACIO_VERDE_400M_PCT'=>['hogares cerca de espacios verdes','espacio verde a menos de 400 metros','espacios verdes a menos de 400 metros'],
        'INE_ECV_HOGARES_RECICLA_PLASTICO_PCT'=>['hogares que reciclan botellas de plastico','recicla botellas de plastico','reciclan botellas de plastico','reciclaje de botellas de plastico'],
        'INE_ECV_BUSQUEDA_VIVIENDA_SIN_CAMBIO_PCT'=>['busqueda de vivienda sin cambiar de residencia','buscaron vivienda sin cambiar de residencia','busqueda activa de vivienda sin cambio'],
        'INE_ECV_DIFICULTAD_FIN_MES_MUCHA_PCT'=>['llegar a fin de mes con mucha dificultad','llegan a fin de mes con mucha dificultad'],
        'INE_ECV_SIN_CAPACIDAD_GASTOS_IMPREVISTOS_PCT'=>['sin capacidad para afrontar gastos imprevistos','no puede afrontar gastos imprevistos','no pueden afrontar gastos imprevistos'],
        'INE_ECV_RETRASOS_VIVIENDA_O_PLAZOS_PCT'=>['retrasos en pagos de vivienda principal o compras a plazos','retrasos en pagos de vivienda o compras a plazos'],
        'INE_ECV_SIN_VACACIONES_UNA_SEMANA_PCT'=>['no pueden permitirse una semana de vacaciones','no puede permitirse una semana de vacaciones','sin vacaciones una semana']
    ];
    $matched=[];$spans=[];
    foreach($choices as $id=>$aliases)foreach($aliases as $alias){
        $normalized=pp_analytics_normalize($alias);
        // Preserve offsets: the longest alias is only a safe winner when the
        // shorter matches are fully contained within it (e.g. AROPE in
        // "AROPE de hogares con hijos"). Otherwise the user asked about
        // multiple distinct indicators and we must NOT answer just one.
        $needle=' '.$normalized.' ';$haystack=' '.$q.' ';
        $offset=0;
        while(($at=strpos($haystack,$needle,$offset))!==false){
            $matched[$id]=max($matched[$id]??0,strlen($normalized));
            $spans[$id][]=[$at+1,$at+1+strlen($normalized)];
            $offset=$at+1;
        }
    }
    $maxScore=$matched?max($matched):0;
    $best=array_keys(array_filter($matched,fn($score)=>$score===$maxScore));
    if(count($best)!==1)return ['status'=>'unsupported_or_ambiguous_indicator','candidates'=>$best,'supported'=>array_keys($choices)];
    $indicator=$best[0];
    if(count($matched)>1){
        $winnerSpans=$spans[$indicator]??[];$independent=[];
        foreach($spans as $id=>$occurrences){
            if($id===$indicator)continue;
            foreach($occurrences as [$from,$to]){
                $inside=false;
                foreach($winnerSpans as [$winFrom,$winTo])if($from>=$winFrom&&$to<=$winTo){$inside=true;break;}
                if(!$inside){$independent[$id]=true;break;}
            }
        }
        if($independent)return ['status'=>'multiple_indicators_not_supported',
            'candidates'=>array_keys($matched),
            'hint'=>'La pregunta menciona varios indicadores distintos. Consulta cada indicador por separado; no se selecciona uno por longitud del nombre.'];
    }
    // Age-specific AROPE is a separate national-only series. Explicit age ranges are
    // required: 'pobreza infantil' is NOT silently equated to AROPE under 16.
    $populationText=$q;
    if($indicator==='ecv_arope_pct'){
        $ageGroups=[
            '/\bmenores de 16(?: anos)?\b/u'=>'Menores de 16 años',
            '/\b(?:de |entre )?16 (?:a|y) 64(?: anos)?\b/u'=>'De 16 a 64 años',
            '/\b(?:65 y mas|mayores de 64|65 o mas)(?: anos)?\b/u'=>'65 y más años'
        ];
        $matchedAge=[];
        foreach($ageGroups as $pattern=>$name){if(preg_match($pattern,$q)){$matchedAge[$name]=true;$populationText=preg_replace($pattern,' ',$populationText);}}
        if(count($matchedAge)>1)return ['status'=>'unsupported_population','hint'=>'Indica un único grupo de edad para esta consulta.'];
        if(count($matchedAge)===1)$group=array_key_first($matchedAge);
    }
    // Never substitute general rates for a different population. Additional unmatched
    // age labels (infantil, jóvenes, adolescentes) remain unsupported.
    if(preg_match('/\b(?:infantil|ninos|ninas|menores|adolescentes|menores de 25|mayores de 65|pensionistas)\b/u',$populationText) ||
       ($indicator!=='EPA_ABANDONO_TEMPRANO_18_24_PCT'&&preg_match('/\b(?:juvenil|jovenes)\b/u',$populationText)))
        return ['status'=>'unsupported_population','hint'=>'La serie seleccionada no tiene el desglose poblacional solicitado. No se sustituye por el dato general.'];
    if($indicator!=='EAES_SALARIO_ANUAL_BRUTO'&&preg_match('/\b(?:mujeres|hombres|femenino|masculino|por sexo)\b/u',$q))
        return ['status'=>'unsupported_subgroup','hint'=>'No existe aquí un desglose por sexo verificado para este indicador.'];
    // The 25-64 EPA series must never silently answer a generic unemployment/employment question.
    if(in_array($indicator,['EPA_TASA_EMPLEO_25_64_PCT','EPA_TASA_PARO_25_64_PCT'],true)
       &&!preg_match('/\b(?:25\s*(?:a|y)?\s*64|entre\s+25\s+y\s+64)\b/u',$q))
        return ['status'=>'age_range_required','hint'=>'La serie disponible corresponde exclusivamente a personas de 25 a 64 años. Especifica esa edad en la pregunta.'];
    if(str_starts_with($indicator,'PEW_SOCIAL_'))$group='Adultos encuestados';
    if($indicator==='EUROSTAT_AROPE_PERSONAS_HOGARES_HIJOS_PCT')$group='Personas en hogares con hijos dependientes';
    if($indicator==='EAES_SALARIO_ANUAL_BRUTO')$group=str_contains($q,'mujer')||str_contains($q,'femenin')?'Mujeres':(str_contains($q,'hombre')||str_contains($q,'masculin')?'Hombres':'Ambos sexos');
    $matches=[];preg_match_all('/\b(?:19|20)\d{2}\b/',$q,$matches);$years=array_unique($matches[0]);
    if(count($years)!==1)return ['status'=>'year_required','hint'=>'Indica exactamente un año (por ejemplo, 2025).'];
    if((int)$years[0]>pp_analytics_current_year())return ['status'=>'future_period_not_available','hint'=>'No se consultan años futuros como si fueran observaciones medidas.'];
    $aliases=[
      'ES'=>['espana'], 'ES61'=>['andalucia'], 'ES30'=>['madrid'],
      'ES51'=>['cataluna','catalunya'], 'ES52'=>['valencia','comunidad valenciana','comunitat valenciana'],
      'ES21'=>['pais vasco','euskadi'], 'ES70'=>['canarias'],
      'ES41'=>['castilla leon','castilla y leon'], 'ES42'=>['castilla la mancha','castilla de la mancha'],
      'ES43'=>['extremadura'], 'ES62'=>['murcia','region de murcia'],
      'ES11'=>['galicia'], 'ES12'=>['asturias'], 'ES13'=>['cantabria'],
      'ES22'=>['navarra'], 'ES23'=>['la rioja'], 'ES24'=>['aragon'],
      'ES53'=>['baleares','illes balears','islas baleares'], 'ES63'=>['ceuta'], 'ES64'=>['melilla']
    ];
    $geos=[];
    foreach($aliases as $code=>$names){foreach($names as $name){
        if(str_contains(' '.$q.' ',' '.$name.' ')){$geos[]=$code;break;}
    }}
    // In 'Andalucía, España' the country is context, not a second comparison.
    if(count($geos)>1&&in_array('ES',$geos,true)&&!preg_match('/\b(?:compara|comparar|comparacion|frente a|versus|contra|respecto a|vs)\b/u',$q))
        $geos=array_values(array_diff($geos,['ES']));
    if(!$geos)return ['status'=>'territory_required','hint'=>'Escribe España o una comunidad/ciudad autónoma.'];
    if(count($geos)>2)return ['status'=>'too_many_territories','hint'=>'Compara un máximo de dos territorios.'];
    if(count($geos)===1&&preg_match('/\b(?:compara|comparar|comparacion|frente a|versus|contra|respecto a|vs)\b/u',$q))
        return ['status'=>'comparison_requires_two_territories','hint'=>'Escribe dos territorios explícitos para realizar una comparación.'];
    return ['status'=>'ok','indicator'=>$indicator,'period'=>$years[0],'subgroup'=>$group,'geos'=>$geos,'kind'=>count($geos)===2?'comparison':'single'];
}
function pp_analytics_ask(): never {
    pp_rate('npl-ask','',30,300);
    $q=pp_clean($_GET['q']??'',260);if(mb_strlen($q)<12)throw new PPError(400,'Formula una pregunta concreta con indicador, año y territorio.');
    $intent=pp_analytics_ask_intent($q);
    if(in_array($intent['status'],['ok_quarterly','ok_ecp'],true)){
        // Exact quarter and territory only; all rows must satisfy PP_PUBLISHED.
        // pp_analytics_rows excludes duplicated published cells rather than picking one.
        $rows=pp_analytics_rows($intent['indicator'],$intent['period'],$intent['subgroup'],$intent['geos']);
        $points=[];foreach($intent['geos'] as $geo)$points[$geo]=pp_analytics_point($rows['rows'][$geo]??null);
        $compat=pp_analytics_compatibility($points);
        $isEcp=$intent['status']==='ok_ecp';
        pp_reply(['question'=>$q,'intent'=>$intent,'points'=>$points,'ambiguous'=>$rows['ambiguous'],'comparability'=>$compat,
            'interpretation_guard'=>$isEcp
                ?'Estimación provisional del número de hogares residentes de la ECP; no es número de viviendas, hogares sin vivienda ni demanda insatisfecha. Las series se revisan.'
                :'Variación del índice de compraventa de vivienda libre; no es alquiler, precio absoluto ni indicador directo de asequibilidad. No implica causalidad.',
            'status'=>!$compat['complete']?'incomplete_coverage':(!$compat['homogeneous']?'incompatible_measurements':(!$compat['metadata_complete']?'insufficient_metadata':'answered_from_reviewed_data')),
            'truth_status'=>'sourced_data_only','methodology'=>'Consulta trimestral exacta sobre registros publicados y revisados; 0 es cero real, null es ausencia. Sin interpolación, medias anuales ni valores inventados.']);
    }
    if($intent['status']!=='ok')pp_reply(['question'=>$q,...$intent,'truth_status'=>'not_assessed','note'=>'No se inventa una respuesta para preguntas ambiguas.']);
    $rows=pp_analytics_rows($intent['indicator'],$intent['period'],$intent['subgroup'],$intent['geos']);
    $points=[];foreach($intent['geos'] as $geo)$points[$geo]=pp_analytics_point($rows['rows'][$geo]??null);
    $compat=pp_analytics_compatibility($points);
    $survey=str_starts_with($intent['indicator'],'PEW_SOCIAL_');
    pp_reply(['question'=>$q,'intent'=>$intent,'points'=>$points,'ambiguous'=>$rows['ambiguous'],'comparability'=>$compat,
        'interpretation_guard'=>$survey?'Porcentaje de opiniones declaradas por adultos encuestados; no mide un daño observado ni un efecto causal.':'Dato descriptivo de la población, unidad, periodo y territorio declarados en la fuente; no implica causalidad.',
        'status'=>!$compat['complete']?'incomplete_coverage':(!$compat['homogeneous']?'incompatible_measurements':(!$compat['metadata_complete']?'insufficient_metadata':'answered_from_reviewed_data')),
        'truth_status'=>'sourced_data_only','methodology'=>'Consulta determinista sobre registros revisados. Sin predicción, causalidad, interpolación ni datos territoriales inventados.']);
}
