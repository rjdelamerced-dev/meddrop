<?php
declare(strict_types=1);

function handle_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    try {
        switch ($action) {
            case 'login':
                $stmt = db()->prepare('SELECT id,name,email,password_hash,role,profile_photo FROM users WHERE email = ? AND is_active = 1');
                $stmt->execute([strtolower(trim((string)($_POST['email'] ?? '')))]); $user = $stmt->fetch();
                if (!$user || !password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) throw new RuntimeException('Email or password is incorrect.');
                session_regenerate_id(true); unset($user['password_hash']); $_SESSION['user'] = $user;
                flash('success', 'Welcome back, ' . $user['name'] . '.'); redirect('?page=dashboard');
            case 'register':
                $name = trim((string)($_POST['name'] ?? '')); $email = strtolower(trim((string)($_POST['email'] ?? '')));
                $password = (string)($_POST['password'] ?? ''); $phone = trim((string)($_POST['phone'] ?? ''));
                if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) throw new RuntimeException('Enter a name, valid email, and password with at least 8 characters.');
                $stmt = db()->prepare('INSERT INTO users(name,email,password_hash,role,phone) VALUES(?,?,?,\'customer\',?)');
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone]);
                $_SESSION['user'] = ['id'=>(int)db()->lastInsertId(),'name'=>$name,'email'=>$email,'role'=>'customer','profile_photo'=>null];
                flash('success', 'Your customer account is ready.'); redirect('?page=dashboard');
            case 'change_photo':
                $user=require_role('customer','pharmacist','rider');
                $avatarFilename=upload_avatar('profile_photo',(int)$user['id']);
                if(!$avatarFilename)throw new RuntimeException('Choose a profile photo first.');
                db()->prepare('UPDATE users SET profile_photo=? WHERE id=?')->execute([$avatarFilename,$user['id']]);
                $oldPhoto=(string)($_SESSION['user']['profile_photo']??'');
                $_SESSION['user']['profile_photo']=$avatarFilename;
                if(str_starts_with($oldPhoto,'user'.(int)$user['id'].'_')&&is_file(AVATAR_DIR.'/'.$oldPhoto))@unlink(AVATAR_DIR.'/'.$oldPhoto);
                flash('success','Your profile photo has been updated.'); redirect('?page=dashboard');
            case 'logout': session_destroy(); redirect('?page=login');
            case 'add_cart':
                require_role('customer'); $id = (int)($_POST['medicine_id'] ?? 0); $qty = max(1, min(20, (int)($_POST['quantity'] ?? 1)));
                $stmt = db()->prepare('SELECT id,name,stock_quantity FROM medicines WHERE id=? AND is_active=1'); $stmt->execute([$id]); $medicine=$stmt->fetch();
                if (!$medicine || $medicine['stock_quantity'] < 1) throw new RuntimeException('This medicine is currently out of stock.');
                $_SESSION['cart'] ??= []; $_SESSION['cart'][$id] = min((int)$medicine['stock_quantity'], (int)($_SESSION['cart'][$id] ?? 0) + $qty);
                flash('success', $medicine['name'] . ' added to your cart.'); redirect('?page=shop');
            case 'update_cart':
                require_role('customer'); $cart = $_SESSION['cart'] ?? [];
                foreach (($_POST['qty'] ?? []) as $id=>$qty) { $id=(int)$id; $qty=(int)$qty; if($qty<=0) unset($cart[$id]); else $cart[$id]=min(99,$qty); }
                $_SESSION['cart']=$cart; flash('success','Cart updated.'); redirect('?page=cart');
            case 'checkout':
                $user = require_role('customer'); $cart=$_SESSION['cart'] ?? [];
                if (!$cart) throw new RuntimeException('Your cart is empty.');
                $prescription = upload_document('prescription',['image/jpeg','image/png','application/pdf'],'prescription');
                $seniorId = upload_document('senior_id',['image/jpeg','image/png','application/pdf'],'senior');
                $pdo=db(); $pdo->beginTransaction();
                $items=[]; $subtotal=0.0; $hasRx=false;
                $ids=array_map('intval',array_keys($cart)); sort($ids);
                foreach($ids as $id) {
                    $q=$pdo->prepare('SELECT id,name,price,category,stock_quantity FROM medicines WHERE id=? AND is_active=1 FOR UPDATE'); $q->execute([$id]); $m=$q->fetch();
                    $qty=(int)$cart[$id]; if(!$m || $qty<1 || $m['stock_quantity']<$qty) throw new RuntimeException('Stock changed for an item in your cart. Please review your cart.');
                    if($m['category']==='RX') $hasRx=true; $subtotal += (float)$m['price']*$qty; $items[]=[$m,$qty];
                }
                if($hasRx && !$prescription) throw new RuntimeException('A prescription file is required for prescription medicines.');
                $requestDiscount=isset($_POST['senior_discount']);
                if($requestDiscount && !$seniorId) throw new RuntimeException('Upload your Senior Citizen or PWD ID to request the discount.');
                $discount=$requestDiscount && $seniorId ? round($subtotal*0.20,2) : 0; $needsReview=$hasRx || ($requestDiscount && (bool)$seniorId);
                $address=trim((string)($_POST['address'] ?? '')); $payment=(string)($_POST['payment_method'] ?? 'COD'); $reference=trim((string)($_POST['payment_reference'] ?? ''));
                if(mb_strlen($address)<8 || !in_array($payment,['COD','GCash'],true)) throw new RuntimeException('Enter a complete delivery address and choose a payment method.');
                $deliveryLat=filter_var($_POST['delivery_lat']??null,FILTER_VALIDATE_FLOAT);
                $deliveryLng=filter_var($_POST['delivery_lng']??null,FILTER_VALIDATE_FLOAT);
                if($deliveryLat===false||$deliveryLng===false||$deliveryLat===null||$deliveryLng===null||$deliveryLat < -90||$deliveryLat > 90||$deliveryLng < -180||$deliveryLng > 180) throw new RuntimeException('Pin your delivery location on the map or use your current location.');
                if($payment==='GCash' && $reference==='') throw new RuntimeException('Enter the GCash reference number.');
                $status=$needsReview?'pending_review':'approved';
                $q=$pdo->prepare('INSERT INTO orders(user_id,status,subtotal,discount_amount,total_amount,prescription_file,senior_id_file,discount_requested,delivery_address,delivery_lat,delivery_lng,payment_method,payment_reference) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $q->execute([$user['id'],$status,$subtotal,$discount,$subtotal-$discount,$prescription,$seniorId,(int)(bool)$seniorId,$address,$deliveryLat,$deliveryLng,$payment,$payment==='GCash'?$reference:null]); $orderId=(int)$pdo->lastInsertId();
                foreach($items as [$m,$qty]) {
                    $q=$pdo->prepare('INSERT INTO order_items(order_id,medicine_id,medicine_name,quantity,unit_price) VALUES(?,?,?,?,?)'); $q->execute([$orderId,$m['id'],$m['name'],$qty,$m['price']]);
                    $remaining=$qty; $b=$pdo->prepare('SELECT id,stock_quantity FROM medicine_batches WHERE medicine_id=? AND stock_quantity>0 AND expiry_date>=CURDATE() ORDER BY expiry_date ASC,id ASC FOR UPDATE'); $b->execute([$m['id']]);
                    foreach($b->fetchAll() as $batch) { if($remaining===0) break; $take=min($remaining,(int)$batch['stock_quantity']); $pdo->prepare('UPDATE medicine_batches SET stock_quantity=stock_quantity-? WHERE id=?')->execute([$take,$batch['id']]); $remaining-=$take; }
                    if($remaining>0) throw new RuntimeException('Insufficient unexpired batch stock for ' . $m['name'] . '.');
                    $pdo->prepare('UPDATE medicines SET stock_quantity=stock_quantity-? WHERE id=?')->execute([$qty,$m['id']]);
                }
                $pdo->commit(); unset($_SESSION['cart']); flash('success','Order #' . $orderId . ' placed successfully.'); redirect('?page=order&id=' . $orderId);
            case 'review_order':
                require_role('pharmacist'); $id=(int)($_POST['order_id']??0); $decision=(string)($_POST['decision']??''); $note=trim((string)($_POST['rejection_note']??''));
                if(!in_array($decision,['approve','reject'],true)) throw new RuntimeException('Choose approve or reject.');
                if($decision==='reject' && mb_strlen($note)<5) throw new RuntimeException('Add a short reason so the customer understands the decision.');
                $next=$decision==='approve'?'approved':'rejected'; $pdo=db(); $pdo->beginTransaction();
                $q=$pdo->prepare('UPDATE orders SET status=?,rider_id=IF(?,NULL,rider_id),rejection_note=?,reviewed_by=?,reviewed_at=NOW(),discount_verified=IF(?,1,discount_verified),discount_amount=IF(?,discount_amount,0),total_amount=IF(?,total_amount,subtotal) WHERE id=? AND status=\'pending_review\'');
                $q->execute([$next,(int)($decision==='approve'),$decision==='reject'?$note:null,current_user()['id'],(int)($decision==='approve'),(int)($decision==='approve'),(int)($decision==='approve'),$id]);
                if($q->rowCount()!==1) throw new RuntimeException('This order has already been reviewed.');
                if($decision==='reject') restore_order_stock($id);
                $pdo->commit();
                flash('success','Order #' . $id . ' ' . ($decision==='approve'?'approved.':'rejected with your note.')); redirect('?page=verification');
            case 'accept_delivery':
                $rider=require_role('rider');$id=(int)($_POST['order_id']??0);
                $q=db()->prepare("UPDATE orders SET rider_id=?,status='out_for_delivery',rider_lat=COALESCE(rider_lat,?),rider_lng=COALESCE(rider_lng,?),updated_at=NOW() WHERE id=? AND rider_id IS NULL AND status='approved'");
                $q->execute([$rider['id'],PHARMACY_LAT,PHARMACY_LNG,$id]);
                if($q->rowCount()!==1)throw new RuntimeException('Another rider claimed this delivery, or it is no longer available.');
                flash('success','Delivery #' . $id . ' is now assigned to you.'); redirect('?page=deliveries&tab=active');
            case 'rider_status':
                $rider=require_role('rider'); $id=(int)($_POST['order_id']??0); $status=(string)($_POST['status']??'');
                if($status!=='delivered') throw new RuntimeException('Invalid delivery update.');
                $q=db()->prepare("UPDATE orders SET status='delivered',delivered_at=NOW(),updated_at=NOW() WHERE id=? AND rider_id=? AND status='out_for_delivery'"); $q->execute([$id,$rider['id']]);
                if($q->rowCount()!==1) throw new RuntimeException('This delivery status has already changed.');
                flash('success','Order #' . $id . ' updated to ' . order_status_label($status) . '.'); redirect('?page=deliveries&tab=history');
            case 'save_medicine':
                require_role('pharmacist');
                $id=(int)($_POST['medicine_id']??0); $name=trim((string)($_POST['name']??'')); $description=trim((string)($_POST['description']??''));
                $category=(string)($_POST['category']??'OTC'); $type=(string)($_POST['type']??''); $dosage=trim((string)($_POST['dosage']??'')); $price=(float)($_POST['price']??0);
                $batch=trim((string)($_POST['batch_number']??'')); $expiry=(string)($_POST['expiry_date']??''); $stock=max(0,(int)($_POST['stock_quantity']??0));
                if(mb_strlen($name)<2 || !in_array($category,['OTC','RX'],true) || !in_array($type,['Pain relief','Allergy','Antibiotic','Cardiovascular','Diabetes','Vitamins','Digestive','First aid'],true) || $dosage==='' || $price<=0 || $batch==='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$expiry)) throw new RuntimeException('Complete all medicine fields with a valid batch and expiry date.');
                $pdo=db(); $pdo->beginTransaction();
                if($id){ $q=$pdo->prepare('UPDATE medicines SET name=?,description=?,category=?,type=?,dosage=?,price=?,stock_quantity=(SELECT COALESCE(SUM(stock_quantity),0) FROM medicine_batches WHERE medicine_id=?),is_active=1 WHERE id=?'); $q->execute([$name,$description,$category,$type,$dosage,$price,$id,$id]); }
                else { $q=$pdo->prepare('INSERT INTO medicines(name,description,category,type,dosage,price,stock_quantity) VALUES(?,?,?,?,?,?,0)'); $q->execute([$name,$description,$category,$type,$dosage,$price]); $id=(int)$pdo->lastInsertId(); }
                $q=$pdo->prepare('INSERT INTO medicine_batches(medicine_id,batch_number,expiry_date,stock_quantity) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE expiry_date=VALUES(expiry_date),stock_quantity=stock_quantity+VALUES(stock_quantity)'); $q->execute([$id,$batch,$expiry,$stock]);
                $pdo->prepare('UPDATE medicines SET stock_quantity=(SELECT COALESCE(SUM(stock_quantity),0) FROM medicine_batches WHERE medicine_id=?) WHERE id=?')->execute([$id,$id]); $pdo->commit();
                flash('success','Medicine and batch saved.'); redirect('?page=inventory');
            case 'delete_medicine':
                require_role('pharmacist'); $id=(int)($_POST['medicine_id']??0); db()->prepare('UPDATE medicines SET is_active=0 WHERE id=?')->execute([$id]); flash('success','Medicine removed from the catalog.'); redirect('?page=inventory');
            default: throw new RuntimeException('That action is not available.');
        }
    } catch (Throwable $ex) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if (in_array($action,['checkout'],true)) { if(isset($prescription) && $prescription) @unlink(UPLOAD_DIR.'/'.$prescription); if(isset($seniorId) && $seniorId) @unlink(UPLOAD_DIR.'/'.$seniorId); }
        if($action==='change_photo'&&isset($avatarFilename)&&$avatarFilename)@unlink(AVATAR_DIR.'/'.$avatarFilename);
        flash('error',$ex instanceof PDOException ? 'That change could not be saved. Check the details and try again.' : $ex->getMessage());
        $returnPages=['login'=>'login','register'=>'register','add_cart'=>'shop','update_cart'=>'cart','checkout'=>'cart','review_order'=>'verification','rider_status'=>'deliveries','accept_delivery'=>'deliveries','save_medicine'=>'inventory','delete_medicine'=>'inventory','change_photo'=>'dashboard'];
        $tabs=['accept_delivery'=>'&tab=pool','rider_status'=>'&tab=active'];
        redirect('?page=' . ($returnPages[$action] ?? 'dashboard') . ($tabs[$action]??''));
    }
}

function restore_order_stock(int $id): void
{
    $pdo=db(); $q=$pdo->prepare('SELECT medicine_id,quantity FROM order_items WHERE order_id=? AND medicine_id IS NOT NULL'); $q->execute([$id]);
    foreach($q->fetchAll() as $item) { $pdo->prepare('UPDATE medicines SET stock_quantity=stock_quantity+? WHERE id=?')->execute([$item['quantity'],$item['medicine_id']]);
        $b=$pdo->prepare('SELECT id FROM medicine_batches WHERE medicine_id=? ORDER BY expiry_date ASC,id ASC LIMIT 1'); $b->execute([$item['medicine_id']]); $batch=$b->fetch();
        if($batch) $pdo->prepare('UPDATE medicine_batches SET stock_quantity=stock_quantity+? WHERE id=?')->execute([$item['quantity'],$batch['id']]);
    }
}
