<?php
declare(strict_types=1);
/** Staging-only NPL importer. Never publishes observations automatically. */
function pp_batch_load(string $id): array {
    if(!preg_match('/^T[0-9]{2}(?:_[A-Z0-9_]{3,61})?$/D',$id)||strlen($id)>64)throw new PPError(400,'Identificador de tanda inválido.');
    $root=realpath(__DIR__.'/../database');if(!$root)throw new PPError(503,'No se encontró la carpeta de datos.');
    $file=$root.'/'.$id.'.json';if(!is_file($file))$file=$root.'/seeds/'.$id.'.json';if(!is_file($file)&&$id==='T19')$file=$root.'/seeds/T19_CCAA_BASELINE.json';
    if(!is_file($file)||filesize($file)>8*1024*1024)throw new PPError(404,'Tanda no encontrada o demasiado grande.');
    $bytes=file_get_contents($file);
    try{$data=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);}catch(Throwable){throw new PPError(422,'JSON de la tanda inválido.');}
    if(!is_array($data)||($data['batch_id']??'')!==$id||!is_array($data['observations']??null)||count($data['observations'])>5000)throw new PPError(422,'Contrato de tanda inválido (máximo 5.000 observaciones).');
    return ['id'=>$id,'sha256'=>hash('sha256',$bytes),'data'=>$data];
}
function pp_batch_key(array $o): string {
    foreach(['indicator_id','period','geo_code','geography','subgroup','unit','source_id','source_url','dataset_code','methodology_flag'] as $key){
        if(!isset($o[$key])||!is_string($o[$key])||trim($o[$key])==='')throw new PPError(422,'Campo obligatorio de observación: '.$key);
        if(mb_strlen($o[$key])>1800)throw new PPError(422,'Campo excesivo: '.$key);
    }
    if(!preg_match('/^[a-zA-Z0-9_-]{2,100}$/D',$o['indicator_id'])||!preg_match('/^(19|20)[0-9]{2}(?:-[0-9]{2})?$/D',$o['period'])||!preg_match('/^[A-Za-z0-9_-]{2,64}$/D',$o['geo_code']))throw new PPError(422,'Código o periodo inválido.');
    if(!preg_match('/^[A-Za-z0-9_-]{2,100}$/D',$o['source_id'])||!preg_match('/^[A-Za-z0-9_.-]{2,100}$/D',$o['dataset_code']))throw new PPError(422,'Fuente/dataset inválidos.');
    if(!str_starts_with($o['source_url'],'https://')||!pp_url($o['source_url']))throw new PPError(422,'La observación necesita URL HTTPS de fuente.');
    if(!array_key_exists('value',$o)||!is_int($o['value'])&&!is_float($o['value'])||!is_finite((float)$o['value']))throw new PPError(422,'Valor numérico obligatorio (0 es válido).');
    if(!pp_percent_value_valid($o))throw new PPError(422,'Porcentaje fuera de rango o derivación con signo no autorizada.');
    $natural=[$o['indicator_id'],$o['period'],$o['geo_code'],$o['subgroup'],$o['source_id'],$o['dataset_code']];
    return hash('sha256',pp_json($natural));
}
function pp_batch_dependents(string $id): array {
    $dependents=[];
    foreach(glob(__DIR__.'/../database/T*.json')?:[] as $file){
        $data=pp_parse(file_get_contents($file));
        if(is_array($data)&&($data['depends_on_batch']??null)===$id&&is_string($data['batch_id']??null))$dependents[]=$data['batch_id'];
    }
    return array_values(array_unique($dependents));
}
function pp_batch_validate(array $data): array {
    $indicators=[];$keys=[];$sources=[];if(isset($data['depends_on_batch'])&&(!is_string($data['depends_on_batch'])||!preg_match('/^T[0-9]{2}(?:_[A-Z0-9_]{3,61})?$/D',$data['depends_on_batch'])||$data['depends_on_batch']===($data['batch_id']??'')))throw new PPError(422,'Dependencia de tanda inválida.');
    foreach($data['new_indicators']??[] as $i){
        if(!is_array($i)||!preg_match('/^[a-zA-Z0-9_-]{2,100}$/D',(string)($i['indicator_id']??''))||!is_string($i['label_es']??null)||!is_string($i['domain']??null))throw new PPError(422,'Metadatos de indicador inválidos.');
        $id=$i['indicator_id'];if(isset($indicators[$id]))throw new PPError(422,'Indicador duplicado en lote.');$indicators[$id]=$i;
    }
    foreach($data['source_endpoints']??[] as $s){if(is_array($s)&&!empty($s['source_id']))$sources[$s['source_id']]=$s;}
    foreach($data['observations'] as $o){
        if(!is_array($o))throw new PPError(422,'Observación inválida.');
        if(($o['batch_id']??null)!==($data['batch_id']??null))throw new PPError(422,'La observación no pertenece a la tanda declarada.');
        $key=pp_batch_key($o);
        if(isset($indicators[$o['indicator_id']])){
            $meta=$indicators[$o['indicator_id']];
            if(($meta['signed_percent']??false)!==($o['signed_percent']??false))throw new PPError(422,'La configuración de porcentaje con signo contradice al indicador.');
            if(($meta['unit_default']??null)!==$o['unit']||($meta['source_id']??null)!==$o['source_id']||($meta['dataset_code']??null)!==$o['dataset_code'])
                throw new PPError(422,'La observación contradice unidad, fuente o dataset del indicador.');
        }
        if(!isset($sources[$o['source_id']]))throw new PPError(422,'Faltan metadatos de la fuente en la tanda.');
        if(isset($keys[$key]))throw new PPError(422,'Duplicado por clave natural dentro del lote.');$keys[$key]=true;
    }
    return ['indicators'=>$indicators,'sources'=>$sources,'keys'=>$keys];
}
/** Same natural key and numeric value are not enough: units and provenance must agree. */
function pp_batch_semantic_conflicts(array $incoming,array $stored): array {
    $differences=[];
    foreach(['unit','geography','source_url','methodology_flag','measurement_type','population','denominator','source_locator'] as $field){
        $a=$incoming[$field]??null;$b=$stored[$field]??null;
        // Legacy imports may not have newer optional fields. Never fabricate their values.
        if($a===null||$a===''||$b===null||$b==='')continue;
        if((string)$a!==(string)$b)$differences[]=$field;
    }
    if(!array_key_exists('value',$stored)||abs((float)$incoming['value']-(float)$stored['value'])>1e-9)$differences[]='value';
    return $differences;
}
function pp_batch_existing(array $o): ?array {
    $candidates=pp_all('SELECT id,value,unit,geography,data FROM npl_observations WHERE indicator_id=? AND period=? AND geo_code=? AND subgroup=? AND source_id=? ORDER BY id',
        [$o['indicator_id'],$o['period'],$o['geo_code'],$o['subgroup'],$o['source_id']]);
    $matches=[];
    foreach($candidates as $c){$data=pp_parse($c['data']);if(($data['dataset_code']??'')===$o['dataset_code'])$matches[]=$c;}
    if(count($matches)>1)throw new PPError(409,'Hay varias observaciones históricas para la misma clave natural. Resolver antes de importar.');
    return $matches[0]??null;
}
function pp_batch_existing_conflicts(array $incoming,array $old): array {
    $stored=[...pp_parse($old['data']), 'value'=>(float)$old['value'], 'unit'=>$old['unit'], 'geography'=>$old['geography']];
    return pp_batch_semantic_conflicts($incoming,$stored);
}
function pp_batch_preview(array $batch): array {
    $validated=pp_batch_validate($batch['data']);$new=0;$existing=0;$conflicts=[];
    foreach($batch['data']['observations'] as $o){
        $old=pp_batch_existing($o);
        if($old){$existing++;$fields=pp_batch_existing_conflicts($o,$old);if($fields)$conflicts[]=['indicator_id'=>$o['indicator_id'],'period'=>$o['period'],'geo_code'=>$o['geo_code'],'reason'=>'Incompatibilidad con registro histórico: '.implode(', ',$fields)];}
        else $new++;
    }
    $previous=pp_one('SELECT id,sha256,status FROM hp_npl_batches WHERE id=?',[$batch['id']]);
    $dependency=$batch['data']['depends_on_batch']??null;
    $dependencyStatus=$dependency?pp_one('SELECT status FROM hp_npl_batches WHERE id=?',[$dependency])['status']??'missing':null;
    $needed=array_values(array_diff(array_unique(array_column($batch['data']['observations'],'indicator_id')),array_keys($validated['indicators'])));
    $available=[];
    foreach(array_chunk($needed,100) as $chunk){
        $marks=implode(',',array_fill(0,count($chunk),'?'));
        foreach(pp_all('SELECT id FROM npl_indicators WHERE id IN ('.$marks.')',$chunk) as $row)$available[]=$row['id'];
    }
    $missing=array_values(array_diff($needed,$available));
    return ['batch_id'=>$batch['id'],'sha256'=>$batch['sha256'],'observations'=>count($batch['data']['observations']),'new_observations'=>$new,'existing_observations'=>$existing,'conflicts'=>$conflicts,'indicators'=>count($validated['indicators']),'requires_existing_indicators'=>$needed,'missing_existing_indicators'=>$missing,'retrieval_date_missing'=>count(array_filter($batch['data']['observations'],fn($o)=>empty($o['retrieved_at']))),'previous'=>$previous,'depends_on_batch'=>$dependency,'dependency_status'=>$dependencyStatus,'note'=>'Vista previa. Ningún dato ha sido modificado ni publicado.'];
}
function pp_batch_stage(array $batch,string $actor): array {
    $id=$batch['id'];$sha=$batch['sha256'];$data=$batch['data'];$validated=pp_batch_validate($data);
    $existing=pp_one('SELECT * FROM hp_npl_batches WHERE id=?',[$id]);
    if($existing&&$existing['sha256']!==$sha)throw new PPError(409,'El batch_id ya existe con un hash distinto. No se sobreescribe.');
    if($existing&&$existing['status']==='staged')return ['ok'=>true,'batch_id'=>$id,'status'=>'staged','already_imported'=>true];
    $plan=pp_batch_preview($batch);if($plan['depends_on_batch']&&$plan['dependency_status']!=='staged')throw new PPError(409,'Primero importa y valida la tanda de origen: '.$plan['depends_on_batch']);if($plan['missing_existing_indicators'])throw new PPError(409,'La tanda depende de indicadores previos ausentes: '.implode(', ',$plan['missing_existing_indicators']));if($plan['conflicts'])throw new PPError(409,'Existen valores diferentes para claves naturales ya importadas. Revisión manual obligatoria.');
    pp_transaction(function()use($id,$sha,$actor,$validated,$data,$existing){
        if(!$existing)pp_query('INSERT INTO hp_npl_batches(id,sha256,status,created_at,created_by,total_rows) VALUES(?,?,?,?,?,?)',[$id,$sha,'partial',pp_now(),$actor,count($data['observations'])]);
        foreach($validated['sources'] as $sourceId=>$source){
            if(!pp_one('SELECT id FROM npl_sources WHERE id=?',[$sourceId]))pp_query('INSERT INTO npl_sources(id,data) VALUES(?,?)',[$sourceId,pp_json(['id'=>$sourceId,'name'=>$sourceId,'url'=>$source['url']??'','imported_by'=>$id])]);
        }
        foreach($validated['indicators'] as $indicatorId=>$i){
            $prior=pp_one('SELECT id,unit,source_id FROM npl_indicators WHERE id=?',[$indicatorId]);
            if($prior){if($prior['unit']!==($i['unit_default']??null))throw new PPError(409,'Unidad incompatible para indicador '.$indicatorId);continue;}
            pp_query('INSERT INTO npl_indicators(id,domain,name,unit,source_id,data) VALUES(?,?,?,?,?,?)',[$indicatorId,$i['domain'],$i['label_es'],$i['unit_default']??null,$i['source_id']??null,pp_json($i)]);
        }
    });
    $inserted=0;$skipped=0;
    foreach(array_chunk($data['observations'],100) as $chunk){
        pp_transaction(function()use($chunk,$id,&$inserted,&$skipped){
            foreach($chunk as $o){
                $key=pp_batch_key($o);$ledger=pp_one('SELECT observation_id FROM hp_npl_batch_items WHERE natural_key=?',[$key]);
                if($ledger){$old=pp_one('SELECT value,unit,geography,data FROM npl_observations WHERE id=?',[$ledger['observation_id']]);if(!$old||pp_batch_existing_conflicts($o,$old))throw new PPError(409,'Clave natural ya registrada con valor o metadatos incompatibles.');$skipped++;continue;}
                $old=pp_batch_existing($o);if($old){
                    if(pp_batch_existing_conflicts($o,$old))throw new PPError(409,'Conflicto de valor o metadatos con observación histórica.');
                    $skipped++;continue;
                }
                if(!pp_one('SELECT id FROM npl_indicators WHERE id=?',[$o['indicator_id']]))throw new PPError(422,'Falta indicador '.$o['indicator_id']);
                if(!pp_one('SELECT id FROM npl_sources WHERE id=?',[$o['source_id']]))throw new PPError(422,'Falta fuente '.$o['source_id']);
                $o['batch_id']=$id;$o['source_quality_flag']=$o['quality_flag']??null;$o['quality_flag']='PROVISIONAL';$o['import_review']='pending';
                $newId=pp_insert('npl_observations',['indicator_id'=>$o['indicator_id'],'period'=>$o['period'],'geo_code'=>$o['geo_code'],'geography'=>$o['geography'],'subgroup'=>$o['subgroup'],'value'=>$o['value'],'unit'=>$o['unit'],'source_id'=>$o['source_id'],'data'=>pp_json($o)]);
                pp_query('INSERT INTO hp_npl_batch_items(batch_id,natural_key,observation_id) VALUES(?,?,?)',[$id,$key,$newId]);$inserted++;
            }
        });
    }
    pp_query("UPDATE hp_npl_batches SET status='staged',staged_at=?,inserted_rows=(SELECT count(*) FROM hp_npl_batch_items WHERE batch_id=?) WHERE id=?",[pp_now(),$id,$id]);
    return ['ok'=>true,'batch_id'=>$id,'status'=>'staged','inserted_now'=>$inserted,'skipped'=>$skipped,'published'=>0,'note'=>'Solo en revisión. La publicación requiere evaluación humana de cada observación.'];
}
/** All-or-nothing rollback. Row locks close the race with a concurrent editorial review.
 *  Never delete an observation not owned by this batch or already reviewed. */
