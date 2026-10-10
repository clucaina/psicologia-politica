<?php
declare(strict_types=1);
/** Editor-only release diagnostics. No provisional observation values leave this endpoint.
 * Buckets are mutually exclusive and based on the exact publication predicate.
 */
function pp_release_bucket(?string $status, ?string $snapshot, ?string $current, bool $hasRequiredFields): string {
    if ($status === null) return 'unreviewed';
    if ($snapshot === null || $current === null || !hash_equals($snapshot, $current)) return 'stale_review';
    if ($status === 'rejected') return 'rejected';
    if ($status === 'published') return $hasRequiredFields ? 'published_current' : 'published_blocked';
    return 'in_review';
}
function pp_release_summary(array $groups): array {
    $buckets=['unreviewed','stale_review','rejected','published_current','published_blocked','in_review'];
    $totals=array_fill_keys($buckets,0);$archive=0;
    foreach($groups as $group){
        $n=(int)($group['total']??0);$sum=0;
        foreach($buckets as $bucket){
            $v=(int)($group[$bucket]??0);
            if($v<0)throw new PPError(503,'Contadores de revisión negativos.');
            $totals[$bucket]+=$v;$sum+=$v;
        }
        if($sum!==$n)throw new PPError(503,'Los estados de revisión no cuadran con el archivo.');
        $archive+=$n;
    }
    return ['archive_observations'=>$archive,'reviewed_public_observations'=>$totals['published_current'],
        'excluded_observations'=>$archive-$totals['published_current'],'by_reason'=>$totals,
        'public_fraction'=>$archive>0?round($totals['published_current']/$archive,6):null,
        'definition'=>'Publicable = revisión publicada vigente, instantánea idéntica al dato, campos mínimos presentes y verificación humana explícita de tandas preparadas. Los demás registros no se muestran como cobertura pública.'];
}
/** Aggregated, non-exclusive source QA blockers. Never returns provisional values. */
function pp_release_source_review_backlog(): array {
    // Separate scientific source blockers from generic editorial statuses. These
    // counts can overlap and never include provisional values or reviewer emails.
    $conflict="LOWER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.source_conflict_flag')),''))) NOT IN ('','false','0','null')";
    $stamp="NULLIF(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.source_verified_at')),'') IS NOT NULL AND "
        ."NULLIF(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.source_verified_by')),'') IS NOT NULL";
    $sourceQa="JSON_EXTRACT(o.data,'$.batch_id') IS NOT NULL OR JSON_EXTRACT(o.data,'$.import_review') IS NOT NULL OR "
        ."LOCATE('PENDING_VISUAL_SOURCE_QA',COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.quality_flag')),''))>0 OR "
        ."LOCATE('PENDING_HUMAN_SOURCE_QA',COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.quality_flag')),''))>0 OR "
        ."LOCATE('PENDING_VISUAL_SOURCE_QA',COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.source_quality_flag')),''))>0 OR "
        ."LOCATE('PENDING_HUMAN_SOURCE_QA',COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.data,'$.source_quality_flag')),''))>0";
    $resolution="CASE WHEN JSON_VALID(r.evidence) THEN ("
        ."CHAR_LENGTH(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.evidence,'$.source_conflict_resolution')),'')))>=60 AND "
        ."JSON_UNQUOTE(JSON_EXTRACT(r.evidence,'$.source_conflict_resolution_url')) LIKE 'https://%'"
        .") ELSE 0 END";
    $sourceReview=pp_one('SELECT '
        .'SUM(CASE WHEN ('.$sourceQa.') AND NOT ('.$stamp.') THEN 1 ELSE 0 END) AS source_qa_pending,'
        .'SUM(CASE WHEN ('.$conflict.') THEN 1 ELSE 0 END) AS source_conflicts_total,'
        .'SUM(CASE WHEN ('.$conflict.') AND (NOT ('.$stamp.') OR COALESCE(('.$resolution.'),0)=0 OR COALESCE(r.status,\'\')<>\'published\' OR COALESCE(BINARY r.reviewed_data=BINARY o.data,0)=0) THEN 1 ELSE 0 END) AS source_conflicts_unresolved '
        .'FROM npl_observations o LEFT JOIN npl_reviews r ON r.observation_id=o.id');
    return array_map(static fn($v)=>(int)($v??0),$sourceReview??[]);
}
/** Query aggregates in MariaDB; no per-row provisional payloads in HTTP responses. */
function pp_release_diagnostics(): never {
    $u=pp_require_role(['superadmin','admin_editor']);
    pp_rate('npl-release-diagnostics',$u['email'],12,300);
    $required="TRIM(COALESCE(o.subgroup,''))<>'' AND TRIM(COALESCE(o.unit,''))<>'' AND o.value IS NOT NULL AND ".PP_STAGED_VERIFIED;
    // SQL three-valued logic: NOT(NULL) is NULL, not TRUE. Explicit NULL guards
    // are necessary so an incomplete snapshot cannot be counted as published_current.
    $fresh='BINARY r.reviewed_data=BINARY o.data';
    $bucket="CASE WHEN r.observation_id IS NULL THEN 'unreviewed' "
       ."WHEN r.reviewed_data IS NULL OR o.data IS NULL OR NOT ($fresh) THEN 'stale_review' "
       ."WHEN r.status='rejected' THEN 'rejected' "
       ."WHEN r.status='published' AND ($required) THEN 'published_current' "
       ."WHEN r.status='published' THEN 'published_blocked' ELSE 'in_review' END";
    $sql='SELECT states.indicator_id,states.reason,COUNT(*) AS n FROM ('
        .'SELECT o.indicator_id,'.$bucket.' AS reason FROM npl_observations o '
        .'LEFT JOIN npl_reviews r ON r.observation_id=o.id) states '
        .'GROUP BY states.indicator_id,states.reason ORDER BY states.indicator_id,states.reason';
    $rows=pp_all($sql);$groups=[];
    foreach($rows as $row){
        $id=(string)$row['indicator_id'];$reason=(string)$row['reason'];
        if(!in_array($reason,['unreviewed','stale_review','rejected','published_current','published_blocked','in_review'],true))
            throw new PPError(503,'Estado editorial inesperado.');
        if(!isset($groups[$id]))$groups[$id]=['indicator_id'=>$id,'total'=>0];
        $groups[$id][$reason]=(int)$row['n'];$groups[$id]['total']+=(int)$row['n'];
    }
    $indicators=array_values($groups);$summary=pp_release_summary($indicators);
    $sourceReview=pp_release_source_review_backlog();
    usort($indicators,static fn($a,$b)=>(($b['total']-($b['published_current']??0))<=>($a['total']-($a['published_current']??0))));
    // Never assume a pre-existing import snapshot is the count of live observations.
    pp_reply(['api_version'=>1,'scope'=>'editor_only_aggregate','generated_at'=>pp_now(),
        'summary'=>$summary,'source_review_backlog'=>$sourceReview,'indicators'=>$indicators,
        'limitations'=>['No se muestran valores de observaciones sin publicar.',
          'La calidad científica, las fechas y los metadatos exigen revisión adicional; el estado publicado no sustituye QA.',
          'Los recuentos no equivalen al número de combinaciones únicas territorio/periodo/subgrupo.',
          'Este informe se obtiene de MariaDB en tiempo de consulta, no de las cifras declaradas en una importación.',
          'Los contadores de fuente y discrepancia no son categorías mutuamente excluyentes y no sustituyen una auditoría documental.']]);
}
