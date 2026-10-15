<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/includes/layout.php';

function contacts_page_html(?string $value): string {
    $html=legacy($value??'');
    if($html==='') return '';
    for($i=0;$i<2 && (str_contains($html,'&lt;')||str_contains($html,'&gt;'));$i++){
        $decoded=html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8');
        if($decoded===$html) break;
        $html=$decoded;
    }
    return $html;
}

render_header('Контакты');
?>
<section class="section"><div class="container"><h1>Контакты</h1>
<?php foreach(['Contacts','Requisites'] as $name){
    try{
        $q=db()->prepare('SELECT Content FROM csPages WHERE PageName=? LIMIT 1');
        $q->execute([$name]);
        if($r=$q->fetch()){
            $html=contacts_page_html($r['Content']??'');
            if($html!=='') echo '<div class="content-card public-page-html">'.$html.'</div>';
        }
    }catch(Throwable){}
} ?>
</div></section>
<?php render_footer();
