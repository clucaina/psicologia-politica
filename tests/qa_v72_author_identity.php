<?php
declare(strict_types=1);
if(!function_exists('mb_strtolower')){function mb_strtolower(string $text): string {return strtolower($text);}}
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/pages.php';
$team=[
 ['user_id'=>'editor-A','full_name'=>'Nombre Repetido','slug'=>'perfil-a','focus_line'=>'Área A','photo_url'=>'/team-assets/photo-1.jpg','is_public'=>1],
 ['user_id'=>'editor-B','full_name'=>'Nombre Repetido','slug'=>'perfil-b','focus_line'=>'Área B','photo_url'=>'/team-assets/photo-2.jpg','is_public'=>1],
 ['user_id'=>'editor-C','full_name'=>'Autora Única','slug'=>'perfil-c','focus_line'=>'Área C','photo_url'=>'/team-assets/photo-3.jpg','is_public'=>1],
 ['user_id'=>'editor-D','full_name'=>'Autora Oculta','slug'=>'perfil-d','focus_line'=>'Área D','photo_url'=>'/team-assets/photo-4.jpg','is_public'=>0]
];
$posts=[
 ['author_id'=>'editor-A','author'=>'Nombre Repetido'],
 ['author_id'=>'editor-missing','author'=>'Autora Única'],
 ['author_id'=>'','author'=>'Nombre Repetido'],
 ['author_id'=>null,'author'=>'Autora Única'],
 ['author_id'=>null,'author'=>'Autora Oculta'],
 ['author_id'=>'editor-B','author'=>'Otro Nombre','author_profile'=>['slug'=>'falso']],
];
$result=pp_attach_public_author_profiles($posts,$team);
$expected=['perfil-a',null,null,'perfil-c',null,'perfil-b'];
foreach($result as $i=>$post){
    $actual=$post['author_profile']['slug']??null;
    if($actual!==$expected[$i]){fwrite(STDERR,"Author identity mismatch $i: ".var_export($actual,true)."\n");exit(1);}
    if(isset($post['author_profile'])&&array_diff(array_keys($post['author_profile']),['slug','full_name','focus_line','photo_url']))exit(2);
}
$team[]=['user_id'=>'editor-C','full_name'=>'Otra Persona','slug'=>'perfil-z','focus_line'=>'Z','photo_url'=>'/team-assets/photo-5.jpg','is_public'=>1];
$result=pp_attach_public_author_profiles([['author_id'=>'editor-C','author'=>'Autora Única']],$team);
if(isset($result[0]['author_profile']))exit(3);
echo "V72 AUTHOR IDENTITY PASS: unique public account first, ambiguous names and duplicate IDs blocked.\n";
