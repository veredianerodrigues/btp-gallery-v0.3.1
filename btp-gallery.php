<?php
/*
 * Plugin Name: BTP Gallery (Filesystem Albums)
 * Description: Lê pastas do disco (E:/uploads/btp/galerias) e exibe como álbuns via shortcodes (índice, árvore e galeria).
 * Version:     0.3.1
 * Author:      Verediane Rodrigues
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) exit;

define('BTP_GAL_PATH', plugin_dir_path(__FILE__));
define('BTP_GAL_URL',  plugin_dir_url(__FILE__));

require_once BTP_GAL_PATH.'includes/config.php';
require_once BTP_GAL_PATH.'includes/cache.php';
require_once BTP_GAL_PATH.'includes/filesystem.php';
require_once BTP_GAL_PATH.'includes/albums.php';
require_once BTP_GAL_PATH.'includes/images.php';
require_once BTP_GAL_PATH.'includes/router.php';
require_once BTP_GAL_PATH.'includes/shortcode.php';
require_once BTP_GAL_PATH.'includes/admin.php';

register_activation_hook(__FILE__, function(){
    btp_gal_register_rewrite();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function(){
    flush_rewrite_rules();
});

add_action('init', function(){
    btp_gal_register_rewrite();
    btp_gal_register_assets();
});
