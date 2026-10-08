<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/npl.php';
require __DIR__.'/../_private/npl_analytics.php';
function v15assert(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
function v15row(mixed $value,string $geo,array $extra=[]): array {
    $data=['dataset_code'=>'ECV2025','methodology_flag'=>'official_final','population'=>'Personas residentes',
        'measurement_type'=>'survey','source_url'=>'https://www.ine.es/','definition_version'=>'1'];
    return ['value'=>$value,'geo_code'=>$geo,'geography'=>$geo,'period'=>'2025','unit'=>'%',
        'source_id'=>'INE','data'=>json_encode([...$data,...$extra]),'evidence'=>'{}','reviewed_at'=>'2026-10-08'];
}
$geos=array_map(fn($i)=>'ES'.str_pad((string)$i,2,'0',STR_PAD_LEFT),range(11,20));
$x=[];$y=[];foreach($geos as $i=>$g){$x[$g]=v15row($i,$g);$y[$g]=v15row($i*2,$g);}
$ok=pp_analytics_correlation_quality($x,$y,$geos);
v15assert($ok['status']==='ok'&&$ok['n']===10,'Correlation should pass with 10 validated rows');
v15assert(abs($ok['pearson_r']-1.0)<1e-10,'Expected perfect Pearson correlation');
v15assert($ok['n']===10&&$x[$geos[0]]['value']===0,'Measured zero must be retained');
$x[$geos[2]]['value']=null;
$missing=pp_analytics_correlation_quality($x,$y,$geos);
v15assert($missing['n']===9&&in_array($geos[2],$missing['excluded'],true),'Null must be excluded, never coerced to zero');
$x[$geos[3]]['value']='unavailable';
$missing=pp_analytics_correlation_quality($x,$y,$geos);
v15assert($missing['n']===8&&count($missing['excluded'])===2,'Non-numeric value must be excluded');
$x[$geos[4]]['value']=null;
v15assert(pp_analytics_correlation_quality($x,$y,$geos)['status']==='insufficient_coverage','Fewer than 8 valid pairs must be blocked');
$x[$geos[2]]=v15row(2,$geos[2]);$x[$geos[3]]=v15row(3,$geos[3]);$x[$geos[4]]=v15row(4,$geos[4]);
$x[$geos[1]]=v15row(1,$geos[1],['population'=>null]);
$meta=pp_analytics_correlation_quality($x,$y,$geos);
v15assert($meta['status']==='insufficient_metadata'&&in_array('x:population',$meta['missing_fields'],true),'Missing population must block correlation');
$x[$geos[1]]=v15row(1,$geos[1]);$x[$geos[1]]=v15row(1,$geos[1],['dataset_code'=>'OTHER']);
v15assert(pp_analytics_correlation_quality($x,$y,$geos)['status']==='incompatible_measurements','Mixed datasets must block correlation');
$a=pp_analytics_point(v15row(0,$geos[0]));$b=pp_analytics_point(v15row(2,$geos[1]));
v15assert(pp_analytics_compatibility(['a'=>$a,'b'=>$b])['comparable'],'Complete homogeneous data should compare');
$b['population']=null;
$compat=pp_analytics_compatibility(['a'=>$a,'b'=>$b]);
v15assert($compat['homogeneous']===false&&$compat['metadata_complete']===false&&$compat['comparable']===false,'Missing population must disable comparison');
$b=$a;$b['population']=null;$a['population']=null;
v15assert(pp_analytics_compatibility(['a'=>$a,'b'=>$b])['homogeneous']===true&&pp_analytics_compatibility(['a'=>$a,'b'=>$b])['comparable']===false,'Two equally missing populations must not be treated as comparable');
echo "V15 SEMANTIC PASS: zero, null, invalid numeric, coverage, metadata, heterogeneous sources.\n";
