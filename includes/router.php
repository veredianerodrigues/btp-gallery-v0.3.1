<?php
defined('ABSPATH') || exit;
function btp_gal_register_rewrite(){
  add_rewrite_tag('%btp_gal_size%','([^&]+)');
  add_rewrite_tag('%btp_gal_path%','(.+)');
  add_rewrite_rule('^'.BTP_GAL_ROUTE.'/serve/([^/]+)/(.*)$','index.php?btp_gal_size=$matches[1]&btp_gal_path=$matches[2]','top');
}
add_filter('query_vars', function($v){ $v[]='btp_gal_size'; $v[]='btp_gal_path'; return $v; });
add_filter('redirect_canonical', function($r,$req){ if(get_query_var('btp_gal_size') && get_query_var('btp_gal_path')) return false; return $r; },10,2);
add_action('template_redirect', function(){ $s=get_query_var('btp_gal_size'); $p=get_query_var('btp_gal_path'); if(!$s||!$p) return; btp_gal_serve_image($s,$p); exit; },0);
