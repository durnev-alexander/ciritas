<?php
require __DIR__.'/includes/layout.php';

$rows=[];
try{$rows=db()->query('SELECT ID,PageName,Content FROM csPages ORDER BY PageName')->fetchAll();}catch(Throwable){}

admin_header('Страницы');
?>
<style>
.pages-table{table-layout:fixed}
.pages-table .page-preview-col{width:46%}
.pages-table .page-name-col{width:auto}
.pages-table .page-actions-col{width:1%;white-space:nowrap;text-align:right}
.page-html-preview{max-height:150px;overflow:hidden;padding:12px 14px;border:1px solid var(--admin-line);border-radius:10px;background:var(--admin-soft);line-height:1.45;overflow-wrap:anywhere;word-break:break-word}
.page-html-preview>:first-child{margin-top:0}.page-html-preview>:last-child{margin-bottom:0}
.page-html-preview img,.page-html-preview video,.page-html-preview iframe{max-width:100%!important;height:auto!important}
.page-html-preview table{max-width:100%;display:block;overflow:hidden}
.page-html-preview pre{max-width:100%;white-space:pre-wrap;overflow-wrap:anywhere}
.page-html-preview.empty{color:var(--admin-muted);font-size:13px}
</style>
<div class="card">
<div class="table-wrap"><table class="table pages-table"><thead><tr><th class="page-preview-col">Превью</th><th class="page-name-col">Страница</th><th class="page-actions-col">Действия</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<?php $html=a_legacy((string)($r['Content']??'')); ?>
<tr>
<td class="page-preview-col"><?php if(trim($html)!==''): ?><div class="page-html-preview"><?=$html?></div><?php else: ?><div class="page-html-preview empty">Нет содержимого</div><?php endif; ?></td>
<td class="page-name-col"><?=aesc(a_legacy((string)$r['PageName']))?></td>
<td class="page-actions-col"><a class="btn" href="page-edit.php?id=<?=(int)$r['ID']?>">Изменить</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div>
<?php admin_footer();
