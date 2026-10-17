<?php
require __DIR__.'/includes/layout.php';

$rows=[];
try{$rows=db()->query('SELECT ID,PageName,Content FROM csPages ORDER BY PageName')->fetchAll();}catch(Throwable){}

function page_preview_html(?string $value): string {
    $html=a_legacy((string)$value);
    if($html==='') return '';
    if(str_contains($html,'&lt;') || str_contains($html,'&gt;')) {
        $html=html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8');
    }
    return $html;
}

function page_preview_document(string $html): string {
    $doc='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'
        .'html,body{margin:0;padding:0;background:#fff;color:#17283c;font-family:Arial,sans-serif;font-size:14px;line-height:1.4;overflow:hidden}'
        .'body{padding:10px;box-sizing:border-box;overflow-wrap:anywhere;word-break:break-word}'
        .'img,video,iframe{max-width:100%!important;height:auto!important}'
        .'table{max-width:100%!important}pre{white-space:pre-wrap;overflow-wrap:anywhere}'
        .'*:first-child{margin-top:0}'
        .'</style></head><body>'.$html.'</body></html>';
    return $doc;
}

admin_header('Страницы');
?>
<style>
.pages-table{width:100%;table-layout:fixed}
.pages-table .page-preview-col{width:264px}
.pages-table .page-name-col{width:auto}
.pages-table .page-actions-col{width:126px;white-space:nowrap;text-align:right}
.pages-table th.page-actions-col,.pages-table td.page-actions-col{text-align:right}
.page-html-preview-frame{display:block;width:240px;height:180px;border:1px solid var(--admin-line);border-radius:10px;background:#fff;overflow:hidden}
.page-html-preview-frame iframe{display:block;width:240px;height:180px;border:0;background:#fff;pointer-events:none}
.page-html-preview-empty{display:grid;place-items:center;width:240px;height:180px;border:1px solid var(--admin-line);border-radius:10px;background:var(--admin-soft);color:var(--admin-muted);font-size:13px}
</style>
<div class="card">
<div class="table-wrap"><table class="table pages-table"><thead><tr><th class="page-preview-col">Превью</th><th class="page-name-col">Страница</th><th class="page-actions-col">Действия</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<?php $html=page_preview_html((string)($r['Content']??'')); ?>
<tr>
<td class="page-preview-col">
<?php if(trim($html)!==''): ?>
<div class="page-html-preview-frame"><iframe sandbox="" loading="lazy" srcdoc="<?=aesc(page_preview_document($html))?>" title="Превью <?=aesc(a_legacy((string)$r['PageName']))?>"></iframe></div>
<?php else: ?><div class="page-html-preview-empty">Нет содержимого</div><?php endif; ?>
</td>
<td class="page-name-col"><?=aesc(a_legacy((string)$r['PageName']))?></td>
<td class="page-actions-col"><a class="btn" href="page-edit.php?id=<?=(int)$r['ID']?>">Изменить</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div>
<?php admin_footer();
