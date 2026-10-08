<?php
declare(strict_types=1);
/** Heuristic, non-factual, non-diagnostic text analysis. No content is persisted. */
function pp_text_propositions(string $text): array {
    $segments=preg_split('/(?:\n{2,}|(?<=[.!?])\s+(?=[\p{Lu}¿¡«“]))/u',trim($text));
    return array_values(array_filter(array_map('trim',$segments),fn($s)=>$s!==''));
}
/** Multiple simultaneous speech acts are possible; avoid forcing an exclusive label. */
function pp_text_types(string $segment): array {
    if(preg_match('/^[«“"].*[»”"]$/us',$segment))return ['cita'];
    $patterns=[
        'prediccion'=>'/\b(mañana|ocurrirá|será|habrá|podría\s+[\p{L}]+|se prevé|predecimos|en el futuro|se espera que)\b/iu',
        'afirmacion_causal'=>'/\b(porque|provoca|causa|causado por|debido a|hace que|produce|consecuencia de)\b/iu',
        'inferencia'=>'/\b(por tanto|por eso|se deduce|en consecuencia|de ello se infiere|significa que)\b/iu',
        'opinion'=>'/\b(creo|opino|considero|me parece|debería|pienso que|a mi juicio)\b/iu',
        'condicional'=>'/\b(si|en caso de que|suponiendo que)\b/iu',
        'cita'=>'/[«“"][^»”"]{1,800}[»”"]/u',
        'hecho_verificable'=>'/\b\d{1,4}(?:[.,]\d+)?\s*(?:%|euros?|años?|personas|casos)(?=\s|[.,;!?]|$)/iu'
    ];
    $types=[];foreach($patterns as $label=>$pattern)if(preg_match($pattern,$segment))$types[]=$label;
    return $types?:['indeterminado'];
}
function pp_text_type(string $segment): string {return pp_text_types($segment)[0];}
/** Avoid attributing a quoted claim or reported speech to the person analysing it. */
function pp_text_signal_context(string $segment,int $offset,int $length,string $type): string {
    if($type==='cita'&&preg_match('/^[«“"].*[»”"]$/us',trim($segment)))return 'quoted_speech';
    preg_match_all('/«[^»]{1,800}»|“[^”]{1,800}”|"[^"]{1,800}"/u',$segment,$quoted,PREG_OFFSET_CAPTURE);
    foreach($quoted[0] as [$text,$start])if($offset>=$start&&$offset+$length<=$start+strlen($text))return 'quoted_speech';
    $prefix=substr($segment,0,$offset); // preg_match offsets are byte positions at UTF-8 boundaries.
    $clause=(string)preg_replace('/^.*[.;:!?\n]/us','',$prefix);
    if(preg_match('/\b(no|sin|ni|nunca|tampoco)\b(?:\s+[\p{L}]+){0,4}\s*$/iu',$clause))return 'negation_nearby';
    if(preg_match('/^\s*(seg[uú]n|dijo|afirm[oó]|declar[oó]|se[ñn]al[oó]|cit[oó])\b/iu',$segment))return 'reported_speech';
    return 'review_context';
}
function pp_text_analyze(string $text): array {
    $rules=pp_parse(file_get_contents(__DIR__.'/text-rules.json'))['rules']??[];$segments=pp_text_propositions($text);
    $results=[];$signalCount=0;
    foreach(array_slice($segments,0,80) as $index=>$segment){
        $types=pp_text_types($segment);$type=$types[0];$signals=[];
        foreach($rules as $rule){
            if(count($signals)>=8)break;$regex='~'.str_replace('~','\\~',$rule['pattern']).'~iu';
            $match=[];$ok=@preg_match($regex,$segment,$match,PREG_OFFSET_CAPTURE);
            if($ok!==1)continue;
            $offset=$match[0][1];$context=pp_text_signal_context($segment,$offset,strlen($match[0][0]),$type);
            $signals[]=['code'=>$rule['code'],'label'=>$rule['label'],'fragment'=>$match[0][0],
                'explanation'=>$rule['explanation'],'alternative'=>$rule['alternative'],'limit'=>$rule['limit'],
                'confidence'=>'heuristic_low','context_flag'=>$context,'requires_human_review'=>true];
        }
        $signalCount+=count($signals);
        $results[]=['index'=>$index,'text'=>$segment,'type'=>$type,'type_tags'=>$types,'truth_status'=>'not_assessed','intent_status'=>'not_assessed','signals'=>$signals,'evidence_needed'=>in_array('hecho_verificable',$types,true)||in_array('afirmacion_causal',$types,true)];
    }
    return ['version'=>'2.0-preliminary','proposition_count'=>count($segments),'analyzed_count'=>count($results),'truncated'=>count($segments)>80,'signals_total'=>$signalCount,'propositions'=>$results,
        'truth_status'=>'not_assessed','intent_status'=>'not_assessed','stored'=>false,
        'limitations'=>['La clasificación es heurística y puede equivocarse.','Una señal lingüística no demuestra falacia, mentira o manipulación intencional.','Las citas no se atribuyen automáticamente al autor.','La verificación factual requiere fuentes externas y revisión humana.']];
}
function pp_text_v2_api(string $path): never {
    if($path==='/api/v1/text/taxonomy'&&$_SERVER['REQUEST_METHOD']==='GET'){
        header('Cache-Control: public,max-age=86400');pp_reply(pp_parse(file_get_contents(__DIR__.'/../database/ANALIZADOR_TAXONOMY_V2.json')));
    }
    if($path==='/api/v1/text/analyze'&&$_SERVER['REQUEST_METHOD']==='POST'){
        pp_rate('text-analysis','',25,300);$p=pp_body();$text=$p['text']??null;
        if(!is_string($text)||mb_strlen($text)<12||mb_strlen($text)>10000)throw new PPError(400,'Introduce entre 12 y 10.000 caracteres.');
        pp_reply(pp_text_analyze($text));
    }
    throw new PPError(405,'Método no permitido.');
}
