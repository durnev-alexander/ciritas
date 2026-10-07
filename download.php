<?php
require __DIR__.'/app/bootstrap.php';
$id=(int)($_GET['id']??0); if($id<1){http_response_code(404);exit;}
$st=db()->prepare('SELECT * FROM csSoftDownloads WHERE ID=? AND IsActive=1');$st->execute([$id]);$f=$st->fetch();if(!$f){http_response_code(404);exit;}
$file=(string)($f['File']??'');$base=realpath((string)cfg('site.uploads_path'));$path=$base?realpath($base.DIRECTORY_SEPARATOR.$file):false;
if(!$base||!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path)){http_response_code(404);exit;}
try{ if(array_key_exists('Downloads',$f)){db()->prepare('UPDATE csSoftDownloads SET Downloads=COALESCE(Downloads,0)+1 WHERE ID=?')->execute([$id]);}}catch(Throwable){}
header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.basename($path).'"');header('Content-Length: '.filesize($path));readfile($path);
