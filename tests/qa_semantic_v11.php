<?php
declare(strict_types=1);
// CI installs mbstring; the offline container may omit it. ASCII-only title test fallback.
if(!function_exists('mb_strtolower')){function mb_strtolower(string $s): string{return strtolower($s);}}
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/npl.php';
require __DIR__.'/../_private/npl_analytics.php';
require __DIR__.'/../_private/news_ingest.php';
require __DIR__.'/../_private/text_analysis_v2.php';
function v11_check(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
$arope=pp_analytics_ask_intent('¿Cuál es la tasa AROPE de Andalucía, España, en 2025?');
v11_check($arope['status']==='ok'&&$arope['geos']===['ES61'],'Country in geographic context should not force comparison');
$compare=pp_analytics_ask_intent('Compara la tasa AROPE de Andalucía frente a España en 2025');
v11_check($compare['status']==='ok'&&count($compare['geos'])===2,'Explicit country comparison must remain possible');
$causal=pp_analytics_ask_intent('¿La tasa de pobreza causa delincuencia en España en 2025?');
v11_check($causal['status']==='causal_question_not_supported','Causal questions must not return correlational answer');
$doi1=pp_news_identity('https://journals.sagepub.com/doi/10.1177/20531680251413944?utm_campaign=x');
$doi2=pp_news_identity('https://doi.org/10.1177/20531680251413944');
v11_check($doi1===$doi2&&$doi1==='doi:10.1177/20531680251413944','Publisher and DOI resolver should deduplicate');
$known=['source'=>[$doi1=>true],'title'=>[]];
v11_check(pp_news_is_known(['source_url'=>'https://doi.org/10.1177/20531680251413944','title'=>'Different heading'],$known),'DOI duplicate not blocked');
$url1=pp_news_identity('https://ec.europa.eu/a?utm_source=x&b=2');
$url2=pp_news_identity('https://ec.europa.eu/a?b=2');
v11_check($url1===$url2,'Tracking URL deduplication broken');
$quoted=pp_text_analyze('El ministro afirmó: «Todos los rivales mienten». Sin embargo, no hay evidencia para concluirlo.');
v11_check($quoted['truth_status']==='not_assessed'&&$quoted['stored']===false,'Text analysis must not infer truth or store text');
v11_check(isset($quoted['propositions'][0]['evidence_needed']),'Evidence flag absent');
echo "V11 PHP SEMANTIC QA PASS: country-context disambiguation, causal guard, DOI dedup, no truth inference.\n";
