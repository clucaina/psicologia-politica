<?php
declare(strict_types=1);
/** Quarterly data are never silently converted into annual observations. */
function pp_quarterly_period(string $period): string {
    if(!preg_match('/^(19|20)[0-9]{2}-Q[1-4]$/D',$period))throw new PPError(400,'Trimestre inválido (AAAA-Q1 a AAAA-Q4).');
    $year=(int)substr($period,0,4);$quarter=(int)substr($period,-1);
    $localNow=new DateTimeImmutable('now',new DateTimeZone('Europe/Madrid'));
    $nowYear=(int)$localNow->format('Y');$nowQuarter=(int)ceil((int)$localNow->format('n')/3);
    if($year>$nowYear||($year===$nowYear&&$quarter>$nowQuarter))throw new PPError(400,'Trimestre futuro no disponible.');
    return $period;
}
function pp_quarterly_latest_period(): string {
    $now=new DateTimeImmutable('now',new DateTimeZone('Europe/Madrid'));
    return $now->format('Y').'-Q'.(int)ceil((int)$now->format('n')/3);
}
/** Temporal identity for quarterly series. Exact start/end dates change by
 * quarter, so comparing raw date strings makes EVERY properly documented
 * quarterly trend appear incompatible. Normalize only full-quarter windows;
 * partial quarters remain distinct and cannot be joined without review. */
/** ECP population and household observations are STOCKS at a reference
 * boundary (Q1 -> 1 April). They must not be mislabelled as three-month sums
 * or averages. Only a documented ECP dataset with exact boundary qualifies. */
function pp_quarterly_ecp_stock_reference(array $point,string $period): bool {
    if(!preg_match('/^((?:19|20)[0-9]{2})-Q([1-4])$/D',$period,$m))return false;
    if(!str_contains(strtoupper((string)($point['dataset_code']??'')),'ECP'))return false;
    $year=(int)$m[1];$quarter=(int)$m[2];
    $expected=$quarter===4?sprintf('%04d-01-01',$year+1):sprintf('%04d-%02d-01',$year,$quarter*3+1);
    $today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Madrid')))->format('Y-m-d');
    return ($point['reference_date']??null)===$expected && $expected<=$today &&
           ($point['measurement_period_start']??null)===null &&
           ($point['measurement_period_end']??null)===null;
}
function pp_quarterly_temporal_basis(array $point,string $period): ?string {
    if(pp_quarterly_full_window($point,$period))return 'full_quarter';
    if(pp_quarterly_ecp_stock_reference($point,$period))return 'ecp_point_in_time';
    return null;
}
function pp_quarterly_signature(array $point,string $period): array {
    $signature=pp_analytics_signature($point,true);
    $basis=pp_quarterly_temporal_basis($point,$period);
    if($basis==='ecp_point_in_time'){
        // The exact reference date is checked above; normalize its moving
        // quarter boundary, not the measured value or its provenance.
        // The signature now includes measurement_window_type: never rely on
        // the last positional element to mean reference_date.
        $keys=['unit','source_id','source_url','dataset_code','methodology_flag','measurement_type',
            'percentage_semantics','population','denominator','definition_version','data_kind','frequency',
            'measurement_window_type','measurement_period_start','measurement_period_end','reference_date'];
        $signature[array_search('reference_date',$keys,true)]='ecp_point_in_time';
    }
    $start=$point['measurement_period_start']??null;
    $end=$point['measurement_period_end']??null;
    if(is_string($start)&&is_string($end)&&preg_match('/^(19|20)[0-9]{2}-Q[1-4]$/D',$period)){
        $year=(int)substr($period,0,4);$quarter=(int)substr($period,-1);
        $month=($quarter-1)*3+1;
        $first=sprintf('%04d-%02d-01',$year,$month);
        $last=(new DateTimeImmutable($first))->modify('+3 months -1 day')->format('Y-m-d');
        if($start===$first&&$end===$last){
            $keys=['unit','source_id','source_url','dataset_code','methodology_flag','measurement_type',
                'percentage_semantics','population','denominator','definition_version','data_kind','frequency',
                'measurement_window_type','measurement_period_start','measurement_period_end','reference_date'];
            $signature[array_search('measurement_period_start',$keys,true)]='full_quarter';
            $signature[array_search('measurement_period_end',$keys,true)]='full_quarter';
        }
    }
    return $signature;
}
/** A quarterly label alone does not prove a complete three-month observation.
 * Require an exact, reviewed calendar window before the series can form a
 * connected line. Partial quarters remain available as isolated values. */
