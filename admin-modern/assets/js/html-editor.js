(function(){
    var editors=[].slice.call(document.querySelectorAll('textarea.html-editor'));
    if(!editors.length) return;

    function initEditor(){
        if(!window.tinymce) return;
        var dark=document.documentElement.dataset.theme==='dark';
        tinymce.init({
            selector:'textarea.html-editor',
            license_key:'gpl',
            menubar:false,
            branding:false,
            promotion:false,
            skin:dark?'oxide-dark':'oxide',
            content_css:dark?'dark':'default',
            plugins:'lists link code',
            toolbar:'undo redo | blocks | bold italic underline | bullist numlist | link | alignleft aligncenter alignright | removeformat | code',
            toolbar_mode:'wrap',
            min_height:260,
            resize:true,
            browser_spellcheck:true,
            contextmenu:false,
            entity_encoding:'raw',
            convert_urls:false,
            verify_html:false,
            valid_elements:'*[*]',
            extended_valid_elements:'*[*]',
            setup:function(editor){
                editor.on('change input undo redo',function(){editor.save();});
            }
        });
        document.querySelectorAll('form').forEach(function(form){
            form.addEventListener('submit',function(){tinymce.triggerSave();});
        });
    }

    if(window.tinymce){
        initEditor();
        return;
    }

    var script=document.createElement('script');
    script.src='https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js';
    script.referrerPolicy='origin';
    script.onload=initEditor;
    script.onerror=function(){console.error('Не удалось загрузить TinyMCE.');};
    document.head.appendChild(script);
})();
