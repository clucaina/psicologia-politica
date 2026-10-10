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
    $host=strtolower((string)parse_url($canonical,PHP_URL_HOST));
    $path=rawurldecode((string)parse_url($canonical,PHP_URL_PATH));
    // DOI resolver links and publisher article URLs can identify the SAME paper.
    // Nature's Humanities and Social Sciences Communications uses 10.1057,
    // whereas the other Nature article families here use 10.1038.
    if(in_array($host,['doi.org','dx.doi.org'],true)&&
       preg_match('~^/(10\.[0-9]{4,9}/[A-Za-z0-9._;()/:-]+?)/?$~iD',$path,$m))
        return 'doi:'.strtolower($m[1]);
    if(in_array($host,['nature.com','www.nature.com'],true)&&
       preg_match('~^/articles/(s[0-9]{5}-[0-9]{3}-[0-9]{5}-[a-z0-9]+)(?:/)?$~iD',$path,$m))
        return 'doi:'.(str_starts_with(strtolower($m[1]),'s41599-')?'10.1057/':'10.1038/').strtolower($m[1]);
    // Frontiers uses /journals/<journal>/articles/<doi>/full, /pdf, /xml, /abstract or /epub.
    // The view mode is not part of the DOI; without this normalization the same
    // study can enter the editorial queue twice through a publisher and a resolver.
    if(in_array($host,['frontiersin.org','www.frontiersin.org'],true)&&
       preg_match('~^/(?:journals/[a-z0-9-]+/)?articles/(10\.3389/[a-z0-9._-]+)(?:/(?:full|pdf|xml|abstract|epub))?/?$~iD',$path,$m))
        return 'doi:'.strtolower($m[1]);
    // Oxford Academic appends an internal article ID AFTER the DOI. It is not part
    // of the DOI: /doi/10.1093/joc/jqag014/8827379 == doi.org/10.1093/joc/jqag014.
    if($host==='academic.oup.com'&&
       preg_match('~/(?:doi/)(10\.1093/[a-z0-9-]+/[a-z0-9.-]+)(?:/|$)~i',$path,$m))
        return 'doi:'.strtolower($m[1]);
    // De Gruyter's /html and /pdf suffixes describe the document format, not the DOI.
    if(in_array($host,['degruyterbrill.com','www.degruyterbrill.com'],true)&&
       preg_match('~^/document/doi/(10\.[0-9]{4,9}/[a-z0-9._;-]+)/(?:html|pdf|xml)/?$~iD',$path,$m))
        return 'doi:'.strtolower($m[1]);
    // Wiley may add a view-mode segment before its DOI; it is not part of the identifier.
    if(in_array($host,['onlinelibrary.wiley.com','www.onlinelibrary.wiley.com'],true)&&
       preg_match('~^/doi/(?:abs/|full/|pdf/)?(10\.[0-9]{4,9}/[a-z0-9._;-]+)(?:/|$)~i',$path,$m))
        return 'doi:'.strtolower($m[1]);
    // SAGE and Taylor & Francis expose multiple URL modes for the same DOI.
    // Strip only documented *leading* view-mode segments. Unknown trailing
    // segments are not silently accepted as DOI suffixes.
    if(in_array($host,['journals.sagepub.com','www.journals.sagepub.com','tandfonline.com','www.tandfonline.com'],true)&&
       preg_match('~^/doi/(?:abs/|full/|pdf/)?(10\.[0-9]{4,9}/[a-z0-9._;()-]+)(?:/)?$~iD',$path,$m))
        return 'doi:'.strtolower($m[1]);
    // Some publishers embed an explicit DOI in their path. Avoid guessing a DOI
    // from a generic slug or from the title of a different study.
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
/** Keyset pagination: never silently truncate the deduplication universe. The fetcher
 * can be replaced by a deterministic fixture in semantic tests without a database. */
