<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/news_ingest.php';
$payload=pp_parse(file_get_contents(__DIR__.'/../database/news_prepared_identity_index_v71.json'));
pp_news_compact_index_assert_valid($payload,'V71');
if($payload['source_count']!==182||$payload['title_count']!==150||$payload['publication_authorized']!==false)exit(2);
$index=pp_news_prepared_index();
foreach(['10.1038/s41467-026-72990-9','10.1038/s44360-026-00206-x'] as $doi){
 if(!isset($index['source']['doi:'.$doi]))exit(3);
 if(!pp_news_is_known(['source_url'=>'https://doi.org/'.$doi,'title'=>'Otro título'],$index))exit(4);
}
$cases=pp_parse(file_get_contents(__DIR__.'/../preintegration/T111_CANDIDATAS_EDITORIALES_20261010.json'));
if(count($cases['candidates'])!==2||$cases['numeric_npl_observations']!==0)exit(5);
foreach($cases['candidates'] as $c){
 if(($c['publication_authorized']??null)!==false||($c['human_editor_approval']??null)!==false)exit(6);
 foreach(['methodology','limitations','evidence','image_brief','image_alt','primary_source_url'] as $field)
  if(!is_string($c[$field]??null)||strlen($c[$field])<25)exit(7);
}
echo "V71 EDITORIAL PASS: newest compact 182 DOI/URL sources, 150 titles; 2 review candidates; no CMS publication.\n";
