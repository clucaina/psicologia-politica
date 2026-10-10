<?php
declare(strict_types=1);
function pp_upload(array $file,string $owner,string $label,bool $imageOnly=false): array {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??''))throw new PPError(400,'No se pudo recibir el archivo. Revisa el límite de subida del hosting.');
    $size=filesize($file['tmp_name']);if(!$size||$size>12*1024*1024)throw new PPError(413,'El archivo debe tener entre 1 byte y 12 MB.');
    $name=mb_substr(preg_replace('/[\x00-\x1f\x7f\/\\\\]/u',' ',basename($file['name'])),0,180);
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$width=null;$height=null;
    $dir=dirname(__DIR__).'/uploads';if(!is_dir($dir))mkdir($dir,0755,true);
    if(in_array($ext,['jpg','jpeg','png'],true)){
        $info=@getimagesize($file['tmp_name']);
        if($size>5*1024*1024||!$info||!in_array($mime,['image/jpeg','image/png'],true)||$info[0]*$info[1]>4000000)throw new PPError(415,'Usa JPG o PNG válido de hasta 5 MB y 4 megapíxeles.');
        $source=@imagecreatefromstring(file_get_contents($file['tmp_name']));if(!$source)throw new PPError(415,'La imagen no es válida.');
        $width=$info[0];$height=$info[1];$image=imagecreatetruecolor($width,$height);$white=imagecolorallocate($image,255,255,255);imagefill($image,0,0,$white);imagecopy($image,$source,0,0,0,0,$width,$height);
        $key=pp_uuid().'.jpg';imagejpeg($image,$dir.'/'.$key,85);imagedestroy($image);imagedestroy($source);$mime='image/jpeg';$kind='image';
    }else{
        if($imageOnly)throw new PPError(415,'El perfil requiere una imagen JPG o PNG.');
        $bytes=file_get_contents($file['tmp_name']);$valid=false;
        if($ext==='pdf'&&$mime==='application/pdf'&&str_starts_with($bytes,'%PDF-')&&str_contains($bytes,'%%EOF')){$valid=true;$mime='application/pdf';}
        if($ext==='doc'&&str_starts_with($bytes,hex2bin('d0cf11e0a1b11ae1'))){$valid=true;$mime='application/msword';}
        if($ext==='docx'&&str_starts_with($bytes,"PK\x03\x04")){
            $z=new ZipArchive();if($z->open($file['tmp_name'])===true){$valid=$z->locateName('[Content_Types].xml')!==false&&$z->locateName('word/document.xml')!==false&&$z->locateName('word/vbaProject.bin')===false;$z->close();}$mime='application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }
        if(!$valid)throw new PPError(415,'Adjunta PDF, DOC o DOCX válido, o JPG/PNG.');
        $key=pp_uuid().'.'.$ext;copy($file['tmp_name'],$dir.'/'.$key);$kind='document';
    }
    chmod($dir.'/'.$key,0644);
    pp_insert('media_assets',['id'=>$key,'owner_id'=>$owner,'file_url'=>'/api/media/'.$key,'mime_type'=>$mime,'width'=>$width,'height'=>$height,'alt_text'=>$label?:$name,'credit'=>$name]);
    return ['key'=>$key,'url'=>'/api/media/'.$key,'kind'=>$kind,'mime_type'=>$mime,'name'=>$name,'width'=>$width,'height'=>$height];
}
/**
 * Media is not public merely because its UUID URL is known. The original
 * upload may be an unpublished article, a private avatar or a legal document.
 * Public images require a live published reference. Documents are never
 * anonymously downloadable. Staff and owners can preview private uploads.
 */
function pp_media_mime_matches(string $key,string $mime): bool {
    $expected=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','avif'=>'image/avif',
      'pdf'=>'application/pdf','doc'=>'application/msword',
      'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $ext=strtolower(pathinfo($key,PATHINFO_EXTENSION));
    return isset($expected[$ext])&&$expected[$ext]===$mime;
}
function pp_media_access_decision(string $mime,bool $owner,bool $editor,bool $publicReference): array {
    $image=str_starts_with($mime,'image/');
    if($owner||$editor)return ['allowed'=>true,'public'=>false,'reason'=>'authenticated_preview'];
    if($image&&$publicReference)return ['allowed'=>true,'public'=>true,'reason'=>'published_image'];
    return ['allowed'=>false,'public'=>false,'reason'=>$image?'unpublished_image':'private_document'];
}
function pp_media_public_image_reference(string $key): bool {
    $url='/api/media/'.$key;
    if(pp_one("SELECT id FROM editorial_posts WHERE cover_image_key=? AND status='published' AND deleted_at IS NULL LIMIT 1",[$key]))return true;
    if(pp_one('SELECT id FROM activities WHERE cover_image_key=? AND published=1 LIMIT 1',[$key]))return true;
    if(pp_one('SELECT id FROM team_members WHERE photo_url=? AND is_public=1 LIMIT 1',[$url]))return true;
    // Reader avatars are visible only when a published comment is attached
    // to a currently published article; a pending comment is not public.
    return (bool)pp_one("SELECT c.id FROM reader_profiles p JOIN article_comments c ON c.email=p.email JOIN editorial_posts e ON e.id=c.post_id WHERE p.avatar_key=? AND c.status='published' AND c.deleted_at IS NULL AND e.status='published' AND e.deleted_at IS NULL LIMIT 1",[$key]);
}
function pp_media(string $key): never {
    if(!preg_match('/^[a-f0-9-]{36}\.(jpg|png|webp|avif|pdf|doc|docx)$/',$key))throw new PPError(404,'Archivo no encontrado.');
    $asset=pp_one('SELECT * FROM media_assets WHERE id=?',[$key]);$path=dirname(__DIR__).'/uploads/'.$key;
    if(!$asset||!is_file($path))throw new PPError(404,'Archivo no encontrado.');
    $mime=(string)$asset['mime_type'];
    if(!pp_media_mime_matches($key,$mime))throw new PPError(404,'Archivo no encontrado.');
    // Publicly referenced images do not require an account/session lookup.
    $publicReference=str_starts_with($mime,'image/')&&pp_media_public_image_reference($key);
    $user=$publicReference?null:pp_user();
    $owner=$user&&in_array((string)$asset['owner_id'],[(string)($user['email']??''),(string)($user['id']??'')],true);
    $editor=$user&&in_array((string)($user['role']??''),['superadmin','admin_editor'],true);
    $access=pp_media_access_decision($mime,(bool)$owner,(bool)$editor,$publicReference);
    // Return 404 to avoid disclosing that a private document or draft exists.
    if(!$access['allowed'])throw new PPError(404,'Archivo no encontrado.');
    header('Content-Type: '.$mime);header('X-Content-Type-Options: nosniff');
    if($access['public'])header('Cache-Control: public, max-age=300');
    else {header('Cache-Control: private, no-store');header('Vary: Cookie');header('X-Robots-Tag: noindex, nofollow');}
    if(!str_starts_with($mime,'image/')){header("Content-Security-Policy: sandbox; default-src 'none'");header('Content-Disposition: attachment; filename="'.$key.'"; filename*=UTF-8\'\''.rawurlencode($asset['credit']?:$key));}
    header('Content-Length: '.filesize($path));readfile($path);exit;
}
