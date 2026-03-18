<?php
defined('ABSPATH') || exit;

function btp_gal_list_albums(string $parent='', int $depth=1): array {
    $startDir = $parent ? btp_gal_get_album_abs_path($parent) : btp_gal_base_path();
    if (!is_dir($startDir)) return [];

    $maxDepth = max(1, (int)$depth);
    $albums = [];

    $start = wp_normalize_path($startDir);
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($startDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($rii as $info) {
        if (!$info->isDir()) continue;
        $abs = wp_normalize_path($info->getPathname());
        if ($abs === $start) continue;
        if (basename($abs) === '.thumbs') continue;

        $albumRel = ltrim(str_replace(btp_gal_base_path().'/', '', $abs), '/');
        $level = $albumRel === '' ? 0 : substr_count($albumRel, '/') - substr_count(trim($parent,'/'), '/');
        if ($level >= $maxDepth) continue;

        $files = btp_gal_list_images($albumRel, false);
        $count = count($files);
        $latest = $count ? max(array_column($files, 'mtime')) : 0;
        $cover_rel = $count ? $files[0]['rel'] : null;
        $albums[] = [
            'name' => basename($abs),
            'album' => $albumRel,
            'count' => $count,
            'mtime' => $latest,
            'cover_rel' => $cover_rel
        ];
    }

    return $albums;
}
