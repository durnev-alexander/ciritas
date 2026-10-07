<?php
require __DIR__.'/includes/layout.php';
admin_header('Продукты');
$order=column_exists('csSoftProducts','OrderIndex')?'p.OrderIndex,p.ID':'p.ID DESC';
$select='p.ID,p.Name,p.ShortDescr,p.IsActive,p.SoftGroupID,g.Name GroupName';
if(column_exists('csSoftProducts','OrderAllow'))$select.=',p.OrderAllow';
if(column_exists('csSoftProducts','OrderIndex'))$select.=',p.OrderIndex';
$rows=db()->query('SELECT '.$select.' FROM csSoftProducts p LEFT JOIN csSoftGroups g ON g.ID=p.SoftGroupID ORDER BY '.$order)->fetchAll();
?>
<div class="card"><div class="toolbar"><div><b>Каталог программ</b><div class="help">Группа используется только в админке; порядок соответствует публичному каталогу.</div></div><a class="btn primary" href="product-edit.php">+ Добавить продукт</a></div>
<div class="table-wrap"><table class="table"><thead><tr><?php if(column_exists('csSoftProducts','OrderIndex')): ?><th>Порядок</th><?php endif; ?><th>Продукт</th><th>Группа</th><th>Статус</th><?php if(column_exists('csSoftProducts','OrderAllow')): ?><th>Заказ</th><?php endif; ?><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><?php if(array_key_exists('OrderIndex',$r)): ?><td><?=(int)$r['OrderIndex']?></td><?php endif; ?><td><b><?=aesc(a_legacy($r['Name']))?></b><div class="help"><?=aesc(a_legacy($r['ShortDescr']??''))?></div></td><td><?=aesc(a_legacy($r['GroupName']??''))?></td><td><span class="badge <?=$r['IsActive']?'on':'off'?>"><?=$r['IsActive']?'Активен':'Скрыт'?></span></td><?php if(array_key_exists('OrderAllow',$r)): ?><td><span class="badge <?=$r['OrderAllow']?'on':'off'?>"><?=$r['OrderAllow']?'Разрешён':'Запрещён'?></span></td><?php endif; ?><td><div class="actions"><a class="btn" href="product-edit.php?id=<?=$r['ID']?>">Изменить</a><a class="btn" href="product-assets.php?id=<?=$r['ID']?>">Ресурсы</a></div></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php admin_footer();
