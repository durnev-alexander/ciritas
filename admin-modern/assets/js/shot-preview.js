(function(){
    var params=new URLSearchParams(window.location.search);
    var productId=params.get('id')||'0';
    var prefix='ciritas_resource_scroll_'+productId;
    var positionKey=prefix+'_y';
    var pendingKey=prefix+'_pending';
    var restoreY=null;

    try{
        if(sessionStorage.getItem(pendingKey)==='1'){
            var saved=parseInt(sessionStorage.getItem(positionKey)||'0',10);
            if(Number.isFinite(saved)&&saved>=0) restoreY=saved;
            sessionStorage.removeItem(pendingKey);
            sessionStorage.removeItem(positionKey);
        }
    }catch(e){}

    function restoreScroll(){
        if(restoreY===null) return;
        window.scrollTo(0,restoreY);
    }
    if(restoreY!==null){
        requestAnimationFrame(function(){requestAnimationFrame(restoreScroll);});
        window.addEventListener('load',restoreScroll,{once:true});
    }

    document.addEventListener('submit',function(event){
        var form=event.target;
        if(!(form instanceof HTMLFormElement)) return;
        try{
            sessionStorage.setItem(positionKey,String(Math.max(0,window.scrollY||window.pageYOffset||0)));
            sessionStorage.setItem(pendingKey,'1');
        }catch(e){}
    },true);
})();

(function(){
    var frames=[].slice.call(document.querySelectorAll('.js-text-section-preview-frame'));
    if(!frames.length) return;

    var cropStyle=document.createElement('style');
    cropStyle.textContent='.text-section-preview .js-text-section-preview-frame{width:512px!important;overflow:hidden!important}';
    document.head.appendChild(cropStyle);

    var gutterCss='<style id="ciritas-preview-gutter-style">html{width:512px!important;min-width:512px!important;max-width:512px!important;overflow:hidden!important;scrollbar-width:none!important;-ms-overflow-style:none!important}html::-webkit-scrollbar,body::-webkit-scrollbar{width:0!important;height:0!important;display:none!important}body{width:472px!important;min-width:472px!important;max-width:472px!important;overflow:visible!important;padding-right:18px!important}</style>';

    function applyPreviewGutter(frame){
        if(!frame) return;
        frame.setAttribute('scrolling','no');
        var source=frame.srcdoc||'';
        if(source===''||source.indexOf('ciritas-preview-gutter-style')!==-1) return;
        if(source.indexOf('</head>')!==-1) source=source.replace('</head>',gutterCss+'</head>');
        else source=gutterCss+source;
        frame.srcdoc=source;
    }

    frames.forEach(function(frame){
        applyPreviewGutter(frame);
        new MutationObserver(function(mutations){
            mutations.forEach(function(mutation){
                if(mutation.attributeName==='srcdoc') setTimeout(function(){applyPreviewGutter(frame);},0);
            });
        }).observe(frame,{attributes:true,attributeFilter:['srcdoc']});
    });
})();

(function(){
    var links=[].slice.call(document.querySelectorAll('.asset-shot-preview[href]'));
    if(!links.length) return;

    var modal=document.createElement('div');
    modal.className='admin-shot-viewer';
    modal.hidden=true;
    modal.setAttribute('aria-hidden','true');
    modal.innerHTML='<div class="admin-shot-viewer-backdrop" data-shot-viewer-close></div><div class="admin-shot-viewer-stage" role="dialog" aria-modal="true" aria-label="Просмотр скриншота"><img class="admin-shot-viewer-image" alt="" data-shot-viewer-close></div>';
    document.body.appendChild(modal);

    var style=document.createElement('style');
    style.textContent='.admin-shot-viewer{position:fixed;inset:0;z-index:10000;display:grid;place-items:center;padding:28px}.admin-shot-viewer[hidden]{display:none}.admin-shot-viewer-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.78);cursor:pointer}.admin-shot-viewer-stage{position:relative;z-index:1;max-width:calc(100vw - 56px);max-height:calc(100vh - 56px);display:flex;align-items:center;justify-content:center}.admin-shot-viewer-image{display:block;max-width:100%;max-height:calc(100vh - 56px);width:auto;height:auto;object-fit:contain;border-radius:10px;box-shadow:0 18px 60px rgba(0,0,0,.45);cursor:pointer}.admin-shot-viewer-open{overflow:hidden}.asset-shots-table tbody tr.js-shot-viewer-row{cursor:pointer}.asset-shots-table tbody tr.js-shot-viewer-row:hover{background:rgba(57,137,205,.07)}';
    document.head.appendChild(style);

    var image=modal.querySelector('.admin-shot-viewer-image');
    var lastTrigger=null;

    function openViewer(link,trigger){
        lastTrigger=trigger||link;
        image.src=link.href;
        image.alt=(link.querySelector('img')||{}).alt||'';
        modal.hidden=false;
        modal.setAttribute('aria-hidden','false');
        document.body.classList.add('admin-shot-viewer-open');
    }
    function closeViewer(){
        modal.hidden=true;
        modal.setAttribute('aria-hidden','true');
        image.removeAttribute('src');
        document.body.classList.remove('admin-shot-viewer-open');
        if(lastTrigger&&typeof lastTrigger.focus==='function') lastTrigger.focus();
    }

    links.forEach(function(link){
        link.removeAttribute('target');
        link.removeAttribute('rel');
        link.addEventListener('click',function(event){
            event.preventDefault();
            event.stopPropagation();
            openViewer(link,link);
        });

        var row=link.closest('tr');
        if(!row) return;
        row.classList.add('js-shot-viewer-row');
        row.tabIndex=0;
        row.addEventListener('click',function(event){
            if(event.target.closest('input,button,select,textarea,label,a,form')) return;
            openViewer(link,row);
        });
        row.addEventListener('keydown',function(event){
            if((event.key==='Enter'||event.key===' ')&&!event.target.closest('input,button,select,textarea,label,a')){
                event.preventDefault();
                openViewer(link,row);
            }
        });
    });
    modal.querySelectorAll('[data-shot-viewer-close]').forEach(function(el){
        el.addEventListener('click',closeViewer);
    });
    document.addEventListener('keydown',function(event){
        if(event.key==='Escape'&&!modal.hidden) closeViewer();
    });
})();
