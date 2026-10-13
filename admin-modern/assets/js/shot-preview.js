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
    style.textContent='.admin-shot-viewer{position:fixed;inset:0;z-index:10000;display:grid;place-items:center;padding:28px}.admin-shot-viewer[hidden]{display:none}.admin-shot-viewer-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.78);cursor:pointer}.admin-shot-viewer-stage{position:relative;z-index:1;max-width:calc(100vw - 56px);max-height:calc(100vh - 56px);display:flex;align-items:center;justify-content:center}.admin-shot-viewer-image{display:block;max-width:100%;max-height:calc(100vh - 56px);width:auto;height:auto;object-fit:contain;border-radius:10px;box-shadow:0 18px 60px rgba(0,0,0,.45);cursor:pointer}.admin-shot-viewer-open{overflow:hidden}';
    document.head.appendChild(style);

    var image=modal.querySelector('.admin-shot-viewer-image');
    var lastTrigger=null;

    function openViewer(link){
        lastTrigger=link;
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
        if(lastTrigger) lastTrigger.focus();
    }

    links.forEach(function(link){
        link.removeAttribute('target');
        link.removeAttribute('rel');
        link.addEventListener('click',function(event){
            event.preventDefault();
            openViewer(link);
        });
    });
    modal.querySelectorAll('[data-shot-viewer-close]').forEach(function(el){
        el.addEventListener('click',closeViewer);
    });
    document.addEventListener('keydown',function(event){
        if(event.key==='Escape'&&!modal.hidden) closeViewer();
    });
})();
