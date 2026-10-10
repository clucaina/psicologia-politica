<?php
declare(strict_types=1);
/** Author projections for public pages; no identity inferred from an ambiguous display name. */
/** Public boot payload: project only approved fields; private SQL columns must not leak. */
function pp_public_post_projection(array $post): array {
    $fields=['id','slug','title','excerpt','content','section','kind','status','author',
        'featured','published_at','created_at','updated_at','cover_image_key',
        'cover_image_alt','source_url','post_kind','source_name','source_type',
        'evidence_type','verification_status','news_body','tags','seo_title',
        'seo_description','canonical_url','event_date','comments_enabled','author_profile'];
    $public=array_intersect_key($post,array_flip($fields));
    if(isset($public['author_profile'])&&is_array($public['author_profile']))
        $public['author_profile']=array_intersect_key($public['author_profile'],
            array_flip(['slug','full_name','focus_line','photo_url']));
    return $public;
}
function pp_public_team_projection(array $member): array {
    return array_intersect_key($member,array_flip(['id','slug','full_name','focus_line',
        'bio','trajectory','credentials','sources','photo_url','sort_order','is_public','updated_at']));
}
function pp_public_activity_projection(array $activity): array {
    return array_intersect_key($activity,array_flip(['id','slug','status','activity_type',
        'title','description','starts_at','ends_at','venue','modality','organizer',
        'participants','source_url','media','materials','published','created_at',
        'updated_at','programme','content','cover_image_key','cover_image_alt']));
}
function pp_public_boot_redact(array $boot): array {
    if(isset($boot['posts'])&&is_array($boot['posts']))
        $boot['posts']=array_map('pp_public_post_projection',$boot['posts']);
    if(isset($boot['team'])&&is_array($boot['team']))
        $boot['team']=array_map('pp_public_team_projection',$boot['team']);
    if(isset($boot['activities'])&&is_array($boot['activities']))
        $boot['activities']=array_map('pp_public_activity_projection',$boot['activities']);
    if(isset($boot['record'])&&is_array($boot['record'])){
        if(array_key_exists('kind',$boot['record']))$boot['record']=pp_public_post_projection($boot['record']);
        elseif(array_key_exists('full_name',$boot['record']))$boot['record']=pp_public_team_projection($boot['record']);
        elseif(array_key_exists('activity_type',$boot['record']))$boot['record']=pp_public_activity_projection($boot['record']);
        else $boot['record']=[];
    }
    if(isset($boot['overview'])&&is_array($boot['overview'])){
        $boot['overview']=array_map(static function(array $row): array {
            $data=pp_parse($row['data']??'{}');
            $evidence=pp_parse($row['evidence']??'{}');
            if(!is_array($data))$data=[];
            if(!is_array($evidence))$evidence=[];
            [$safeData,$safeEvidence]=pp_npl_public_projection($data,$evidence);
            return ['data'=>pp_json($safeData),'evidence'=>pp_json($safeEvidence),
                'reviewed_at'=>$row['reviewed_at']??null];
        },$boot['overview']);
    }
    foreach(['saved','followed'] as $section){
        if(isset($boot['account'][$section])&&is_array($boot['account'][$section]))
            $boot['account'][$section]=array_map('pp_public_post_projection',$boot['account'][$section]);
    }
    return $boot;
}
function pp_attach_public_author_profiles(array $posts,array $team): array {
    $byId=[];$byName=[];
    foreach($team as $member){
        if(!is_array($member)||((int)($member['is_public']??0))!==1)continue;
        $id=mb_strtolower(trim((string)($member['user_id']??'')));
        $name=mb_strtolower(trim((string)($member['full_name']??'')));
        if($id!=='')$byId[$id][]=$member;
        if($name!=='')$byName[$name][]=$member;
    }
    foreach($posts as &$post){
        if(!is_array($post))continue;
        unset($post['author_profile']);
        $id=mb_strtolower(trim((string)($post['author_id']??'')));
        $name=mb_strtolower(trim((string)($post['author']??'')));
        $matches=$id!==''?($byId[$id]??[]):($byName[$name]??[]);
        if(count($matches)!==1)continue;
        $person=$matches[0];
        $post['author_profile']=array_intersect_key($person,array_flip(['slug','full_name','focus_line','photo_url']));
    }
    unset($post);
    return $posts;
}
function pp_bootstrap(string $path): array {
    $aliases=['/panel'=>'/admin','/periodico'=>'/noticias','/laboratorio'=>'/herramientas'];
    if(isset($aliases[$path]))pp_redirect($aliases[$path],301);
    if(preg_match('~^/periodico/(.+)$~',$path,$m))pp_redirect('/noticias/'.$m[1],301);
    if($path==='/blog/psicologia-politica-conducta-polarizacion-instituciones-entrevista-2026'){
        $activity=pp_one("SELECT slug FROM activities WHERE id='activity-chacon-20260126' AND published=1");
        if($activity)pp_redirect('/actividades/'.$activity['slug'],301);
    }
    if($path==='/conflictometro'&&($_GET['vista']??'')==='text')pp_redirect('/herramientas/analizar-texto');
    if($path==='/conflictometro'&&((($_GET['vista']??'')==='spain')||!empty($_GET['indicador'])))pp_redirect('/herramientas/datos-espana'.(!empty($_GET['indicador'])?'?indicador='.rawurlencode($_GET['indicador']):''));
    $routes=['/','/noticias','/blog','/actividades','/quienes-somos','/herramientas','/herramientas/analizar-texto','/herramientas/datos-espana','/conflictometro','/contacto','/privacidad','/normas','/observatorio','/acceso','/cuenta','/blog/escribir','/blog/proponer','/blog/recursos','/admin','/admin/datos'];
    $route=$path;$params=[];
    if(!in_array($path,$routes,true)){
        if(preg_match('~^/(noticias|blog|actividades|quienes-somos)/([^/]+)$~',$path,$m)){$route='/'.$m[1].'/[slug]';$params=['slug'=>$m[2]];}
        elseif(preg_match('~^/admin/preview/([0-9]+)$~',$path,$m)){$route='/admin/preview/[id]';$params=['id'=>$m[1]];}
        elseif(preg_match('~^/admin/(content|activities|team|comments|reports|proposals|users|contacts|audit|revisions|categories|media|settings)$~',$path,$m)){$route='/admin/[section]';$params=['section'=>$m[1]];}
        else throw new PPError(404,'Página no encontrada.');
    }
    $u=pp_user();$editor=$u&&in_array($u['role'],['superadmin','admin_editor'],true);$admin=str_starts_with($route,'/admin');
    if(in_array($route,['/cuenta','/blog/proponer','/blog/escribir'],true)||$admin){if(!$u)pp_redirect('/acceso?returnTo='.rawurlencode($path));}
    if($route==='/blog/escribir'&&!$editor)throw new PPError(403,'Solo las cuentas editoriales pueden abrir el editor de artículos.');
    if(($admin||$route==='/blog/escribir')&&$u&&$u['mustChangePassword'])pp_redirect('/cuenta#seguridad');
    if($admin&&!in_array($u['role'],['superadmin','admin_editor','moderator'],true))throw new PPError(403,'Esta cuenta no tiene permisos de redacción.');
    if(in_array($route,['/admin/datos','/admin/preview/[id]'],true)&&!$editor)throw new PPError(403,'Acceso editorial restringido.');
    $b=['route'=>$route,'params'=>$params,'site_url'=>pp_origin(),'csrf'=>$_SESSION['csrf'],'user'=>$u,'posts'=>pp_all("SELECT * FROM editorial_posts WHERE status='published' AND deleted_at IS NULL ORDER BY featured DESC,published_at DESC,id DESC"),'team'=>pp_all('SELECT id,slug,full_name,focus_line,bio,trajectory,credentials,sources,photo_url,user_id,is_public FROM team_members WHERE is_public=1 ORDER BY sort_order'),'activities'=>pp_all('SELECT * FROM activities WHERE published=1 ORDER BY starts_at DESC')];
    $b['posts']=pp_attach_public_author_profiles($b['posts'],$b['team']);
    if($route==='/'){
        $b['comment_counts']=pp_all("SELECT post_id,count(*) AS total,max(created_at) AS latest FROM article_comments WHERE status='published' AND deleted_at IS NULL GROUP BY post_id");
        $b['overview']=pp_all('SELECT o.data,r.evidence,r.reviewed_at FROM npl_observations o '.PP_REVIEW_JOIN.' WHERE '.PP_PUBLISHED." AND o.geo_code='ES' AND ((o.indicator_id='MH_RISK_ADULT' AND o.subgroup='Ambos sexos') OR (o.indicator_id='CRIME_SEXUAL' AND o.subgroup='Total hechos conocidos')) ORDER BY o.period DESC LIMIT 100");
    }
    if(in_array($route,['/noticias/[slug]','/blog/[slug]'],true)){
        $post=current(array_filter($b['posts'],fn($p)=>$p['slug']===$params['slug']));if(!$post)throw new PPError(404,'Publicación no encontrada.');
        if(($route==='/blog/[slug]')!==($post['kind']==='blog'))pp_redirect(($post['kind']==='blog'?'/blog/':'/noticias/').$post['slug'],301);
        $b['record']=$post;$b['comments']=pp_comments($post['id'],$u['email']??'');
        if($u){$b['saved']=(bool)pp_one('SELECT id FROM saved_articles WHERE email=? AND post_id=?',[$u['email'],$post['id']]);$b['following']=(bool)pp_one('SELECT id FROM followed_threads WHERE email=? AND post_id=?',[$u['email'],$post['id']]);$b['profile']=pp_one('SELECT preferred_font FROM reader_profiles WHERE email=?',[$u['email']]);}
    }
    if(in_array($route,['/actividades/[slug]','/quienes-somos/[slug]'],true)){$table=$route==='/actividades/[slug]'?'activities':'team';$b['record']=current(array_filter($b[$table],fn($r)=>$r['slug']===$params['slug']));if(!$b['record'])throw new PPError(404,'Ficha no encontrada.');}
    if($u&&in_array($route,['/cuenta','/blog/escribir'],true)){
        if($editor){$b['own_posts']=pp_all("SELECT * FROM editorial_posts WHERE author_id IN (?,?) AND kind='blog' AND deleted_at IS NULL ORDER BY updated_at DESC",[$u['email'],$u['id']]);require_once __DIR__.'/editorial.php';foreach($b['own_posts'] as &$ownPost)$ownPost['_server_content_hash']=pp_author_fingerprint($ownPost);unset($ownPost);}
        if($route==='/cuenta'){
            $b['profile']=pp_one('SELECT * FROM reader_profiles WHERE email=?',[$u['email']]);$email=$u['email'];
            $b['account']=['saved'=>pp_all("SELECT e.* FROM saved_articles s JOIN editorial_posts e ON e.id=s.post_id WHERE s.email=? AND e.status='published' AND e.deleted_at IS NULL ORDER BY s.saved_at DESC LIMIT 12",[$email]),'followed'=>pp_all("SELECT e.* FROM followed_threads f JOIN editorial_posts e ON e.id=f.post_id WHERE f.email=? AND e.status='published' AND e.deleted_at IS NULL ORDER BY f.created_at DESC LIMIT 12",[$email]),'comments'=>pp_all('SELECT c.id,c.body,c.status,c.created_at,e.title,e.slug,e.kind FROM article_comments c JOIN editorial_posts e ON e.id=c.post_id WHERE c.email=? ORDER BY c.created_at DESC LIMIT 20',[$email]),'proposals'=>pp_all('SELECT title,status,review_note,created_at FROM thread_proposals WHERE proposer_id=? ORDER BY created_at DESC LIMIT 20',[$email]),'sessions'=>pp_all('SELECT id,input_text,created_at FROM conflictometer_sessions WHERE user_id=? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 20',[$email])];
            if($editor)$b['review_count']=pp_one("SELECT count(*) AS count FROM editorial_posts WHERE status='review' AND deleted_at IS NULL")['count'];
        }
    }
    if($route==='/admin/preview/[id]'){$b['preview']=pp_one('SELECT * FROM editorial_posts WHERE id=?',[$params['id']]);if(!$b['preview'])throw new PPError(404,'Publicación no encontrada.');}
    if(in_array($route,['/admin','/admin/[section]'],true)){
        $queries=['comments'=>'SELECT c.*,p.display_name,e.title AS post_title FROM article_comments c JOIN reader_profiles p ON p.email=c.email JOIN editorial_posts e ON e.id=c.post_id ORDER BY c.created_at DESC LIMIT 300','reports'=>'SELECT * FROM comment_reports ORDER BY created_at DESC LIMIT 200','proposals'=>'SELECT * FROM thread_proposals ORDER BY created_at DESC LIMIT 200'];
        if($editor)$queries=[...$queries,'content'=>'SELECT * FROM editorial_posts ORDER BY updated_at DESC','activities'=>'SELECT * FROM activities ORDER BY starts_at DESC','team'=>'SELECT * FROM team_members ORDER BY sort_order','media'=>'SELECT * FROM media_assets ORDER BY created_at DESC LIMIT 100','contacts'=>'SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 100','audit'=>'SELECT * FROM moderation_log ORDER BY created_at DESC LIMIT 200','revisions'=>'SELECT * FROM editorial_revisions ORDER BY created_at DESC LIMIT 150','categories'=>'SELECT * FROM editorial_categories ORDER BY sort_order'];
        if($u['role']==='superadmin')$queries['users']='SELECT p.email,p.display_name,p.email_verified,p.status,r.role,r.team_id,EXISTS(SELECT 1 FROM local_credentials c WHERE c.email=p.email) AS local_access FROM reader_profiles p LEFT JOIN user_roles r ON r.email=p.email ORDER BY joined_at DESC LIMIT 300';
        foreach($queries as $k=>$sql)$b['admin'][$k]=pp_all($sql);
    }
    return pp_public_boot_redact($b);
}
function pp_navigation(): string {$s='<nav aria-label="Navegación principal">';foreach(['Inicio'=>'/','Noticias'=>'/noticias','Blog'=>'/blog','Actividades'=>'/actividades','Herramientas'=>'/herramientas','Quiénes somos'=>'/quienes-somos','Contacto'=>'/contacto','Acceder'=>'/acceso'] as $label=>$href)$s.='<a style="margin-right:1rem" href="'.$href.'">'.$label.'</a>';return $s.'</nav>';}
function pp_markdown_inline(string $text): string {
    // The old editor escaped Markdown delimiters before persisting some articles.
    // Historic editor saves occasionally escaped Markdown emphasis twice.
    // Strip only escape slashes preceding Markdown emphasis, never arbitrary HTML.
    for($layer=0;$layer<3;$layer++){
        $normalized=str_replace([chr(92).'*',chr(92).'_'],['*','_'],$text);
        if($normalized===$text)break;
        $text=$normalized;
    }
    $links=[];
    $text=preg_replace_callback('~\[([^\]\\r\\n]{1,180})\]\((https?://[^\s)<>]{1,1500})\)~u',function($m)use(&$links){
        $url=pp_url($m[2]);if(!$url)return $m[1];
        $key='PPSAFELINK'.count($links).'END';
        $links[$key]='<a href="'.pp_escape($url).'" rel="noopener noreferrer">'.pp_escape($m[1]).'</a>';
        return $key;
    },$text);
    $html=pp_escape($text);
    $html=preg_replace('~\*\*([^*\n]{1,400})\*\*~u','<strong>$1</strong>',$html);
    $html=preg_replace('~(?<!\*)\*([^*\n]{1,400})\*(?!\*)~u','<em>$1</em>',$html);
    $html=preg_replace('~__([^_\n]{1,400})__~u','<strong>$1</strong>',$html);
    $html=preg_replace('~(?<!_)_([^_\n]{1,400})_(?!_)~u','<em>$1</em>',$html);
    return strtr($html,$links);
}
function pp_plain_content(string $text): string {
    $text=str_replace(["\r\n","\r"],"\n",$text);
    $blocks=preg_split('/\n\s*\n/u',trim($text));$html='';
    foreach($blocks as $block){
        if($block==='')continue;
        if(preg_match('/^(#{1,4})\s+([^\n]+)$/u',trim($block),$m)){
            $level=min(4,max(2,strlen($m[1])+1));$html.='<h'.$level.'>'.pp_markdown_inline($m[2]).'</h'.$level.'>';continue;
        }
        $lines=explode("\n",trim($block));$list=true;$ordered=null;
        foreach($lines as $line){
            if(!preg_match('/^\s*(?:([-*])|(\d{1,3})\.)\s+(.+)$/u',$line,$m)){$list=false;break;}
            $thisOrdered=!empty($m[2]);if($ordered!==null&&$ordered!==$thisOrdered){$list=false;break;}$ordered=$thisOrdered;
        }
        if($list){$tag=$ordered?'ol':'ul';$html.='<'.$tag.'>';foreach($lines as $line){preg_match('/^\s*(?:([-*])|(\d{1,3})\.)\s+(.+)$/u',$line,$m);$html.='<li>'.pp_markdown_inline($m[3]).'</li>';}$html.='</'.$tag.'>';continue;}
        $html.='<p>'.implode('<br>',array_map('pp_markdown_inline',$lines)).'</p>';
    }
    return $html;
}
function pp_fallback_tool(string $route): string {
    $data=[
        '/conflictometro'=>['El Conflictómetro permite explorar territorios, periodos e indicadores con fuentes identificables.','El mapa interactivo requiere JavaScript. No se deben interpretar los colores sin consultar la leyenda, la cobertura y el periodo.','/mapa-espana','Abrir mapa de España 2D (experimental)'],
        '/herramientas/datos-espana'=>['Datos de España reúne indicadores territoriales con unidades, periodos y fuentes documentadas.','Las cifras publicadas pueden diferir del archivo histórico en revisión. Ausencia de datos no significa cero.','/mapa-espana','Explorar mapa 2D de España (experimental)'],
        '/herramientas/analizar-texto'=>['Analiza afirmaciones, recursos retóricos, sesgos y posibles falacias en mensajes públicos.','Una señal lingüística no demuestra que un texto sea falso ni revela por sí sola la intención de quien lo escribió. La comprobación factual requiere fuentes externas.','/herramientas/datos-espana','Contrastar con datos'],
        '/contacto'=>['Contacto con el equipo editorial para propuestas, colaboraciones, actividades y correcciones de contenido.','El formulario interactivo necesita JavaScript. Evita incluir información clínica, datos personales de terceros o documentación confidencial.','/privacidad','Consultar privacidad'],
        '/herramientas'=>['Herramientas para estudiar los conflictos, los datos y el lenguaje político.','Elige una herramienta para conocer su metodología y límites.','/conflictometro','Abrir Conflictómetro'],
    ];
    if(!isset($data[$route]))return '';
    [$intro,$method,$href,$label]=$data[$route];
    $links='<p><a href="'.pp_escape($href).'">'.pp_escape($label).'</a></p>';
    if($route==='/herramientas')$links='<ul><li><a href="/conflictometro">Conflictómetro</a></li><li><a href="/mapa-espana">Mapa de España 2D (experimental)</a></li><li><a href="/herramientas/datos-espana">Datos de España</a></li><li><a href="/herramientas/analizar-texto">Analizador de texto</a></li></ul>';
    return '<section aria-labelledby="tool-method"><h2 id="tool-method">Qué puedes hacer aquí</h2><p>'.pp_escape($intro).'</p><h3>Metodología y límites</h3><p>'.pp_escape($method).'</p>'.$links.'</section>';
}

