<?php
declare(strict_types=1);

class PPError extends RuntimeException { public function __construct(public int $status, string $message) { parent::__construct($message); } }
function pp_config(): array {
    static $config;
    if ($config !== null) return $config;
    $file = __DIR__.'/config.php';
    if (!is_file($file)) throw new PPError(503, 'Falta la configuración del sitio. Sigue la guía de instalación.');
    $config = require $file;
    if (!is_array($config)) throw new PPError(503,'Configuración no válida.');
    return $config;
}
function pp_db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $d=pp_config()['database'];
    $dsn=($d['socket']??'') ? 'mysql:unix_socket='.$d['socket'] : 'mysql:host='.$d['host'].';port='.intval($d['port']??3306);
    $pdo=new PDO($dsn.';dbname='.$d['name'].';charset=utf8mb4',$d['user'],$d['password'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false,
    ]);
    $pdo->exec("SET time_zone='+00:00'");
    $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}
function pp_query(string $sql, array $args=[]): PDOStatement { $s=pp_db()->prepare($sql);foreach(array_values($args) as $i=>$v)$s->bindValue($i+1,$v,$v===null?PDO::PARAM_NULL:(is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR));$s->execute();return $s; }
function pp_all(string $sql,array $args=[]): array {return pp_query($sql,$args)->fetchAll();}
function pp_one(string $sql,array $args=[]): ?array {return pp_query($sql,$args)->fetch()?:null;}
function pp_now(): string {return gmdate('Y-m-d\TH:i:s').sprintf('.%03dZ',(int)(microtime(true)*1000)%1000);}
function pp_uuid(): string {$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
function pp_clean(mixed $v,int $max=1000): string {return is_string($v)?mb_substr(trim($v),0,$max):'';}
/** Signed derived percentages (e.g. deviation from national mean) may be negative.
 *  Prevalence/rate percentages remain constrained to [0,100]. */
function pp_percent_value_valid(array $o): bool {
    $v=$o['value']??null;
    if(!is_int($v)&&!is_float($v)&&!is_numeric($v))return false;
    $v=(float)$v;
    if(!is_finite($v))return false;
    if(($o['unit']??'')!=='%')return true;
    // A year-on-year/quarter-on-quarter index variation may be negative or
    // exceed +100%. It is NOT a prevalence, a share, or a probability. Permit
    // signed changes only with an explicit, narrowly defined semantic marker
    // and traceable source; keep bounded percentages [0,100] by default.
    if(in_array($o['percentage_semantics']??null,['relative_change','year_over_year_index_change'],true)
       &&in_array($o['measurement_type']??null,['administrative_record','estimate','derived'],true)
       &&str_starts_with((string)($o['source_url']??''),'https://')
       &&in_array($o['frequency']??null,['quarterly','annual','monthly'],true))
        return $v>=-100&&$v<=1000000;
    if(($o['measurement_type']??'')==='derived'&&($o['signed_percent']??false)===true
       &&str_starts_with((string)($o['methodology_flag']??''),'NPL_DERIVED_'))return abs($v)<=1000000;
    return $v>=0&&$v<=100;
}
function pp_json(mixed $v): string {return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function pp_parse(mixed $v,mixed $fallback=[]): mixed {if(!is_string($v))return $v??$fallback;try{return json_decode($v,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){return $fallback;}}
function pp_url(mixed $v): string {
    $v=pp_clean($v,2000);
    // Published source links are never executable, local or credential-bearing.
    if($v===''||preg_match('/[\\x00-\\x20\\x7f]/',$v)||str_contains($v,chr(92))||!filter_var($v,FILTER_VALIDATE_URL))return '';
    $p=parse_url($v);
    if(!is_array($p)||!in_array(strtolower((string)($p['scheme']??'')),['https','http'],true)||isset($p['user'])||isset($p['pass']))return '';
    $host=strtolower(rtrim((string)($p['host']??''),'.'));
    if($host===''||$host==='localhost'||str_ends_with($host,'.localhost')||str_ends_with($host,'.local'))return '';
    if(filter_var($host,FILTER_VALIDATE_IP)){
        if(!filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return '';
    }elseif(!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/i',$host))return '';
    return $v;
}
function pp_links(mixed $v): array {$v=is_array($v)?$v:explode("\n",(string)$v);return array_slice(array_values(array_unique(array_filter(array_map('pp_url',$v)))),0,5);}
function pp_slug(string $v): string {$s=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v);return substr(trim(preg_replace('/[^a-z0-9]+/','-',strtolower($s?:$v)),'-'),0,140);}
function pp_escape(mixed $s): string {return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function pp_reply(mixed $data,int $code=200): never {if(function_exists('pp_runtime_headers'))pp_runtime_headers();http_response_code($code);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo pp_json($data);exit;}
/** Prevent staging/preview copies from being indexed as production.
 * The configured canonical origin AND actual HTTP Host must match the official
 * site; a staging vhost with a copied production config still stays noindex. */
function pp_indexable_origin(string $configuredOrigin,string $requestHost): bool {
 $configured=strtolower(rtrim($configuredOrigin,'/'));
 $host=strtolower(trim($requestHost));
 return in_array($configured,['https://psicologiapolitica.es','https://www.psicologiapolitica.es'],true)
     &&in_array($host,['psicologiapolitica.es','www.psicologiapolitica.es'],true)
     &&parse_url($configured,PHP_URL_HOST)===$host;
}
function pp_is_public_production_host(): bool {
 return pp_indexable_origin(pp_origin(),(string)($_SERVER['HTTP_HOST']??''));
}
function pp_origin(): string {return rtrim(pp_config()['site_url'],'/');}
function pp_session(): void {
    if(session_status()===PHP_SESSION_ACTIVE)return;
    $dir=__DIR__.'/sessions';if(!is_dir($dir))mkdir($dir,0700,true);
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    session_save_path($dir);session_name('pp_hostinger');
    session_set_cookie_params(['lifetime'=>86400,'path'=>'/','secure'=>str_starts_with(pp_origin(),'https://'),'httponly'=>true,'samesite'=>'Lax']);
    session_start();if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
}
function pp_csrf(): void {
    pp_session();$origin=$_SERVER['HTTP_ORIGIN']??'';
    if(($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site'||($origin&&$origin!==pp_origin()))throw new PPError(403,'Solicitud de otro origen no permitida.');
    $token=$_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['_csrf']??'';
    if(!is_string($token)||!hash_equals($_SESSION['csrf'],$token))throw new PPError(403,'La sesión del formulario caducó. Recarga la página.');
}
function pp_body(): array {pp_csrf();$text=file_get_contents('php://input');if(strlen($text)>100000)throw new PPError(413,'El contenido es demasiado largo.');try{$v=json_decode($text,true,32,JSON_THROW_ON_ERROR);}catch(Throwable){throw new PPError(400,'Solicitud no válida.');}if(!is_array($v)||array_is_list($v)&&count($v)>0)throw new PPError(400,'Solicitud no válida.');return $v;}
function pp_rate(string $action,string $account='',int $max=20,int $seconds=3600): void {
    $bucket=intdiv(time(),$seconds);$subjects=[$_SERVER['REMOTE_ADDR']??'local'];if($account)$subjects[]=$account;
    foreach($subjects as $subject){$key=hash('sha256','hostinger:'.$action.':'.$subject.':'.$bucket);pp_query('INSERT INTO rate_limits(id,count,expires_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE count=count+1',[$key,($bucket+1)*$seconds]);$r=pp_one('SELECT count FROM rate_limits WHERE id=?',[$key]);if($r['count']>$max){$retry=max(1,($bucket+1)*$seconds-time());header('Retry-After: '.$retry);header('X-RateLimit-Policy: application');throw new PPError(429,'Demasiados intentos. Espera antes de volver a intentarlo.');}}
}
function pp_user(): ?array {
    pp_session();$token=$_SESSION['auth_token']??'';if(!$token)return null;
    // New tokens use a distinct table: old provider sessions cannot authenticate.
    $p=pp_one('SELECT p.*,r.role,r.team_id,c.must_change_password,c.email AS credential_email FROM hp_sessions s JOIN reader_profiles p ON p.email=s.email LEFT JOIN user_roles r ON r.email=p.email LEFT JOIN local_credentials c ON c.email=p.email WHERE s.token_hash=? AND s.revoked_at IS NULL AND s.expires_at>?',[hash('sha256',$token),time()]);
    if(!$p||$p['status']!=='active'||!$p['email_verified'])return null;
    return ['id'=>$p['user_id']?:'email:'.$p['email'],'email'=>$p['email'],'displayName'=>$p['display_name'],'fullName'=>$p['display_name'],'role'=>$p['role']?:'registered_user','teamId'=>$p['team_id'],'provider'=>'email','hasLocalPassword'=>(bool)$p['credential_email'],'mustChangePassword'=>(bool)$p['must_change_password']];
}
function pp_require_user(): array {$u=pp_user();if(!$u)throw new PPError(401,'Inicia sesión con una cuenta verificada.');return $u;}
function pp_require_role(array $roles,bool $allowInitial=false): array {$u=pp_require_user();if(!in_array($u['role'],$roles,true))throw new PPError(403,'Tu rol no permite esta operación.');if($u['mustChangePassword']&&!$allowInitial)throw new PPError(403,'Cambia primero la contraseña inicial desde Configuración.');return $u;}
function pp_login(string $email): void {
    $p=pp_one('SELECT * FROM reader_profiles WHERE email=?',[$email]);if(!$p||$p['status']!=='active'||!$p['email_verified'])throw new PPError(403,'Verifica tu email antes de entrar.');
    pp_session();session_regenerate_id(true);$token=bin2hex(random_bytes(32));$_SESSION['auth_token']=$token;$_SESSION['csrf']=bin2hex(random_bytes(32));
    pp_query('INSERT INTO hp_sessions(token_hash,email,expires_at) VALUES(?,?,?)',[hash('sha256',$token),$email,time()+86400]);
}
function pp_password(string $password): void {if(mb_strlen($password)<12||strlen($password)>128||!preg_match('/[\p{L}]/u',$password)||!preg_match('/[0-9]/',$password))throw new PPError(400,'Usa una contraseña de 12 a 128 caracteres con letras y números.');}
function pp_verify_password(string $email,string $password): bool {
    $c=pp_one('SELECT * FROM local_credentials WHERE email=?',[$email]);if(!$c){password_verify($password,'$2y$12$nEnpgIdzcCbGVbjOudIRweRY.u74UkJBjdLMHbQArLt8LeNTLU50.');return false;}
    if($c['algorithm']==='PBKDF2-SHA256'){$salt=base64_decode($c['salt'],true);$valid=$salt!==false&&hash_equals($c['password_hash'],base64_encode(hash_pbkdf2('sha256',$password,$salt,(int)$c['iterations'],32,true)));}
    else $valid=password_verify(hash('sha256',$password),$c['password_hash']);
    // Prehash to 64 ASCII characters so bcrypt never truncates long passwords.
    if($valid&&($c['algorithm']==='PBKDF2-SHA256'||password_needs_rehash($c['password_hash'],PASSWORD_DEFAULT)))pp_query('UPDATE local_credentials SET password_hash=?,salt=?,iterations=0,algorithm=?,updated_at=? WHERE email=?',[password_hash(hash('sha256',$password),PASSWORD_DEFAULT),'','PHP-PASSWORD-SHA256',pp_now(),$email]);
    return $valid;
}
function pp_set_password(string $email,string $password,bool $initial=false): void {
    pp_password($password);pp_query('INSERT INTO local_credentials(email,password_hash,salt,iterations,algorithm,must_change_password,password_changed_at,updated_at) VALUES(?,?,?,0,?,?,?,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),salt=VALUES(salt),iterations=0,algorithm=VALUES(algorithm),must_change_password=VALUES(must_change_password),password_changed_at=VALUES(password_changed_at),updated_at=VALUES(updated_at)',[$email,password_hash(hash('sha256',$password),PASSWORD_DEFAULT),'','PHP-PASSWORD-SHA256',$initial?1:0,pp_now(),pp_now()]);
}
function pp_audit(string $actor,string $entity,string $id,string $action,?array $before,?array $after,string $note=''): void {
    pp_query('INSERT INTO moderation_log(id,actor_id,action,entity_type,entity_id,before_state,after_state,note) VALUES(?,?,?,?,?,?,?,?)',[pp_uuid(),$actor,$action,$entity,$id,pp_json($before),pp_json($after),$note]);
    if($before&&in_array($entity,['content','team','activities','npl_observations','npl_indicators'],true))pp_query('INSERT INTO editorial_revisions(id,entity_type,entity_id,editor_id,snapshot) VALUES(?,?,?,?,?)',[pp_uuid(),$entity,$id,$actor,pp_json($before)]);
}
function pp_transaction(callable $fn): mixed {$db=pp_db();$db->beginTransaction();try{$v=$fn();$db->commit();return $v;}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}}
function pp_insert(string $table,array $values): string {
    $keys=array_keys($values);pp_query('INSERT INTO `'.$table.'` (`'.implode('`,`',$keys).'`) VALUES ('.implode(',',array_fill(0,count($keys),'?')).')',array_values($values));return pp_db()->lastInsertId();
}
function pp_update(string $table,array $values,string $key,mixed $id): void {pp_query('UPDATE `'.$table.'` SET '.implode(',',array_map(fn($k)=>'`'.$k.'`=?',array_keys($values))).' WHERE `'.$key.'`=?',[...array_values($values),$id]);}
function pp_revoke(string $email): void {pp_query('UPDATE hp_sessions SET revoked_at=? WHERE email=?',[pp_now(),$email]);}
function pp_email(mixed $value): string {$email=strtolower(pp_clean($value,254));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new PPError(400,'Introduce un email válido.');return $email;}
function pp_redirect(string $path,int $status=302): never {if(!str_starts_with($path,'/')||str_starts_with($path,'//')||preg_match('/[\r\n\\\\]/',$path))$path='/';header('Location: '.$path,true,$status);exit;}
function pp_cover(mixed $key): ?string {return is_string($key)&&preg_match('~^(?:[a-f0-9-]{36}\.jpg|static:(?:editorial|news-visuals|group-assets)/[a-z0-9.-]+\.(?:jpg|png|svg))$~',$key)?$key:null;}
function pp_image(?string $key): string {return !$key?'':(str_starts_with($key,'static:')?'/'.substr($key,7):'/api/media/'.$key);}
