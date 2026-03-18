<?php
defined('ABSPATH') || exit;

function btp_gal_register_assets(){
  wp_register_style('btp-gal', BTP_GAL_URL.'assets/gallery.css', [], '0.3.1');
  wp_register_script('btp-gal', BTP_GAL_URL.'assets/gallery.js', [], '0.3.1', false);
}
function btp_gal_localize_front($extra=[]){
  $data = array_merge(['ajax'=>admin_url('admin-ajax.php')], $extra);
  wp_localize_script('btp-gal','BTP_GAL',$data);
}

function btp_gal_render_pager(array $p): string {
  $current=(int)$p['page']; $total=(int)$p['pages']; if($total<=1) return '';
  $window=max(1,(int)apply_filters('btp_gal_pager_window',2));
  $html='<nav class="btp-gal-pager">';
  if($current>1){ $html.='<a class="prev" href="'.esc_url(add_query_arg('pg',$current-1)).'">‹</a>'; }
  $pages=[1]; for($i=$current-$window;$i<=$current+$window;$i++){ if($i>1 && $i<$total) $pages[]=(int)$i; } if($total>1) $pages[]=$total;
  $pages=array_values(array_unique(array_map('intval',$pages))); sort($pages); $last=0;
  foreach($pages as $pg){ $pg=(int)$pg; $gap=$pg - (int)$last; if($last>0 && $gap>1){ $html.='<span class="btp-gal-ellipsis">…</span>'; } $cls=$pg===$current?' class="current"':''; $html.='<a'.$cls.' href="'.esc_url(add_query_arg('pg',$pg)).'">'.$pg.'</a>'; $last=$pg; }
  if($current<$total){ $html.='<a class="next" href="'.esc_url(add_query_arg('pg',$current+1)).'">›</a>'; }
  $html.='</nav>'; return $html;
}

add_shortcode('btp_gallery', function($atts){
  $defaults=array_merge([
    'album'=>'','per_page'=>24,'order'=>'name','dir'=>'ASC','columns'=>4,
    'lightbox'=>'true','recursive'=>'false','download'=>'true',
    'back_link'=>'','back_label'=>'← Voltar',
    'page'=>isset($_GET['pg'])?(int)$_GET['pg']:1
  ], (array)get_option('btp_gal_defaults',[]));
  $a=shortcode_atts($defaults,$atts,'btp_gallery');
  if(!$a['album'] && isset($_GET['album'])) $a['album']=rawurldecode((string)$_GET['album']);
  if(!$a['album']) return '<p>Álbum não informado.</p>';

  wp_enqueue_style('btp-gal'); wp_enqueue_script('btp-gal'); btp_gal_localize_front();

  $recursive=in_array(strtolower((string)$a['recursive']),['1','true','yes','on'],true);
  $show_dl=in_array(strtolower((string)$a['download']),['1','true','yes','on'],true);

  $list=btp_gal_cached_filelist($a['album'],$recursive); if(empty($list)) return '<p>Nenhuma imagem encontrada.</p>';
  $sorted=btp_gal_sort($list,$a['order'],$a['dir']);
  [$items,$pg]=btp_gal_paginate($sorted,(int)$a['page'],(int)$a['per_page']);

  $html=btp_gal_render_breadcrumb($a['album'],$a['back_link'],$a['back_label']);
  $html.=btp_gal_render_grid($a['album'],$items,$a,$show_dl);
  $html.=btp_gal_render_pager($pg);
  return $html;
});

