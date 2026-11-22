<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/actions.php';
handle_post();
$page = (string)($_GET['page'] ?? (current_user() ? 'dashboard' : 'login'));
$user = current_user();
$publicPages = ['login','register'];
if (!$user && !in_array($page,$publicPages,true)) redirect('?page=login');
if ($user && in_array($page,$publicPages,true)) redirect('?page=dashboard');
if ($user && $user['role'] !== 'customer' && in_array($page,['shop','cart','order'],true)) { http_response_code(403); exit('This customer page is not available for your account.'); }
if ($user && $user['role'] === 'customer' && in_array($page,['verification','inventory','deliveries'],true)) { http_response_code(403); exit('This workspace is not available for your account.'); }
if ($user && $user['role'] === 'rider' && in_array($page,['orders'],true)) { http_response_code(403); exit('This customer page is not available for your account.'); }

$data=[];
if (in_array($page,['dashboard','shop','cart','orders','order','verification','inventory','deliveries'],true)) {
    if ($page==='shop') {
        $category=(string)($_GET['category']??'All'); $type=(string)($_GET['type']??'All'); $search=trim((string)($_GET['q']??''));
        $sql='SELECT m.*, (SELECT b.expiry_date FROM medicine_batches b WHERE b.medicine_id=m.id AND b.stock_quantity>0 ORDER BY b.expiry_date LIMIT 1) next_expiry FROM medicines m WHERE m.is_active=1'; $args=[];
        if(in_array($category,['OTC','RX'],true)){$sql.=' AND m.category=?';$args[]=$category;}
        if(in_array($type,['Pain relief','Allergy','Antibiotic','Cardiovascular','Diabetes','Vitamins','Digestive','First aid'],true)){$sql.=' AND m.type=?';$args[]=$type;}
        if($search!==''){$sql.=' AND (m.name LIKE ? OR m.description LIKE ?)';$args[]='%'.$search.'%';$args[]='%'.$search.'%';}
        $sql.=' ORDER BY m.name'; $q=db()->prepare($sql);$q->execute($args);$data['medicines']=$q->fetchAll();
    } elseif($page==='cart') {
        $cart=$_SESSION['cart']??[];$data['items']=[];$data['subtotal']=0;$data['has_rx']=false;
        $data['has_senior']=false;
        if($cart){$ids=array_map('intval',array_keys($cart));$marks=implode(',',array_fill(0,count($ids),'?'));$q=db()->prepare("SELECT * FROM medicines WHERE id IN ($marks) AND is_active=1");$q->execute($ids);foreach($q->fetchAll() as $m){$m['quantity']=(int)$cart[$m['id']];$m['line_total']=$m['quantity']*(float)$m['price'];$data['subtotal']+=$m['line_total'];$data['has_rx']=$data['has_rx']||$m['category']==='RX';$data['items'][]=$m;}}
    } elseif($page==='order') {
        $q=db()->prepare('SELECT o.*,r.name rider_name,r.profile_photo rider_profile_photo FROM orders o LEFT JOIN users r ON r.id=o.rider_id WHERE o.id=? AND o.user_id=?');$q->execute([(int)($_GET['id']??0),$user['id']]);$data['order']=$q->fetch();
        if($data['order']){$q=db()->prepare('SELECT * FROM order_items WHERE order_id=?');$q->execute([$data['order']['id']]);$data['items']=$q->fetchAll();}
    } elseif($page==='orders') {
        $q=db()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');$q->execute([$user['id']]);$data['orders']=$q->fetchAll();
    } elseif($page==='verification') {
        $user=require_role('pharmacist');$q=db()->query("SELECT o.*,u.name customer_name,u.phone,u.profile_photo customer_profile_photo FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status='pending_review' ORDER BY o.created_at");$data['orders']=$q->fetchAll();
        foreach($data['orders'] as &$o){$q=db()->prepare('SELECT * FROM order_items WHERE order_id=?');$q->execute([$o['id']]);$o['items']=$q->fetchAll();}unset($o);
        $historyStatus=(string)($_GET['history_status']??'All');$historyMap=['All'=>null,'Approved & Preparing'=>'approved','Out for Delivery'=>'out_for_delivery','Delivered'=>'delivered','Rejected'=>'rejected'];
        if(!array_key_exists($historyStatus,$historyMap))$historyStatus='All';
        $historySql="SELECT o.*,u.name customer_name,u.profile_photo customer_profile_photo FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status IN ('approved','out_for_delivery','delivered','rejected')";$historyArgs=[];
        if($historyMap[$historyStatus]!==null){$historySql.=' AND o.status=?';$historyArgs[]=$historyMap[$historyStatus];}
        $historySql.=' ORDER BY COALESCE(o.reviewed_at,o.delivered_at,o.updated_at) DESC';$q=db()->prepare($historySql);$q->execute($historyArgs);$data['history']=$q->fetchAll();$data['history_status']=$historyStatus;
        foreach($data['history'] as &$o){$q=db()->prepare('SELECT medicine_name,quantity FROM order_items WHERE order_id=?');$q->execute([$o['id']]);$o['items']=$q->fetchAll();}unset($o);
    } elseif($page==='inventory') {
        $user=require_role('pharmacist');$data['medicines']=db()->query('SELECT m.id,m.name,m.description,m.category,m.type,m.dosage,m.price,COALESCE(SUM(b.stock_quantity),0) stock_quantity,m.is_active,m.created_at,MIN(CASE WHEN b.stock_quantity>0 THEN b.expiry_date END) next_expiry FROM medicines m LEFT JOIN medicine_batches b ON b.medicine_id=m.id WHERE m.is_active=1 GROUP BY m.id ORDER BY m.name')->fetchAll();
        foreach($data['medicines'] as &$m){$q=db()->prepare('SELECT * FROM medicine_batches WHERE medicine_id=? ORDER BY expiry_date');$q->execute([$m['id']]);$m['batches']=$q->fetchAll();}unset($m);
    } elseif($page==='deliveries') {
        $user=require_role('rider');
        $q=db()->query("SELECT o.*,u.name customer_name,u.phone,u.profile_photo customer_profile_photo FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status='approved' AND o.rider_id IS NULL ORDER BY o.created_at");$data['pool']=$q->fetchAll();
        $q=db()->prepare("SELECT o.*,u.name customer_name,u.phone,u.profile_photo customer_profile_photo FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status='out_for_delivery' AND o.rider_id=? ORDER BY o.created_at");$q->execute([$user['id']]);$data['active']=$q->fetchAll();
        $q=db()->prepare("SELECT o.*,u.name customer_name,u.profile_photo customer_profile_photo FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status='delivered' AND o.rider_id=? ORDER BY o.delivered_at DESC");$q->execute([$user['id']]);$data['history']=$q->fetchAll();
        foreach(['pool','active','history'] as $key){foreach($data[$key] as &$o){$q=db()->prepare('SELECT * FROM order_items WHERE order_id=?');$q->execute([$o['id']]);$o['items']=$q->fetchAll();}unset($o);}
    } elseif($page==='dashboard') {
        if($user['role']==='customer'){$page='shop';$sql='SELECT * FROM medicines WHERE is_active=1 ORDER BY created_at DESC LIMIT 8';$data['medicines']=db()->query($sql)->fetchAll();}
        elseif($user['role']==='pharmacist'){$page='verification';$q=db()->query("SELECT o.*,u.name customer_name,u.phone,u.profile_photo customer_profile_photo FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status='pending_review' ORDER BY o.created_at");$data['orders']=$q->fetchAll();foreach($data['orders'] as &$o){$q=db()->prepare('SELECT * FROM order_items WHERE order_id=?');$q->execute([$o['id']]);$o['items']=$q->fetchAll();}unset($o);$data['history']=db()->query("SELECT o.*,u.name customer_name,u.profile_photo customer_profile_photo FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status IN ('approved','out_for_delivery','delivered','rejected') ORDER BY COALESCE(o.reviewed_at,o.delivered_at,o.updated_at) DESC")->fetchAll();$data['history_status']='All';foreach($data['history'] as &$o){$q=db()->prepare('SELECT medicine_name,quantity FROM order_items WHERE order_id=?');$q->execute([$o['id']]);$o['items']=$q->fetchAll();}unset($o);$data['alerts']=db()->query('SELECT m.name,m.stock_quantity,MIN(CASE WHEN b.stock_quantity>0 THEN b.expiry_date END) next_expiry FROM medicines m LEFT JOIN medicine_batches b ON b.medicine_id=m.id WHERE m.is_active=1 GROUP BY m.id HAVING stock_quantity<15 OR next_expiry<=DATE_ADD(CURDATE(),INTERVAL 60 DAY) ORDER BY next_expiry LIMIT 8')->fetchAll();}
        else {$page='deliveries';$data['pool']=db()->query("SELECT o.*,u.name customer_name,u.phone,u.profile_photo customer_profile_photo FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status='approved' AND o.rider_id IS NULL ORDER BY o.created_at")->fetchAll();$q=db()->prepare("SELECT o.*,u.name customer_name,u.phone,u.profile_photo customer_profile_photo FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status='out_for_delivery' AND o.rider_id=? ORDER BY o.created_at");$q->execute([$user['id']]);$data['active']=$q->fetchAll();$q=db()->prepare("SELECT o.*,u.name customer_name,u.profile_photo customer_profile_photo FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status='delivered' AND o.rider_id=? ORDER BY o.delivered_at DESC");$q->execute([$user['id']]);$data['history']=$q->fetchAll();foreach(['pool','active','history'] as $key){foreach($data[$key] as &$o){$q=db()->prepare('SELECT * FROM order_items WHERE order_id=?');$q->execute([$o['id']]);$o['items']=$q->fetchAll();}unset($o);}}
    }
}
$flash=take_flash();
require __DIR__ . '/views/layout.php';
