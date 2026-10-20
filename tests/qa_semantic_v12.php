<?php
declare(strict_types=1);
if(!function_exists('mb_strtolower')){function mb_strtolower(string $s): string{return strtolower($s);}}
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/npl.php';
require __DIR__.'/../_private/npl_analytics.php';
require __DIR__.'/../_private/news_ingest.php';
require __DIR__.'/../_private/pages.php';
function v12_assert(bool $ok,string $reason): void {if(!$ok)throw new RuntimeException($reason);}
$cases=[
 ['¿Cuál es la tasa de pobreza infantil en Andalucía en 2025?','unsupported_population'],
 ['¿Cuál es la tasa de paro juvenil en Madrid en 2025?','unsupported_population'],
 ['¿Cuál es la tasa de paro de mujeres en Madrid en 2025?','unsupported_subgroup'],
 ['Compara la tasa AROPE de Andalucía en 2025','comparison_requires_two_territories'],
 ['¿Cuál es el abandono escolar temprano de jóvenes de Andalucía en 2025?','ok'],
 ['¿Cuál es el salario medio de mujeres en Madrid en 2024?','ok'],
 ['Compara la tasa AROPE de Andalucía frente a España en 2025','ok']
];
foreach($cases as [$question,$expected]){
 $actual=pp_analytics_ask_intent($question)['status'];
 v12_assert($actual===$expected,"Wrong question guard: $question => $actual instead of $expected");
}
v12_assert(pp_news_title_key('  Polarización: ¿qué cambia?  ')===pp_news_title_key('POLARIZACION - QUE CAMBIA'),'Title normalization failed');
v12_assert(pp_news_identity('https://doi.org/10.1111/example')===pp_news_identity('https://journal.example.org/doi/10.1111/example?utm_source=x'),'DOI identity mismatch');
v12_assert(str_contains(pp_fallback_tool('/contacto'),'documentación confidencial'),'Contact fallback must be specific and privacy-aware');
$code=file_get_contents(__DIR__.'/../_private/news_ingest.php');
v12_assert(substr_count($code,"GET_LOCK('pp_news_ingest_v12'")===2,'Cron and manual staging must share a lock');
v12_assert(str_contains($code,'$current=pp_news_known_index()'),'Manual staging must re-read identity index inside lock');
if(extension_loaded('simplexml')){
 $rss='<rss version="2.0"><channel><item><title>Political disinformation and democracy</title><link>https://ec.europa.eu/news/democracy</link><description>Media literacy and political communication</description><pubDate>Thu, 08 Oct 2026 08:00:00 GMT</pubDate></item></channel></rss>';
 $atom='<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Political disinformation and democracy</title><link href="https://ec.europa.eu/news/democracy" rel="alternate"/><summary>Media literacy and political communication</summary><updated>2026-10-08T08:00:00Z</updated></entry></feed>';
 $r=pp_news_parse_feed($rss,'test_feed');$a=pp_news_parse_feed($atom,'test_feed');
 v12_assert(count($r)===1&&count($a)===1&&$r[0]['source_url']===$a[0]['source_url'],'RSS/Atom parity failed');
 echo "V12 RSS and Atom parser tests passed.\n";
}else echo "V12 RSS/Atom runtime test skipped: SimpleXML unavailable locally; CI requires extension.\n";
echo "V12 semantic QA PASS: population guard, DOI/title identity, shared ingest lock.\n";
