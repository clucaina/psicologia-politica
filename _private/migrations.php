<?php
declare(strict_types=1);
/** Deliberately conservative schema runner: CREATE TABLE IF NOT EXISTS only. */
function pp_migration_files(): array {
    $paths=glob(__DIR__.'/../database/migrations/*.schema.sql')?:[];sort($paths,SORT_STRING);
    $items=[];
    foreach($paths as $path){$id=basename($path,'.schema.sql');if(!preg_match('/^20[0-9]{6}_[0-9]{3}_[a-z0-9_]+$/D',$id))throw new PPError(422,'Nombre de migración inválido.');
        $bytes=file_get_contents($path);if(strlen($bytes)>50000)throw new PPError(422,'Migración demasiado grande.');
        // Explicitly reject destructive SQL, even in comments.
        if(preg_match('/\b(?:DROP|DELETE|UPDATE|INSERT|REPLACE|TRUNCATE|ALTER|GRANT|REVOKE|LOAD|CALL|EXECUTE)\b/i',$bytes))throw new PPError(422,'Migración con operaciones no permitidas: '.$id);
        $statements=array_values(array_filter(array_map('trim',explode(';',$bytes))));
        if(!$statements)throw new PPError(422,'Migración vacía.');
        foreach($statements as $sql)if(!preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+[a-zA-Z0-9_]+\s*\(/i',$sql))throw new PPError(422,'Solo se admite CREATE TABLE IF NOT EXISTS: '.$id);
        $items[]=['id'=>$id,'sha256'=>hash('sha256',$bytes),'statements'=>$statements];
    }
    return $items;
}
function pp_migration_ledger_exists(): bool {return (bool)pp_one("SHOW TABLES LIKE 'hp_schema_migrations'");}
function pp_migration_plan(): array {
    $files=pp_migration_files();$exists=pp_migration_ledger_exists();$ledger=$exists?pp_all('SELECT id,checksum,status FROM hp_schema_migrations'):[];$known=array_column($ledger,null,'id');$plan=[];
    foreach($files as $f){$prior=$known[$f['id']]??null;$status='pending';if($prior){$status=hash_equals($prior['checksum'],$f['sha256'])?$prior['status']:'checksum_mismatch';}
        $plan[]=['id'=>$f['id'],'sha256'=>$f['sha256'],'status'=>$status,'statements'=>count($f['statements'])];
    }
    return ['ledger_exists'=>$exists,'migrations'=>$plan,'safe_to_apply'=>!in_array('checksum_mismatch',array_column($plan,'status'),true),'note'=>'Solo creación de tablas; no se ejecutan seeds ni SQL históricos.'];
}
function pp_migration_apply(): array {
    if((int)(pp_one("SELECT GET_LOCK('pp_schema_migrations_v1',5) AS acquired")['acquired']??0)!==1)throw new PPError(423,'Otra migración está en curso.');
    try{
        pp_query('CREATE TABLE IF NOT EXISTS hp_schema_migrations (id VARCHAR(190) PRIMARY KEY,checksum CHAR(64) NOT NULL,status VARCHAR(20) NOT NULL,applied_at VARCHAR(40) NULL,duration_ms BIGINT NULL,error_message TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $done=[];
        foreach(pp_migration_files() as $f){
            $prior=pp_one('SELECT checksum,status FROM hp_schema_migrations WHERE id=?',[$f['id']]);
            if($prior){if(!hash_equals($prior['checksum'],$f['sha256']))throw new PPError(409,'Checksum modificado: '.$f['id']);if($prior['status']==='applied')continue;}
            $start=microtime(true);
            try{
                foreach($f['statements'] as $sql)pp_query($sql);
                pp_query('INSERT INTO hp_schema_migrations(id,checksum,status,applied_at,duration_ms,error_message) VALUES(?,?,?,?,?,NULL) ON DUPLICATE KEY UPDATE status=VALUES(status),applied_at=VALUES(applied_at),duration_ms=VALUES(duration_ms),error_message=NULL',[$f['id'],$f['sha256'],'applied',pp_now(),(int)((microtime(true)-$start)*1000)]);
                $done[]=$f['id'];
            }catch(Throwable $e){
                pp_query('INSERT INTO hp_schema_migrations(id,checksum,status,applied_at,duration_ms,error_message) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),duration_ms=VALUES(duration_ms),error_message=VALUES(error_message)',[$f['id'],$f['sha256'],'failed',null,(int)((microtime(true)-$start)*1000),mb_substr($e->getMessage(),0,1000)]);
                throw $e;
            }
        }
        return ['ok'=>true,'applied'=>$done,'plan'=>pp_migration_plan()];
    }finally{pp_query("SELECT RELEASE_LOCK('pp_schema_migrations_v1')");}
}
function pp_migration_api(): never {
    $u=pp_require_role(['superadmin']);$p=pp_body();pp_rate('schema-migrations',$u['email'],8,3600);
    $mode=$p['mode']??'preview';if($mode==='preview')pp_reply(pp_migration_plan());
    if($mode!=='apply'||($p['confirmation']??'')!=='APLICAR_ESQUEMA_SEGURO')throw new PPError(400,'Confirmación de migraciones incorrecta.');
    pp_reply(pp_migration_apply());
}