function pp_batch_rollback(string $id): array {
    return pp_transaction(function()use($id){
        $batch=pp_one('SELECT * FROM hp_npl_batches WHERE id=? FOR UPDATE',[$id]);
        if(!$batch)throw new PPError(404,'Tanda no importada.');
        if($batch['status']==='rolled_back')return ['ok'=>true,'batch_id'=>$id,'status'=>'rolled_back','already_rolled_back'=>true,'removed'=>0];
        if(!in_array($batch['status'],['staged','partial'],true))throw new PPError(409,'Estado de tanda incompatible con rollback.');
        foreach(pp_batch_dependents($id) as $dependent){$child=pp_one('SELECT status FROM hp_npl_batches WHERE id=?',[$dependent]);if($child&&in_array($child['status'],['staged','partial'],true))throw new PPError(409,'Rollback bloqueado: primero revierte la tanda derivada '.$dependent);}
        $items=pp_all('SELECT observation_id FROM hp_npl_batch_items WHERE batch_id=? ORDER BY observation_id FOR UPDATE',[$id]);
        $ids=array_map(fn($item)=>(string)$item['observation_id'],$items);
        foreach(array_chunk($ids,100) as $chunk){
            if(!$chunk)continue;
            $marks=implode(',',array_fill(0,count($chunk),'?'));
            $rows=pp_all('SELECT id,data FROM npl_observations WHERE id IN ('.$marks.') FOR UPDATE',$chunk);
            if(count($rows)!==count($chunk))throw new PPError(409,'El lote contiene observaciones que ya no existen.');
            foreach($rows as $row){
                $metadata=pp_parse($row['data']);
                if(($metadata['batch_id']??null)!==$id||($metadata['import_review']??null)!=='pending')
                    throw new PPError(409,'Una observación fue modificada o no pertenece a esta tanda. Rollback bloqueado.');
            }
            $reviewed=pp_one('SELECT observation_id FROM npl_reviews WHERE observation_id IN ('.$marks.') LIMIT 1',$chunk);
            if($reviewed)throw new PPError(409,'Hay observaciones revisadas. No se elimina ningún registro.');
        }
        foreach(array_chunk($ids,100) as $chunk){
            if(!$chunk)continue;
            $marks=implode(',',array_fill(0,count($chunk),'?'));
            pp_query('DELETE FROM hp_npl_batch_items WHERE batch_id=? AND observation_id IN ('.$marks.')',[$id,...$chunk]);
            pp_query('DELETE FROM npl_observations WHERE id IN ('.$marks.')',$chunk);
        }
        pp_query("UPDATE hp_npl_batches SET status='rolled_back',rolled_back_at=?,inserted_rows=0 WHERE id=?",[pp_now(),$id]);
        return ['ok'=>true,'batch_id'=>$id,'status'=>'rolled_back','removed'=>count($ids),'note'=>'Rollback transaccional completo. Se conservaron fuentes e indicadores compartidos.'];
    });
}
function pp_npl_batch_api(): never {
    $u=pp_require_role(['superadmin']);$p=pp_body();pp_rate('npl-batch',$u['email'],15,300);
    $mode=$p['mode']??'preview';if(!in_array($mode,['preview','stage','rollback'],true))throw new PPError(400,'Operación no admitida.');
    $batch=pp_batch_load((string)($p['batch_id']??''));
    if(!pp_one("SHOW TABLES LIKE 'hp_npl_batches'")||!pp_one("SHOW TABLES LIKE 'hp_npl_batch_items'"))throw new PPError(503,'Primero aplica la migración del importador desde mantenimiento.');
    if($mode==='preview')pp_reply(pp_batch_preview($batch));
    if(!is_string($p['confirm_sha256']??null)||!hash_equals($batch['sha256'],$p['confirm_sha256']))throw new PPError(409,'Confirma el SHA-256 obtenido en la vista previa.');
    if($mode==='rollback'&&($p['confirm_batch_id']??'')!==$batch['id'])throw new PPError(409,'Confirma el identificador completo para revertir.');
    if((int)pp_one("SELECT GET_LOCK('pp_npl_import_v1',5) AS acquired")['acquired']!==1)throw new PPError(423,'Otra importación está en curso.');
    try{$result=$mode==='stage'?pp_batch_stage($batch,$u['email']):pp_batch_rollback($batch['id']);}finally{pp_query("SELECT RELEASE_LOCK('pp_npl_import_v1')");}
    pp_reply($result);
}
