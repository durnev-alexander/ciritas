<?php
require __DIR__.'/includes/layout.php';
require __DIR__.'/../app/product_text_sections.php';

function product_text_db(string $value): string {
    $charset=strtolower((string)cfg2('db.charset','utf8mb4'));
    if(str_starts_with($charset,'utf8'))return $value;
    $converted=@iconv('UTF-8','Windows-1251//TRANSLIT',$value);
    return $converted!==false?$converted:$value;
}

csrf_check();
$productId=(int)($_POST['product_id']??0);
$sectionId=(int)($_POST['section_id']??0);
$action=(string)($_POST['action']??'');
if($productId<1)redirect('products.php');

try{
    if($action==='add'){
        $title=trim((string)($_POST['title']??''));
        if($title==='')throw new RuntimeException('Укажите заголовок текстового раздела.');
        $rows=product_text_section_normalize($productId);
        $position=(int)($_POST['position']??0);
        $max=count($rows)+1;
        if($position<1)$position=$max;
        if($position>$max)$position=$max;
        $q=db()->prepare('UPDATE prod_descr SET OrderIndex=? WHERE ID=? AND ProductID=?');
        foreach($rows as $i=>$row){
            $place=$i+1;
            $q->execute([($place>=$position?$place+1:$place)*10,(int)$row['ID'],$productId]);
        }
        db()->prepare('INSERT INTO prod_descr (ProductID,Title,Content,OrderIndex,IsActive) VALUES (?,?,?,?,?)')->execute([$productId,product_text_db($title),'',$position*10,isset($_POST['active'])?1:0]);
        admin_set_notice('Текстовый раздел добавлен.','success');
    }elseif($action==='save'&&$sectionId){
        $title=trim((string)($_POST['title']??''));
        if($title==='')throw new RuntimeException('Укажите заголовок текстового раздела.');
        db()->prepare('UPDATE prod_descr SET Title=?,IsActive=? WHERE ID=? AND ProductID=?')->execute([product_text_db($title),isset($_POST['active'])?1:0,$sectionId,$productId]);
        admin_set_notice('Текстовый раздел сохранён.','success');
    }elseif($action==='content_save'&&$sectionId){
        $content=(string)($_POST['content']??'');
        db()->prepare('UPDATE prod_descr SET Content=? WHERE ID=? AND ProductID=?')->execute([product_text_db($content),$sectionId,$productId]);
        admin_set_notice('Текст раздела сохранён.','success');
    }elseif(($action==='sort_up'||$action==='sort_down')&&$sectionId){
        product_text_section_move($productId,$sectionId,$action==='sort_up');
        admin_set_notice('Порядок текстовых разделов изменён.','success');
    }elseif($action==='delete'&&$sectionId){
        db()->prepare('DELETE FROM prod_descr WHERE ID=? AND ProductID=?')->execute([$sectionId,$productId]);
        product_text_section_normalize($productId);
        admin_set_notice('Текстовый раздел удалён.','success');
    }
}catch(Throwable $e){
    admin_set_notice($e->getMessage(),'error');
}
redirect('product-assets.php?id='.$productId.'#product-text-sections');
