<?php
defined('ABSPATH') || exit;

function btp_gal_thumb_dir_base(): string { return btp_gal_base_path().'/.thumbs'; }
function btp_gal_thumb_ext(): string { return function_exists('imagewebp') ? 'webp' : 'jpg'; }
function btp_gal_thumb_abs_path(string $album, string $filename, string $sizeKey): string {
    $sizes = btp_gal_get_sizes();
    if (!isset($sizes[$sizeKey])) $sizeKey = 'thumb';
    $sub = $sizes[$sizeKey]['w'].'x'.($sizes[$sizeKey]['h'] ?: 'auto');
    $dir = wp_normalize_path(btp_gal_thumb_dir_base().'/'.$sizeKey.'-'.$sub.'/'.$album);
    if (!is_dir($dir)) wp_mkdir_p($dir);
    $name = pathinfo($filename, PATHINFO_FILENAME).'.'.btp_gal_thumb_ext();
    return $dir.'/'.$name;
}
function btp_gal_guess_mime(string $path): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
    if (isset($map[$ext])) return $map[$ext];
    if (function_exists('mime_content_type')) { $m = @mime_content_type($path); if ($m) return $m; }
    $ft = wp_check_filetype($path);
    return $ft['type'] ?: 'application/octet-stream';
}
function btp_gal_serve_image(string $sizeKey, string $relPath) {
    $relPath = rawurldecode($relPath);
    $relPath = wp_normalize_path(ltrim($relPath,'/'));
    if (strpos($relPath, '..') !== false) wp_die('Invalid path', '', 400);

    $parts = explode('/', $relPath);
    $file  = array_pop($parts);
    $album = implode('/', $parts);
    $srcAbs = btp_gal_get_album_abs_path($album).'/'.$file;

    if (!is_file($srcAbs)) {
        btp_gal_log('Arquivo não encontrado: '.$srcAbs.' (album='.$album.', file='.$file.')');
        wp_die('Not found', '', 404);
    }

    if ($sizeKey === 'raw') {
        if (isset($_GET['download']) && $_GET['download'] == '1') {
            nocache_headers(); header_remove('Cache-Control');
            header('Content-Type: '.btp_gal_guess_mime($srcAbs));
            header('Content-Disposition: attachment; filename="'.basename($srcAbs).'"');
            header('Content-Length: '.filesize($srcAbs));
            readfile($srcAbs); exit;
        }
        btp_gal_output_file($srcAbs, btp_gal_guess_mime($srcAbs));
        return;
    }

    $sizes = btp_gal_get_sizes();
    if (!isset($sizes[$sizeKey])) wp_die('Invalid size', '', 400);
    $thumbAbs = btp_gal_thumb_abs_path($album, $file, $sizeKey);
    if (!is_file($thumbAbs)) {
        btp_gal_make_thumb($srcAbs, $thumbAbs, $sizes[$sizeKey]);
    }
    $etag = 'W/"'.md5($thumbAbs.'|'.@filemtime($thumbAbs).'|'.@filesize($thumbAbs)).'"';
    $lm = gmdate('D, d M Y H:i:s', @filemtime($thumbAbs)).' GMT';
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) { status_header(304); exit; }
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && trim($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $lm) { status_header(304); exit; }
    nocache_headers(); header_remove('Cache-Control');
    header('Content-Type: '.btp_gal_guess_mime($thumbAbs));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('ETag: '.$etag); header('Last-Modified: '.$lm);
    header('Content-Length: '.filesize($thumbAbs));
    readfile($thumbAbs); exit;
}
function btp_gal_make_thumb(string $srcAbs, string $dstAbs, array $opt): void {
    $w = (int)($opt['w'] ?? 0); $h = (int)($opt['h'] ?? 0); $fit = $opt['fit'] ?? 'cover';
    $img = btp_gal_image_create_from($srcAbs); if (!$img) wp_die('Unsupported image', '', 415);
    $srcW = imagesx($img); $srcH = imagesy($img);
    if ($fit === 'cover' && $w>0 && $h>0) {
        $srcRatio = $srcW / $srcH; $dstRatio = $w / $h;
        if ($srcRatio > $dstRatio) { $newH = $srcH; $newW = (int)round($srcH * $dstRatio); $sx = (int)(($srcW - $newW) / 2); $sy = 0; }
        else { $newW = $srcW; $newH = (int)round($srcW / $dstRatio); $sx = 0; $sy = (int)(($srcH - $newH) / 2); }
        $dst = imagecreatetruecolor($w, $h); imagealphablending($dst, true); imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0,0, $sx,$sy, $w,$h, $newW,$newH);
    } else {
        if ($w>0 && $h===0) { $scale = $w / $srcW; $dw = $w; $dh = (int)round($srcH * $scale); }
        elseif ($h>0 && $w===0) { $scale = $h / $srcH; $dh = $h; $dw = (int)round($srcW * $scale); }
        else { $dw = $w ?: $srcW; $dh = $h ?: $srcH; }
        $dst = imagecreatetruecolor($dw, $dh); imagealphablending($dst, true); imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0,0, 0,0, $dw,$dh, $srcW,$srcH);
    }
    $dir = dirname($dstAbs); if (!is_dir($dir)) wp_mkdir_p($dir);
    if (function_exists('imagewebp') && strtolower(pathinfo($dstAbs, PATHINFO_EXTENSION))==='webp') imagewebp($dst, $dstAbs, 85);
    else imagejpeg($dst, $dstAbs, 85);
    imagedestroy($img); imagedestroy($dst);
}
function btp_gal_image_create_from(string $abs) {
    $mime = btp_gal_guess_mime($abs);
    switch ($mime) {
        case 'image/jpeg': return imagecreatefromjpeg($abs);
        case 'image/png':  return imagecreatefrompng($abs);
        case 'image/gif':  return imagecreatefromgif($abs);
        case 'image/webp': return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($abs) : null;
        default: return null;
    }
}
function btp_gal_output_file(string $abs, string $mime) {
    if (!is_file($abs)) { btp_gal_log('Output: arquivo não existe '.$abs); wp_die('Not found', '', 404); }
    $etag = 'W/"'.md5($abs.'|'.@filemtime($abs).'|'.@filesize($abs)).'"';
    $lm = gmdate('D, d M Y H:i:s', @filemtime($abs)).' GMT';
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) { status_header(304); exit; }
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && trim($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $lm) { status_header(304); exit; }
    nocache_headers(); header_remove('Cache-Control');
    header('Content-Type: '.$mime);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('ETag: '.$etag); header('Last-Modified: '.$lm);
    header('Content-Length: '.filesize($abs));
    readfile($abs); exit;
}