/** Accessible, route-specific server rendering. Never substitute the news feed for an unrelated page. */
function pp_ssr_card(array $row,string $kind): string {
    $title=trim((string)($row['title']??$row['full_name']??''));
    $slug=(string)($row['slug']??'');
    if($title===''||!preg_match('/^[a-z0-9][a-z0-9-]{0,139}$/D',$slug))return '';
    $prefix=match($kind){'blog'=>'/blog/','activity'=>'/actividades/','team'=>'/quienes-somos/',default=>'/noticias/'};
    $description=trim((string)($row['excerpt']??$row['description']??$row['focus_line']??''));
    return '<article><h3><a href="'.pp_escape($prefix.$slug).'">'.pp_escape($title).'</a></h3>'.
        ($description!==''?'<p>'.pp_escape(function_exists('mb_substr')?mb_substr($description,0,320):substr($description,0,320)).'</p>':'').'</article>';
}
function pp_ssr_section(string $title,array $rows,string $kind,int $limit=30): string {
    $html='';$count=0;
    foreach($rows as $row){
        if($count>=$limit)break;
        if(!is_array($row))continue;
        $card=pp_ssr_card($row,$kind);
        if($card==='')continue;
        $html.=$card;$count++;
    }
    return '<section class="pp-ssr-collection"><h2>'.pp_escape($title).'</h2>'.
        ($html!==''?$html:'<p>No hay publicaciones disponibles en esta sección.</p>').'</section>';
}
function pp_ssr_route_content(array $data): string {
    $route=(string)($data['route']??'');
    $posts=is_array($data['posts']??null)?$data['posts']:[];
    $news=array_values(array_filter($posts,static fn($x)=>is_array($x)&&($x['kind']??'')!=='blog'));
    $blog=array_values(array_filter($posts,static fn($x)=>is_array($x)&&($x['kind']??'')==='blog'));
    $team=is_array($data['team']??null)?$data['team']:[];
    $activities=is_array($data['activities']??null)?$data['activities']:[];
    if($route==='/')return '<p><a href="/mapa-espana">Mapa de España 2D (experimental)</a> · '.
        '<a href="/conflictometro">Conflictómetro</a> · <a href="/herramientas/analizar-texto">Analizar textos</a></p>'.
        pp_ssr_section('Noticias recientes',$news,'news',6).
        pp_ssr_section('Artículos del Blog',$blog,'blog',4).
        pp_ssr_section('Actividades',$activities,'activity',3);
    if($route==='/noticias')return pp_ssr_section('Noticias',$news,'news',40);
    if($route==='/blog')return pp_ssr_section('Artículos del Blog',$blog,'blog',40);
    if($route==='/actividades')return pp_ssr_section('Actividades',$activities,'activity',40);
    if($route==='/quienes-somos')return pp_ssr_section('Equipo',$team,'team',40);
    if($route==='/blog/recursos')return pp_ssr_section('Recursos publicados',
        array_values(array_filter($blog,static fn($x)=>($x['post_kind']??'')==='resource')),'blog',40);
    if($route==='/observatorio')return '<section><h2>Observatorio de datos</h2><p>Indicadores territoriales publicados y revisados. '.
        'Un registro histórico no acredita disponibilidad ni equivale a un cero medido.</p>'.
        '<p><a href="/mapa-espana">Abrir mapa de España</a></p></section>';
    if($route==='/privacidad')return '<section><h2>Privacidad y contacto</h2><p>Evita enviar datos clínicos '.
        'o información personal de terceros. Para ejercer derechos, utiliza <a href="/contacto">Contacto</a>.</p></section>';
    if($route==='/normas')return '<section><h2>Participación responsable</h2><p>Distingue hechos, inferencias '.
        'y opiniones. La publicación de propuestas requiere revisión humana.</p><p><a href="/blog">Blog</a></p></section>';
    return '';
}

