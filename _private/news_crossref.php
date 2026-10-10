<?php
declare(strict_types=1);
/** Curated Crossref metadata discovery. Bibliographic metadata is NOT article evidence.
 * Manual CLI preview only: no write to editorial_posts or review queue.
 */
function pp_crossref_registry(): array {
    $data=pp_parse(file_get_contents(__DIR__.'/../database/news_crossref_sources_v23.json'));
    return array_column($data['journal_sources']??[],null,'id');
}
function pp_crossref_date(string $date): DateTimeImmutable {
    if(!preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$date))throw new PPError(400,'Fecha Crossref inválida.');
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('UTC'));
    if(!$dt||$dt->format('Y-m-d')!==$date)throw new PPError(400,'Fecha Crossref inexistente.');
    return $dt;
}
function pp_crossref_query_url(string $source,string $from,string $until): string {
    $registry=pp_crossref_registry();$config=$registry[$source]??null;
    if(!$config||!preg_match('/^[0-9]{4}-[0-9]{3}[0-9X]$/D',(string)$config['issn']))throw new PPError(400,'Revista no autorizada.');
    $start=pp_crossref_date($from);$end=pp_crossref_date($until);
    if($start>$end||$start->diff($end)->days>62||$end>new DateTimeImmutable('today',new DateTimeZone('UTC')))
        throw new PPError(400,'Intervalo Crossref inválido o futuro (máximo 62 días).');
    $params=['filter'=>'from-pub-date:'.$from.',until-pub-date:'.$until.',type:journal-article',
        'rows'=>'50','select'=>'DOI,title,container-title,published,published-online,URL,type'];
    return 'https://api.crossref.org/journals/'.$config['issn'].'/works?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
}
function pp_crossref_published_date(array $item): ?string {
    foreach(['published-online','published'] as $key){
        $parts=$item[$key]['date-parts'][0]??null;
        if(!is_array($parts)||count($parts)<3)continue;
        $y=(int)$parts[0];$m=(int)$parts[1];$d=(int)$parts[2];
        if($y>=2000&&$y<=2099&&checkdate($m,$d,$y))return sprintf('%04d-%02d-%02d',$y,$m,$d);
    }
    return null;
}
function pp_crossref_relevant_title(string $title): bool {
    return (bool)preg_match('/\b(?:politic(?:al|s)?|partisan|polariz(?:ation|ed)|polarisation|misinformation|disinformation|election(?:s)?|electoral|democra\w*|ideolog\w*|vot(?:e|ing|ers)|intergroup|social norms?|persuasion|public opinion|citizen(?:s|ship)?|conspirac\w*|propaganda|political communication)\b/iu',$title);
}
/** Corrections, retractions and editorial notices are not new empirical findings.
 * Their metadata may still be useful for a separate human source-review alert.
 */
function pp_crossref_is_editorial_notice(array $item,string $title): bool {
    if(preg_match('/^(?:correction|author correction|publisher correction|retraction|erratum|corrigendum|expression of concern|editorial notice|addendum|withdrawal)\b/iu',$title))return true;
    // Some publishers label the notice 'Retracted: <original title>' or
    // 'Withdrawn: <original title>'. The article topic may otherwise match
    // the discovery keywords and be mistaken for a fresh empirical result.
    if(preg_match('/^(?:retracted|withdrawn|retracted article)\s*[:—-]\s*\S/iu',$title))return true;
    $subtype=strtolower((string)($item['subtype']??''));
    if(in_array($subtype,['correction','retraction','erratum','editorial','expression-of-concern'],true))return true;
    foreach(['relation','update-to'] as $field){
        $relations=$item[$field]??[];
        if(!is_array($relations))continue;
        foreach(array_keys($relations) as $key)
            if(preg_match('/correction|retraction|erratum|withdrawal|expression.of.concern/i',(string)$key))return true;
    }
    return false;
}
/** No full-text abstract copied, no claims inferred, no results invented. */
function pp_crossref_candidates(array $payload,string $source,array $known=[]): array {
    $registry=pp_crossref_registry();if(!isset($registry[$source]))throw new PPError(400,'Revista no autorizada.');
    if(($payload['status']??null)!=='ok'||!is_array($payload['message']['items']??null)||count($payload['message']['items'])>50)
        throw new PPError(422,'Respuesta Crossref inesperada o excesiva.');
    $out=[];$skipped=['irrelevant'=>0,'invalid'=>0,'duplicate'=>0,'future'=>0,'editorial_notice'=>0];
    foreach($payload['message']['items'] as $item){
        if(!is_array($item)||($item['type']??'')!=='journal-article'){$skipped['invalid']++;continue;}
        $doi=strtolower(trim((string)($item['DOI']??'')));
        $title=trim(strip_tags((string)($item['title'][0]??'')));
        if(pp_crossref_is_editorial_notice($item,$title)){$skipped['editorial_notice']++;continue;}
        $date=pp_crossref_published_date($item);
        if(!preg_match('~^10\.[0-9]{4,9}/[a-z0-9._;()/:+-]{3,180}$~iD',$doi)||mb_strlen($title)<18||!$date){$skipped['invalid']++;continue;}
        if($date>gmdate('Y-m-d')){$skipped['future']++;continue;}
        if(!pp_crossref_relevant_title($title)){$skipped['irrelevant']++;continue;}
        $url='https://doi.org/'.$doi;$identity=pp_news_identity($url);
        $titleKey=pp_news_title_key($title);
        if(isset($known['source'][$identity])||isset($known['title'][$titleKey])){$skipped['duplicate']++;continue;}
        $known['source'][$identity]=true;$known['title'][$titleKey]=true;
        $out[]=['url_sha256'=>hash('sha256',$url),'feed_id'=>'crossref_'.$source,'title'=>mb_substr($title,0,400),
            'source_url'=>$url,'short_summary'=>'CANDIDATO BIBLIOGRÁFICO. Leer fuente primaria, comprobar método, resultados, límites, fecha y retractaciones antes de redactar.',
            'published_at'=>$date.'T00:00:00+00:00','matched_terms'=>['academic_metadata','primary_source_not_yet_reviewed'],
            'image_brief'=>'Ilustración editorial original a definir después de revisar el estudio; no copiar figuras ni fotografías.',
            'status'=>'review','human_editor_approval'=>false,'evidence_status'=>'metadata_only_unverified'];
    }
    return ['candidates'=>$out,'skipped'=>$skipped,'publication_policy'=>'review_only','published'=>0];
}
/** Crossmark/relations triage for already-known DOI records. This is an ALERT,
 * never a conclusion that a paper has been retracted or a write to publication.
 * It accepts an externally retrieved Crossref /works response with relation and
 * update-to fields intact (the compact discovery `select` response lacks them).
 */
