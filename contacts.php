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

function contacts_section(string $pageName,string $title,string $comment,bool $trimTrailing=false): void {
    try{
        $q=db()->prepare('SELECT Content FROM csPages WHERE PageName=? LIMIT 1');
        $q->execute([$pageName]);
        if($r=$q->fetch()){
            $html=contacts_page_html($r['Content']??'');
            if($trimTrailing) $html=contacts_trim_trailing_empty_html($html);
            if($html!==''){
                echo '<div class="content-card contacts-section-card">';
                echo '<h2>'.htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</h2>';
                echo '<p class="muted">'.htmlspecialchars($comment,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';
                echo '<div class="public-page-html">'.$html.'</div>';
                echo '</div>';
            }
        }
    }catch(Throwable){}
}

render_header('Контакты');
?>
<section class="section contacts-section public-compact-section"><div class="container">
<div class="section-head"><div><h1>Контакты</h1><p class="muted">Контактная информация и банковские реквизиты компании ЦИРИТАС</p></div></div>
<?php
contacts_section('MainPage','О компании','Краткая информация о компании');
contacts_section('Contacts','Контактная информация','Адреса, телефоны, электронная почта');
contacts_section('Requisites','Банковские реквизиты','Банк, БИК, расчетный и кор. счет',true);
?>
</div></section>
<style>
.contacts-section-card h2{margin:0 0 6px}.contacts-section-card>.muted{margin:0 0 18px}.contacts-section-card>.public-page-html{margin-top:0}.contacts-section-card .public-page-html>:first-child{margin-top:0}.contacts-section-card .public-page-html>:last-child{margin-bottom:0}
</style>
<?php render_footer();
