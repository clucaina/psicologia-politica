<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/pages.php';
$base=[
 'posts'=>[
 ['kind'=>'news','title'=>'Actualidad científica','slug'=>'noticia-cientifica','excerpt'=>'Metodología y evidencia'],
 ['kind'=>'blog','title'=>'Decreto Mari Carmen','slug'=>'decreto-mari-carmen','excerpt'=>'Análisis de vivienda','post_kind'=>'opinion'],
 ['kind'=>'blog','title'=>'Manual de fuentes','slug'=>'manual-fuentes','excerpt'=>'Recursos','post_kind'=>'resource']],
 'activities'=>[['title'=>'Jornada profesional','slug'=>'jornada-profesional','description'=>'COP']],
 'team'=>[['full_name'=>'Maribel Gómez Hernando','slug'=>'maribel-gomez','focus_line'=>'Psicología']]
];
$checks=[
 '/noticias'=>['Actualidad científica','Decreto Mari Carmen'],
 '/blog'=>['Decreto Mari Carmen','Actualidad científica'],
 '/actividades'=>['Jornada profesional','Actualidad científica'],
 '/quienes-somos'=>['Maribel Gómez Hernando','Actualidad científica'],
 '/blog/recursos'=>['Manual de fuentes','Decreto Mari Carmen'],
 '/contacto'=>['','Actualidad científica'],
 '/privacidad'=>['Privacidad','Actualidad científica'],
 '/normas'=>['Participación responsable','Actualidad científica']
];
foreach($checks as $route=>[$yes,$no]){
 $html=pp_ssr_route_content(['route'=>$route,...$base]);
 if(($yes!==''&&!str_contains($html,$yes))||str_contains($html,$no)){
  fwrite(STDERR,"SSR mismatch $route\n");exit(1);
 }
}
if(pp_ssr_route_content(['route'=>'/no-existe',...$base])!=='')exit(2);
if(str_contains(pp_ssr_card(['title'=>'<script>alert(1)</script>','slug'=>'x'],'news'),'<script>'))exit(3);
echo "PASS V75 SSR route semantics, protected pages, no news leaks, escaped HTML\n";
