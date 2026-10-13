<?php
require_once __DIR__.'/../../app/product_text_sections.php';
$productId=(int)($_GET['id']??0);
if($productId<1)return;
$sections=product_text_sections_all($productId,false);
$sectionContents=[];
foreach($sections as $section){
    $sectionContents[(int)$section['ID']]=a_legacy((string)($section['Content']??''));
}
?>
<div id="product-text-sections" class="card product-text-sections-card" style="margin-top:18px">
    <div class="section-title" style="margin-bottom:16px">
        <div><h2>Текстовые разделы</h2><div class="help">Дополнительные HTML-разделы выводятся на странице программы после блока «Возможности».</div></div>
        <div class="admin-products-summary">Всего: <?=count($sections)?></div>
    </div>
    <style>
        .product-text-sections-table{table-layout:auto}
        .product-text-sections-table .text-section-preview-col{width:240px;min-width:240px}
        .product-text-sections-table .text-section-title{width:100%}
        .product-text-sections-table .text-section-active,.product-text-sections-table .text-section-sort,.product-text-sections-table .text-section-action{width:1%;white-space:nowrap}
        .product-text-sections-table .actions{flex-wrap:nowrap}
        .product-text-sections-table tbody tr.text-section-row{cursor:pointer}
        .product-text-sections-table tbody tr.text-section-row:hover{background:rgba(57,137,205,.07)}
        .product-text-sections-table .text-section-position{width:100%;min-width:76px;text-align:center}
        .product-text-sections-table .text-section-add{min-width:150px;width:100%}
        .text-section-preview{position:relative;width:240px;height:180px;overflow:hidden;border:1px solid var(--admin-line);border-radius:8px;background:var(--admin-card);user-select:none;-webkit-user-select:none;cursor:pointer}
        .text-section-preview iframe{display:block;width:480px;height:360px;border:0;transform:scale(.5);transform-origin:0 0;pointer-events:none;user-select:none;-webkit-user-select:none;background:transparent}
        .text-section-preview-empty{display:flex;align-items:center;justify-content:center;color:var(--admin-muted);font-size:13px;text-align:center;padding:16px}
        .product-text-editor-modal .admin-modal-dialog{width:min(920px,calc(100vw - 40px));max-width:920px}
        .product-text-editor-modal .field{margin-top:14px}
        .text-section-hidden-form{display:none}
    </style>
    <div class="table-wrap">
        <table class="table product-text-sections-table">
            <thead><tr><th class="text-section-preview-col">Превью</th><th class="text-section-title">Заголовок</th><th class="text-section-active">Активен</th><th class="text-section-sort">Сортировка</th><th class="text-section-action">Действия</th></tr></thead>
            <tbody>
            <?php foreach($sections as $i=>$section): $sid=(int)$section['ID']; $formId='textSectionRow'.$sid; $sectionTitle=a_legacy((string)$section['Title']); ?>
                <tr class="text-section-row js-text-section-open" data-section-id="<?=$sid?>" data-section-title="<?=aesc($sectionTitle)?>">
                    <td class="text-section-preview-col">
                        <div class="text-section-preview js-text-section-preview" data-section-id="<?=$sid?>" data-section-title="<?=aesc($sectionTitle)?>" title="Открыть HTML-редактор">
                            <iframe class="js-text-section-preview-frame" title="Превью: <?=aesc($sectionTitle)?>" tabindex="-1" sandbox=""></iframe>
                        </div>
                    </td>
                    <td class="text-section-title">
                        <form id="<?=$formId?>" class="text-section-hidden-form" method="post" action="product-text-section-action.php"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="product_id" value="<?=$productId?>"><input type="hidden" name="section_id" value="<?=$sid?>"></form>
                        <input class="input" form="<?=$formId?>" name="title" value="<?=aesc($sectionTitle)?>">
                    </td>
                    <td class="text-section-active"><input form="<?=$formId?>" type="checkbox" name="active" <?=!empty($section['IsActive'])?'checked':''?>></td>
                    <td class="text-section-sort"><div class="actions"><button class="btn sort-btn" form="<?=$formId?>" name="action" value="sort_up" <?=$i===0?'disabled':''?> title="Выше">↑</button><button class="btn sort-btn" form="<?=$formId?>" name="action" value="sort_down" <?=$i===count($sections)-1?'disabled':''?> title="Ниже">↓</button></div></td>
                    <td class="text-section-action"><div class="actions"><button class="btn" form="<?=$formId?>" name="action" value="save">Сохранить</button><button class="btn danger js-text-section-delete" type="button" data-section-id="<?=$sid?>" data-section-title="<?=aesc($sectionTitle)?>">Удалить</button></div></td>
                </tr>
            <?php endforeach; ?>
                <tr>
                    <td class="text-section-preview-col"><div class="text-section-preview text-section-preview-empty">Превью появится после добавления</div></td>
                    <td class="text-section-title">
                        <form id="textSectionAdd" class="text-section-hidden-form" method="post" action="product-text-section-action.php"><input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>"><input type="hidden" name="product_id" value="<?=$productId?>"></form>
                        <input class="input" form="textSectionAdd" name="title" placeholder="Заголовок раздела">
                    </td>
                    <td class="text-section-active"><input form="textSectionAdd" type="checkbox" name="active" checked></td>
                    <td class="text-section-sort"><input class="input text-section-position" form="textSectionAdd" type="number" name="position" min="1" max="<?=count($sections)+1?>" value="<?=count($sections)+1?>" title="Место в списке"></td>
                    <td class="text-section-action"><button class="btn primary text-section-add" form="textSectionAdd" name="action" value="add">Добавить</button></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div id="productTextEditorModal" class="admin-modal product-text-editor-modal" hidden aria-hidden="true">
    <div class="admin-modal-backdrop" data-text-editor-close></div>
    <div class="admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="productTextEditorTitle">
        <h2 id="productTextEditorTitle">Редактирование текста</h2>
        <form id="productTextEditorForm" method="post" action="product-text-section-action.php">
            <input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>">
            <input type="hidden" name="product_id" value="<?=$productId?>">
            <input type="hidden" id="productTextEditorSectionId" name="section_id" value="">
            <input type="hidden" name="action" value="content_save">
            <div class="field"><textarea id="productTextEditorContent" class="textarea html-editor" name="content" rows="14"></textarea></div>
            <div class="admin-modal-actions"><button class="btn primary" type="submit">Сохранить</button><button class="btn" type="button" data-text-editor-close>Отмена</button></div>
        </form>
    </div>
