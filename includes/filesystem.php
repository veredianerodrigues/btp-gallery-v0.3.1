<?php
defined('ABSPATH') || exit;

function btp_gal_log($msg){ if(BTP_GAL_DEBUG) error_log('[BTP_GAL] '.$msg); }
function btp_gal_get_sizes(): array { return unserialize(BTP_GAL_THUMB_SIZES); }
function btp_gal_get_allowed_ext(): array { return array_map('strtolower', unserialize(BTP_GAL_ALLOWED_EXT)); }

function btp_gal_base_path(): string {
    $opt = (array) get_option('btp_gal_settings', []);
    $base = !empty($opt['base_path']) ? $opt['base_path'] : BTP_GAL_BASE_PATH;
    return untrailingslashit(wp_normalize_path($base));
}

function btp_gal_sanitize_album(string $album): string {
    $album = rawurldecode(wp_normalize_path(trim($album, "/\\ ")));
    $album = preg_replace('#(^|/)\.(\.?)(/|$)#', '/', $album);
    $album = preg_replace('#/+#', '/', $album);
    $album = ltrim($album, '/');
    return $album;
}

function btp_gal_get_album_abs_path(string $album): string {
    $album = btp_gal_sanitize_album($album);
    $abs   = wp_normalize_path(btp_gal_base_path().'/'.$album);
    $base  = btp_gal_base_path();
    if (strpos($abs.'/', $base.'/') !== 0) { btp_gal_log('Path escape bloqueado: '.$abs); return $base; }
    return $abs;
}

function btp_gal_list_images(string $album, bool $recursive=false): array {
    $dir = btp_gal_get_album_abs_path($album);
    if (!is_dir($dir)) { btp_gal_log('btp_gal_list_images: dir não existe: '.$dir); return []; }

    $exts = btp_gal_get_allowed_ext();
    $files = [];

    if ($recursive) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $info) {
            if ($info->isFile()) {
                $ext = strtolower($info->getExtension());
                if (!in_array($ext, $exts, true)) continue;
                $abs = wp_normalize_path($info->getPathname());
                $rel = ltrim(str_replace(btp_gal_base_path().'/', '', $abs), '/');
                $files[] = ['name'=>$info->getBasename(),'rel'=>$rel,'abs'=>$abs,'mtime'=>$info->getMTime(),'ext'=>$ext,'size'=>$info->getSize()];
            }
        }
    } else {
        foreach (new DirectoryIterator($dir) as $info) {
            if ($info->isDot() || !$info->isFile()) continue;
            $ext = strtolower(pathinfo($info->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, $exts, true)) continue;
            $abs = wp_normalize_path($info->getPathname());
            $rel = ltrim(str_replace(btp_gal_base_path().'/', '', $abs), '/');
            $files[] = ['name'=>$info->getFilename(),'rel'=>$rel,'abs'=>$abs,'mtime'=>$info->getMTime(),'ext'=>$ext,'size'=>$info->getSize()];
        }
    }
    return $files;
}

function btp_gal_album_signature(string $album, bool $recursive=false): string {
    $dir = btp_gal_get_album_abs_path($album);
    if (!is_dir($dir)) return md5($album.'|empty|rec='.(int)$recursive);
    $latest = 0; $count = 0; $exts = btp_gal_get_allowed_ext();

    if ($recursive) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $info) {
            if ($info->isFile()) {
                $ext = strtolower($info->getExtension());
                if (!in_array($ext, $exts, true)) continue;
                $count++; $latest = max($latest, $info->getMTime());
            }
        }
    } else {
        foreach (new DirectoryIterator($dir) as $info) {
            if ($info->isDot() || !$info->isFile()) continue;
            $ext = strtolower(pathinfo($info->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, $exts, true)) continue;
            $count++; $latest = max($latest, $info->getMTime());
        }
    }

    return md5($album.'|'.$count.'|'.$latest.'|rec='.(int)$recursive);
}

