<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
$user=current_user();
if(!$user || $user['role']!=='customer'){http_response_code(401);echo json_encode(['error'=>'Sign in as the customer who owns this order.']);exit;}
$orderId=(int)($_GET['order_id']??0);
$q=db()->prepare('SELECT o.id,o.status,o.delivery_lat,o.delivery_lng,o.rider_lat,o.rider_lng,o.updated_at,r.name rider_name,r.profile_photo rider_profile_photo FROM orders o LEFT JOIN users r ON r.id=o.rider_id WHERE o.id=? AND o.user_id=?');
$q->execute([$orderId,$user['id']]);$order=$q->fetch();
if(!$order){http_response_code(404);echo json_encode(['error'=>'Order not found.']);exit;}
$order['rider_photo_url']=avatar_url($order['rider_profile_photo']);
echo json_encode($order,JSON_THROW_ON_ERROR);
