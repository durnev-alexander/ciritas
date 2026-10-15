<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/includes/layout.php';

function faq_public_html(?string $value): string {
    $html=legacy($value??'');
    if($html==='') return '';
    for($i=0;$i<2 && (str_contains($html,'&lt;')||str_contains($html,'&gt;'));$i++){
        $decoded=html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8');
        if($decoded===$html) break;
        $html=$decoded;
    }
    return $html;
}

$topics=[];$questions=[];
try{
    $topics=db()->query('SELECT c.*, (SELECT COUNT(*) FROM faq f WHERE f.CategoryID=c.ID AND f.IsActive=1) FaqCount FROM faq_category c WHERE c.IsActive=1 ORDER BY c.OrderIndex,c.ID')->fetchAll();
    $questions=db()->query('SELECT f.* FROM faq f JOIN faq_category c ON c.ID=f.CategoryID WHERE f.IsActive=1 AND c.IsActive=1 ORDER BY c.OrderIndex,c.ID,f.OrderIndex,f.ID')->fetchAll();
}catch(Throwable){}
$selectedTopic=(int)($topics[0]['ID']??0);

render_header('ЧАВО');
?>
<section class="section"><div class="container">
<div class="section-head"><div><h1>ЧАВО</h1><p class="muted">Часто задаваемые вопросы и ответы</p></div></div>

<div class="content-card faq-public-card">
    <h2>Темы</h2>
    <?php if($topics): ?>
    <div class="table-responsive"><table class="faq-public-table faq-topics-public"><thead><tr><th>Название темы</th><th>Вопросов</th></tr></thead><tbody>
    <?php foreach($topics as $i=>$topic): $tid=(int)$topic['ID']; ?>
        <tr class="faq-topic-row<?=$i===0?' active':''?>" tabindex="0" role="button" data-topic-id="<?=$tid?>" aria-selected="<?=$i===0?'true':'false'?>">
            <td><?=htmlspecialchars(legacy((string)($topic['Title']??'')),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></td>
            <td><?= (int)($topic['FaqCount']??0) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php else: ?><p class="muted">Темы ЧАВО пока не опубликованы.</p><?php endif; ?>
</div>

<div class="content-card faq-public-card">
    <h2>Вопросы</h2>
    <?php if($topics): ?>
    <div class="table-responsive"><table class="faq-public-table faq-questions-public"><thead><tr><th>Вопрос</th></tr></thead><tbody>
    <?php foreach($questions as $question): $qid=(int)$question['ID'];$tid=(int)$question['CategoryID'];$answer=faq_public_html($question['Answer']??''); ?>
        <tr class="faq-question-row" data-topic-id="<?=$tid?>" data-question-id="<?=$qid?>" <?=$tid===$selectedTopic?'':'hidden'?>>
            <td>
                <button class="faq-question-toggle" type="button" aria-expanded="false" aria-controls="faqAnswer<?=$qid?>"><?=htmlspecialchars(legacy((string)($question['Question']??'')),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></button>
                <div class="faq-answer public-page-html" id="faqAnswer<?=$qid?>" hidden><?=$answer?></div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <p class="muted faq-empty-topic" hidden>В выбранной теме пока нет опубликованных вопросов.</p>
    <?php else: ?><p class="muted">Вопросы появятся после публикации первой темы.</p><?php endif; ?>
</div>
</div></section>
<style>
.faq-public-card h2{margin-top:0}.faq-public-table{width:100%;border-collapse:collapse}.faq-public-table th,.faq-public-table td{padding:14px 16px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}.faq-public-table th{color:var(--muted);font-size:13px;text-transform:uppercase;letter-spacing:.04em;background:var(--soft)}.faq-public-table tbody tr:last-child td{border-bottom:0}.faq-topic-row{cursor:pointer;transition:.16s ease}.faq-topic-row td:first-child{font-weight:600}.faq-topic-row:hover{background:var(--soft)}.faq-topic-row.active{background:var(--soft-2);box-shadow:inset 4px 0 0 var(--navy)}.faq-topics-public th:last-child,.faq-topics-public td:last-child{width:1%;white-space:nowrap;text-align:center}.faq-question-row{transition:.16s ease}.faq-question-row.open{box-shadow:inset 4px 0 0 var(--navy)}.faq-question-toggle{display:block;width:100%;padding:0;border:0;background:transparent;color:var(--text);font:inherit;font-weight:600;text-align:left;cursor:pointer}.faq-question-toggle:hover{color:var(--navy)}.faq-answer{margin-top:14px;padding:16px 18px;border:1px solid var(--line);border-radius:12px;background:var(--soft);line-height:1.7}.faq-answer>:first-child{margin-top:0}.faq-answer>:last-child{margin-bottom:0}
</style>
<script>
(function(){
    var topicRows=[].slice.call(document.querySelectorAll('.faq-topic-row'));
    var questionRows=[].slice.call(document.querySelectorAll('.faq-question-row'));
    var empty=document.querySelector('.faq-empty-topic');
    function selectTopic(topicId){
        var visible=0;
        topicRows.forEach(function(row){var active=row.dataset.topicId===String(topicId);row.classList.toggle('active',active);row.setAttribute('aria-selected',active?'true':'false');});
        questionRows.forEach(function(row){
            var show=row.dataset.topicId===String(topicId);
            row.hidden=!show;
            var toggle=row.querySelector('.faq-question-toggle');
            var answer=row.querySelector('.faq-answer');
            if(show){visible++;}else if(toggle&&answer){answer.hidden=true;toggle.setAttribute('aria-expanded','false');row.classList.remove('open');}
        });
        if(empty)empty.hidden=visible>0;
    }
    topicRows.forEach(function(row){
        function activate(){selectTopic(row.dataset.topicId||'');}
        row.addEventListener('click',activate);
        row.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();activate();}});
    });
    document.querySelectorAll('.faq-question-toggle').forEach(function(button){
        button.addEventListener('click',function(){
            var id=button.getAttribute('aria-controls');
            var answer=id?document.getElementById(id):null;
            if(!answer)return;
            var row=button.closest('.faq-question-row');
            var open=button.getAttribute('aria-expanded')==='true';
            button.setAttribute('aria-expanded',open?'false':'true');
            answer.hidden=open;
            if(row)row.classList.toggle('open',!open);
        });
    });
    if(topicRows.length)selectTopic(topicRows[0].dataset.topicId||'');
})();
</script>
<?php render_footer();
