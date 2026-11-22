<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
$user=require_role('pharmacist','customer');
$file=basename((string)($_GET['file']??''));
if($file===''||!preg_match('/^(prescription|senior)_[a-f0-9]{32}\.(jpg|png|pdf)$/',$file)){http_response_code(404);exit('Document not found.');}
$q=db()->prepare("SELECT user_id FROM orders WHERE prescription_file=? OR senior_id_file=?");$q->execute([$file,$file]);$order=$q->fetch();
if(!$order||($user['role']!=='pharmacist'&&(int)$order['user_id']!==(int)$user['id'])){http_response_code(404);exit('Document not found.');}
$path=UPLOAD_DIR.'/'.$file;if(!is_file($path)){http_response_code(404);exit('Document not found.');}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);if(!in_array($mime,['image/jpeg','image/png','application/pdf'],true)){http_response_code(415);exit('Unsupported document.');}
header('Content-Type: '.$mime);header('Content-Length: '.(string)filesize($path));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0');header('Content-Disposition: inline; filename="'.str_replace('"','',$file).'"');readfile($path);
