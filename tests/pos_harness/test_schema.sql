-- TEST-ONLY reconstructed schema (inferred from admin/ queries). Not shipped.
DROP DATABASE IF EXISTS groco_test; CREATE DATABASE groco_test CHARACTER SET utf8mb4; USE groco_test;
CREATE TABLE admin_roles(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(60));
CREATE TABLE admin_permissions(id INT AUTO_INCREMENT PRIMARY KEY,permission_key VARCHAR(80));
CREATE TABLE admin_role_permissions(role_id INT,permission_id INT);
CREATE TABLE admins(id INT AUTO_INCREMENT PRIMARY KEY,role_id INT,username VARCHAR(60),email VARCHAR(120),full_name VARCHAR(120),password VARCHAR(255),avatar VARCHAR(255) NULL,is_active TINYINT DEFAULT 1,must_change_password TINYINT DEFAULT 0,remember_token VARCHAR(100) NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE admin_activity_logs(id INT AUTO_INCREMENT PRIMARY KEY,admin_id INT,activity_type VARCHAR(60),description TEXT,ip_address VARCHAR(60),user_agent VARCHAR(255),created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE admin_login_logs(id INT AUTO_INCREMENT PRIMARY KEY,admin_id INT NULL,identity VARCHAR(120),ip_address VARCHAR(60),success TINYINT,reason VARCHAR(255) NULL,login_time DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY,role_id INT DEFAULT 2,full_name VARCHAR(120),email VARCHAR(120),phone VARCHAR(20),password VARCHAR(255),wallet_balance DECIMAL(12,2) DEFAULT 0,reward_points INT DEFAULT 0,is_verified TINYINT DEFAULT 1,is_active TINYINT DEFAULT 1,is_banned TINYINT DEFAULT 0,deleted_at DATETIME NULL,created_at DATETIME NULL,updated_at DATETIME NULL);
CREATE TABLE categories(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100),is_active TINYINT DEFAULT 1);
CREATE TABLE brands(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100));
CREATE TABLE products(id INT AUTO_INCREMENT PRIMARY KEY,category_id INT NULL,brand_id INT NULL,name VARCHAR(200),slug VARCHAR(220),sku VARCHAR(60),barcode VARCHAR(60) NULL,price DECIMAL(12,2),cost_price DECIMAL(12,2) DEFAULT 0,discount_price DECIMAL(12,2) NULL,stock INT DEFAULT 0,min_stock INT DEFAULT 5,weight VARCHAR(30) NULL,unit VARCHAR(20) DEFAULT 'pcs',thumbnail VARCHAR(255) NULL,is_active TINYINT DEFAULT 1,status VARCHAR(20) DEFAULT 'active',deleted_at DATETIME NULL,created_at DATETIME NULL,updated_at DATETIME NULL);
CREATE TABLE orders(id INT AUTO_INCREMENT PRIMARY KEY,order_number VARCHAR(40) UNIQUE,user_id INT,address_id INT NULL,subtotal DECIMAL(12,2),discount_amount DECIMAL(12,2) DEFAULT 0,total_amount DECIMAL(12,2),payment_method ENUM('cod','card','mobile_banking') DEFAULT 'cod',payment_status VARCHAR(20) DEFAULT 'pending',status VARCHAR(20) DEFAULT 'pending',note TEXT NULL,created_at DATETIME);
CREATE TABLE order_items(id INT AUTO_INCREMENT PRIMARY KEY,order_id INT,product_id INT,product_name VARCHAR(200),product_sku VARCHAR(60),price DECIMAL(12,2),quantity INT,line_total DECIMAL(12,2));
CREATE TABLE inventory_logs(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT,admin_id INT NULL,type VARCHAR(30),quantity INT,remaining_stock INT,note VARCHAR(255),created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE expense_categories(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100));
CREATE TABLE transactions(id INT AUTO_INCREMENT PRIMARY KEY,type VARCHAR(20),category_id INT NULL,amount DECIMAL(12,2),reference VARCHAR(255),payment_method VARCHAR(40),reconciled TINYINT DEFAULT 0,created_at DATETIME);
CREATE TABLE settings(id INT AUTO_INCREMENT PRIMARY KEY,key_name VARCHAR(80) UNIQUE,value TEXT,updated_at DATETIME NULL);
CREATE TABLE pos_shifts(id INT AUTO_INCREMENT PRIMARY KEY,admin_id INT,opening_cash DECIMAL(12,2),closing_cash DECIMAL(12,2) NULL,actual_cash DECIMAL(12,2) NULL,status ENUM('open','closed') DEFAULT 'open',start_time DATETIME NULL,end_time DATETIME NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE pos_drawer_transactions(id INT AUTO_INCREMENT PRIMARY KEY,shift_id INT,type ENUM('cash_in','cash_out'),amount DECIMAL(12,2),notes VARCHAR(255),created_at DATETIME);
CREATE TABLE pos_hold_orders(id INT AUTO_INCREMENT PRIMARY KEY,admin_id INT,customer_id INT NULL,cart_data LONGTEXT,hold_notes VARCHAR(255),created_at DATETIME);
CREATE TABLE pos_returns(id INT AUTO_INCREMENT PRIMARY KEY,order_id INT,admin_id INT,refund_amount DECIMAL(12,2),refund_method VARCHAR(30),created_at DATETIME);
CREATE TABLE pos_return_items(id INT AUTO_INCREMENT PRIMARY KEY,pos_return_id INT,product_id INT,quantity INT);
CREATE TABLE dashboard_notifications(id INT AUTO_INCREMENT PRIMARY KEY,type VARCHAR(30),title VARCHAR(160),message TEXT,is_read TINYINT DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
INSERT INTO admin_roles(name) VALUES('Super Admin'),('Cashier'),('Manager');
INSERT INTO admin_permissions(permission_key) VALUES('pos.access'),('pos.sale'),('pos.cash'),('pos.return'),('pos.discount'),('pos.override'),('pos.manage');
INSERT INTO admin_role_permissions SELECT 2,id FROM admin_permissions WHERE permission_key IN('pos.access','pos.sale','pos.cash');
INSERT INTO admin_role_permissions SELECT 3,id FROM admin_permissions;
INSERT INTO categories(name) VALUES('Rice'),('Dairy'),('Vegetables');
INSERT INTO brands(name) VALUES('Pran'),('Fresh');
INSERT INTO products(category_id,brand_id,name,slug,sku,barcode,price,discount_price,stock,min_stock,unit,created_at) VALUES
(1,1,'Miniket Rice 5kg','miniket-5kg','RICE5','8901000000011',450,430,50,10,'pack',NOW()),
(2,2,'Milk 1L','milk-1l','MILK1','8901000000028',90,NULL,3,5,'liter',NOW()),
(3,2,'Potato (loose)','potato','POT','8901000000035',45,NULL,100,10,'kg',NOW()),
(1,1,'Soap','soap','SOAP','8901000000042',60,NULL,0,5,'pcs',NOW());
