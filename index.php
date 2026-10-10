<?php
declare(strict_types=1);
require __DIR__.'/_private/core.php';
require __DIR__.'/_private/runtime.php';
require __DIR__.'/_private/security_paths.php';
pp_runtime_headers();
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: strict-origin-when-cross-origin');header('X-Frame-Options: SAMEORIGIN');
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');$path=$path==='/'?'/':rtrim($path,'/');
try{
    if(pp_private_path_forbidden($path))throw new PPError(404,'Página no encontrada.');
    if(!pp_is_public_production_host())header('X-Robots-Tag: noindex, nofollow');
    require __DIR__.'/_private/community.php';require __DIR__.'/_private/npl.php';require __DIR__.'/_private/pages.php';
    if(str_starts_with($path,'/api/')){require __DIR__.'/_private/api.php';pp_api($path);}
    if($path==='/robots.txt'){header('Content-Type: text/plain; charset=utf-8');if(!pp_is_public_production_host()){echo "User-agent: *\nDisallow: /\n";exit;}echo "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api/\nDisallow: /cuenta\nDisallow: /acceso\nDisallow: /install.php\nDisallow: /mantenimiento\nDisallow: /mapa-espana\nSitemap: ".pp_origin()."/sitemap.xml\n";exit;}
    if($path==='/sitemap.xml'){
        if(!pp_is_public_production_host())throw new PPError(404,'Sitemap no disponible en preproducción.');
        header('Content-Type: application/xml; charset=utf-8');$paths=['/','/noticias','/blog','/actividades','/quienes-somos','/herramientas','/conflictometro','/herramientas/analizar-texto','/herramientas/datos-espana','/contacto','/blog/recursos'];
        foreach(pp_all("SELECT slug,kind FROM editorial_posts WHERE status='published' AND deleted_at IS NULL") as $r)$paths[]=($r['kind']==='blog'?'/blog/':'/noticias/').$r['slug'];
        foreach(['activities'=>['published','actividades'],'team_members'=>['is_public','quienes-somos']] as $table=>[$field,$prefix])foreach(pp_all("SELECT slug FROM $table WHERE $field=1") as $r)$paths[]='/'.$prefix.'/'.$r['slug'];
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';foreach($paths as $p)echo '<url><loc>'.pp_escape(pp_origin().$p).'</loc></url>';echo '</urlset>';exit;
    }
    if($path==='/mapa-espana'){header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");readfile(__DIR__.'/_private/observatorio-espana.html');exit;}
    if($path==='/admin/revision-npl'){require __DIR__.'/_private/npl_review_ui.php';pp_npl_review_console();}
    if($path==='/mantenimiento'){require __DIR__.'/_private/backup.php';pp_maintenance();}
    if($path==='/signin-with-chatgpt')pp_redirect('/acceso');if($path==='/signout-with-chatgpt')pp_redirect('/cuenta');
    pp_render_page(pp_bootstrap($path));
}catch(Throwable $e){
    $code=$e instanceof PPError?$e->status:500;$message=$e instanceof PPError?$e->getMessage():'No se pudo cargar esta página. Vuelve a intentarlo o contacta con la redacción.';
    if(!($e instanceof PPError))error_log('Hostinger application error: '.get_class($e).' '.$e->getMessage());
    if(str_starts_with($path,'/api/'))pp_reply(['error'=>$message],$code);
    http_response_code($code);header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>'.($code===404?'Página no encontrada':'No se pudo abrir la página').'</title><link rel="stylesheet" href="/assets/site.css"><main class="pp-shell reading-body"><h1>'.pp_escape($message).'</h1><p><a href="/">Volver a Inicio</a> · <a href="/contacto">Contactar con la redacción</a></p></main></html>';
}
