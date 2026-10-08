<?php
declare(strict_types=1);
// CI container may omit mbstring; production PHP 8.3 must have ext-mbstring.
if(!function_exists('mb_substr')){function mb_substr(string $s,int $start,?int $length=null): string {return substr($s,$start,$length);}}
if(!function_exists('mb_strlen')){function mb_strlen(string $s): int {return strlen($s);}}
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/pages.php';
require __DIR__.'/../_private/npl_batch.php';
require __DIR__.'/../_private/npl.php';
require __DIR__.'/../_private/migrations.php';
require __DIR__.'/../_private/npl_analytics.php';
require __DIR__.'/../_private/news_ingest.php';
require __DIR__.'/../_private/text_analysis_v2.php';
function check(bool $condition,string $message): void {if(!$condition)throw new RuntimeException('QA FAILED: '.$message);echo "PASS $message\n";}
$article="\\*\\*En negrita\\*\\* y **otra** <script>alert(1)</script>\n\n# Encabezado\n\n- uno\n- dos\n\n[INE](https://www.ine.es/?a=1&b=2)";
$markup=pp_plain_content($article);
check(substr_count($markup,'<strong>')===2,'Markdown escapado y normal');
check(!str_contains($markup,'<script>'),'HTML hostil neutralizado');
check(str_contains($markup,'<h2>Encabezado</h2>'),'Encabezado semántico');
check(str_contains($markup,'<ul><li>uno</li><li>dos</li></ul>'),'Listas semánticas');
check(str_contains($markup,'href="https://www.ine.es/?a=1&amp;b=2"'),'Enlaces seguros');
foreach(['/conflictometro','/herramientas/datos-espana','/herramientas/analizar-texto'] as $route){$fallback=pp_fallback_tool($route);check(strlen($fallback)>100&&!str_contains($fallback,'Digital News Report'),'SSR específico '.$route);}
foreach(['T19','T26_VULNERABILIDAD_SEGURIDAD_2025','T27_SALARIOS_CCAA_2024'] as $id){$batch=pp_batch_load($id);$valid=pp_batch_validate($batch['data']);check(count($valid['keys'])===count($batch['data']['observations']),'Dedupe '.$id);check(!empty($valid['indicators']),'Indicadores '.$id);check(!empty($valid['sources']),'Fuentes '.$id);}
$t26=pp_batch_load('T26_VULNERABILIDAD_SEGURIDAD_2025')['data']['observations'];
check(count($t26)===85,'T26 85 observaciones');
$t27=pp_batch_load('T27_SALARIOS_CCAA_2024')['data']['observations'];check(count($t27)===54,'T27 54 salarios oficiales');check(count(array_unique(array_column($t27,'geo_code')))===18,'T27 España + 17 CCAA, sin Ceuta/Melilla');
check(count(array_filter($t26,fn($o)=>str_starts_with($o['indicator_id'],'ecv_')&&$o['geo_code']!==null))===80,'T26 geocódigos completos');
check(count(array_unique(array_map(fn($o)=>$o['geo_code'],$t26)))===20,'T26 19 territorios + España');
check(is_numeric($t26[0]['value']),'Valor numérico válido');
$zero=$t26[0];$zero['value']=0;check(strlen(pp_batch_key($zero))===64,'Cero válido y distinto de ausencia');
$missing=$zero;$missing['value']=null;try{pp_batch_key($missing);throw new RuntimeException('QA FAILED: null no rechazado');}catch(PPError $e){check($e->status===422,'Null rechazado en staging');}
$files=pp_migration_files();check(count($files)===2,'Migraciones seguras detectadas');
check(count($files[0]['statements'])===2,'Dos tablas ledger, sin SQL destructivo');
check(abs(pp_analytics_pearson([1,2,3,4],[2,4,6,8])-1)<1e-10,'Pearson relación lineal perfecta');
check(abs(pp_analytics_pearson([1,2,3],[3,2,1])+1)<1e-10,'Pearson relación inversa');
check(pp_analytics_pearson([1,1,1],[3,2,1])===null,'Serie constante no genera correlación falsa');
check(pp_analytics_ranks([10,10,20])==[1.5,1.5,3.0],'Spearman rangos con empates');
check(count(pp_analytics_geos())===19,'19 códigos NUTS2 en el mapa');
check(pp_news_normalize_url('https://ec.europa.eu/a?utm_source=x&b=2')==='https://ec.europa.eu/a?b=2','Dedupe URLs con tracking');
check(pp_news_normalize_url('http://ec.europa.eu/a')==='','Rechaza enlaces HTTP');
check(in_array('desinformacion',pp_news_topics('Deepfake and misinformation in elections'),true),'Clasificación temática de noticias');
check(pp_news_topics('Unrelated agricultural pricing')===[],'Filtro de noticias irrelevantes');
check(count(pp_migration_files())===2,'Dos migraciones seguras');
$analysis=pp_text_analyze('Creo que todos están equivocados. El porcentaje es 35% y por eso ocurrirá mañana.');
check($analysis['stored']===false&&$analysis['truth_status']==='not_assessed','Analizador no almacena ni decide veracidad');
check($analysis['analyzed_count']===2,'Segmentación de dos proposiciones');
check($analysis['signals_total']>=1,'Señales retóricas detectadas');
check(pp_text_type('«Todos mienten»')==='cita','Cita no atribuida automáticamente');
check(pp_text_type('El 25% de los encuestados respondió.')==='hecho_verificable','Hecho verificable no equivale a verdad');
// V7 regression: the importer must never stage observations owned by a different batch.
$badBatch=pp_batch_load('T26_VULNERABILIDAD_SEGURIDAD_2025')['data'];
$badBatch['observations'][0]['batch_id']='T27_SALARIOS_CCAA_2024';
try{pp_batch_validate($badBatch);throw new RuntimeException('QA FAILED: foreign batch_id not rejected');}
catch(PPError $e){check($e->status===422,'Importador rechaza observación de otra tanda');}
$ask=pp_analytics_ask_intent('¿Cuál es la tasa AROPE de Andalucía en 2025?');
check($ask['status']==='ok'&&$ask['indicator']==='ecv_arope_pct'&&$ask['geos']===['ES61'],'Pregunta España: AROPE Andalucía 2025');
$ask2=pp_analytics_ask_intent('Compara el salario medio de mujeres en Madrid y Cataluña en 2024');
check($ask2['status']==='ok'&&$ask2['indicator']==='EAES_SALARIO_ANUAL_BRUTO'&&$ask2['subgroup']==='Mujeres'&&$ask2['kind']==='comparison','Pregunta España: salario por sexo, dos CCAA');
check(pp_analytics_ask_intent('¿Cuál es la pobreza en Andalucía en 2025?')['status']==='unsupported_or_ambiguous_indicator','No confunde pobreza genérica con AROPE');
check(pp_analytics_ask_intent('¿Cuál es la tasa AROPE en Andalucía?')['status']==='year_required','No inventa el año de la consulta');
check(pp_analytics_ask_intent('¿Cuál es la carencia severa en Ceuta en 2025?')['indicator']==='ecv_carencia_material_social_severa_pct','Indicador de carencia severa exacto');
check(pp_analytics_ask_intent('¿Cuál es la baja intensidad laboral en Galicia en 2025?')['indicator']==='ecv_baja_intensidad_empleo_pct','Indicador de baja intensidad exacto');
check(str_contains(file_get_contents(__DIR__.'/../_private/npl_batch.php'),"FOR UPDATE"),'Rollback con bloqueo de filas');
check(str_contains(file_get_contents(__DIR__.'/../_private/npl.php'),"human_source_verification"),'Revisión exige fecha de comprobación real');
check(str_contains(file_get_contents(__DIR__.'/../_private/api.php'),"pp_author_get()"),'API recupera borradores por ID de forma autenticada');
check(str_contains(file_get_contents(__DIR__.'/../assets/chunk-KECI7H7X.js'),'/api/editor/posts?id='),'Editor recupera deep-links aunque falte artículo en bootstrap');
check(str_contains(file_get_contents(__DIR__.'/../_private/editorial.php'),"author_id IN (?,?) AND kind='blog'"),'Borradores privados filtrados por autor');
check(count(pp_news_feeds())===2,'Dos feeds institucionales en registro editorial');
check(pp_text_signal_context('El portavoz dijo: «Todos mienten»',24,6,'indeterminado')==='quoted_speech','Citas parciales no atribuidas al autor');
check(pp_text_signal_context('No todos están equivocados',3,5,'indeterminado')==='negation_nearby','Negación cercana señalizada');
check(pp_text_signal_context('Según el portavoz, todos están equivocados',27,5,'indeterminado')==='reported_speech','Discurso referido señalizado');
// V8: cross-territory comparability must not turn missing data into zero.
$point=['value'=>0.0,'unit'=>'%','source_id'=>'INE','source_url'=>'https://www.ine.es/','population'=>'Población residente de 16 años o más','measurement_type'=>'survey','period'=>'2025','reviewed_at'=>'2026-10-01T12:00:00+00:00','methodology_flag'=>'ECV2025'];
$c=pp_analytics_compatibility(['ES61'=>$point,'ES52'=>$point]);
check($c['comparable']===true&&$c['available']===2,'Comparación territorial homogénea con cero real');
$c=pp_analytics_compatibility(['ES61'=>$point,'ES52'=>null]);
check($c['comparable']===false&&$c['reason']==='missing_data','Comparación incompleta no sustituye null por cero');
$different=[...$point,'source_id'=>'EUROSTAT'];
$c=pp_analytics_compatibility(['ES61'=>$point,'ES52'=>$different]);
check($c['comparable']===false&&$c['reason']==='incompatible_source_unit_or_methodology','Comparación rechaza fuentes distintas');
$different=[...$point,'methodology_flag'=>'REVISED_SERIES'];
check(pp_analytics_compatibility([$point,$different])['comparable']===false,'Comparación rechaza metodología distinta');
$htaccess=file_get_contents(__DIR__.'/../.htaccess');
check(str_contains($htaccess,'|database|docs|experiments'), 'Directorios internos y tandas no expuestos directamente');
check(str_contains(file_get_contents(__DIR__.'/../_private/editorial.php'),'$d=[...($before??[]),...$p]'),'Editor conserva campos no enviados en guardados parciales');
// V8: derived metrics preserve negative percentage deviations without relaxing raw prevalence validation.
$t28=pp_batch_load('T28_DERIVADOS_SALARIOS_2024')['data'];
check(count($t28['observations'])===36,'T28 36 observaciones derivadas, no oficiales');
check(count(pp_batch_validate($t28)['keys'])===36,'T28 esquema, fuentes y dedupe válidos');
check(in_array('T28_DERIVADOS_SALARIOS_2024',pp_batch_dependents('T27_SALARIOS_CCAA_2024'),true),'Dependencia T28 -> T27 identificada para rollback');
$signed=['value'=>-13.2,'unit'=>'%','measurement_type'=>'derived','signed_percent'=>true,'methodology_flag'=>'NPL_DERIVED_EAES2024_DESCRIPTIVE'];
check(pp_percent_value_valid($signed),'Desviación porcentual derivada negativa permitida');
check(!pp_percent_value_valid([...$signed,'measurement_type'=>'survey']),'Prevalencia negativa prohibida');
check(!pp_percent_value_valid([...$signed,'signed_percent'=>false]),'Porcentaje con signo no autorizado prohibido');
// V8: avoid classifying retrospective years as future predictions.
check(pp_text_type('En 2024 hubo 25 casos.')==='hecho_verificable','Año pasado no implica predicción');
$tags=pp_text_types('Creo que habrá cambios porque la confianza disminuye.');
check(in_array('prediccion',$tags,true)&&in_array('opinion',$tags,true)&&in_array('afirmacion_causal',$tags,true),'Una proposición puede ser opinión, predicción y causalidad');
check(isset(pp_text_analyze('Creo que habrá cambios porque la confianza disminuye.')['propositions'][0]['type_tags']),'Analizador expone etiquetas simultáneas');
// V9: official education/labour territorial data are candidates only, never auto-published.
foreach(['T30_ABANDONO_EDUCATIVO_2022_2025'=>80,'T31_EMPLEO_PARO_25_64_2015_2025'=>108] as $id=>$count){
 $b=pp_batch_load($id);check(count($b['data']['observations'])===$count,'V9 '.$id.' row count');
 check(count(pp_batch_validate($b['data'])['keys'])===$count,'V9 '.$id.' validated natural keys');
}
check(pp_analytics_ask_intent('¿Cuál es el abandono escolar temprano de Murcia en 2025?')['indicator']==='EPA_ABANDONO_TEMPRANO_18_24_PCT','Pregunta España: abandono educativo territorial');
check(pp_analytics_ask_intent('¿Cuál es la tasa de empleo de Madrid en 2025?')['indicator']==='EPA_TASA_EMPLEO_25_64_PCT','Pregunta España: tasa de empleo 25-64');
check(pp_analytics_ask_intent('¿Cuál es la tasa de paro de Canarias en 2020?')['indicator']==='EPA_TASA_PARO_25_64_PCT','Pregunta España: tasa de paro 25-64');
$pending=['source_quality_flag'=>'PENDING_VISUAL_SOURCE_QA_SMALL_SAMPLE'];
try{pp_npl_source_verification_gate($pending,['retrieved_at'=>'2026-10-07']);throw new RuntimeException('QA FAILED: source review bypass');}
catch(PPError $e){check($e->status===400,'V9 source PDF verification gate blocks silent publish');}
pp_npl_source_verification_gate($pending,['retrieved_at'=>'2026-10-07','source_verification_confirmed'=>true]);
check(true,'V9 explicit human PDF verification is accepted');

$tags=pp_text_types('Según el portavoz, «todos mienten», pero creo que podría ocurrir otra vez.');
check(in_array('cita',$tags,true)&&in_array('opinion',$tags,true),'V9 detecta cita parcial y opinión sin atribuir cita al autor');
$tags=pp_text_types('Si hubiera datos suficientes, podría aumentar la tasa.');
check(in_array('condicional',$tags,true)&&in_array('prediccion',$tags,true),'V9 distingue escenario condicional y predicción');

check(str_contains(file_get_contents(__DIR__.'/../_private/news_ingest.php'),'CURLOPT_WRITEFUNCTION'),'V9 RSS download bounded while streaming');

echo "ALL V9 STATIC QA PASSED\n";
