<?php
declare(strict_types=1);
/** Feed discovery creates review candidates only; never articles or published posts. */
function pp_news_feeds(): array {
    $registry=pp_parse(file_get_contents(__DIR__.'/../database/news_feeds.json'));
    return array_column($registry['feeds']??[],null,'id');
}
function pp_news_normalize_url(string $url): string {
    $p=parse_url($url);if(!$p||strtolower($p['scheme']??'')!=='https'||!isset($p['host'])||isset($p['user'])||isset($p['pass']))return '';
    $host=strtolower($p['host']);if(!preg_match('/^[a-z0-9.-]+$/D',$host))return '';
    $params=[];parse_str($p['query']??'',$params);foreach(array_keys($params) as $key)if(preg_match('/^(utm_|fbclid$|gclid$)/i',$key))unset($params[$key]);ksort($params);
    return 'https://'.$host.($p['path']??'/').($params?'?'.http_build_query($params):'');
}
/** DOI and normalized-URL identity: candidate review queue must not duplicate the same study via different publisher URLs. */
function pp_news_identity(string $url): string {
    $canonical=pp_news_normalize_url($url);
    if($canonical==='')return '';
    $path=rawurldecode((string)parse_url($canonical,PHP_URL_PATH));
    if(preg_match('~/(10\.[0-9]{4,9}/[A-Za-z0-9._;()/:-]+)~i',$path,$m))
        return 'doi:'.strtolower(rtrim($m[1],'/'));
    return 'url:'.$canonical;
}
/** Conservative title identity: normalize typographic differences, never fuzzy-match topics. */
function pp_news_title_key(string $title): string {
    $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',trim($title));
    $normalized=strtolower($ascii?:$title);
    return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $normalized)));
}
function pp_news_known_index(bool $includeQueue=true): array {
    $index=['source'=>[],'title'=>[]];
    $rows=pp_all("SELECT source_url,title FROM editorial_posts WHERE source_url IS NOT NULL AND source_url<>'' LIMIT 10001");
    if(count($rows)>10000)throw new PPError(503,'Índice editorial incompleto: amplía la paginación antes de importar noticias.');
    if($includeQueue&&pp_one("SHOW TABLES LIKE 'hp_news_candidates'")){
        $queue=pp_all('SELECT source_url,title FROM hp_news_candidates LIMIT 10001');
        if(count($queue)>10000)throw new PPError(503,'Cola editorial demasiado grande para comprobar duplicados de forma segura.');
        $rows=[...$rows,...$queue];
    }
    foreach($rows as $row){
        $identity=pp_news_identity((string)($row['source_url']??''));
        if($identity!=='')$index['source'][$identity]=true;
        $title=pp_news_title_key((string)($row['title']??''));
        if($title!=='')$index['title'][$title]=true;
    }
    return $index;
}
function pp_news_is_known(array $candidate,array $index): bool {
    $identity=pp_news_identity((string)($candidate['source_url']??''));
    $title=pp_news_title_key((string)($candidate['title']??''));
    return ($identity!==''&&isset($index['source'][$identity]))||($title!==''&&isset($index['title'][$title]));
}
function pp_news_add_known(array $candidate,array &$index): void {
    $identity=pp_news_identity((string)($candidate['source_url']??''));
    if($identity!=='')$index['source'][$identity]=true;
    $title=pp_news_title_key((string)($candidate['title']??''));
    if($title!=='')$index['title'][$title]=true;
}
function pp_news_topics(string $text): array {
    $patterns=['desinformacion'=>'/disinformation|misinformation|fake news|deepfake|false information|desinformaci[oó]n/ui',
      'elecciones'=>'/election|electoral|vot(?:e|ing)|elecci[oó]n|democra/ui',
      'comunicacion_politica'=>'/political advertising|political communication|media literacy|political campaign|public opinion/ui',
      'regulacion_ia'=>'/AI Act|artificial intelligence|algorithmic|digital services|online platforms/ui',
      'polarizacion'=>'/polari[sz]ation|polarizaci[oó]n|hate speech|social cohesion/ui',
      'datos_sociales'=>'/mental health|well-being|housing|poverty|social exclusion|youth unemployment|social trust|migration|political participation|media consumption|digital literacy/ui'];
    $found=[];foreach($patterns as $name=>$pattern)if(preg_match($pattern,$text))$found[]=$name;return $found;
}
/** Supports RSS 2.0 and Atom 1.0, never follows arbitrary item links or publishes content. */
function pp_news_parse_feed(string $xml,string $feedId): array {
    if(!extension_loaded('simplexml'))throw new PPError(503,'Falta la extensión PHP SimpleXML.');
    $prior=libxml_use_internal_errors(true);
    $feed=simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA);
    libxml_clear_errors();libxml_use_internal_errors($prior);
    if($feed===false)throw new PPError(422,'El feed XML no es válido.');
    $entries=[];
    if($feed->getName()==='rss'&&isset($feed->channel->item)){
        foreach($feed->channel->item as $item){
            $entries[]=['url'=>(string)$item->link,'title'=>(string)$item->title,
                'description'=>(string)$item->description,'date'=>(string)$item->pubDate];
        }
    }elseif($feed->getName()==='feed'){
        // Atom link href is an attribute, not an element's text content.
        $atom=$feed->children('http://www.w3.org/2005/Atom');
        $entriesXml=count($atom->entry)?$atom->entry:$feed->entry;
        foreach($entriesXml as $entry){
            $fields=$entry->children('http://www.w3.org/2005/Atom');
            if(!count($fields))$fields=$entry;
            $url='';foreach($fields->link as $link){$attr=$link->attributes();
                if((string)($attr['rel']??'alternate')==='alternate'&&isset($attr['href'])){$url=(string)$attr['href'];break;}}
            $entries[]=['url'=>$url,'title'=>(string)$fields->title,
                'description'=>(string)($fields->summary?:$fields->content),
                'date'=>(string)($fields->published?:$fields->updated)];
        }
    }else throw new PPError(422,'Solo se admiten feeds RSS 2.0 o Atom 1.0.');
    $out=[];$known=[];
    foreach($entries as $item){
        $url=pp_news_normalize_url(trim($item['url']));
        $title=trim(strip_tags($item['title']));
        $description=trim(strip_tags($item['description']));
        if(!$url||!in_array(parse_url($url,PHP_URL_HOST),['ec.europa.eu','commission.europa.eu','eurostat.ec.europa.eu'],true)||mb_strlen($title)<15)continue;
        $topics=pp_news_topics($title.' '.$description);if(!$topics)continue;
        $identity=pp_news_identity($url);if(isset($known[$identity]))continue;$known[$identity]=true;
        $timestamp=$item['date']?strtotime($item['date']):false;
        $brief='Ilustración editorial original y sobria sobre '.implode(', ',$topics).'; sin logotipos ni rostros reales, sin titulares incrustados, apta para prensa académica.';
        $out[]=['url_sha256'=>hash('sha256',$url),'feed_id'=>$feedId,'title'=>mb_substr($title,0,400),
            'source_url'=>$url,'short_summary'=>mb_substr($description,0,750),
            'published_at'=>$timestamp?gmdate('c',$timestamp):null,'matched_terms'=>$topics,
            'image_brief'=>$brief,'status'=>'review'];
        if(count($out)>=25)break;
    }
    return $out;
}
/** HTTPS-only fixed allowlist, no redirects, bounded download and TLS verification. */
function pp_news_download(string $url): string {
    if(function_exists('curl_init')){
        $ch=curl_init($url);$bytes='';$maxBytes=2*1024*1024;
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'PsicologiaPoliticaNewsAudit/1.0',
            CURLOPT_HTTPHEADER=>['Accept: application/rss+xml, application/xml'],
            CURLOPT_MAXREDIRS=>0]);
        // Enforce the 2 MB limit during streaming, not after a potentially huge download.
        curl_setopt($ch,CURLOPT_WRITEFUNCTION,static function($handle,string $chunk)use(&$bytes,$maxBytes): int {
            $size=strlen($chunk);if(strlen($bytes)+$size>$maxBytes)return 0;
            $bytes.=$chunk;return $size;
        });
        $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($ok!==true||$status!==200||strlen($bytes)>$maxBytes)throw new PPError(503,'RSS no disponible, redirigido o excesivo.');
        return $bytes;
    }
    if(!filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN))throw new PPError(503,'Se necesita PHP cURL o allow_url_fopen para leer RSS.');
    $ctx=stream_context_create(['http'=>['timeout'=>12,'follow_location'=>0,'max_redirects'=>0,'ignore_errors'=>true,
        'header'=>"Accept: application/rss+xml, application/xml\r\nUser-Agent: PsicologiaPoliticaNewsAudit/1.0\r\n"],
        'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $bytes=@file_get_contents($url,false,$ctx,0,2*1024*1024+1);
    $statusLine=$http_response_header[0]??'';
    if(!preg_match('~^HTTP/\S+ 200(?:\s|$)~',$statusLine)||!is_string($bytes)||strlen($bytes)>2*1024*1024)
        throw new PPError(503,'RSS no disponible, redirigido o excesivo.');
    return $bytes;
}
function pp_news_fetch(string $feedId): array {
    $feed=pp_news_feeds()[$feedId]??null;if(!$feed)throw new PPError(404,'Fuente editorial no autorizada.');
    $url=$feed['url'];$host=parse_url($url,PHP_URL_HOST);if($host!==$feed['host']||!in_array($host,['ec.europa.eu'],true))throw new PPError(403,'Dominio de fuente no permitido.');
    return pp_news_parse_feed(pp_news_download($url),$feedId);
}
function pp_news_ingest_api(): never {
    $u=pp_require_role(['superadmin','admin_editor']);$p=pp_body();pp_rate('news-ingest',$u['email'],8,3600);
    $mode=$p['mode']??'preview';if(!in_array($mode,['preview','stage'],true))throw new PPError(400,'Operación no admitida.');
    $feedId=(string)($p['feed_id']??'');if(!preg_match('/^[a-z_]{4,100}$/D',$feedId))throw new PPError(400,'Feed inválido.');
    $items=pp_news_fetch($feedId);$candidates=[];$duplicates=0;$known=pp_news_known_index();
    foreach($items as $item){if(pp_news_is_known($item,$known)){$duplicates++;continue;}$candidates[]=$item;pp_news_add_known($item,$known);}
    $hash=hash('sha256',pp_json(array_column($candidates,'url_sha256')));
    if($mode==='preview')pp_reply(['feed_id'=>$feedId,'candidate_count'=>count($candidates),'existing_articles'=>$duplicates,'confirmation_hash'=>$hash,'candidates'=>$candidates,'published'=>0]);
    if(!hash_equals($hash,(string)($p['confirm_hash']??'')))throw new PPError(409,'Vuelve a previsualizar: la fuente cambió.');
    if(!pp_one("SHOW TABLES LIKE 'hp_news_candidates'"))throw new PPError(503,'Aplica antes la migración de la cola editorial.');
    // Serialize with cron and re-read identities inside the lock: the preview is not a DB lock.
    if((int)(pp_one("SELECT GET_LOCK('pp_news_ingest_v12',5) AS acquired")['acquired']??0)!==1)
        throw new PPError(423,'Otra ingesta editorial está en curso.');
    $inserted=0;$alreadyKnown=0;
    try {
        $current=pp_news_known_index();
        foreach($candidates as $c){
            if(pp_news_is_known($c,$current)){$alreadyKnown++;continue;}
            $result=pp_query('INSERT IGNORE INTO hp_news_candidates(url_sha256,feed_id,title,source_url,short_summary,published_at,matched_terms,image_brief,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',[$c['url_sha256'],$c['feed_id'],$c['title'],$c['source_url'],$c['short_summary'],$c['published_at'],pp_json($c['matched_terms']),$c['image_brief'],'review',pp_now()]);
            if($result->rowCount()===1){$inserted++;pp_news_add_known($c,$current);}else $alreadyKnown++;
        }
    } finally {pp_query("SELECT RELEASE_LOCK('pp_news_ingest_v12')");}
    pp_reply(['ok'=>true,'feed_id'=>$feedId,'new_candidates'=>$inserted,'concurrent_duplicates_avoided'=>$alreadyKnown,'published'=>0,'status'=>'review','note'=>'Solo cola de revisión; ninguna noticia se publica automáticamente.']);
}

/** CLI cron: discover and deduplicate official items; NEVER create published posts.
 *  Can be scheduled in Hostinger only after staging proves RSS + DB availability. */
function pp_news_cron_run(): array {
    if(PHP_SAPI!=='cli')throw new PPError(403,'La ingesta programada solo se ejecuta desde CLI.');
    if(!pp_one("SHOW TABLES LIKE 'hp_news_candidates'"))throw new PPError(503,'Falta la migración de la cola de noticias.');
    $lock=pp_one("SELECT GET_LOCK('pp_news_ingest_v12',1) AS acquired");
    if((int)($lock['acquired']??0)!==1)throw new PPError(423,'Otra ingesta editorial sigue activa.');
    try{
        $summary=['mode'=>'review_queue_only','published'=>0,'feeds'=>[],'total_new'=>0,'errors'=>[]];
        $known=pp_news_known_index();
        foreach(pp_news_feeds() as $id=>$feed){
            try{
                $items=pp_news_fetch($id);$new=0;$duplicate=0;
                foreach($items as $c){
                    if(pp_news_is_known($c,$known)){$duplicate++;continue;}
                    pp_news_add_known($c,$known);
                    $stmt=pp_query('INSERT IGNORE INTO hp_news_candidates(url_sha256,feed_id,title,source_url,short_summary,published_at,matched_terms,image_brief,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',
                        [$c['url_sha256'],$c['feed_id'],$c['title'],$c['source_url'],$c['short_summary'],$c['published_at'],pp_json($c['matched_terms']),$c['image_brief'],'review',pp_now()]);
                    if($stmt->rowCount()===1)$new++;else $duplicate++;
                }
                $summary['feeds'][$id]=['seen'=>count($items),'new'=>$new,'duplicates'=>$duplicate];
                $summary['total_new']+=$new;
            }catch(Throwable $e){
                // No automatic retry storm; next scheduled execution may try again.
                $summary['errors'][$id]=$e instanceof PPError?$e->getMessage():'Fuente no disponible o respuesta inesperada.';
            }
        }
        return $summary;
    }finally{pp_query("SELECT RELEASE_LOCK('pp_news_ingest_v12')");}
}
