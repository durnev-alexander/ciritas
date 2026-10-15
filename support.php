<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/includes/layout.php';

function public_page_html(?string $value): string {
    $html=legacy($value??'');
    if($html==='') return '';
    for($i=0;$i<2 && (str_contains($html,'&lt;')||str_contains($html,'&gt;'));$i++){
        $decoded=html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8');
        if($decoded===$html) break;
        $html=$decoded;
    }
    return $html;
}

render_header('Поддержка');
?>
<section class="section"><div class="container"><h1>Поддержка</h1>
<?php
try{
    $q=db()->prepare('SELECT Content FROM csPages WHERE PageName=? LIMIT 1');
    $q->execute(['Support']);
    $r=$q->fetch();
    $html=$r?public_page_html($r['Content']??''):'';
    echo $html!==''?'<div class="content-card public-page-html">'.$html.'</div>':'<div class="content-card"><p>Свяжитесь с нами для получения технической поддержки.</p></div>';
}catch(Throwable){
    echo '<div class="content-card"><p>Свяжитесь с нами для получения технической поддержки.</p></div>';
}
?>
</div></section>
<?php render_footer();