function pp_render_page(array $b): never {
    $names=['/'=>'Entender la política desde la conducta humana.','/noticias'=>'Noticias','/blog'=>'Blog','/actividades'=>'Actividades','/quienes-somos'=>'Quiénes somos','/herramientas'=>'Herramientas','/conflictometro'=>'Conflictómetro','/herramientas/datos-espana'=>'Datos de España','/herramientas/analizar-texto'=>'Analizar un texto','/acceso'=>'Acceso seguro','/cuenta'=>'Mi panel','/contacto'=>'Contacto','/privacidad'=>'Privacidad','/normas'=>'Normas de participación','/blog/escribir'=>'Escribir en el Blog','/admin'=>'Panel editorial'];
    $record=$b['record']??null;$toolDescriptions=['/conflictometro'=>'Mapa interactivo de conflictos e indicadores sociales. Explora territorios, series temporales, fuentes y límites metodológicos.','/herramientas/datos-espana'=>'Indicadores oficiales de España y comunidades autónomas: consulta periodos, unidades, cobertura, fuentes y comparaciones.','/herramientas/analizar-texto'=>'Examina sesgos, falacias y recursos retóricos sin confundir estilo lingüístico con veracidad.','/contacto'=>'Contacta con el equipo de Psicología Política para propuestas, colaboraciones, actividades y rectificaciones.'];$title=($record['seo_title']??'')?:($record['title']??$record['full_name']??$names[$b['route']]??'Psicología Política');$description=($record['seo_description']??'')?:($record['excerpt']??$record['description']??$record['focus_line']??$toolDescriptions[$b['route']]??'Psicología, comunicación y política. Noticias con fuentes, análisis, actividades y herramientas para comprender la vida pública.');
    $canonical=pp_origin().parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$private=str_starts_with($b['route'],'/admin')||in_array($b['route'],['/cuenta','/acceso','/blog/escribir','/blog/proponer'],true);
    header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header("Content-Security-Policy: default-src 'self'; script-src 'self' https://www.googletagmanager.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self' https://www.google-analytics.com https://region1.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com; frame-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    $assets=pp_parse(file_get_contents(__DIR__.'/assets.json'));$image=$record?pp_image($record['cover_image_key']??null):'';
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.pp_escape($title).' · Psicología Política</title><meta name="description" content="'.pp_escape($description).'"><meta name="robots" content="'.($private?'noindex,nofollow':'index,follow').'"><link rel="canonical" href="'.pp_escape($canonical).'"><meta property="og:title" content="'.pp_escape($title).'"><meta property="og:description" content="'.pp_escape($description).'"><meta property="og:url" content="'.pp_escape($canonical).'"><meta property="og:type" content="'.($record&&isset($record['kind'])?'article':'website').'"><meta name="twitter:card" content="summary_large_image">'.($image?'<meta property="og:image" content="'.pp_escape(pp_origin().$image).'">':'').'<link rel="icon" href="/favicon.svg"><link rel="stylesheet" href="'.pp_escape($assets['css']).'"><script async src="https://www.googletagmanager.com/gtag/js?id=G-D7KPSFPMM5"></script><script defer src="/analytics.js"></script></head><body'.(!empty($b['user']['email'])&&$b['route']==='/blog/escribir'?' data-pp-editor-scope="'.hash('sha256',mb_strtolower(trim($b['user']['email']))).'"':'').'><a class="skip-link" href="#pp-root">Saltar al contenido</a><div id="pp-root"></div><div id="pp-fallback"><header class="site-header">'.pp_navigation().'</header><main class="pp-shell reading-body"><h1>'.pp_escape($title).'</h1><p>'.pp_escape($description).'</p>';
    if($record){if($image)echo '<img style="max-width:100%" src="'.pp_escape($image).'" alt="'.pp_escape($record['cover_image_alt']??$title).'">';if(!empty($record['kind'])){ $ap=$record['author_profile']??null;echo '<aside class="pp-ssr-author" aria-label="Autoría">';if($ap&&!empty($ap['photo_url'])&&str_starts_with($ap['photo_url'],'/'))echo '<img width="56" height="56" loading="lazy" src="'.pp_escape($ap['photo_url']).'" alt="Retrato de '.pp_escape($ap['full_name']).'">';echo '<span>Por ';if($ap)echo '<a href="/quienes-somos/'.pp_escape($ap['slug']).'">'.pp_escape($ap['full_name']).'</a>';else echo pp_escape($record['author']??'Redacción');echo '</span></aside>';}echo pp_plain_content($record['content']??$record['bio']??'');if(!empty($record['source_url']))echo '<p>Fuente original: <a href="'.pp_escape($record['source_url']).'" rel="noreferrer">'.pp_escape($record['source_name']??'Consultar fuente').'</a></p>';}
    elseif(!$private){
        $tool=pp_fallback_tool((string)$b['route']);
        echo $tool!==''?$tool:pp_ssr_route_content($b);
    }
    echo '</main><footer class="site-footer">'.pp_navigation().'</footer></div><p id="pp-loading" class="pp-shell" role="status">Activando funciones interactivas…</p><noscript><p class="pp-shell">Activa JavaScript para usar el mapa, los filtros, formularios y tu cuenta.</p></noscript>';
    $flags=JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES;
    echo '<script id="pp-data" type="application/json">'.json_encode($b,$flags).'</script>';
    if($record&&isset($record['kind']))echo '<script type="application/ld+json">'.json_encode(['@context'=>'https://schema.org','@type'=>$record['kind']==='blog'?'BlogPosting':'NewsArticle','headline'=>$record['title'],'description'=>$record['excerpt'],'datePublished'=>$record['published_at'],'dateModified'=>$record['updated_at'],'author'=>['@type'=>'Person','name'=>$record['author'],'url'=>!empty($record['author_profile']['slug'])?pp_origin().'/quienes-somos/'.$record['author_profile']['slug']:null],'publisher'=>['@type'=>'Organization','name'=>'Psicología Política'],'mainEntityOfPage'=>$canonical,'image'=>$image?pp_origin().$image:null,'citation'=>$record['source_url']],$flags).'</script>';
    echo '<script type="module" src="'.pp_escape($assets['js']).'"></script></body></html>';exit;
}
