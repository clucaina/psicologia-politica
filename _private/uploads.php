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
function pp_media(string $key): never {
    if(!preg_match('/^[a-f0-9-]{36}\.(jpg|png|webp|avif|pdf|doc|docx)$/',$key))throw new PPError(404,'Archivo no encontrado.');
    $asset=pp_one('SELECT * FROM media_assets WHERE id=?',[$key]);$path=dirname(__DIR__).'/uploads/'.$key;
    if(!$asset||!is_file($path))throw new PPError(404,'Archivo no encontrado.');
    header('Content-Type: '.$asset['mime_type']);header('X-Content-Type-Options: nosniff');header('Cache-Control: public, max-age=86400');
    if(!str_starts_with($asset['mime_type'],'image/')){header("Content-Security-Policy: sandbox; default-src 'none'");header('Content-Disposition: attachment; filename="'.$key.'"; filename*=UTF-8\'\''.rawurlencode($asset['credit']?:$key));}
    header('Content-Length: '.filesize($path));readfile($path);exit;
}