function pp_news_known_pages(callable $fetchPage,int $maxRows=100000): array {
    $index=['source'=>[],'title'=>[]];$scanned=0;
    foreach(['posts','queue'] as $kind){
        $cursor=$kind==='posts'?'0':'';
        while(true){
            $rows=$fetchPage($kind,$cursor,500);
            if(!is_array($rows)||count($rows)>500)throw new PPError(503,'Página editorial inválida: ingesta suspendida.');
            if(!$rows)break;
            foreach($rows as $row){
                $id=(string)($row['_cursor']??'');
                if($id===''||($kind==='posts'&&!ctype_digit($id))||($kind==='queue'&&!preg_match('/^[a-f0-9]{64}$/D',$id))||
                    ($kind==='queue'&&strcmp($id,$cursor)<=0)||
                    ($kind==='posts'&&pp_news_compare_decimal($id,$cursor)<=0))
                    throw new PPError(503,'Orden de índice editorial inválido: ingesta suspendida.');
                $cursor=$id;
                $identity=pp_news_identity((string)($row['source_url']??''));
                if($identity!=='')$index['source'][$identity]=true;
                $title=pp_news_title_key((string)($row['title']??''));
                if($title!=='')$index['title'][$title]=true;
                if(++$scanned>$maxRows)throw new PPError(503,'Índice editorial supera el límite seguro: se necesita deduplicación persistente.');
            }
            if(count($rows)<500)break;
        }
    }
    return $index;
}
/** Compare nonnegative decimal primary keys without requiring the optional BCMath extension. */
function pp_news_compare_decimal(string $a,string $b): int {
    $a=ltrim($a,'0');$b=ltrim($b,'0');$a=$a===''?'0':$a;$b=$b===''?'0':$b;
    return strlen($a)<=>strlen($b) ?: strcmp($a,$b);
}
/** Prepared editorial studies also count as known. A DOI in an offline evidence
 * graph must not reappear as a fresh "discovery" just because it is not in MariaDB.
 * This index contains identities only, never private draft text or provisional NPL values.
 */
/** Deploy only DOI/URL identities and SHA-256 title keys, never private
 * candidate texts. This compact index preserves offline deduplication when
 * staging deliberately excludes database proposals and editorial queues. */
function pp_news_compact_index_merge(array $index,array $payload): array {
    if(!is_string($payload['version']??null)||!preg_match('/^V(?:[4-9][0-9]|[1-9][0-9]{2,})$/D',$payload['version']))return $index;
    $sources=$payload['source_keys']??[];$titles=$payload['title_hashes']??[];
    if(!is_array($sources)||!is_array($titles)||count($sources)>10000||count($titles)>10000)return $index;
    foreach($sources as $key){
        if(!is_string($key)||strlen($key)>2100)continue;
        if(preg_match('~^doi:10\.[0-9]{4,9}/[a-z0-9._;()/:+\-]+$~iD',$key)||
           (str_starts_with($key,'url:https://')&&pp_news_normalize_url(substr($key,4))!==''))
            $index['source'][$key]=true;
    }
    foreach($titles as $hash){
        if(is_string($hash)&&preg_match('/^[a-f0-9]{64}$/D',$hash))$index['title']['sha256:'.$hash]=true;
    }
    return $index;
}
/** Ingestion must fail closed on a truncated/corrupted compact dedupe index.
 * Silently ignoring a damaged file can create duplicate scientific articles. */
