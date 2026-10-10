<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/news_ingest.php';
$d=json_decode(file_get_contents(__DIR__.'/../database/news_prepared_identity_index_v76.json'),true,64,JSON_THROW_ON_ERROR);
pp_news_compact_index_assert_valid($d,'V76');
if($d['publication_authorized']!==false||$d['source_count']!==185||$d['title_count']!==153)throw new RuntimeException('Editorial index unreviewed identity count or status');
$index=pp_news_compact_index_merge(['source'=>[],'title'=>[]],$d);
if(!pp_news_is_known(['source_url'=>'https://www.ine.es/dyngs/Prensa/TV0826.htm','title'=>'La encuesta de transportes de agosto'], $index))throw new RuntimeException('INE Oct source not deduplicated');
if(pp_news_is_known(['source_url'=>'https://example.org/new-independent-source','title'=>'Investigación independiente sin duplicado'], $index))throw new RuntimeException('Unrelated research incorrectly blocked');
echo 'PASS V76 editorial index: 185 sources,153 titles, no autopublish, no unrelated false positive'.PHP_EOL;
