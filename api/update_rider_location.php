<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['error'=>'POST required.']);exit;}
$user=current_user();
if(!$user || $user['role']!=='rider'){http_response_code(401);echo json_encode(['error'=>'Sign in as a rider to share location.']);exit;}
csrf_check();
$orderId=(int)($_POST['order_id']??0);
$lat=filter_var($_POST['lat']??null,FILTER_VALIDATE_FLOAT);
$lng=filter_var($_POST['lng']??null,FILTER_VALIDATE_FLOAT);
if($lat===false||$lng===false||$lat===null||$lng===null||!is_finite((float)$lat)||!is_finite((float)$lng)||$lat < -90||$lat > 90||$lng < -180||$lng > 180){http_response_code(422);echo json_encode(['error'=>'Valid latitude and longitude are required.']);exit;}
$q=db()->prepare("SELECT id FROM orders WHERE id=? AND rider_id=? AND status='out_for_delivery'");$q->execute([$orderId,$user['id']]);
if(!$q->fetch()){http_response_code(404);echo json_encode(['error'=>'This active delivery is not assigned to you.']);exit;}
$q=db()->prepare("UPDATE orders SET rider_lat=?,rider_lng=?,updated_at=NOW() WHERE id=? AND rider_id=? AND status='out_for_delivery'");
$q->execute([$lat,$lng,$orderId,$user['id']]);
echo json_encode(['ok'=>true,'rider_lat'=>(float)$lat,'rider_lng'=>(float)$lng]);
