<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/text_analysis_v2.php';
function check_v76_codes(string $s): array {return array_column(pp_text_verification_tasks($s,['hecho_verificable']),'code');}
$a=check_v76_codes('El transporte público subió un 3,3% en agosto de 2026.');
if(!in_array('transport_journeys_vs_unique_people',$a,true))throw new RuntimeException('Missing journey units qualification');
$b=check_v76_codes('El índice de producción industrial registró una variación anual del 1,5 %.');
if(!in_array('industrial_index_series_compatibility',$b,true))throw new RuntimeException('Missing IPI adjustment qualification');
$c=check_v76_codes('La comida estuvo muy buena en el restaurante.');
if(in_array('transport_journeys_vs_unique_people',$c,true)||in_array('industrial_index_series_compatibility',$c,true))throw new RuntimeException('Text V2 false positives');
echo 'PASS V76 text: journeys vs people, adjusted IPI, no unrelated labels'.PHP_EOL;
