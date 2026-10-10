<?php
/** Fail-closed route guard for sensitive runtime and unpublished files.
 * Apache/LiteSpeed .htaccess must ALSO deny direct static-file requests.
 * This function protects only requests that actually reach index.php.
 */
declare(strict_types=1);
function pp_private_path_forbidden(string $path): bool {
    // No URL path should contain a NUL or a Windows-style separator.
    if(str_contains($path,"\0") || str_contains($path,'\\'))return true;
    // An encoded slash/dot may have been decoded once by the web server.
    // Inspect up to three layers; do not treat the decoded path as a route.
    $candidate=$path;
    for($i=0;$i<3;$i++){
        if(str_contains($candidate,"\0")||str_contains($candidate,'\\'))return true;
        $normalized=preg_replace('~/+~','/',$candidate);
        if(preg_match('~(?:^|/)\.[^/]*(?:/|$)~iD',$normalized))return true;
        if(preg_match('~^/(?:_private|src|tests|vendor|database|docs|experiments|scripts|preintegration|uploads)(?:/|$)|^/editorial/review_queue(?:/|$)~iD',$normalized))return true;
        $decoded=rawurldecode($candidate);
        if($decoded===$candidate)break;
        $candidate=$decoded;
    }
    return false;
}