add_shortcode('btp_gallery_index', function($atts){
  $a=shortcode_atts(['year'=>'','columns'=>4,'order'=>'name','dir'=>'ASC','link'=>'','cover'=>'first','depth'=>1,'title'=>'human'],$atts,'btp_gallery_index');
  wp_enqueue_style('btp-gal');

  $albums=btp_gal_list_albums(trim($a['year']),(int)$a['depth']);
  if(!$albums) return '<p>Nenhuma galeria encontrada.</p>';
  $albums=btp_gal_sort($albums,$a['order'],$a['dir']);
  $cols=max(2,min(6,(int)$a['columns']));

  $base=esc_url(home_url('/'.BTP_GAL_ROUTE.'/serve'));
  $current_url=$a['link']?esc_url($a['link']):esc_url(add_query_arg([]));

  $html='<div class="btp-gal-wrap" data-cols="'.$cols.'"><ul class="btp-gal-grid cols-'.$cols.'">';
  foreach($albums as $al){
    $albumRel=$al['album'];
    $coverRel=$al['cover_rel']?:'';
    $thumb=$coverRel?($base.'/thumb/'.btp_gal_rel_to_url($coverRel)) : '';
    $labelRaw=$al['name'];
    $label=($a['title']==='raw')?$labelRaw:btp_gal_humanize_title($labelRaw);
    $count=(int)$al['count'];
    $link=esc_url(add_query_arg('album',rawurlencode($albumRel),$current_url));

    $html.='<li class="btp-gal-item"><a class="btp-gal-card" href="'.$link.'">';
    if($thumb) $html.='<img loading="lazy" src="'.$thumb.'" alt="'.esc_attr($label).'">';
    $html.='</a><div class="btp-gal-title"><span class="btp-gal-name">'.esc_html($label).'</span><span class="btp-gal-count"> ('.$count.')</span></div></li>';
  }
  $html.='</ul></div>';
  return $html;
});

add_shortcode('btp_gallery_tree', function($atts){
  $a=shortcode_atts(['year'=>'','columns'=>4,'link'=>'','title'=>'human','open'=>'','back_label'=>'← Voltar','sep'=>' / ','root_label'=>''],$atts,'btp_gallery_tree');
  wp_enqueue_style('btp-gal'); wp_enqueue_script('btp-gal'); btp_gal_localize_front(['link'=>$a['link']]);

  $root=trim($a['year']); if($root==='') return '<p>Informe year="2025".</p>';
  $cols=max(2,min(6,(int)$a['columns']));
  $base=esc_url(home_url('/'.BTP_GAL_ROUTE.'/serve'));

  $dirs=btp_gal_list_dirs_immediate($root); if(!$dirs) return '<p>Nenhuma subpasta.</p>';

  $html=sprintf('<div class="btp-gal-tree" data-root="%s" data-title="%s" data-link="%s" data-cols="%d" data-open="%s" data-sep="%s" data-back="%s" data-root-label="%s">',
     esc_attr($root),esc_attr($a['title']),esc_attr($a['link']),(int)$cols,esc_attr($a['open']),esc_attr($a['sep']),esc_attr($a['back_label']),esc_attr($a['root_label']));
  $html.='<div class="btp-tree-header"><a href="#" class="btp-tree-back" style="display:none">'.esc_html($a['back_label']).'</a><nav class="btp-breadcrumb"></nav></div>';
  $html.='<div class="btp-tree-grid"><ul class="btp-gal-grid cols-'.$cols.'">';

  foreach($dirs as $rel){
    $info=btp_gal_dir_info($rel);
    $label=($a['title']==='raw')?$info['name']:btp_gal_humanize_title($info['name']);
    $thumb=$info['cover_rel']?($base.'/thumb/'.btp_gal_rel_to_url($info['cover_rel'])):'';
    $isLeaf=(!$info['has_children'] && $info['count_direct']>0);

    $leafHref = ($isLeaf && !empty($a['link']))
        ? esc_url( add_query_arg('album', rawurlencode($rel), $a['link']) )
        : '#';

    $html.='<li class="btp-gal-item btp-tree-node" data-album="'.esc_attr($rel).'" data-leaf="'.($isLeaf?1:0).'">';
    $html.='<a class="btp-gal-card btp-tree-toggle" href="'.$leafHref.'">';
    if($thumb) $html.='<img loading="lazy" src="'.$thumb.'" alt="'.esc_attr($label).'">';
    $html.='</a><div class="btp-gal-title"><span class="btp-gal-name">'.esc_html($label).'</span>';
    if($isLeaf){ $html.='<span class="btp-gal-count"> ('.(int)$info['count_direct'].')</span>'; }
    $html.='</div></li>';
  }

  $html.='</ul></div></div>';
  return $html;
});

