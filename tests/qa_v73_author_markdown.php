<?php
declare(strict_types=1);
if(!function_exists('pp_escape')) { function pp_escape(string $s): string {return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');} }
if(!function_exists('pp_url')) { function pp_url(string $s): ?string {return str_starts_with($s,'https://')?$s:null;} }
require_once __DIR__.'/../_private/pages.php';
function check73(bool $pass,string $message): void {
    if(!$pass)throw new RuntimeException('V73 FAIL: '.$message);
}
$team=[
 ['user_id'=>'user-1','full_name'=>'Ana','slug'=>'ana','photo_url'=>'/ana.jpg','focus_line'=>'A'],
 ['user_id'=>'user-2','full_name'=>'Ana','slug'=>'ana-duplicate','photo_url'=>'/ana2.jpg','focus_line'=>'B'],
 ['user_id'=>'user-3','full_name'=>'Maribel','slug'=>'maribel','photo_url'=>'/m.jpg','focus_line'=>'C'],
 ['user_id'=>'DUP','full_name'=>'Carlos','slug'=>'carlos1','photo_url'=>'','focus_line'=>'D'],
 ['user_id'=>'dup','full_name'=>'Carlos','slug'=>'carlos2','photo_url'=>'','focus_line'=>'E'],
];
$posts=[
 ['author_id'=>'user-1','author'=>'Ana','slug'=>'id-1'],
 ['author_id'=>'user-unknown','author'=>'Maribel','slug'=>'no-name-fallback'],
 ['author_id'=>'','author'=>'Ana','slug'=>'ambiguous-name'],
 ['author_id'=>'','author'=>'Maribel','slug'=>'unique-name'],
 ['author_id'=>'dup','author'=>'Carlos','slug'=>'ambiguous-id'],
];
$resolved=pp_attach_public_author_profiles($posts,$team);
check73(($resolved[0]['author_profile']['slug']??'')==='ana','exact immutable identity');
check73(!isset($resolved[1]['author_profile']),'must not impersonate by display name');
check73(!isset($resolved[2]['author_profile']),'ambiguous display name');
check73(($resolved[3]['author_profile']['slug']??'')==='maribel','unique legacy fallback');
check73(!isset($resolved[4]['author_profile']),'ambiguous immutable identifier');
check73(str_contains(pp_markdown_inline('**prueba**'),'<strong>prueba</strong>'),'ordinary Markdown');
$escaped=chr(92).'**prueba'.chr(92).'**';
$rendered=pp_markdown_inline($escaped);
if(!str_contains($rendered,'<strong>prueba</strong>'))fwrite(STDERR,"escaped markdown was: ".var_export($rendered,true)."\\n");
check73(str_contains($rendered,'<strong>prueba</strong>'),'historic escaped Markdown');
check73(!str_contains(pp_markdown_inline('<script>alert(1)</script>'),'<script>'),'HTML must remain escaped');
echo "PASS: V73 identity privacy, ambiguous names/IDs, escaped Markdown, HTML escaping\n";
