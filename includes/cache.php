<?php
defined('ABSPATH') || exit;

function btp_gal_cache_key(string $album, bool $recursive=false): string {
    $album = preg_replace('#[^a-z0-9_\-/]#i','',$album);
    return 'btp_gal:list:'.md5($album).'|rec='.(int)$recursive;
}
function btp_gal_cache_get(string $key){ return get_transient($key); }
function btp_gal_cache_set(string $key, $value, int $ttl=BTP_GAL_CACHE_TTL){ return set_transient($key,$value,$ttl); }
function btp_gal_cache_del(string $key){ return delete_transient($key); }
