<?php

function product_text_sections_all(int $productId,bool $activeOnly=false): array {
    try{
        $sql='SELECT * FROM prod_descr WHERE ProductID=?'.($activeOnly?' AND IsActive=1':'').' ORDER BY OrderIndex ASC, ID ASC';
        $q=db()->prepare($sql);
        $q->execute([$productId]);
        return $q->fetchAll();
    }catch(Throwable){
        return [];
    }
}

function product_text_section_normalize(int $productId): array {
    $rows=product_text_sections_all($productId,false);
    try{
        $q=db()->prepare('UPDATE prod_descr SET OrderIndex=? WHERE ID=? AND ProductID=?');
        $position=10;
        foreach($rows as &$row){
            $row['OrderIndex']=$position;
            $q->execute([$position,(int)$row['ID'],$productId]);
            $position+=10;
        }
        unset($row);
    }catch(Throwable){}
    return $rows;
}

function product_text_section_move(int $productId,int $sectionId,bool $up): void {
    $rows=product_text_section_normalize($productId);
    $index=null;
    foreach($rows as $i=>$row){
        if((int)$row['ID']===$sectionId){$index=$i;break;}
    }
    if($index===null)return;
    $other=$up?$index-1:$index+1;
    if(!isset($rows[$other]))return;
    $a=$rows[$index];$b=$rows[$other];
    $q=db()->prepare('UPDATE prod_descr SET OrderIndex=? WHERE ID=? AND ProductID=?');
    $q->execute([(int)$b['OrderIndex'],(int)$a['ID'],$productId]);
    $q->execute([(int)$a['OrderIndex'],(int)$b['ID'],$productId]);
}
