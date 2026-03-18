<?php
defined('ABSPATH') || exit;

if (!defined('BTP_GAL_BASE_PATH')) define('BTP_GAL_BASE_PATH', 'E:/uploads/btp/galerias');
if (!defined('BTP_GAL_ROUTE'))     define('BTP_GAL_ROUTE', 'btp-gallery');
if (!defined('BTP_GAL_CACHE_TTL')) define('BTP_GAL_CACHE_TTL', 600);

if (!defined('BTP_GAL_THUMB_SIZES')) define('BTP_GAL_THUMB_SIZES', serialize([
    'thumb' => ['w'=>360,'h'=>360,'fit'=>'cover'],
    'large' => ['w'=>1440,'h'=>0,'fit'=>'contain'],
]));

if (!defined('BTP_GAL_ALLOWED_EXT')) define('BTP_GAL_ALLOWED_EXT', serialize(['jpg','jpeg','png','gif','webp']));

if (!defined('BTP_GAL_DEBUG')) define('BTP_GAL_DEBUG', false);
