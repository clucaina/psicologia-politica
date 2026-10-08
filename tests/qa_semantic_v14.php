<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/npl.php';
require __DIR__.'/../_private/npl_analytics.php';
require __DIR__.'/../_private/editorial.php';
function qa14(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$cases=[
 ['¿Cuál es la tasa AROPE de Andalucía en 2025?','ok','ecv_arope_pct','total'],
 ['¿Cuál es la tasa AROPE de hogares con hijos en España en 2025?','ok','EUROSTAT_AROPE_PERSONAS_HOGARES_HIJOS_PCT','Personas en hogares con hijos dependientes'],
 ['¿Cuál es la pobreza infantil de España en 2025?','unsupported_or_ambiguous_indicator',null,null],
 ['¿Cuál es la tasa AROPE de menores en España en 2025?','unsupported_population',null,null],
 ['¿Qué causó la pobreza de España en 2025?','causal_question_not_supported',null,null],
];
foreach($cases as [$question,$status,$indicator,$group]){
 $result=pp_analytics_ask_intent($question);
 qa14($result['status']===$status,"Wrong intent status: $question: ".json_encode($result));
 if($indicator!==null)qa14($result['indicator']===$indicator&&$result['subgroup']===$group,"Wrong subgroup: $question");
}
$one=['title'=>'Artículo de ejemplo','excerpt'=>'Resumen','content'=>'Versión A','section'=>'España','status'=>'draft','cover_image_key'=>null];
$two=[...$one,'content'=>'Versión B'];
qa14(pp_author_fingerprint($one)!==pp_author_fingerprint($two),'Same-timestamp content edit undetected');
qa14(pp_author_fingerprint($one)===pp_author_fingerprint([...$one,'updated_at'=>'2026-10-08']),'Fingerprint must reflect content, not timestamps');
qa14(pp_author_fingerprint($one)===pp_author_fingerprint($one),'Fingerprint is nondeterministic');
echo "V14 PHP SEMANTIC PASS: specific population, child-poverty guard, content fingerprint.\n";
