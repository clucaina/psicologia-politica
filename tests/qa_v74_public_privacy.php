<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/pages.php';
$public=pp_public_post_projection([
    'id'=>17,'slug'=>'example','title'=>'Título','content'=>'Texto',
    'author_id'=>'secret-user-id','editor_email'=>'secret@example.org',
    'access_token'=>'never_publish','revision_internal_note'=>'private',
    'author_profile'=>['slug'=>'autora','full_name'=>'Autora','photo_url'=>'/team-assets/a.jpg',
                       'email'=>'private@example.org','user_id'=>'invisible']
]);
foreach(['author_id','editor_email','access_token','revision_internal_note'] as $field){
    if(array_key_exists($field,$public))throw new RuntimeException('PRIVATE_POST_FIELD: '.$field);
}
if(array_key_exists('email',$public['author_profile'])||array_key_exists('user_id',$public['author_profile']))
    throw new RuntimeException('PRIVATE_AUTHOR_FIELD');
$team=pp_public_team_projection(['id'=>1,'slug'=>'autora','full_name'=>'Autora','user_id'=>'private',
                                  'email'=>'p@example.org','phone'=>'600000000','is_public'=>1]);
if(isset($team['email'])||isset($team['phone'])||isset($team['user_id']))
    throw new RuntimeException('PRIVATE_TEAM_FIELD');
$activity=pp_public_activity_projection(['title'=>'Evento','starts_at'=>'2026-11-01',
    'hidden_contact_email'=>'secret@sample.org','registration_secret'=>'hidden']);
if(count($activity)!==2)throw new RuntimeException('PRIVATE_ACTIVITY_FIELD');
$boot=pp_public_boot_redact([
    'posts'=>[['id'=>1,'content'=>'Bien','author_id'=>'secret','password_hash'=>'secret']],
    'team'=>[['slug'=>'a','user_id'=>'secret','is_public'=>1]],
    'activities'=>[['title'=>'Evento','secret'=>'private']],
    'record'=>['id'=>2,'kind'=>'blog','title'=>'Artículo','draft_session_secret'=>'do not leak'],
    'account'=>['saved'=>[['id'=>3,'content'=>'Item','token'=>'secret']]],
    'overview'=>[],
]);
$serialized=json_encode($boot,JSON_THROW_ON_ERROR);
foreach(['password_hash','draft_session_secret','user_id','"token"','"secret"'] as $key)
    if(str_contains($serialized,$key))throw new RuntimeException('PRIVATE_BOOT_DATA: '.$key);
echo "PASS: V74 public projections exclude account IDs, editor secrets, draft notes and private fields\n";