add_action('wp_ajax_btp_gal_tree_children','btp_gal_tree_children');
add_action('wp_ajax_nopriv_btp_gal_tree_children','btp_gal_tree_children');
function btp_gal_tree_children(){
  $parent=isset($_POST['parent'])?btp_gal_sanitize_album((string)wp_unslash($_POST['parent'])):'';
  $title =isset($_POST['title']) ?sanitize_text_field((string)wp_unslash($_POST['title'])):'human';
  $cols  =isset($_POST['cols'])  ?(int)$_POST['cols']:4;
  $link  =isset($_POST['link'])  ?esc_url_raw((string)wp_unslash($_POST['link'])):'';
  if(!$parent) wp_send_json_error(['msg'=>'parent vazio']);
  $dirs=btp_gal_list_dirs_immediate($parent);
  $base=esc_url(home_url('/'.BTP_GAL_ROUTE.'/serve'));

  $items=[];
  foreach($dirs as $rel){
    $info=btp_gal_dir_info($rel);
    $isLeaf=(!$info['has_children'] && $info['count_direct']>0);
    $label=($title==='raw')?$info['name']:btp_gal_humanize_title($info['name']);
    $thumb=$info['cover_rel']?($base.'/thumb/'.btp_gal_rel_to_url($info['cover_rel'])):'';
    $items[]=['album'=>$rel,'label'=>$label,'thumb'=>$thumb,'leaf'=>$isLeaf?1:0,'count'=>(int)$info['count_direct']];
  }
  wp_send_json_success(['items'=>$items,'cols'=>$cols,'link'=>$link]);
}

function btp_gal_render_breadcrumb(string $album, string $back_link, string $back_label): string {
  if(!$album) return '';

  $segments = explode('/', $album);
  $base_url = $back_link ? esc_url($back_link) : esc_url(remove_query_arg(['album','pg']));

  $html = '<nav class="btp-gal-breadcrumb">';

  if($back_link) {
    $html .= '<a class="btp-gal-back" href="'.$base_url.'">'.esc_html($back_label).'</a>';
    $html .= '<span class="btp-bc-sep">|</span>';
  }

  $accumulated = '';
  foreach($segments as $i => $seg) {
    $accumulated = $accumulated ? $accumulated.'/'.$seg : $seg;
    $is_last     = ($i === count($segments) - 1);
    $label       = esc_html(btp_gal_humanize_title($seg));

    if($is_last) {
      $html .= '<span class="btp-bc-current">'.$label.'</span>';
    } elseif($back_link) {
      $url   = esc_url(add_query_arg('album', rawurlencode($accumulated), $base_url));
      $html .= '<a class="btp-bc-link" href="'.$url.'">'.$label.'</a>';
      $html .= '<span class="btp-bc-sep">/</span>';
    } else {
      $html .= '<span class="btp-bc-text">'.$label.'</span>';
      $html .= '<span class="btp-bc-sep">/</span>';
    }
  }

  $html .= '</nav>';
  return $html;
}

function btp_gal_render_grid(string $album, array $items, array $args, bool $show_dl=true): string {
  $cols=max(2,min(6,(int)$args['columns']));
  $slug=esc_attr(btp_gal_slugify_album($album));
  $lightbox=($args['lightbox']==='true'||$args['lightbox']===true);
  $base=esc_url(home_url('/'.BTP_GAL_ROUTE.'/serve'));

  $html='<div class="btp-gal-wrap" data-cols="'.$cols.'"><ul class="btp-gal-grid cols-'.$cols.'">';
  foreach($items as $i=>$f){
    $rel=btp_gal_rel_to_url($f['rel']);
    $thumb=$base.'/thumb/'.$rel;
    $large=$base.'/large/'.$rel;
    $raw  =$base.'/raw/'.$rel;
    $alt=esc_attr(pathinfo($f['name'],PATHINFO_FILENAME));
    $filename=esc_attr($f['name']);

    $html.='<li class="btp-gal-item"><div class="btp-gal-card-wrap">';
    if($lightbox){
      $html.='<a class="btp-gal-card" href="'.$large.'" data-lightbox="album-'.$slug.'" data-index="'.$i.'" data-file="'.$filename.'">';
    } else {
      $html.='<a class="btp-gal-card" href="'.$large.'" target="_blank" rel="noopener">';
    }
    $html.='<img loading="lazy" src="'.$thumb.'" alt="'.$alt.'"></a>';

    if($show_dl){
      $html.='<a class="btp-gal-dl" href="'.$raw.'?download=1" download="'.$filename.'" title="Baixar arquivo">Baixar</a>';
    }

    $html.='</div></li>';
  }
  $html.='</ul></div>';
  return $html;
}
