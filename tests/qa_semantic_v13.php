<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/npl.php';
require __DIR__.'/../_private/npl_analytics.php';
function qa13(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
$base=['value'=>0,'unit'=>'%','source_id'=>'INE','period'=>'2025','geography'=>'Andalucía','geo_code'=>'ES61',
 'data'=>json_encode(['source_url'=>'https://www.ine.es/original','dataset_code'=>'ECV2025','methodology_flag'=>'official_final','population'=>'Población SIN revisar','limits'=>'Límites sin revisar','source_locator'=>'Tabla inicial']),
 'evidence'=>json_encode(['population'=>'Población residente revisada','source_url'=>'https://www.ine.es/revisado','measurement_type'=>'survey','locator'=>'Tabla 2, fila Andalucía','citation'=>'ECV 2025 tabla 2','limits'=>'Encuesta y no causalidad','retrieved_at'=>'2026-10-08']),
 'reviewed_at'=>'2026-10-08'];
$p=pp_analytics_point($base);
qa13($p['value']===0.0,'Cero legítimo se pierde');
qa13($p['population']==='Población residente revisada','Se ignora la población revisada');
qa13($p['source_url']==='https://www.ine.es/revisado','Se ignora la URL verificada');
qa13($p['source_locator']==='Tabla 2, fila Andalucía','No llega el localizador');
qa13($p['measurement_type']==='survey'&&$p['citation']==='ECV 2025 tabla 2','No llega el tipo de medida o cita');
qa13($p['limits']==='Encuesta y no causalidad','No llegan los límites revisados');
qa13(pp_analytics_point([...$base,'value'=>null])===null,'Null convertido a cero');
qa13(pp_analytics_point([...$base,'value'=>'no es número'])===null,'Texto convertido a cero');
qa13(pp_analytics_point([...$base,'evidence'=>'{}'])['population']==='Población SIN revisar','Fallback legacy de metadatos roto');
qa13(pp_analytics_compatibility(['ES61'=>$p,'ES30'=>null])['comparable']===false,'Comparación incompleta marcada comparable');
echo "V13 PHP SEMANTIC PASS: reviewed evidence reaches map, 0 != null, incompatible missing values guarded.\n";