function pp_quarterly_full_window(array $point,string $period): bool {
    if(!preg_match('/^(19|20)[0-9]{2}-Q[1-4]$/D',$period))return false;
    $year=(int)substr($period,0,4);
    $quarter=(int)substr($period,-1);
    $first=sprintf('%04d-%02d-01',$year,($quarter-1)*3+1);
    $last=(new DateTimeImmutable($first))->modify('+3 months -1 day')->format('Y-m-d');
    return ($point['measurement_period_start']??null)===$first &&
           ($point['measurement_period_end']??null)===$last;
}
function pp_quarterly_index(string $period): int {
    return (int)substr($period,0,4)*4+(int)substr($period,-1)-1;
}
function pp_quarterly_quality(array $rows): array {
    $points=[];$ambiguous=[];
    foreach($rows as $r){
        $period=pp_quarterly_period((string)$r['period']);
        if(array_key_exists($period,$points)){$ambiguous[$period]=true;continue;}
        $points[$period]=pp_analytics_point($r);
    }
    foreach(array_keys($ambiguous) as $period)unset($points[$period]);
    ksort($points,SORT_STRING);
    $valid=array_values(array_filter($points,fn($p)=>$p!==null));
    $rawSignatures=[];foreach($points as $period=>$point)if($point!==null)$rawSignatures[pp_json(pp_quarterly_signature($point,$period))]=true;
    $signatures=[];$missingMetadata=[];$runs=[];$isolated=[];$current=[];$gaps=[];$breaks=[];$temporalBases=[];
    $lastPeriod=null;$lastSignature=null;
    $flush=static function()use(&$current,&$runs,&$isolated):void{
        if(count($current)>=2)$runs[]=$current;
        elseif(count($current)===1)$isolated[]=$current[0];
        $current=[];
    };
    foreach($points as $period=>$point){
        $basis=$point===null?null:pp_quarterly_temporal_basis($point,$period);
        if($point===null||!pp_analytics_metadata_complete($point)||$basis===null){
            $flush();$missingMetadata[]=$period;$lastPeriod=null;$lastSignature=null;continue;
        }
        $temporalBases[$period]=$basis;
        $signature=pp_json(pp_quarterly_signature($point,$period));$signatures[$signature]=true;
        if($lastPeriod!==null){
            if(pp_quarterly_index($period)!==pp_quarterly_index($lastPeriod)+1){
                $gaps[]=['from'=>$lastPeriod,'to'=>$period];$flush();
            }elseif($signature!==$lastSignature){
                $breaks[]=['from'=>$lastPeriod,'to'=>$period,'reason'=>'incompatible_measurement'];$flush();
            }
        }
        $current[]=$period;$lastPeriod=$period;$lastSignature=$signature;
    }
    $flush();
    $complete=count($valid)>0&&count($missingMetadata)===0;
    $comparable=count($valid)>=2&&$complete&&count($signatures)===1&&
        count($runs)===1&&count($gaps)===0&&count($breaks)===0&&count($isolated)===0&&count($ambiguous)===0;
    return ['points'=>$points,'ambiguous_periods'=>array_keys($ambiguous),
        'comparable_trend'=>$comparable,'drawable_segments'=>$runs,'isolated_periods'=>$isolated,
        'missing_or_blocked_periods'=>$missingMetadata,'temporal_gaps'=>$gaps,'methodology_breaks'=>$breaks,
        'metadata_complete'=>$complete,'full_quarter_windows_required'=>!in_array('ecp_point_in_time',$temporalBases,true),
        'temporal_basis_by_period'=>$temporalBases,
        'temporal_basis_policy'=>'Full-quarter counts/rates require exact start/end. ECP stocks require the next-quarter reference boundary; neither may be silently converted into the other.',
        'n'=>count($valid),
        'reason'=>count($valid)<2?'insufficient_periods':(count($rawSignatures)>1?'incompatible_measurements':
            ($missingMetadata?'missing_metadata':($ambiguous?'ambiguous_duplicate_periods':
            ($gaps?'nonconsecutive_periods':($breaks?'methodology_break':'ok')))))];
}

