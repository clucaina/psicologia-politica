<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/npl.php';
require __DIR__.'/../_private/npl_analytics.php';
require __DIR__.'/../_private/text_analysis_v2.php';
require __DIR__.'/../_private/editorial.php';
function v16check(bool $value,string $message): void {if(!$value)throw new RuntimeException($message);}
$p=['unit'=>'%','source_id'=>'INE','source_url'=>'https://www.ine.es/','population'=>'Personas residentes','measurement_type'=>'survey','period'=>'2025','reviewed_at'=>'2026-10-08'];
v16check(pp_analytics_metadata_complete($p),'Reviewed HTTPS metadata must pass');
$p['source_url']='http://www.ine.es/';v16check(!pp_analytics_metadata_complete($p),'HTTP source is not sufficient for comparative publication');
$p['source_url']='https://user:pass@www.ine.es/';v16check(!pp_analytics_metadata_complete($p),'Embedded URL credentials must fail');
$p['source_url']='https://www.ine.es/';v16check(pp_analytics_metadata_complete($p),'Restored HTTPS must pass');
$s='No todos los resultados son falsos';
v16check(pp_text_signal_context($s,strpos($s,'todos'),strlen('todos'),'hecho_verificable')==='negation_nearby','Local negation should be flagged');
$s='El informe no aporta esa conclusión. Todos los resultados son falsos';
v16check(pp_text_signal_context($s,strpos($s,'Todos'),strlen('Todos'),'hecho_verificable')!=='negation_nearby','Negation across clause must not be attributed to later signal');
$a=['title'=>'Artículo','content'=>'Texto'];$b=$a;$b['content']='Texto modificado';
v16check(pp_author_fingerprint($a)!==pp_author_fingerprint($b),'Blog fingerprint must change with content');
require __DIR__.'/../_private/news_ingest.php';
v16check(pp_news_identity('https://doi.org/10.1111/pops.70138')===pp_news_identity('https://onlinelibrary.wiley.com/doi/10.1111/pops.70138'),'DOI should deduplicate across publisher hosts');
echo "V16 SEMANTIC PASS: HTTPS metadata, local negation, strong hash, DOI identity.\n";
