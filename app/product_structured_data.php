<?php
declare(strict_types=1);

/**
 * JSON-LD for published product pages. Use only facts present on the page.
 * No speculative ratings, reviews, supported OS or software version.
 */
function product_software_json_ld(array $product, array $seo, array $prices, string $canonical): string {
    $name=trim(legacy((string)($product['Name']??'')));
    if($name==='')return '';

    $description=trim((string)($seo['SeoDescription']??''));
    if($description===''){
        $summary=legacy((string)($product['ShortDescr']??''));
        for($i=0;$i<2&&str_contains($summary,'&lt;');$i++){
            $decoded=html_entity_decode($summary,ENT_QUOTES|ENT_HTML5,'UTF-8');
            if($decoded===$summary)break;
            $summary=$decoded;
        }
        $description=html_entity_decode(strip_tags($summary),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $description=trim(preg_replace('/\s+/u',' ',$description)??$description);
    }

    $data=[
        '@context'=>'https://schema.org',
        '@type'=>'SoftwareApplication',
        'name'=>$name,
        'url'=>$canonical,
        'applicationCategory'=>'BusinessApplication',
        'publisher'=>['@type'=>'Organization','name'=>'CIRITAS','url'=>'https://www.ciritas.ru/'],
    ];
    if($description!=='')$data['description']=$description;

    // Only advertise licensed offers when checkout is enabled on the page.
    if(!isset($product['OrderAllow'])||(int)$product['OrderAllow']===1){
        $offers=[];
        foreach($prices as $price){
            $amount=$price['Price']??null;
            if(!is_numeric($amount)|| (float)$amount<0)continue;
            $offer=[
                '@type'=>'Offer',
                'url'=>$canonical.'#product-prices',
                'price'=>number_format((float)$amount,2,'.',''),
                'priceCurrency'=>'RUB',
            ];
            $licenseName=trim(legacy((string)($price['Title']??$price['Name']??'')));
            if($licenseName!=='')$offer['name']=$licenseName;
            $offers[]=$offer;
        }
        if($offers)$data['offers']=$offers;
    }

    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE);
    return is_string($json)?$json:'';
}