function pp_quarterly_api(): never {
    pp_rate('npl-quarterly','',50,300);
    $regex='^(19|20)[0-9]{2}-Q[1-4]$';
    if(($_GET['catalog']??'')==='1'){
        // Stable, bounded pages. The previous LIMIT 1000 silently hid later series.
        // One extra row signals a next page; never infer completeness from a full page.
        $offsetRaw=$_GET['offset']??'0';
        if(!is_string($offsetRaw)&&!is_int($offsetRaw))throw new PPError(400,'Desplazamiento de catálogo inválido.');
        $offsetRaw=(string)$offsetRaw;
        if(!preg_match('/^(0|[1-9][0-9]{0,5})$/D',$offsetRaw)||(int)$offsetRaw>20000)
            throw new PPError(400,'Desplazamiento del catálogo fuera del límite seguro.');
        $offset=(int)$offsetRaw;$pageSize=pp_catalog_page_size($_GET['page_size']??null,250);
        $latestQuarter=pp_quarterly_latest_period();
        $sql='SELECT o.indicator_id,i.name,o.subgroup,o.geo_code,COUNT(*) AS rows_count '
            .'FROM npl_observations o '.PP_REVIEW_JOIN.' JOIN npl_indicators i ON i.id=o.indicator_id '
            .'WHERE '.PP_PUBLISHED.' AND o.period REGEXP ? AND o.period<=? '
            .'GROUP BY o.indicator_id,i.name,o.subgroup,o.geo_code '
            .'ORDER BY o.indicator_id ASC,o.subgroup ASC,o.geo_code ASC LIMIT ? OFFSET ?';
        $stampSql='SELECT COUNT(*) AS row_count,MAX(r.reviewed_at) AS max_review, '
           .'COALESCE(SUM(o.id),0) AS id_sum, '
           ."COALESCE(SUM(CRC32(CONCAT_WS('|',o.id,i.name,o.period,o.subgroup,o.geo_code,COALESCE(o.value,'NULL'),COALESCE(o.data,''),COALESCE(r.reviewed_data,''),COALESCE(r.status,''),COALESCE(r.reviewed_at,'')))),0) AS row_checksum "
           .'FROM npl_observations o '.PP_REVIEW_JOIN.' JOIN npl_indicators i ON i.id=o.indicator_id '
           .'WHERE '.PP_PUBLISHED.' AND o.period REGEXP ? AND o.period<=?';
        [$rows,$snapshot]=pp_catalog_consistent(function()use($stampSql,$sql,$regex,$latestQuarter,$pageSize,$offset){
            $snapshot=pp_catalog_token(pp_one($stampSql,[$regex,$latestQuarter])??[]);
            pp_catalog_check_snapshot($_GET['snapshot']??null,$snapshot,$offset);
            return [pp_all($sql,[$regex,$latestQuarter,$pageSize+1,$offset]),$snapshot];
        });
        $hasMore=count($rows)>$pageSize;
        if($hasMore)array_pop($rows);
        pp_reply(['catalog'=>$rows,'offset'=>$offset,'page_size'=>$pageSize,'snapshot'=>$snapshot,
            'has_more'=>$hasMore,'next_offset'=>$hasMore?$offset+count($rows):null,
            'truncated'=>$hasMore,
            'note'=>'Paginación revisada con huella de cambio entre páginas; no convertir trimestres en años. Si cambia la publicación, reiniciar la consulta.']);
    }
    $indicator=pp_analytics_id((string)($_GET['indicator']??''));
    $group=pp_clean($_GET['group']??'',200);
    $geo=(string)($_GET['geo']??'ES');
    if($group===''||!in_array($geo,[...pp_analytics_geos(),'ES'],true))throw new PPError(400,'Subgrupo o territorio no válido.');
    $sql='SELECT o.geo_code,o.geography,o.period,o.value,o.unit,o.source_id,o.subgroup,o.data,r.evidence,r.reviewed_at '
       .'FROM npl_observations o '.PP_REVIEW_JOIN.' WHERE '.PP_PUBLISHED
       .' AND o.indicator_id=? AND o.subgroup=? AND o.geo_code=? AND o.period REGEXP ? AND o.period<=? '
       .'ORDER BY o.period DESC,o.id LIMIT 500';
    $rows=pp_all($sql,[$indicator,$group,$geo,$regex,pp_quarterly_latest_period()]);
    // A truncated result could omit a duplicate and falsely declare comparability.
    if(count($rows)===500)throw new PPError(503,'Serie demasiado extensa: se requiere paginación para evitar ambigüedad.');
    $quality=pp_quarterly_quality($rows);
    pp_reply(['indicator'=>$indicator,'subgroup'=>$group,'geo'=>$geo,...$quality,
        'methodology'=>'Solo registros trimestrales revisados. Las líneas unen trimestres consecutivos con base temporal verificada: ventana completa para flujos/tasas o fecha de referencia explícita para stocks ECP. Nunca se convierten stocks en flujos ni se interpolan huecos. Sin medias anuales inferidas, causalidad ni mezcla de fuentes. 0 = cero observado; null = ausencia.']);
}
