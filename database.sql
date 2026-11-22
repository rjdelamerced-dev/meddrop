-- MedDrop — Online Pharmacy & Medicine Delivery System
CREATE DATABASE IF NOT EXISTS meddrop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE meddrop;
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','pharmacist','rider') NOT NULL DEFAULT 'customer',
  phone VARCHAR(32) NULL,
  profile_photo VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS medicines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  category ENUM('OTC','RX') NOT NULL,
  type VARCHAR(50) NOT NULL,
  dosage VARCHAR(120) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_medicine_name(name),
  INDEX idx_medicine_browse(category,type,is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS medicine_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  medicine_id BIGINT UNSIGNED NOT NULL,
  batch_number VARCHAR(60) NOT NULL,
  expiry_date DATE NOT NULL,
  stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_medicine_batch(medicine_id,batch_number),
  INDEX idx_fefo(medicine_id,expiry_date,stock_quantity),
  CONSTRAINT fk_batch_medicine FOREIGN KEY(medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  rider_id BIGINT UNSIGNED NULL,
  status ENUM('pending_review','approved','out_for_delivery','delivered','rejected') NOT NULL DEFAULT 'pending_review',
  subtotal DECIMAL(10,2) NOT NULL,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(10,2) NOT NULL,
  prescription_file VARCHAR(255) NULL,
  senior_id_file VARCHAR(255) NULL,
  discount_requested TINYINT(1) NOT NULL DEFAULT 0,
  discount_verified TINYINT(1) NOT NULL DEFAULT 0,
  delivery_address TEXT NOT NULL,
  payment_method ENUM('COD','GCash') NOT NULL DEFAULT 'COD',
  payment_reference VARCHAR(100) NULL,
  rejection_note VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  delivered_at DATETIME NULL,
  delivery_lat DECIMAL(10,7) NULL,
  delivery_lng DECIMAL(10,7) NULL,
  rider_lat DECIMAL(10,7) NULL,
  rider_lng DECIMAL(10,7) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_customer FOREIGN KEY(user_id) REFERENCES users(id),
  CONSTRAINT fk_order_rider FOREIGN KEY(rider_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_order_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_order_queue(status,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  medicine_id BIGINT UNSIGNED NULL,
  medicine_name VARCHAR(160) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_item_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_medicine FOREIGN KEY(medicine_id) REFERENCES medicines(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- All demo users use password_hash('password123', PASSWORD_DEFAULT).
INSERT IGNORE INTO users(name,email,password_hash,role,phone,profile_photo) VALUES
('Alex Santos','customer@meddrop.test','$2y$10$vAEHNtfnG24MHAnzGSU3uOdtnOdxajHjJBmNrKmUIoQiYNf.BiyBm','customer','0917 555 0101','demo-customer.svg'),
('Mia Reyes, RPh','pharmacist@meddrop.test','$2y$10$vAEHNtfnG24MHAnzGSU3uOdtnOdxajHjJBmNrKmUIoQiYNf.BiyBm','pharmacist','0917 555 0102','demo-pharmacist.svg'),
('Noah Garcia','rider@meddrop.test','$2y$10$vAEHNtfnG24MHAnzGSU3uOdtnOdxajHjJBmNrKmUIoQiYNf.BiyBm','rider','0917 555 0103','demo-rider.svg');

INSERT IGNORE INTO medicines(name,description,category,type,dosage,price,stock_quantity) VALUES
('Biogesic Paracetamol 500 mg','Reliable relief for mild pain and fever.','OTC','Pain relief','500 mg · 20 tablets',68.00,0),
('Cetirizine 10 mg','Non-drowsy allergy relief for sneezing and itchy eyes.','OTC','Allergy','10 mg · 10 tablets',42.00,0),
('Ibuprofen 200 mg','Anti-inflammatory pain and fever relief. Take with food.','OTC','Pain relief','200 mg · 10 tablets',55.00,0),
('Amoxicillin 500 mg','Prescription antibiotic. Complete the prescribed course.','RX','Antibiotic','500 mg · 21 capsules',189.00,0),
('Losartan 50 mg','Prescription medicine for blood pressure management.','RX','Cardiovascular','50 mg · 30 tablets',165.00,0),
('Metformin 500 mg','Prescription medicine to support blood sugar control.','RX','Diabetes','500 mg · 30 tablets',138.00,0),
('Vitamin C 500 mg','Daily immune support with ascorbic acid.','OTC','Vitamins','500 mg · 30 tablets',95.00,0),
('Omeprazole 20 mg','Prescription acid reducer for reflux and heartburn.','RX','Digestive','20 mg · 14 capsules',112.00,0),
('Loratadine 10 mg','Once-daily non-drowsy relief from allergy symptoms.','OTC','Allergy','10 mg · 10 tablets',58.00,0),
('Amlodipine 5 mg','Prescription medicine for high blood pressure.','RX','Cardiovascular','5 mg · 30 tablets',125.00,0),
('Oral Rehydration Salts','Electrolyte powder to help prevent dehydration.','OTC','Digestive','1 sachet · 4 sachets',36.00,0),
('Povidone-Iodine 10%','Topical antiseptic solution for minor cuts and scrapes.','OTC','First aid','30 ml bottle',89.00,0),
('Azithromycin 500 mg','Prescription antibiotic supplied in a 3-tablet course.','RX','Antibiotic','500 mg · 3 tablets',220.00,0),
('Multivitamins + Zinc','Daily multivitamin blend with zinc.','OTC','Vitamins','30 tablets',145.00,0);

INSERT IGNORE INTO medicine_batches(medicine_id,batch_number,expiry_date,stock_quantity)
SELECT id, CONCAT('MD-',LPAD(id,3,'0'),'-A'), DATE_ADD(CURDATE(),INTERVAL (CASE WHEN id=2 THEN 42 WHEN id=5 THEN 55 WHEN id=11 THEN 28 ELSE 280+id*17 END) DAY), CASE WHEN id=3 THEN 8 WHEN id=9 THEN 12 ELSE 42+id*3 END FROM medicines;
INSERT IGNORE INTO medicine_batches(medicine_id,batch_number,expiry_date,stock_quantity)
SELECT id, CONCAT('MD-',LPAD(id,3,'0'),'-B'), DATE_ADD(CURDATE(),INTERVAL (520+id*9) DAY), 30 FROM medicines WHERE id IN (1,4,7,10,12,14);
UPDATE medicines m SET stock_quantity=(SELECT COALESCE(SUM(b.stock_quantity),0) FROM medicine_batches b WHERE b.medicine_id=m.id);

-- A sample live delivery is ready when the customer and rider demo accounts sign in.
INSERT IGNORE INTO orders(id,user_id,rider_id,status,subtotal,discount_amount,total_amount,delivery_address,payment_method,delivery_lat,delivery_lng,rider_lat,rider_lng,reviewed_by,reviewed_at)
SELECT 1,c.id,r.id,'out_for_delivery',204.00,0.00,204.00,'Unit 4, Molino Boulevard, Bacoor, Cavite', 'COD',14.4422000,120.9810000,14.4650000,120.9300000,p.id,NOW()
FROM users c JOIN users r ON r.email='rider@meddrop.test' JOIN users p ON p.email='pharmacist@meddrop.test'
WHERE c.email='customer@meddrop.test';
INSERT IGNORE INTO order_items(id,order_id,medicine_id,medicine_name,quantity,unit_price)
SELECT 1,o.id,m.id,m.name,3,68.00 FROM orders o JOIN users c ON c.id=o.user_id JOIN medicines m ON m.name='Biogesic Paracetamol 500 mg'
WHERE o.id=1 AND c.email='customer@meddrop.test' AND o.delivery_address='Unit 4, Molino Boulevard, Bacoor, Cavite';

INSERT IGNORE INTO orders(id,user_id,rider_id,status,subtotal,discount_amount,total_amount,delivery_address,payment_method,delivery_lat,delivery_lng,reviewed_by,reviewed_at)
SELECT 2,c.id,NULL,'approved',136.00,0.00,136.00,'Apartment 6, Aguinaldo Highway, Imus, Cavite','COD',14.4296000,120.9367000,p.id,NOW()
FROM users c JOIN users p ON p.email='pharmacist@meddrop.test'
WHERE c.email='customer@meddrop.test';
INSERT IGNORE INTO order_items(id,order_id,medicine_id,medicine_name,quantity,unit_price)
SELECT 2,o.id,m.id,m.name,2,68.00 FROM orders o JOIN users c ON c.id=o.user_id JOIN medicines m ON m.name='Biogesic Paracetamol 500 mg'
WHERE o.id=2 AND c.email='customer@meddrop.test' AND o.delivery_address='Apartment 6, Aguinaldo Highway, Imus, Cavite';