</div>

<div id="productTextDeleteModal" class="admin-modal" hidden aria-hidden="true">
    <div class="admin-modal-backdrop" data-text-delete-close></div>
    <div class="admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="productTextDeleteTitle">
        <h2 id="productTextDeleteTitle">Подтверждение удаления</h2>
        <p>Удалить текстовый раздел?<span id="productTextDeleteName" class="admin-modal-product"></span></p>
        <form method="post" action="product-text-section-action.php" class="admin-modal-actions">
            <input type="hidden" name="_csrf" value="<?=aesc(csrf_token())?>">
            <input type="hidden" name="product_id" value="<?=$productId?>">
            <input type="hidden" id="productTextDeleteId" name="section_id" value="">
            <input type="hidden" name="action" value="delete">
            <button class="btn" type="button" data-text-delete-close>Отмена</button>
            <button class="btn" type="submit">Удалить</button>
        </form>
    </div>
</div>
<script>
window.ciritasProductTextContents=<?=json_encode($sectionContents,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
(function(){
    var card=document.getElementById('product-text-sections');
    if(card){
        var backLinks=[].slice.call(document.querySelectorAll('.admin-content > .actions'));
        var target=backLinks.length?backLinks[backLinks.length-1]:null;
        if(target&&target.parentNode)target.parentNode.insertBefore(card,target);
    }
    var editorModal=document.getElementById('productTextEditorModal');
    var editorId=document.getElementById('productTextEditorSectionId');
    var editorTitle=document.getElementById('productTextEditorTitle');
    var editorText=document.getElementById('productTextEditorContent');
    var lastTrigger=null;
    function decodeHtml(value){
        value=value||'';
        for(var i=0;i<2&&/&lt;\/?[a-z][\s\S]*?&gt;/i.test(value);i++){
            var box=document.createElement('textarea');
            box.innerHTML=value;
            value=box.value;
        }
        return value;
    }
    function escapeHtml(value){
        return String(value||'').replace(/[&<>"']/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];});
    }
    function previewDocument(title,content){
        content=decodeHtml(content);
        return '<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;padding:0;background:transparent!important;color:#17283c;font-family:Arial,sans-serif}body{padding:18px;font-size:14px;line-height:1.45}h1,h2,h3{margin:0 0 12px;line-height:1.25}h2{font-size:23px}p{margin:0 0 10px}img{max-width:100%;height:auto}table{max-width:100%;border-collapse:collapse}*{box-sizing:border-box;background-color:transparent!important;background-image:none!important}</style></head><body><h2>'+escapeHtml(title)+'</h2>'+content+'</body></html>';
    }
    document.querySelectorAll('.js-text-section-preview').forEach(function(preview){
        var id=preview.dataset.sectionId||'';
        var title=preview.dataset.sectionTitle||'';
        var frame=preview.querySelector('.js-text-section-preview-frame');
        if(frame)frame.srcdoc=previewDocument(title,(window.ciritasProductTextContents||{})[id]||'');
    });
    function setEditorContent(value){
        value=decodeHtml(value);
        editorText.value=value;
        if(window.tinymce){var ed=tinymce.get('productTextEditorContent');if(ed)ed.setContent(value,{format:'html'});}
    }
    function focusEditor(attempt){
        attempt=attempt||0;
        if(window.tinymce){
            var ed=tinymce.get('productTextEditorContent');
            if(ed){ed.focus();return;}
        }
        if(attempt<20){setTimeout(function(){focusEditor(attempt+1);},50);return;}
        editorText.focus();
    }
    function openEditor(row){
        var id=row.dataset.sectionId||'';
        lastTrigger=row;
        editorId.value=id;
        editorTitle.textContent='Редактирование текста: '+(row.dataset.sectionTitle||'');
        editorModal.hidden=false;
        editorModal.setAttribute('aria-hidden','false');
        document.body.classList.add('admin-modal-open');
        setEditorContent((window.ciritasProductTextContents||{})[id]||'');
        requestAnimationFrame(function(){focusEditor(0);});
    }
    function closeEditor(){editorModal.hidden=true;editorModal.setAttribute('aria-hidden','true');document.body.classList.remove('admin-modal-open');if(lastTrigger)lastTrigger.focus();}
    document.querySelectorAll('.js-text-section-open').forEach(function(row){row.tabIndex=0;row.addEventListener('click',function(e){if(e.target.closest('input,button,a,label,form'))return;openEditor(row);});row.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.target.closest('input,button,a'))openEditor(row);});});
    editorModal.querySelectorAll('[data-text-editor-close]').forEach(function(el){el.addEventListener('click',closeEditor);});

    var deleteModal=document.getElementById('productTextDeleteModal'),deleteId=document.getElementById('productTextDeleteId'),deleteName=document.getElementById('productTextDeleteName'),deleteTrigger=null;
    function openDelete(button){deleteTrigger=button;deleteId.value=button.dataset.sectionId||'';deleteName.textContent=button.dataset.sectionTitle||'';deleteModal.hidden=false;deleteModal.setAttribute('aria-hidden','false');document.body.classList.add('admin-modal-open');}
    function closeDelete(){deleteModal.hidden=true;deleteModal.setAttribute('aria-hidden','true');document.body.classList.remove('admin-modal-open');if(deleteTrigger)deleteTrigger.focus();}
    document.querySelectorAll('.js-text-section-delete').forEach(function(button){button.addEventListener('click',function(e){e.stopPropagation();openDelete(button);});});
    deleteModal.querySelectorAll('[data-text-delete-close]').forEach(function(el){el.addEventListener('click',closeDelete);});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'){if(!editorModal.hidden)closeEditor();else if(!deleteModal.hidden)closeDelete();}});
})();
</script>
<script src="assets/js/html-editor.js"></script>
<script src="assets/js/shot-preview.js"></script>