function pp_crossref_normalize_doi(string $doi): ?string {
    $doi=strtolower(trim($doi));
    $doi=preg_replace('~^https?://(?:dx\.)?doi\.org/~i','',$doi);
    if(!preg_match('~^10\.[0-9]{4,9}/[a-z0-9._;()/:+-]{3,180}$~iD',$doi))return null;
    return $doi;
}
function pp_crossref_relation_alerts(array $payload,array $known): array {
    if(($payload['status']??null)!=='ok'||!is_array($payload['message']['items']??null)
       ||count($payload['message']['items'])>100)throw new PPError(422,'Lote Crossref de vigilancia inválido.');
    $alerts=[];$seen=[];
    foreach($payload['message']['items'] as $item){
        if(!is_array($item))continue;
        $self=pp_crossref_normalize_doi((string)($item['DOI']??''));
        if(!$self)continue;
        $relations=[];
        foreach(['relation','update-to'] as $field){
            $list=$item[$field]??[];
            if(!is_array($list))continue;
            foreach($list as $relation=>$entries){
                if(!is_array($entries))continue;
                // update-to is an indexed array; relation is a keyed dictionary.
                $type=is_int($relation)?'update':strtolower((string)$relation);
                foreach($field==='update-to'?[$entries]:[$entries] as $block){
                    if(!is_array($block))continue;
                    $candidates=array_is_list($block)?$block:[$block];
                    foreach($candidates as $entry){
                        if(!is_array($entry))continue;
                        $idType=strtolower((string)($entry['id-type']??$entry['type']??'doi'));
                        $doi=pp_crossref_normalize_doi((string)($entry['id']??$entry['DOI']??''));
                        if(!$doi||!in_array($idType,['doi','update','retraction','correction','expression-of-concern'],true))continue;
                        $subtype=strtolower((string)($entry['type']??''));
                        $relations[]=['doi'=>$doi,'type'=>$type==='update'?$subtype:$type];
                    }
                }
            }
        }
        // Notice points to original; or original points to a later notice.
        foreach($relations as $rel){
            $related=$rel['doi'];$knownSelf=isset($known['source']['doi:'.$self]);
            $knownRelated=isset($known['source']['doi:'.$related]);
            if(!$knownSelf&&!$knownRelated)continue;
            $kind=$rel['type'];
            if(preg_match('/retract|withdraw/i',$kind))$severity='potential_retraction';
            elseif(preg_match('/concern/i',$kind))$severity='potential_expression_of_concern';
            elseif(preg_match('/correct|errat|corrigend|update|addend/i',$kind))$severity='potential_correction';
            else continue; // A generic relation (e.g. references) is not a retraction alert.
            $affected=$knownRelated?$related:$self;
            $key=$affected.'|'.$self.'|'.$related.'|'.$severity;
            if(isset($seen[$key]))continue;$seen[$key]=true;
            $alerts[]=['affected_doi'=>$affected,'crossref_record_doi'=>$self,'related_doi'=>$related,
                'relation_type'=>$kind,'severity'=>$severity,'status'=>'human_source_reassessment_required',
                'action'=>'Verify publisher notice, Crossmark status and DOI relation; hold or correct editorial article only after human approval.',
                'automatic_publication_change'=>false,'evidence_status'=>'bibliographic_metadata_only'];
        }
    }
    return ['alerts'=>$alerts,'checked_records'=>count($payload['message']['items']),
        'mutations'=>0,'limitations'=>'Crossref relation metadata can be missing or delayed. Absence of an alert is not evidence that an article is unretracted.'];
}
