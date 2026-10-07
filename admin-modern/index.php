<?php require __DIR__.'/includes/layout.php'; admin_header('Обзор');
$stats=[]; foreach(['Продукты'=>'csSoftProducts','Заказы'=>'csOrders','Новости'=>'csNews','Группы'=>'csSoftGroups'] as $label=>$table){try{$stats[$label]=(int)db()->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();}catch(Throwable){$stats[$label]=0;}}
?><div class="grid"><?php foreach($stats as $label=>$value): ?><div class="card"><div class="muted"><?=$label?></div><div style="font-size:36px;font-weight:800;margin-top:8px"><?=$value?></div></div><?php endforeach; ?></div><?php admin_footer();
