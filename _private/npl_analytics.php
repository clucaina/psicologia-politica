<?php
declare(strict_types=1);
/** Public, reviewed-only comparative analytics; no synthetic geography or causal scores. */
function pp_analytics_id(string $value): string {if(!preg_match('/^[A-Za-z0-9_-]{2,100}$/D',$value))throw new PPError(400,'Indicador inválido.');return $value;}
function pp_analytics_period(string $value): string {if(!preg_match('/^(19|20)[0-9]{2}$/D',$value))throw new PPError(400,'Año inválido.');return $value;}
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
/** Lightweight published-only availability index. A missing territory is not a zero. */
function pp_analytics_coverage(): never {
    pp_rate('npl-coverage','',60,300);
    $indicator=pp_analytics_id((string)($_GET['indicator']??''));
    $geos=[...pp_analytics_geos(),'ES'];
    // A territory with two conflicting reviewed rows is NOT an available, comparable cell.
    $sql='SELECT period,subgroup,SUM(CASE WHEN geo_rows=1 THEN 1 ELSE 0 END) AS territories, '
       .'COUNT(*) AS raw_territories,SUM(geo_rows) AS row_count, '
       .'SUM(CASE WHEN geo_rows>1 THEN 1 ELSE 0 END) AS ambiguous_territories '
       .'FROM (SELECT o.period,o.subgroup,o.geo_code,COUNT(*) AS geo_rows '
       .'FROM npl_observations o '.PP_REVIEW_JOIN.' WHERE '.PP_PUBLISHED
       .' AND o.indicator_id=? AND o.period REGEXP ? AND o.geo_code IN ('
       .implode(',',array_fill(0,count($geos),'?')).') GROUP BY o.period,o.subgroup,o.geo_code) AS coverage_by_geo '
       .'GROUP BY period,subgroup ORDER BY period DESC,subgroup LIMIT 1000';
    $rows=pp_all($sql,[$indicator,'^(19|20)[0-9]{2}$',...$geos]);
    $groups=[];$years=[];$cells=[];
    foreach($rows as $row){
        $group=(string)$row['subgroup'];$year=(string)$row['period'];
        $groups[$group]=true;$years[$year]=true;
        $cells[]=['group'=>$group,'year'=>$year,'territories'=>(int)$row['territories'],
            'possible'=>count($geos),'ambiguous_rows'=>max(0,(int)$row['row_count']-(int)$row['raw_territories']),'ambiguous_territories'=>(int)$row['ambiguous_territories']];
    }
    pp_reply(['indicator'=>$indicator,'groups'=>array_keys($groups),'years'=>array_keys($years),
        'coverage'=>$cells,'truncated'=>count($rows)===1000,
        'methodology'=>'Solo observaciones publicadas y revisadas. Cobertura = territorios con exactamente un registro revisado; territorios duplicados se excluyen hasta resolver la ambigüedad.']);
}
/** Measurement fingerprint: equal percentages may refer to different populations or concepts. */
/** Do not label a comparison valid when both records omit the same population definition. */
function pp_analytics_metadata_complete(array $point): bool {
    foreach(['unit','source_id','source_url','population','measurement_type','period','reviewed_at'] as $key)
        if(!is_string($point[$key]??null)||trim($point[$key])==='')return false;
    $url=$point['source_url'];$parts=parse_url($url);
    if(!$parts||strtolower($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))return false;
    return true;
}
function pp_analytics_signature(array $p): array {
    return array_map(fn($key)=>$p[$key]??null,
        ['unit','source_id','dataset_code','methodology_flag','measurement_type','population','denominator','definition_version']);
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
    return ['complete'=>$complete,'homogeneous'=>$homogeneous,'metadata_complete'=>$metadataComplete,'comparable'=>$complete&&$homogeneous&&$metadataComplete,
        'available'=>count($available),'requested'=>count($points),
        'reason'=>!$complete?'missing_data':(!$homogeneous?'incompatible_source_unit_or_methodology':(!$metadataComplete?'missing_measurement_metadata':'ok'))];
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
        'measurement_type'=>$reviewed('measurement_type'),'population'=>$reviewed('population'),
        'denominator'=>$data['denominator']??null,'definition_version'=>$data['definition_version']??null,
        'source_updated'=>$data['source_updated']??null,'source_locator'=>$reviewed('locator','source_locator'),
        'citation'=>$reviewed('citation'),'limits'=>$reviewed('limits'),
        'retrieved_at'=>$reviewed('retrieved_at'),'reviewed_at'=>$r['reviewed_at'],
        'geography'=>$r['geography'],'geo_code'=>$r['geo_code']];
}
function pp_analytics_ranks(array $values): array {
    $indexed=[];foreach($values as $i=>$v)$indexed[]=['index'=>$i,'value'=>$v];usort($indexed,fn($a,$b)=>$a['value']<=>$b['value']);
    $ranks=array_fill(0,count($values),0.0);$n=count($values);
    for($i=0;$i<$n;){$j=$i+1;while($j<$n&&abs($indexed[$j]['value']-$indexed[$i]['value'])<1e-12)$j++;$rank=($i+1+$j)/2;for($k=$i;$k<$j;$k++)$ranks[$indexed[$k]['index']]=$rank;$i=$j;}
    return $ranks;
}
function pp_analytics_pearson(array $x,array $y): ?float {
    $n=count($x);if($n<2||$n!==count($y))return null;
    $mx=array_sum($x)/$n;$my=array_sum($y)/$n;$a=0.0;$b=0.0;$c=0.0;
    for($i=0;$i<$n;$i++){$dx=$x[$i]-$mx;$dy=$y[$i]-$my;$a+=$dx*$dy;$b+=$dx*$dx;$c+=$dy*$dy;}
    if($b<1e-12||$c<1e-12)return null;
    return max(-1.0,min(1.0,$a/sqrt($b*$c)));
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
    if($n<8)return ['status'=>'insufficient_coverage','n'=>$n,'min_n'=>8,'territories'=>$matched,'excluded'=>$excluded];
    if($metadataMissing)return ['status'=>'insufficient_metadata','n'=>$n,'territories'=>$matched,'excluded'=>$excluded,
        'missing_fields'=>array_keys($metadataMissing),'warning'=>'No se calcula correlación hasta verificar población, tipo de medida, fuente y fecha de revisión.'];
    $xSignatures=array_unique(array_map(fn($p)=>pp_json(pp_analytics_signature($p)),$leftPoints));
    $ySignatures=array_unique(array_map(fn($p)=>pp_json(pp_analytics_signature($p)),$rightPoints));
    if(count($xSignatures)!==1||count($ySignatures)!==1)
        return ['status'=>'incompatible_measurements','n'=>$n,'territories'=>$matched,'excluded'=>$excluded,
            'warning'=>'Series heterogéneas en unidad, fuente, población, definición o metodología.'];
    $pearson=pp_analytics_pearson($vx,$vy);
    $spearman=pp_analytics_pearson(pp_analytics_ranks($vx),pp_analytics_ranks($vy));
    return ['status'=>$pearson===null||$spearman===null?'constant_series':'ok',
        'n'=>$n,'territories'=>$matched,'excluded'=>$excluded,'pearson_r'=>$pearson,'spearman_rho'=>$spearman,
        'sources'=>['x'=>array_intersect_key($leftPoints[0],array_flip(['unit','source_id','dataset_code','population','measurement_type','source_url','methodology_flag'])),
                    'y'=>array_intersect_key($rightPoints[0],array_flip(['unit','source_id','dataset_code','population','measurement_type','source_url','methodology_flag']))]];
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

/** Published-only annual series; every point carries its own provenance. */
function pp_analytics_series(): never {
    pp_rate('npl-series','',50,300);
    $indicator=pp_analytics_id((string)($_GET['indicator']??''));
    $group=pp_clean($_GET['group']??'',200);if($group==='')throw new PPError(400,'Selecciona subgrupo.');
    $from=(int)pp_analytics_period((string)($_GET['from']??''));
    $to=(int)pp_analytics_period((string)($_GET['to']??''));
    if($from>$to||$to-$from>29||$to>(int)gmdate('Y'))throw new PPError(400,'Intervalo anual inválido (máximo 30 años).');
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
            if($point)$metadata[$year]=pp_analytics_signature($point);
        }
        $distinct=array_unique(array_map('json_encode',array_values($metadata)));
        if(count($distinct)>1){$incompatible[]=$geo; /* Do not draw a misleading continuous series. */
            $series[$geo]=['points'=>$points,'comparable'=>false];
        }else $series[$geo]=['points'=>$points,'comparable'=>count($metadata)>0&&count(array_filter($points,fn($p)=>$p!==null&&!pp_analytics_metadata_complete($p)))===0];
    }
    // Across-territory comparisons must share a measurement signature as well.
    $allSignatures=[];
    foreach($series as $seriesData)foreach($seriesData['points'] as $point){
        if($point===null)continue;
        $allSignatures[pp_json(pp_analytics_signature($point))]=true;
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
    if(count($signatures)>1||count(array_filter($valid,fn($p)=>!pp_analytics_metadata_complete($p)))>0)throw new PPError(422,'Ranking bloqueado: faltan metadatos de población/medición o hay fuentes, unidades o metodologías incompatibles.');
    usort($valid,fn($a,$b)=>($direction==='asc'?1:-1)*($a['value']<=>$b['value'])?:strcmp($a['geo_code'],$b['geo_code']));
    $previous=null;$rank=0;
    foreach($valid as $i=>&$row){if($previous===null||abs($row['value']-$previous)>1e-12)$rank=$i+1;$row['rank']=$rank;$previous=$row['value'];}unset($row);
    pp_reply(['indicator'=>$indicator,'period'=>$period,'subgroup'=>$group,'direction'=>$direction,'rows'=>$valid,'missing'=>$missing,
        'ambiguous'=>$data['ambiguous'],'coverage'=>['available'=>count($valid),'possible'=>count($geos)],
        'methodology'=>'Ranking descriptivo de magnitud. El orden no implica que mayor o menor sea mejor. Sin imputación ni equivalencia entre ausencia y cero.']);
}
function pp_analytics_normalize(string $text): string {
    $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text);
    return trim(preg_replace('/\s+/',' ',preg_replace('/[^a-z0-9]+/',' ',strtolower($ascii?:$text))));
}
/** Strict, explainable question routing. Unsupported/ambiguous queries are not guessed. */
function pp_analytics_ask_intent(string $question): array {
    $q=pp_analytics_normalize($question);
    // Reject requests for a causal explanation; this API can only retrieve reviewed observations.
    if(preg_match('/\b(?:causa|causan|causo|causaron|provoca|provocan|provoco|provocaron|por que|a que se debe|demuestra|demuestran|explica|explican|culpa|culpable|responsable de)\b/u',$q))
        return ['status'=>'causal_question_not_supported','hint'=>'Los indicadores describen mediciones, no demuestran causas ni responsabilidades.'];
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
        'PEW_SOCIAL_DIVISION_MORE_PCT'=>['redes aumentan la division politica','division politica por redes']
    ];
    $matched=[];
    foreach($choices as $id=>$aliases)foreach($aliases as $alias){
        $normalized=pp_analytics_normalize($alias);
        if(str_contains(' '.$q.' ',' '.$normalized.' '))$matched[$id]=max($matched[$id]??0,strlen($normalized));
    }
    // A subgroup-specific question also contains the generic term AROPE.
    // Prefer the most specific matching alias; tied alternatives remain ambiguous.
    $maxScore=$matched?max($matched):0;
    $best=array_keys(array_filter($matched,fn($score)=>$score===$maxScore));
    if(count($best)!==1)return ['status'=>'unsupported_or_ambiguous_indicator','candidates'=>$best,'supported'=>array_keys($choices)];
    $indicator=$best[0];
    // Prevent an apparent answer to a different population than the series actually measures.
    // A generic poverty or 25-64 employment rate is NOT a child/youth-specific statistic.
    if(preg_match('/\b(?:infantil|ninos|ninas|menores|adolescentes|menores de 25|mayores de 65|pensionistas)\b/u',$q) ||
       ($indicator!=='EPA_ABANDONO_TEMPRANO_18_24_PCT'&&preg_match('/\b(?:juvenil|jovenes)\b/u',$q)))
        return ['status'=>'unsupported_population','hint'=>'La serie seleccionada no tiene el desglose poblacional solicitado. No se sustituye por el dato general.'];
    if($indicator!=='EAES_SALARIO_ANUAL_BRUTO'&&preg_match('/\b(?:mujeres|hombres|femenino|masculino|por sexo)\b/u',$q))
        return ['status'=>'unsupported_subgroup','hint'=>'No existe aquí un desglose por sexo verificado para este indicador.'];
    if(str_starts_with($indicator,'PEW_SOCIAL_'))$group='Adultos encuestados';
    if($indicator==='EUROSTAT_AROPE_PERSONAS_HOGARES_HIJOS_PCT')$group='Personas en hogares con hijos dependientes';
    if($indicator==='EAES_SALARIO_ANUAL_BRUTO')$group=str_contains($q,'mujer')||str_contains($q,'femenin')?'Mujeres':(str_contains($q,'hombre')||str_contains($q,'masculin')?'Hombres':'Ambos sexos');
    $matches=[];preg_match_all('/\b(?:19|20)\d{2}\b/',$q,$matches);$years=array_unique($matches[0]);
    if(count($years)!==1)return ['status'=>'year_required','hint'=>'Indica exactamente un año (por ejemplo, 2025).'];
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
    if($intent['status']!=='ok')pp_reply(['question'=>$q,...$intent,'truth_status'=>'not_assessed','note'=>'No se inventa una respuesta para preguntas ambiguas.']);
    $rows=pp_analytics_rows($intent['indicator'],$intent['period'],$intent['subgroup'],$intent['geos']);
    $points=[];foreach($intent['geos'] as $geo)$points[$geo]=pp_analytics_point($rows['rows'][$geo]??null);
    $compat=pp_analytics_compatibility($points);
    $survey=str_starts_with($intent['indicator'],'PEW_SOCIAL_');
    pp_reply(['question'=>$q,'intent'=>$intent,'points'=>$points,'ambiguous'=>$rows['ambiguous'],'comparability'=>$compat,
        'interpretation_guard'=>$survey?'Porcentaje de opiniones declaradas por adultos encuestados; no mide un daño observado ni un efecto causal.':'Dato descriptivo de la población, unidad, periodo y territorio declarados en la fuente; no implica causalidad.',
        'status'=>!$compat['complete']?'incomplete_coverage':(!$compat['homogeneous']?'incompatible_measurements':'answered_from_reviewed_data'),
        'truth_status'=>'sourced_data_only','methodology'=>'Consulta determinista sobre registros revisados. Sin predicción, causalidad, interpolación ni datos territoriales inventados.']);
}