function pp_news_compact_index_assert_valid(mixed $payload,string $version): void {
    if(!is_array($payload)||($payload['version']??null)!==$version||
       ($payload['publication_authorized']??null)!==false||
       !is_array($payload['source_keys']??null)||!is_array($payload['title_hashes']??null))
        throw new PPError(503,'Índice editorial de deduplicación inválido: ingesta suspendida.');
    $sources=$payload['source_keys'];$titles=$payload['title_hashes'];
    if(count($sources)>10000||count($titles)>10000||
       ($payload['source_count']??null)!==count($sources)||
       ($payload['title_count']??null)!==count($titles)||
       count(array_unique($sources))!==count($sources)||
       count(array_unique($titles))!==count($titles))
        throw new PPError(503,'Índice editorial incompleto o duplicado: ingesta suspendida.');
    foreach($sources as $key){
        if(!is_string($key)||strlen($key)>2100||
           !(preg_match('~^doi:10\.[0-9]{4,9}/[a-z0-9._;()/:+\-]+$~iD',$key)||
             (str_starts_with($key,'url:https://')&&
              pp_news_normalize_url(substr($key,4))===substr($key,4))))
            throw new PPError(503,'Identidad de fuente editorial inválida: ingesta suspendida.');
    }
    foreach($titles as $hash)if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash))
        throw new PPError(503,'Huella de titular editorial inválida: ingesta suspendida.');
}
function pp_news_prepared_index(bool $includeCompact=true): array {
    $index=['source'=>[],'title'=>[]];
    $files=[...(glob(__DIR__.'/../database/T*.json')?:[]),...(glob(__DIR__.'/../database/seeds/T*.json')?:[]),...(glob(__DIR__.'/../database/proposals/T*_CANDIDATAS*.json')?:[]),...(glob(__DIR__.'/../editorial/review_queue/T*_CANDIDATAS*.json')?:[])];
    foreach($files as $file){
        $data=pp_parse(file_get_contents($file));
        if(!is_array($data))continue;
        foreach(['candidates','items','records','articles','editorial_candidates'] as $section){
            foreach(($data[$section]??[]) as $item){
                if(!is_array($item))continue;
                $url=(string)($item['primary_source_url']??$item['source_url']??'');
                $doi=trim((string)($item['doi']??''));
                if($doi!==''&&preg_match('~^10\.[0-9]{4,9}/[a-z0-9._;()/:+-]+$~iD',$doi))
                    $index['source']['doi:'.strtolower($doi)]=true;
                $identity=pp_news_identity($url);
                if($identity!=='')$index['source'][$identity]=true;
                $title=pp_news_title_key((string)($item['title_es']??$item['title']??''));
                if($title!=='')$index['title'][$title]=true;
            }
        }
    }
    if($includeCompact){
        // The newest compact manifest is the staging-only identity source of truth.
        // Fall back only for older installations, never combine stale versions.
        $compactFiles=glob(__DIR__.'/../database/news_prepared_identity_index_v*.json')?:[];
        $versions=[];
        foreach($compactFiles as $file){
            if(preg_match('/_v([0-9]+)\.json$/D',$file,$m) && (int)$m[1]>=47)
                $versions[(int)$m[1]]=$file;
        }
        if(!$versions)throw new PPError(503,'Falta el índice editorial de deduplicación: ingesta suspendida.');
        if($versions){
            krsort($versions,SORT_NUMERIC);
            $version=(int)array_key_first($versions);
            $payload=pp_parse(file_get_contents($versions[$version]),null);
            // A corrupt newest manifest is a hard error. Falling back to an
            // older index would silently re-admit already prepared stories.
            pp_news_compact_index_assert_valid($payload,'V'.$version);
            $index=pp_news_compact_index_merge($index,$payload);
        }
    }
    return $index;
}
function pp_news_known_index(bool $includeQueue=true): array {
    $queueExists=$includeQueue&&pp_one("SHOW TABLES LIKE 'hp_news_candidates'");
    $db=pp_news_known_pages(static function(string $kind,string $cursor,int $limit)use($queueExists): array {
        if($kind==='posts')return pp_all('SELECT id AS _cursor,source_url,title FROM editorial_posts WHERE id>? ORDER BY id ASC LIMIT '.$limit,[$cursor]);
        if(!$queueExists)return [];
        return pp_all('SELECT url_sha256 AS _cursor,source_url,title FROM hp_news_candidates WHERE url_sha256>? ORDER BY url_sha256 ASC LIMIT '.$limit,[$cursor]);
    });
    $prepared=pp_news_prepared_index();
    return ['source'=>$db['source']+$prepared['source'],'title'=>$db['title']+$prepared['title']];
}
function pp_news_is_known(array $candidate,array $index): bool {
    $identity=pp_news_identity((string)($candidate['source_url']??''));
    $title=pp_news_title_key((string)($candidate['title']??''));
    return ($identity!==''&&isset($index['source'][$identity]))||($title!==''&&(isset($index['title'][$title])||isset($index['title']['sha256:'.hash('sha256',$title)])));
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
/** Eurostat catalogue RSS is a data-product change log, NOT a research/news feed.
 * Deleted datasets and code-list/structure-only updates must never be proposed as
 * new empirical findings. Missing categories (e.g. Atom fixtures) stay review-only.
 */
function pp_news_eurostat_event_allowed(string $category): bool {
    if(trim($category)==='')return true; // Unknown metadata: human review required.
    return in_array(strtoupper(trim($category)),['NEW_DATASET','UPDATED_DATASET_DATA','UPDATED_DATASET_STRUCTURE_DATA'],true);
}
/** Refuse XML DTD/entity declarations even with LIBXML_NONET: internal entities
 * can expand in memory, and feeds do not require custom document types. */
function pp_news_feed_preflight(string $xml): void {
    if(strlen($xml)>2*1024*1024||preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i',$xml))
        throw new PPError(422,'Feed XML demasiado grande o con entidades no permitidas.');
}
/** A feed timestamp is not a verified scientific publication date. Never
 * silently turn a future or malformed value into an actual publication date. */
function pp_news_safe_published_at(string $raw): ?string {
    $raw=trim($raw);
    if($raw===''||strlen($raw)>90)return null;
    // DateTimeImmutable accepts offsets such as +24:00/+2400, which do not
    // describe real civil time zones. RSS/Atom metadata must not normalize them.
    if(preg_match('/([+-])(\d{2}):?(\d{2})$/D',$raw,$offset)){
        $hours=(int)$offset[2];$minutes=(int)$offset[3];
        if($hours>14||$minutes>59||($hours===14&&$minutes!==0))return null;
    }
    // RSS RFC 2822 and Atom ISO 8601 only. strtotime() normalizes impossible dates
    // such as 2026-02-30 into March, inventing a publication date.
    if(preg_match('/^(?:19|20)\d{2}-(\d{2})-(\d{2})T/i',$raw)){
        if(!preg_match('/^((?:19|20)\d{2})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.\d{1,9})?(?:Z|[+-]\d{2}:\d{2})$/iD',$raw,$m))return null;
        if(!checkdate((int)$m[2],(int)$m[3],(int)$m[1]) || (int)$m[4]>23 || (int)$m[5]>59 || (int)$m[6]>59)return null;
        try{$date=new DateTimeImmutable($raw);}catch(Throwable){return null;}
        if($date->format('Y-m-d')!==$m[1].'-'.$m[2].'-'.$m[3])return null;
    }else{
        // Strict English RFC 2822 weekday/date/month and explicit timezone.
        if(!preg_match('/^(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun),?\s+(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+((?:19|20)\d{2})\s+(\d{2}):(\d{2}):(\d{2})\s+(?:GMT|UT|UTC|[+-]\d{4})$/iD',$raw,$m))return null;
        $months=['jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12];
        if(!checkdate($months[strtolower($m[2])],(int)$m[1],(int)$m[3]) || (int)$m[4]>23 || (int)$m[5]>59 || (int)$m[6]>59)return null;
        try{$date=new DateTimeImmutable($raw);}catch(Throwable){return null;}
        // Reject weekday mismatches rather than silently trusting a normalized date.
        if((int)$date->format('j')!==(int)$m[1]||strcasecmp($date->format('M'),$m[2])!==0 || strtolower(substr($raw,0,3))!==strtolower($date->format('D')))return null;
    }
    $timestamp=$date->getTimestamp();
    if($timestamp>time()+86400||$timestamp<946684800)return null;
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP');
}
/** Supports RSS 2.0 and Atom 1.0, never follows arbitrary item links or publishes content. */
function pp_news_parse_feed(string $xml,string $feedId): array {
    pp_news_feed_preflight($xml);
    if(!extension_loaded('simplexml'))throw new PPError(503,'Falta la extensión PHP SimpleXML.');
    $prior=libxml_use_internal_errors(true);
    $feed=simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA);
    libxml_clear_errors();libxml_use_internal_errors($prior);
    if($feed===false)throw new PPError(422,'El feed XML no es válido.');
    $entries=[];
    if($feed->getName()==='rss'&&isset($feed->channel->item)){
        foreach($feed->channel->item as $item){
            $entries[]=['url'=>(string)$item->link,'title'=>(string)$item->title,
                'description'=>(string)$item->description,'date'=>(string)$item->pubDate,
                'category'=>(string)$item->category];
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
            $category='';
            foreach($fields->category as $categoryNode){
                $categoryAttrs=$categoryNode->attributes();
                $category=(string)($categoryAttrs['term']??$categoryNode);break;
            }
            $entries[]=['url'=>$url,'title'=>(string)$fields->title,
                'description'=>(string)($fields->summary?:$fields->content),
                'date'=>(string)($fields->published?:$fields->updated),
                'category'=>$category];
        }
    }else throw new PPError(422,'Solo se admiten feeds RSS 2.0 o Atom 1.0.');
    $out=[];$known=[];
    foreach($entries as $item){
        $url=pp_news_normalize_url(trim($item['url']));
        $title=trim(strip_tags($item['title']));
        $description=trim(strip_tags($item['description']));
        if(!$url||!in_array(parse_url($url,PHP_URL_HOST),['ec.europa.eu','commission.europa.eu','eurostat.ec.europa.eu'],true)||mb_strlen($title)<15)continue;
        $isCatalogue=$feedId==='eurostat_updates_en';
        if($isCatalogue&&!pp_news_eurostat_event_allowed((string)($item['category']??'')))continue;
        $topics=pp_news_topics($title.' '.$description);if(!$topics)continue;
        if($isCatalogue)$topics[]='npl_dataset_update_not_news';
        $identity=pp_news_identity($url);if(isset($known[$identity]))continue;$known[$identity]=true;
        $publishedAt=pp_news_safe_published_at((string)$item['date']);
        $brief=$isCatalogue
            ?'ALERTA DE DATOS, NO NOTICIA: cotejar tabla, geografía, versión y fecha en Eurostat antes de redactar o ilustrar. Si procede, usar gráfico original con cifras verificadas; no copiar figuras.'
            :'Ilustración editorial original y sobria sobre '.implode(', ',$topics).'; sin logotipos ni rostros reales, sin titulares incrustados, apta para prensa académica.';
        $out[]=['url_sha256'=>hash('sha256',$url),'feed_id'=>$feedId,'title'=>mb_substr($title,0,400),
            'source_url'=>$url,'short_summary'=>mb_substr($description,0,750),
            'published_at'=>$publishedAt,'matched_terms'=>$topics,
            'image_brief'=>$brief,'status'=>'review'];
        if(count($out)>=25)break;
    }
    return $out;
}
/** Validate the actual download URL, not just the configured feed ID.
 * The same downloader serves the official Eurostat/Commission feeds and the
 * manual Crossref metadata preview; no other remote host is authorized. */
function pp_news_download_allowed(string $url): bool {
    $p=parse_url($url);
    return is_array($p)&&strtolower((string)($p['scheme']??''))==='https'
        &&in_array(strtolower((string)($p['host']??'')),['ec.europa.eu','api.crossref.org'],true)
        &&!isset($p['user'])&&!isset($p['pass'])&&!isset($p['fragment'])
        &&(!isset($p['port'])||(int)$p['port']===443);
}
/** HTTPS-only fixed allowlist, no redirects, bounded download and TLS verification. */
function pp_news_download(string $url,string $accept='application/rss+xml, application/xml'): string {
    if(!pp_news_download_allowed($url))throw new PPError(403,'Fuente remota no autorizada.');
    if(function_exists('curl_init')){
        $ch=curl_init($url);$bytes='';$maxBytes=2*1024*1024;
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'PsicologiaPoliticaNewsAudit/1.0',
            CURLOPT_HTTPHEADER=>['Accept: '.$accept],
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
        'header'=>"Accept: ".$accept."\r\nUser-Agent: PsicologiaPoliticaNewsAudit/1.0\r\n"],
        'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $bytes=@file_get_contents($url,false,$ctx,0,2*1024*1024+1);
    $statusLine=$http_response_header[0]??'';
    if(!preg_match('~^HTTP/\S+ 200(?:\s|$)~',$statusLine)||!is_string($bytes)||strlen($bytes)>2*1024*1024)
        throw new PPError(503,'RSS no disponible, redirigido o excesivo.');
    return $bytes;
}
function pp_news_fetch(string $feedId): array {
    $feed=pp_news_feeds()[$feedId]??null;if(!$feed)throw new PPError(404,'Fuente editorial no autorizada.');
    $url=$feed['url'];$host=parse_url($url,PHP_URL_HOST);if(!pp_news_download_allowed($url)||$host!==$feed['host']||$host!=='ec.europa.eu')throw new PPError(403,'Dominio de fuente no permitido.');
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
