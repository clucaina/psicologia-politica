<?php
declare(strict_types=1);
if(!function_exists('mb_strtolower'))function mb_strtolower(string $s):string{return strtolower($s);}
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/pages.php';
$src=file_get_contents(__DIR__.'/../_private/pages.php');
if(!str_contains($src,'photo_url,user_id,is_public FROM team_members WHERE is_public=1'))exit(1);
$people=[
['user_id'=>'user-1','full_name'=>'Autora','slug'=>'autora','photo_url'=>'/team-assets/portrait.jpg','focus_line'=>'Neuro','is_public'=>1],
['user_id'=>'user-2','full_name'=>'Oculta','slug'=>'oculta','photo_url'=>'/team-assets/private.jpg','focus_line'=>'Privado','is_public'=>0]
];
$posts=pp_attach_public_author_profiles([
['author_id'=>'user-1','author'=>'Autora'],['author_id'=>'user-2','author'=>'Oculta']
],$people);
if(($posts[0]['author_profile']['photo_url']??'')!=='/team-assets/portrait.jpg')exit(2);
if(isset($posts[1]['author_profile']))exit(3);
echo "PASS V75 public profile SQL/identity/privacy\n";
