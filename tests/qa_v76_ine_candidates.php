<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/npl_batch.php';
$data=json_decode(file_get_contents(__DIR__.'/../database/T111_INE_IPI_TV_202608.json'),true,64,JSON_THROW_ON_ERROR);
if(count($data['new_indicators'])!==12||count($data['observations'])!==25)throw new RuntimeException('Wrong official batch size');
$contract=pp_batch_validate($data);
if(count($contract['indicators'])!==12||count($contract['keys'])!==25)throw new RuntimeException('Candidate contract rejected');
$seen=[];$regions=[];$negative=[];$statuses=[];
foreach($data['observations'] as $row){
 if(($row['human_source_verification']??null)!==false||($row['publication_policy']??null)!=='review_only'||($row['provisional']??null)!==true)throw new RuntimeException('Unapproved record presented as approved');
 if($row['period']!=='2026-08'||$row['frequency']!=='monthly')throw new RuntimeException('Incompatible frequency');
 $key=implode('|',[$row['indicator_id'],$row['period'],$row['geo_code'],$row['subgroup']]);
 if(isset($seen[$key]))throw new RuntimeException('Duplicate natural key');$seen[$key]=true;
 if($row['indicator_id']==='INE_TV_URBAN_BUS_YOY_PCT'&&$row['geo_code']!=='ES')$regions[$row['geo_code']]=$row['value'];
 if($row['value']<0)$negative[$row['indicator_id'].'|'.$row['geo_code']]=$row['value'];
}
if(count($regions)!==13||$negative['INE_IPI_MOM_ADJUSTED_PCT|ES']!==-0.7||$negative['INE_TV_URBAN_BUS_YOY_PCT|ES30']!==-1.5)throw new RuntimeException('Observed negative rates not preserved');
if(count($data['territorial_disclosure']['bus_urban_annual_change']['suppressed_geo_codes'])!==6)throw new RuntimeException('Missing != zero suppressed geography');
if(count(array_unique(array_column($data['new_indicators'],'indicator_id')))!==12)throw new RuntimeException('Duplicate indicators');
echo 'PASS V76 INE provisional candidates: 25 rows, 12 indicators, 13 CCAA, 6 suppressed'.PHP_EOL;
