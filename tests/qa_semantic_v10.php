<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/npl.php';
require __DIR__.'/../_private/npl_batch.php';
require __DIR__.'/../_private/npl_analytics.php';
function check(bool $ok,string $msg): void {if(!$ok)throw new RuntimeException($msg);}
$base=['indicator_id'=>'TEST_RATE','period'=>'2025','geo_code'=>'ES61','geography'=>'Andalucía','subgroup'=>'total','value'=>0,'unit'=>'%','source_id'=>'PEW_GAS','source_url'=>'https://example.org/source','dataset_code'=>'test2025','methodology_flag'=>'survey','population'=>'Personas 18+','measurement_type'=>'survey'];
check(pp_batch_semantic_conflicts($base,$base)===[],'Identical observation rejected');
foreach(['unit'=>'‰','methodology_flag'=>'administrative','population'=>'Personas 15+','source_url'=>'https://example.org/different','value'=>1,'geography'=>'Madrid','measurement_type'=>'administrative_record'] as $field=>$replacement){
    $old=$base;$old[$field]=$replacement;
    check(in_array($field,pp_batch_semantic_conflicts($base,$old),true),'Silent collision in '.$field);
}
check(pp_batch_semantic_conflicts($base,[...$base,'population'=>null])===[],'Legacy absent population should not be fabricated');
$compatible=pp_analytics_compatibility(['ES61'=>['value'=>0,'unit'=>'%','source_id'=>'A','dataset_code'=>'A','population'=>'18+','measurement_type'=>'survey','source_url'=>'https://example.org/a','period'=>'2025','reviewed_at'=>'2026-10-08'], 'ES30'=>['value'=>12,'unit'=>'%','source_id'=>'A','dataset_code'=>'A','population'=>'18+','measurement_type'=>'survey','source_url'=>'https://example.org/a','period'=>'2025','reviewed_at'=>'2026-10-08']]);
check($compatible['comparable'],'Comparable zero should not be treated as missing');
$bad=pp_analytics_compatibility(['ES61'=>['value'=>0,'unit'=>'%','source_id'=>'A','population'=>'18+'],'ES30'=>['value'=>12,'unit'=>'%','source_id'=>'A','population'=>'25+']]);
check(!$bad['homogeneous'],'Different denominators/populations must block ranking');
$missing=pp_analytics_compatibility(['ES61'=>['value'=>0,'unit'=>'%','source_id'=>'A'],'ES30'=>null]);
check(!$missing['complete'],'Missing must not be counted as zero');
$intent=pp_analytics_ask_intent('¿Las redes perjudican a la democracia en España en 2026?');
check($intent['status']==='ok'&&$intent['indicator']==='PEW_SOCIAL_DEMOCRACY_BAD_PCT'&&$intent['subgroup']==='Adultos encuestados','Pew question intent failed');
$unsupported=pp_analytics_ask_intent('¿Las redes causan realmente violencia en España en 2026?');
check($unsupported['status']!=='ok','Causal claim must not be inferred');
echo "V10 PHP SEMANTIC QA PASS: metadata collisions blocked, 0 != null, incompatible populations blocked, perception not causal.\n";
