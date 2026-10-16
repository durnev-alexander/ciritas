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
    return trim($html);
}

function contacts_trim_trailing_empty_html(string $html): string {
    $html=trim($html);
    if($html==='') return '';
    $empty='(?:\s|&nbsp;|&#160;|<br\s*\/?\s*>)*';
    do{
        $before=$html;
        $html=preg_replace('~(?:<p\b[^>]*>'.$empty.'</p>|<div\b[^>]*>'.$empty.'</div>|<br\s*\/?\s*>)\s*$~iu','',$html)??$html;
        $html=rtrim($html);
    }while($html!==$before);
    return $html;
}

render_header('Контакты');
?>
<section class="section contacts-section"><div class="container"><h1>Контакты</h1>
<?php foreach(['Contacts','Requisites'] as $name){
    try{
        $q=db()->prepare('SELECT Content FROM csPages WHERE PageName=? LIMIT 1');
        $q->execute([$name]);
        if($r=$q->fetch()){
            $html=contacts_page_html($r['Content']??'');
            if($name==='Requisites') $html=contacts_trim_trailing_empty_html($html);
            if($html!=='') echo '<div class="content-card contacts-card contacts-card-'.strtolower($name).'"><div class="public-page-html">'.$html.'</div></div>';
        }
    }catch(Throwable){}
} ?>
</div></section>
<?php render_footer();
