-- reviewed_at already exists in the earlier MedDrop schema.
ALTER TABLE users
  ADD COLUMN profile_photo VARCHAR(255) NULL AFTER phone;

ALTER TABLE orders
  ADD COLUMN delivered_at DATETIME NULL AFTER reviewed_at;

UPDATE users
SET profile_photo=CASE email
  WHEN 'customer@meddrop.test' THEN 'demo-customer.svg'
  WHEN 'pharmacist@meddrop.test' THEN 'demo-pharmacist.svg'
  WHEN 'rider@meddrop.test' THEN 'demo-rider.svg'
  ELSE profile_photo
END
WHERE email IN ('customer@meddrop.test','pharmacist@meddrop.test','rider@meddrop.test')
  AND profile_photo IS NULL;

UPDATE orders
SET reviewed_at=CASE WHEN reviewed_at IS NULL AND status IN ('approved','out_for_delivery','delivered','rejected') THEN updated_at ELSE reviewed_at END,
    delivered_at=CASE WHEN status='delivered' THEN COALESCE(delivered_at,updated_at) ELSE delivered_at END
WHERE (status IN ('approved','out_for_delivery','delivered','rejected') AND reviewed_at IS NULL)
   OR (status='delivered' AND delivered_at IS NULL);

-- Ensure the manual rider pool has a ready-to-claim sample job after upgrading.
INSERT INTO orders(user_id,rider_id,status,subtotal,discount_amount,total_amount,delivery_address,payment_method,delivery_lat,delivery_lng,reviewed_at)
SELECT c.id,NULL,'approved',136.00,0.00,136.00,'Apartment 6, Aguinaldo Highway, Imus, Cavite','COD',14.4296000,120.9367000,NOW()
FROM users c
WHERE c.email='customer@meddrop.test'
  AND NOT EXISTS (SELECT 1 FROM orders x WHERE x.user_id=c.id AND x.delivery_address='Apartment 6, Aguinaldo Highway, Imus, Cavite' AND x.status='approved');

INSERT INTO order_items(order_id,medicine_id,medicine_name,quantity,unit_price)
SELECT o.id,m.id,m.name,2,68.00 FROM orders o JOIN users c ON c.id=o.user_id JOIN medicines m ON m.name='Biogesic Paracetamol 500 mg'
WHERE c.email='customer@meddrop.test' AND o.delivery_address='Apartment 6, Aguinaldo Highway, Imus, Cavite' AND o.status='approved'
  AND NOT EXISTS (SELECT 1 FROM order_items x WHERE x.order_id=o.id);
