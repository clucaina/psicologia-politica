<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/pages.php';
$cases=[
  '**La crisis de la vivienda existe.**'=>'<strong>La crisis de la vivienda existe.</strong>',
  '\*\*La crisis\*\*'=>'<strong>La crisis</strong>',
  '\\*\\*La crisis\\*\\*'=>'<strong>La crisis</strong>',
  '**<script>no</script>**'=>'<strong>&lt;script&gt;no&lt;/script&gt;</strong>',
  'Una *opinión* no es dato'=>'Una <em>opinión</em> no es dato',
];
foreach($cases as $src=>$expected){
  $got=pp_markdown_inline($src);
  if($got!==$expected){fwrite(STDERR,'Mismatch '.json_encode([$src,$got,$expected])."\n");exit(1);}
}
$malicious='**<img src=x onerror=alert(1)>**';
if(str_contains(pp_markdown_inline($malicious),'<img'))exit(2);
$article=pp_plain_content("# Decreto Mari Carmen\n\n\\*\\*La crisis de la vivienda existe.\\*\\*\n\nUna opinión, no causalidad.");
if(!str_contains($article,'<h2>Decreto Mari Carmen</h2>')||
   !str_contains($article,'<strong>La crisis de la vivienda existe.</strong>'))exit(3);
echo 'V71 MARKDOWN PASS: historic one/two layers, semantics, H2, HTML escaping. '.count($cases).' fixtures.';
