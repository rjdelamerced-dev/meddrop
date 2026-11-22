-- Run once only if this database does not already have the four location columns.
ALTER TABLE orders
  ADD COLUMN delivery_lat DECIMAL(10,7) NULL AFTER reviewed_at,
  ADD COLUMN delivery_lng DECIMAL(10,7) NULL AFTER delivery_lat,
  ADD COLUMN rider_lat DECIMAL(10,7) NULL AFTER delivery_lng,
  ADD COLUMN rider_lng DECIMAL(10,7) NULL AFTER rider_lat;

UPDATE orders
SET delivery_lat=COALESCE(delivery_lat,14.4422000),
    delivery_lng=COALESCE(delivery_lng,120.9810000),
    rider_lat=CASE WHEN status='out_for_delivery' THEN COALESCE(rider_lat,14.4650000) ELSE rider_lat END,
    rider_lng=CASE WHEN status='out_for_delivery' THEN COALESCE(rider_lng,120.9300000) ELSE rider_lng END
WHERE delivery_lat IS NULL OR delivery_lng IS NULL
   OR (status='out_for_delivery' AND (rider_lat IS NULL OR rider_lng IS NULL));

-- Add demonstration jobs when upgrading a database made from the first portfolio seed.
INSERT INTO orders(user_id,rider_id,status,subtotal,discount_amount,total_amount,delivery_address,payment_method,delivery_lat,delivery_lng,rider_lat,rider_lng,reviewed_at)
SELECT c.id,r.id,'out_for_delivery',204.00,0.00,204.00,'Unit 4, Molino Boulevard, Bacoor, Cavite','COD',14.4422000,120.9810000,14.4650000,120.9300000,NOW()
FROM users c JOIN users r ON r.email='rider@meddrop.test'
WHERE c.email='customer@meddrop.test'
  AND NOT EXISTS (SELECT 1 FROM orders x WHERE x.user_id=c.id AND x.delivery_address='Unit 4, Molino Boulevard, Bacoor, Cavite' AND x.status='out_for_delivery');

INSERT INTO orders(user_id,rider_id,status,subtotal,discount_amount,total_amount,delivery_address,payment_method,delivery_lat,delivery_lng,reviewed_at)
SELECT c.id,NULL,'approved',136.00,0.00,136.00,'Apartment 6, Aguinaldo Highway, Imus, Cavite','COD',14.4296000,120.9367000,NOW()
FROM users c
WHERE c.email='customer@meddrop.test'
  AND NOT EXISTS (SELECT 1 FROM orders x WHERE x.user_id=c.id AND x.delivery_address='Apartment 6, Aguinaldo Highway, Imus, Cavite' AND x.status='approved');

INSERT INTO order_items(order_id,medicine_id,medicine_name,quantity,unit_price)
SELECT o.id,m.id,m.name,3,68.00 FROM orders o JOIN users c ON c.id=o.user_id JOIN medicines m ON m.name='Biogesic Paracetamol 500 mg'
WHERE c.email='customer@meddrop.test' AND o.delivery_address='Unit 4, Molino Boulevard, Bacoor, Cavite' AND o.status='out_for_delivery'
  AND NOT EXISTS (SELECT 1 FROM order_items x WHERE x.order_id=o.id);
INSERT INTO order_items(order_id,medicine_id,medicine_name,quantity,unit_price)
SELECT o.id,m.id,m.name,2,68.00 FROM orders o JOIN users c ON c.id=o.user_id JOIN medicines m ON m.name='Biogesic Paracetamol 500 mg'
WHERE c.email='customer@meddrop.test' AND o.delivery_address='Apartment 6, Aguinaldo Highway, Imus, Cavite' AND o.status='approved'
  AND NOT EXISTS (SELECT 1 FROM order_items x WHERE x.order_id=o.id);