function btp_gal_cached_filelist(string $album, bool $recursive=false): array {
    $album = btp_gal_sanitize_album($album);
    $key   = btp_gal_cache_key($album, $recursive);
    $cached = btp_gal_cache_get($key);
    $sig = btp_gal_album_signature($album, $recursive);

    if (is_array($cached) && isset($cached['sig']) && $cached['sig'] === $sig) {
        return $cached['list'];
    }
    $list = btp_gal_list_images($album, $recursive);
    btp_gal_cache_set($key, ['sig'=>$sig,'list'=>$list]);
    return $list;
}

function btp_gal_sort(array $list, string $field='name', string $dir='ASC'): array {
    $field = in_array($field, ['name','mtime','size','count'], true) ? $field : 'name';
    $dir   = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
    usort($list, function($a,$b) use($field,$dir){
        $va = $a[$field] ?? null; $vb = $b[$field] ?? null;
        $cmp = $va <=> $vb;
        return $dir === 'DESC' ? -$cmp : $cmp;
    });
    return $list;
}

function btp_gal_paginate(array $list, int $page, int $per_page): array {
    $total = max(0, count($list));
    $per_page = max(1, (int)$per_page);
    $page = max(1, (int)$page);
    $offset = ($page-1)*$per_page;
    $items = array_slice($list, $offset, $per_page);
    $pages = (int)ceil($total / $per_page);
    return [$items, ['page'=>$page,'pages'=>$pages,'total'=>$total,'per_page'=>$per_page]];
}

function btp_gal_slugify_album(string $album): string {
    $slug = strtolower(preg_replace('#[^a-z0-9]+#i','-', $album));
    return trim($slug,'-');
}

function btp_gal_humanize_title(string $name): string {
    $t = str_replace(['_', '-'], ' ', $name);
    $t = preg_replace('/(?<=\D)(?=\d)|(?<=\d)(?=\D)/u', ' ', $t);
    $t = preg_replace('/(?<=[a-z])(?=[A-Z])/u', ' ', $t);
    $t = preg_replace('/\s{2,}/', ' ', $t);
    return trim($t);
}

function btp_gal_rel_to_url(string $rel): string {
    $segments = explode('/', $rel);
    $segments = array_map('rawurlencode', $segments);
    return implode('/', $segments);
}

function btp_gal_list_dirs_immediate(string $parent): array {
    $dir = btp_gal_get_album_abs_path($parent);
    if (!is_dir($dir)) return [];
    $out = [];
    foreach (new DirectoryIterator($dir) as $info) {
        if ($info->isDot() || !$info->isDir()) continue;
        $abs = wp_normalize_path($info->getPathname());
        $rel = ltrim(str_replace(btp_gal_base_path().'/', '', $abs), '/');
        $out[] = $rel;
    }
    return $out;
}

function btp_gal_ensure_album_dir(string $album) {
    $abs = btp_gal_get_album_abs_path($album);
    if ($abs === btp_gal_base_path()) return false;
    if (!is_dir($abs) && !wp_mkdir_p($abs)) return false;
    return $abs;
}

function btp_gal_dir_info(string $rel): array {
    $abs = btp_gal_get_album_abs_path($rel);
    if (!is_dir($abs)) return [];
    $has_children = false; $children = [];
    foreach (new DirectoryIterator($abs) as $info) {
        if ($info->isDot()) continue;
        if ($info->isDir()) { $has_children = true; $children[] = $info->getFilename(); }
    }
    $files = btp_gal_list_images($rel, false);
    $count_direct = count($files);
    $cover_rel = $count_direct ? $files[0]['rel'] : null;
    if (!$cover_rel && $has_children) {
        foreach ($children as $child) {
            $cf = btp_gal_list_images($rel.'/'.$child, true);
            if ($cf) { $cover_rel = $cf[0]['rel']; break; }
        }
    }
    return [
        'name' => basename($abs),
        'album' => $rel,
        'has_children' => $has_children,
        'count_direct' => $count_direct,
        'cover_rel' => $cover_rel
    ];
}
