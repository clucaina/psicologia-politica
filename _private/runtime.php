<?php
declare(strict_types=1);

/** Runtime identity is independent from the database so staging can prove which
 * code is serving requests even while DB integration is unavailable. */
function pp_release_info(): array {
    static $info;
    if($info!==null)return $info;
    $path=dirname(__DIR__).'/VERSION.json';
    $fallback=['version'=>'UNKNOWN','date'=>null,'status'=>'UNKNOWN','release_gate'=>'NO-GO','updated_at'=>null];
    if(!is_file($path))return $info=[...$fallback,'fingerprint'=>null];
    $raw=file_get_contents($path);$doc=is_string($raw)?pp_parse($raw,null):null;
    if(!is_array($doc))return $info=[...$fallback,'fingerprint'=>substr(hash_file('sha256',$path),0,16)];
    $version=is_string($doc['version']??null)&&preg_match('/^V[0-9]{1,4}$/D',$doc['version'])?$doc['version']:'UNKNOWN';
    $gate=strtoupper(trim((string)($doc['release_gate']??'NO-GO')));if(!in_array($gate,['GO','NO-GO'],true))$gate='NO-GO';
    $status=preg_replace('/[^A-Za-z0-9_.-]/','_',substr((string)($doc['status']??'UNKNOWN'),0,80));
    return $info=['version'=>$version,'date'=>is_string($doc['date']??null)?$doc['date']:null,
        'status'=>$status?:'UNKNOWN','release_gate'=>$gate,
        'updated_at'=>is_string($doc['updated_at']??null)?$doc['updated_at']:null,
        'fingerprint'=>substr(hash_file('sha256',$path),0,16)];
}
function pp_release_contract(): string {return '2026-10-v2';}
function pp_runtime_headers(): void {
    if(headers_sent())return;$r=pp_release_info();
    header('X-PP-Release: '.$r['version']);header('X-PP-Release-Gate: '.$r['release_gate']);header('X-PP-Contract: '.pp_release_contract());
}
/** Public status proves code identity and supported route contract only. It does
 * not claim MariaDB availability or scientific approval of prepared data. */
function pp_public_status(): never {
    pp_reply(['ok'=>true,'service'=>'psicologiapolitica.es','release'=>pp_release_info(),
        'contract'=>pp_release_contract(),'production_host'=>pp_is_public_production_host(),
        'features'=>['mapa_espana'=>true,'npl_coverage'=>true,'npl_quarterly'=>true,
            'npl_compare'=>true,'npl_correlation'=>true,'npl_series'=>true,'npl_rank'=>true,
            'npl_ask'=>true,'text_analyzer_v2'=>true,'blog_editor_fingerprint'=>true,'npl_review_console'=>true],
        'note'=>'Identidad del código y contrato de rutas. No implica disponibilidad de MariaDB ni aprobación científica de datos.']);
}
function pp_runtime_table_exists(string $table): bool {
    if(!preg_match('/^[a-z][a-z0-9_]{0,63}$/D',$table))return false;
    $r=pp_one('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$table]);return (int)($r['n']??0)===1;
}
/** Authenticated, read-only diagnostics. No credentials, emails or raw DB errors
 * are returned. */
function pp_admin_runtime_diagnostics(): never {
    $u=pp_require_role(['superadmin','admin_editor']);pp_rate('runtime-diagnostics',$u['email'],60,300);
    $required=['editorial_posts','reader_profiles','user_roles','hp_sessions','local_credentials','npl_observations','npl_indicators','npl_sources','npl_reviews','npl_imports'];
    $database=['available'=>false,'required_tables'=>(object)[],'all_required_tables'=>false];
    $migrations=['available'=>false,'safe_to_apply'=>false,'migrations'=>[]];
    try{
        pp_one('SELECT 1 AS ok');$database['available']=true;$found=[];
        foreach($required as $table)$found[$table]=pp_runtime_table_exists($table);
        $database['required_tables']=$found;$database['all_required_tables']=!in_array(false,$found,true);
        if($database['all_required_tables']){
            $database['published_npl']=(int)(pp_one('SELECT COUNT(*) AS n FROM npl_observations o '.PP_REVIEW_JOIN.' WHERE '.PP_PUBLISHED)['n']??0);
            $database['total_npl']=(int)(pp_one('SELECT COUNT(*) AS n FROM npl_observations')['n']??0);
            $database['published_posts']=(int)(pp_one("SELECT COUNT(*) AS n FROM editorial_posts WHERE status='published' AND deleted_at IS NULL")['n']??0);
        }
        require_once __DIR__.'/migrations.php';$migrations=pp_migration_plan();$migrations['available']=true;
    }catch(Throwable){$database['diagnostic']='database_or_schema_unavailable';}
    pp_reply(['ok'=>$database['available']&&$database['all_required_tables'],'release'=>pp_release_info(),'contract'=>pp_release_contract(),
        'database'=>$database,'migrations'=>$migrations,
        'expected_public_routes'=>['/','/conflictometro','/mapa-espana','/herramientas/datos-espana','/herramientas/analizar-texto','/blog','/contacto','/api/v1/status','/api/v1/npl','/api/v1/npl/coverage','/api/v1/npl/quarterly'],
        'protected_remote_paths'=>['analytics.js','uploads/','_private/config.php','_private/backups/','_private/sessions/'],
        'note'=>'Diagnóstico de solo lectura. No ejecuta migraciones, importaciones, publicaciones ni restauraciones.']);
}
