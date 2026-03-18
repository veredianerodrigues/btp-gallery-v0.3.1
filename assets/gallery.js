(function(){
  // ---- Lightbox ----
  var state={list:[],index:-1,group:null};
  function ensureLB(){
    var lb=document.querySelector('.btp-lightbox');
    if(!lb){
      lb=document.createElement('div');
      lb.className='btp-lightbox';
      lb.innerHTML='<span class="nav prev">‹</span><img alt=""><span class="nav next">›</span><span class="close">✕</span><a class="download" href="#" download>Baixar</a>';
      document.body.appendChild(lb);
      lb.addEventListener('click',function(e){
        if(e.target===lb||e.target.classList.contains('close')) closeLB();
        if(e.target.classList.contains('prev')) nav(-1);
        if(e.target.classList.contains('next')) nav(1);
      });
    }
    return lb;
  }
  function openLB(href,group,index){
    var lb=ensureLB();
    state.group=group;
    state.list=Array.from(document.querySelectorAll('a[data-lightbox="'+group+'"]')).map(function(a){return a.getAttribute('href');});
    state.index=(typeof index==='number')?index:state.list.indexOf(href);
    setLBSource(href);
    lb.classList.add('show');
  }
  function setLBSource(href){
    var lb=document.querySelector('.btp-lightbox'); if(!lb) return;
    lb.querySelector('img').src=href;
    var dl=lb.querySelector('.download');
    var raw=href.replace('/large/','/raw/');
    dl.href=raw+'?download=1';
    try{ var fn=(new URL(href, window.location.href)).pathname.split('/').pop(); if(fn) dl.setAttribute('download', fn); }catch(e){}
  }
  function closeLB(){var lb=document.querySelector('.btp-lightbox'); if(lb) lb.classList.remove('show'); state.index=-1; state.group=null; state.list=[];}
  function nav(d){ if(state.index<0) return; var len=state.list.length; state.index=(state.index+d+len)%len; setLBSource(state.list[state.index]); }
  document.addEventListener('click',function(e){
    var a=e.target.closest('a[data-lightbox]'); if(!a) return;
    e.preventDefault(); openLB(a.getAttribute('href'),a.getAttribute('data-lightbox'),parseInt(a.getAttribute('data-index')||'-1',10));
  });
  document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeLB(); if(e.key==='ArrowRight') nav(1); if(e.key==='ArrowLeft') nav(-1); if(e.key.toLowerCase()==='d'){ var lb=document.querySelector('.btp-lightbox .download'); if(lb){ lb.click(); }} });

  // ---- Árvore ----
  function humanize(s){return (s||'').replace(/[_-]+/g,' ').replace(/(?<=\D)(?=\d)|(?<=\d)(?=\D)/g,' ').replace(/(?<=[a-z])(?=[A-Z])/g,' ').replace(/\s{2,}/g,' ').trim();}
  function parentOf(p){var i=p.lastIndexOf('/');return i>0?p.slice(0,i):'';}
  function escapeHtml(s){return (s||'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');}

  function renderGrid(node,data){
    var wrap=node.querySelector('.btp-tree-grid'); if(!wrap) return;
    var ul=document.createElement('ul'); ul.className='btp-gal-grid cols-'+data.cols;
    (data.items||[]).forEach(function(it){
      var li=document.createElement('li'); li.className='btp-gal-item btp-tree-node'; li.setAttribute('data-album',it.album); li.setAttribute('data-leaf',it.leaf?'1':'0');
      var a=document.createElement('a'); a.className='btp-gal-card btp-tree-toggle';
      a.href=(it.leaf && data.link) ? (data.link+(data.link.indexOf('?')>-1?'&':'?')+'album='+encodeURIComponent(it.album)) : '#';
      if(it.thumb){ var img=document.createElement('img'); img.loading='lazy'; img.src=it.thumb; img.alt=it.label; a.appendChild(img); }
      li.appendChild(a);
      var title=document.createElement('div'); title.className='btp-gal-title';
      var name=document.createElement('span'); name.className='btp-gal-name'; name.textContent=it.label; title.appendChild(name);
      if(it.leaf){ var count=document.createElement('span'); count.className='btp-gal-count'; count.textContent=' ('+it.count+')'; title.appendChild(count); }
      li.appendChild(title);
      ul.appendChild(li);
    });
    wrap.innerHTML=''; wrap.appendChild(ul);
  }

  function bc(node,album){
    var nav=node.querySelector('.btp-breadcrumb'); if(!nav) return;
    var root=node.getAttribute('data-root')||'';
    var sep=node.getAttribute('data-sep')||' / ';
    var rootLabel=node.getAttribute('data-root-label')||humanize(root.split('/').pop());
    var parts=[];
    if(album&&album.startsWith(root)){ var rel=album.slice(root.length).replace(/^\//,''); if(rel){ parts=rel.split('/'); } }
    var html=''; html+='<a href="#" class="crumb" data-target="'+root+'">'+escapeHtml(rootLabel)+'</a>';
    var cur=root;
    for(var i=0;i<parts.length;i++){ cur+='/'+parts[i]; html+='<span class="sep">'+sep+'</span>';
      if(i===parts.length-1){ html+='<span class="current">'+escapeHtml(humanize(parts[i]))+'</span>'; }
      else { html+='<a href="#" class="crumb" data-target="'+cur+'">'+escapeHtml(humanize(parts[i]))+'</a>'; } }
    nav.innerHTML=html;
  }
  function setBack(node,album){
    var back=node.querySelector('.btp-tree-back'); if(!back) return;
    var root=node.getAttribute('data-root')||''; var label=node.getAttribute('data-back')||'← Voltar';
    back.textContent=label;
    if(album&&album!==root){ back.style.display='inline-block'; back.setAttribute('data-target',parentOf(album)||root); }
    else { back.style.display='none'; back.removeAttribute('data-target'); }
  }

  function load(node,album,opt){
    var fd=new FormData();
    fd.append('action','btp_gal_tree_children');
    fd.append('parent',album);
    fd.append('title',node.getAttribute('data-title')||'human');
    fd.append('link',node.getAttribute('data-link')||'');
    fd.append('cols',node.getAttribute('data-cols')||'4');
    fetch((window.BTP_GAL&&BTP_GAL.ajax)||'/wp-admin/admin-ajax.php',{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(resp){
        if(!resp||!resp.success) return;
        renderGrid(node,resp.data);
        bc(node,album); setBack(node,album);
        node.setAttribute('data-current',album);
        if(opt&&opt.push){ try{ history.pushState({btpgal:true,album:album},'',window.location.pathname+window.location.search); }catch(e){} }
      });
  }

  document.addEventListener('click',function(e){
    var t=e.target.closest('.btp-tree-toggle'); if(!t) return;
    var link=t.getAttribute('href');
    if(link && link!=='#'){ return; }
    e.preventDefault();
    var tree=t.closest('.btp-gal-tree'); if(!tree) return;
    var item=t.closest('.btp-tree-node'); var album=item.getAttribute('data-album'); var leaf=item.getAttribute('data-leaf')==='1';
    var page=tree.getAttribute('data-link')||(window.BTP_GAL&&BTP_GAL.link)||'';
    if(leaf && page){
      window.location.href = page + (page.indexOf('?')>-1?'&':'?') + 'album=' + encodeURIComponent(album);
      return;
    }
    load(tree,album,{push:true});
  });

  document.addEventListener('click',function(e){
    var b=e.target.closest('.btp-tree-back'); if(!b) return;
    e.preventDefault();
    var tree=b.closest('.btp-gal-tree'); if(!tree) return;
    var target=b.getAttribute('data-target')||tree.getAttribute('data-root');
    load(tree,target,{push:true});
  });

  document.addEventListener('click',function(e){
    var c=e.target.closest('.btp-breadcrumb a.crumb'); if(!c) return;
    e.preventDefault();
    var tree=c.closest('.btp-gal-tree'); if(!tree) return;
    var target=c.getAttribute('data-target');
    load(tree,target,{push:true});
  });

  window.addEventListener('popstate',function(){
    var tree=document.querySelector('.btp-gal-tree'); if(!tree) return;
    var cur=tree.getAttribute('data-current')||tree.getAttribute('data-root');
    load(tree,cur,{push:false});
  });

  document.addEventListener('DOMContentLoaded',function(){
    var tree=document.querySelector('.btp-gal-tree'); if(!tree) return;
    bc(tree,tree.getAttribute('data-root')||''); setBack(tree,tree.getAttribute('data-root')||'');
    var open=tree.getAttribute('data-open'); if(open){ load(tree,open,{push:false}); }
  });
})();