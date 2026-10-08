<?php
declare(strict_types=1);
function pp_thread(int $id): ?array {return pp_one("SELECT id,comments_enabled FROM editorial_posts WHERE id=? AND status='published' AND deleted_at IS NULL",[$id]);}
function pp_comments(int $id,string $email=''): array {
    return pp_all("SELECT c.id,c.parent_id,c.body,CASE WHEN c.parent_id IS NULL OR EXISTS(SELECT 1 FROM article_comments parent WHERE parent.id=c.parent_id AND parent.status='published' AND parent.deleted_at IS NULL) THEN c.quote ELSE NULL END AS quote,c.source_urls,c.relation,c.status,c.created_at,p.display_name,p.avatar_key,CASE WHEN c.email=? THEN 1 ELSE 0 END AS own,(SELECT count(*) FROM comment_reactions WHERE comment_id=c.id AND reaction='useful') AS useful,(SELECT count(*) FROM comment_reactions WHERE comment_id=c.id AND reaction='adds_evidence') AS adds_evidence FROM article_comments c JOIN reader_profiles p ON p.email=c.email WHERE c.post_id=? AND c.deleted_at IS NULL AND (c.status='published' OR (c.email=? AND c.status='review')) ORDER BY c.created_at,c.id LIMIT 300",[$email,$id,$email]);
}
function pp_comment_api(): never {
    $method=$_SERVER['REQUEST_METHOD'];
    if($method==='GET'){$id=(int)($_GET['postId']??0);if(!pp_thread($id))throw new PPError(404,'Hilo no encontrado.');pp_reply(['comments'=>pp_comments($id,pp_user()['email']??'')]);}
    $u=pp_require_user();$p=pp_body();
    if($method==='DELETE'){
        pp_transaction(function()use($u,$p){$before=pp_one('SELECT * FROM article_comments WHERE id=? AND email=? FOR UPDATE',[(int)($p['id']??0),$u['email']]);if(!$before)throw new PPError(404,'Comentario no encontrado.');$values=['status'=>'deleted','deleted_at'=>pp_now(),'updated_at'=>pp_now()];pp_update('article_comments',$values,'id',$before['id']);pp_audit($u['email'],'comments',(string)$before['id'],'self_delete',['status'=>$before['status']],$values,'Retirado por su autor');});pp_reply(['ok'=>true]);
    }
    if($method!=='POST')throw new PPError(405,'Método no permitido.');
    pp_rate('comment',$u['email'],8);$postId=(int)($p['postId']??0);$post=pp_thread($postId);
    if(!$post||!$post['comments_enabled'])throw new PPError(403,'Este hilo no admite comentarios.');
    $body=pp_clean($p['body']??'',6000);if(mb_strlen($body)<8)throw new PPError(400,'Escribe al menos ocho caracteres.');
    $parentId=!empty($p['parentId'])?(int)$p['parentId']:null;$quote=null;
    if($parentId){
        $parent=pp_one("SELECT * FROM article_comments WHERE id=? AND post_id=? AND status='published' AND deleted_at IS NULL",[$parentId,$postId]);
        if(!$parent)throw new PPError(400,'Responde a un comentario publicado de este hilo.');
        $depth=1;$ancestor=$parent;while($ancestor['parent_id']&&$depth<6){$ancestor=pp_one('SELECT parent_id FROM article_comments WHERE id=?',[$ancestor['parent_id']]);if(!$ancestor)break;$depth++;}
        if($depth>=6)throw new PPError(400,'Responde al hilo principal para continuar.');$quote=!empty($p['quote'])?mb_substr($parent['body'],0,700):null;
    }
    $sources=pp_json(pp_links($p['sources']??[]));
    if(count(pp_parse($sources))>3&&pp_one('SELECT id FROM article_comments WHERE email=? AND source_urls=? AND created_at>?',[$u['email'],$sources,gmdate('Y-m-d',time()-86400)]))throw new PPError(429,'Estas fuentes ya se han compartido. Añade contexto nuevo.');
    $previous=pp_one("SELECT id FROM article_comments WHERE email=? AND status='published' AND deleted_at IS NULL LIMIT 1",[$u['email']]);
    $status=$u['role']!=='registered_user'||$previous?'published':'review';
    $id=pp_insert('article_comments',['post_id'=>$postId,'email'=>$u['email'],'body'=>$body,'status'=>$status,'parent_id'=>$parentId,'quote'=>$quote,'source_urls'=>$sources,'relation'=>in_array($p['relation']??'',['claim','question','agreement','disagreement'],true)?$p['relation']:'claim']);
    pp_reply(['ok'=>true,'id'=>(int)$id,'status'=>$status,'message'=>$status==='review'?'Tu primera aportación queda pendiente de moderación.':'Comentario publicado.'],201);
}
function pp_forum(string $action,?array $payload=null): never {
    $u=pp_require_user();$p=$payload??pp_body();pp_rate('forum-'.$action,$u['email'],$action==='propose'?5:40);
    if($action==='propose'){
        $title=pp_clean($p['title']??'',240);$body=pp_clean($p['body']??'',12000);
        if(mb_strlen($title)<12||mb_strlen($body)<40)throw new PPError(400,'Completa título y contexto de la propuesta.');
        pp_insert('thread_proposals',['id'=>pp_uuid(),'proposer_id'=>$u['email'],'proposed_kind'=>pp_post_kind($p['kind']??'debate'),'title'=>$title,'body'=>$body,'source_urls'=>pp_json(pp_links($p['sources']??[]))]);pp_reply(['ok'=>true,'message'=>'Propuesta enviada a moderación.']);
    }
    if($action==='follow'){
        $postId=(int)($p['postId']??0);if(!pp_thread($postId))throw new PPError(404,'Hilo no encontrado.');
        if(!empty($p['remove']))pp_query('DELETE FROM followed_threads WHERE email=? AND post_id=?',[$u['email'],$postId]);else pp_query('INSERT IGNORE INTO followed_threads(id,email,post_id) VALUES(?,?,?)',[pp_uuid(),$u['email'],$postId]);
        pp_reply(['ok'=>true,'following'=>empty($p['remove'])]);
    }
    $comment=pp_one("SELECT c.id FROM article_comments c JOIN editorial_posts p ON p.id=c.post_id WHERE c.id=? AND c.status='published' AND c.deleted_at IS NULL AND p.status='published' AND p.deleted_at IS NULL",[(int)($p['commentId']??0)]);
    if(!$comment)throw new PPError(404,'Comentario no disponible.');
    if($action==='reaction'){
        if(!in_array($p['reaction']??'',['useful','adds_evidence'],true))throw new PPError(400,'Reacción no válida.');
        if(!empty($p['remove']))pp_query('DELETE FROM comment_reactions WHERE comment_id=? AND email=? AND reaction=?',[$comment['id'],$u['email'],$p['reaction']]);else pp_query('INSERT IGNORE INTO comment_reactions(id,comment_id,email,reaction) VALUES(?,?,?,?)',[pp_uuid(),$comment['id'],$u['email'],$p['reaction']]);pp_reply(['ok'=>true]);
    }
    if($action==='report'){
        $reason=pp_clean($p['reason']??'',1200);if(mb_strlen($reason)<10)throw new PPError(400,'Explica la conducta que incumple las normas.');
        if(pp_one("SELECT id FROM comment_reports WHERE comment_id=? AND reporter_id=? AND status IN ('open','reviewing')",[$comment['id'],$u['email']]))throw new PPError(409,'Ya tienes una denuncia pendiente.');
        pp_insert('comment_reports',['id'=>pp_uuid(),'comment_id'=>$comment['id'],'reporter_id'=>$u['email'],'reason'=>$reason]);pp_reply(['ok'=>true,'message'=>'La denuncia queda pendiente de revisión.']);
    }
    throw new PPError(404,'Acción no encontrada.');
}
function pp_post_kind(mixed $kind): string {return in_array($kind,['article','debate','question','reading','interview','discourse_analysis','communication_case','resource'],true)?$kind:'article';}
function pp_account(string $action): never {
    $u=pp_require_user();$email=$u['email'];$method=$_SERVER['REQUEST_METHOD'];
    if($action==='export'&&$method==='GET'){
        $out=['profile'=>pp_one('SELECT * FROM reader_profiles WHERE email=?',[$email])];
        foreach(['comments'=>['article_comments','email'],'saved'=>['saved_articles','email'],'followed'=>['followed_threads','email'],'proposals'=>['thread_proposals','proposer_id']] as $k=>[$t,$f])$out[$k]=pp_all("SELECT * FROM $t WHERE $f=?",[$email]);
        $out['analyses']=pp_all('SELECT id,input_text,result,created_at FROM conflictometer_sessions WHERE user_id=? AND deleted_at IS NULL',[$email]);header('Content-Disposition: attachment; filename="mis-datos-psicologia-politica.json"');pp_reply($out);
    }
    if($action==='saved'){
        if($method==='GET')pp_reply(['saved'=>(bool)pp_one('SELECT id FROM saved_articles WHERE email=? AND post_id=?',[$email,(int)($_GET['postId']??0)])]);
        $p=pp_body();$id=(int)($p['postId']??0);if(!pp_thread($id))throw new PPError(404,'Publicación no encontrada.');
        if($method==='DELETE')pp_query('DELETE FROM saved_articles WHERE email=? AND post_id=?',[$email,$id]);elseif($method==='POST')pp_query('INSERT IGNORE INTO saved_articles(email,post_id) VALUES(?,?)',[$email,$id]);else throw new PPError(405,'Método no permitido.');pp_reply(['saved'=>$method==='POST']);
    }
    if($action==='profile'&&$method==='POST'){
        pp_csrf();pp_rate('profile',$email,15);$name=preg_replace('/\s+/u',' ',pp_clean($_POST['displayName']??'',60));$font=$_POST['preferredFont']??'editorial';
        if(mb_strlen($name)<2||!in_array($font,['editorial','clarity','accessible'],true))throw new PPError(400,'Revisa el nombre público y estilo de lectura.');
        $values=['display_name'=>$name,'preferred_font'=>$font,'bio'=>pp_clean($_POST['bio']??'',600),'interests'=>pp_json(array_slice(array_values(array_filter(array_map('trim',explode(',',pp_clean($_POST['interests']??'',1000))))),0,10)),'last_seen_at'=>pp_now()];
        if(!empty($_FILES['avatar']['size']))$values['avatar_key']=pp_upload($_FILES['avatar'],$email,$name,true)['key'];
        pp_update('reader_profiles',$values,'email',$email);$row=pp_one('SELECT * FROM reader_profiles WHERE email=?',[$email]);pp_reply(['profile'=>['displayName'=>$row['display_name'],'avatarKey'=>$row['avatar_key'],'preferredFont'=>$row['preferred_font'],'bio'=>$row['bio'],'interests'=>$row['interests']]]);
    }
    throw new PPError(404,'Acción no encontrada.');
}
function pp_contact(): never {
    $p=pp_body();pp_rate('contact','',8);if(pp_clean($p['website']??'',120))pp_reply(['ok'=>true,'reference'=>'PP-RECIBIDO'],201);
    $name=pp_clean($p['name']??'',90);$email=pp_email($p['email']??'');$subject=pp_clean($p['subject']??'',100);$message=pp_clean($p['message']??'',4000);
    if(mb_strlen($name)<2||mb_strlen($subject)<2||mb_strlen($message)<15||($p['consent']??false)!==true)throw new PPError(400,'Revisa los campos y acepta la información de privacidad.');
    pp_rate('contact-email',$email,3,600);$id=pp_insert('contact_messages',['name'=>$name,'email'=>$email,'subject'=>$subject,'message'=>$message,'consent'=>1]);
    if(!empty(pp_config()['contact_email'])){require_once __DIR__.'/mail.php';pp_mail(pp_config()['contact_email'],'Nuevo contacto: '.$subject,"Nombre: $name\nCorreo: $email\n\n$message",false);}
    pp_reply(['ok'=>true,'reference'=>'PP-'.str_pad($id,5,'0',STR_PAD_LEFT)],201);
}
function pp_pulse(): never {
    if($_SERVER['REQUEST_METHOD']==='POST'){$p=pp_body();pp_rate('pulse','',8);if(!in_array($p['emotion']??'',['miedo','rabia','esperanza','cansancio','confusion'],true))throw new PPError(400,'Emoción no válida.');pp_insert('pulse_votes',['emotion'=>$p['emotion']]);}
    elseif($_SERVER['REQUEST_METHOD']!=='GET')throw new PPError(405,'Método no permitido.');
    pp_reply(['counts'=>(object)array_column(pp_all('SELECT emotion,count(*) AS total FROM pulse_votes GROUP BY emotion'),'total','emotion')]);
}
