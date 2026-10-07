# GroCo Grocery Store — Production Deployment Guide

This guide details the complete deployment process for running the **GroCo Grocery Store** application on a production Linux or Windows server with Apache or Nginx, PHP 8.2+, and MySQL 8+.

---

## 1. System Requirements & Server Prerequisites

### Hardware Requirements
- **CPU**: 2+ vCPU recommended
- **RAM**: 2 GB minimum (4 GB+ recommended for high-volume traffic)
- **Disk**: 20 GB SSD storage minimum

### Software Stack
- **Operating System**: Ubuntu 22.04 / 24.04 LTS, Debian 12, or AlmaLinux / RHEL 9
- **PHP**: PHP 8.2 or PHP 8.3
  - Required Extensions: `php8.2-fpm`, `php8.2-mysql`, `php8.2-curl`, `php8.2-mbstring`, `php8.2-gd`, `php8.2-zip`, `php8.2-xml`, `php8.2-bcmath`, `php8.2-intl`
- **Database**: MySQL 8.0+ or MariaDB 10.11+
- **Web Server**: Apache 2.4+ (with `mod_rewrite`, `mod_headers`, `mod_deflate`, `mod_expires`) or Nginx 1.22+
- **SSL Certificate**: Let's Encrypt Certbot or commercial SSL

---

## 2. Directory Layout & Permissions

Deploy the codebase into `/var/www/grocery-store`:

```bash
# Clone or copy project files
cd /var/www/grocery-store

# Ensure web server ownership
sudo chown -R www-data:www-data /var/www/grocery-store

# Standard file & directory permissions
sudo find /var/www/grocery-store -type d -exec chmod 755 {} \;
sudo find /var/www/grocery-store -type f -exec chmod 644 {} \;

# Storage and uploads require write access
sudo chmod -R 775 /var/www/grocery-store/storage
sudo chmod -R 775 /var/www/grocery-store/public/uploads

# Protect environment secrets
sudo chmod 600 /var/www/grocery-store/.env
```

---

## 3. Environment Configuration (`.env`)

1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
2. Configure production values:
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-grocery-domain.com

   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=grocery_store
   DB_USER=groco_prod_user
   DB_PASS=A_Strong_Random_Password_Here
   DB_CHARSET=utf8mb4

   SESSION_SECURE=true
   SESSION_HTTPONLY=true
   SESSION_SAMESITE=Lax
   SESSION_TIMEOUT=7200
   CSRF_SECRET_KEY=generate_a_64_character_random_hex_secret_key_here

   CLOUDINARY_CLOUD_NAME=your_cloud_name
   CLOUDINARY_API_KEY=your_api_key
   CLOUDINARY_API_SECRET=your_api_secret

   GA_MEASUREMENT_ID=G-XXXXXXXXXX
   GSC_VERIFICATION_TOKEN=your_google_verification_token
   ```

---

## 4. Database Setup & Migration

1. Create production database and user in MySQL:
   ```sql
   CREATE DATABASE grocery_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'groco_prod_user'@'localhost' IDENTIFIED BY 'A_Strong_Random_Password_Here';
   GRANT ALL PRIVILEGES ON grocery_store.* TO 'groco_prod_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
2. Import schema and initial data:
   ```bash
   mysql -u groco_prod_user -p grocery_store < database/schema.sql
   ```

---

## 5. Web Server Configuration

### Apache VirtualHost (Recommended)

Ensure required Apache modules are enabled:
```bash
sudo a2enmod rewrite headers deflate expires ssl
```

VirtualHost configuration (`/etc/apache2/sites-available/grocery-store.conf`):

```apache
<VirtualHost *:80>
    ServerName your-grocery-domain.com
    ServerAlias www.your-grocery-domain.com
    Redirect permanent / https://your-grocery-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-grocery-domain.com
    DocumentRoot /var/www/grocery-store/public

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/your-grocery-domain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-grocery-domain.com/privkey.pem

    <Directory /var/www/grocery-store/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Block access to internal project root if docroot is set to repo root
    <Directory /var/www/grocery-store/storage>
        Require all denied
    </Directory>
    <Directory /var/www/grocery-store/database>
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/grocery_store_error.log
    CustomLog ${APACHE_LOG_DIR}/grocery_store_access.log combined
</VirtualHost>
```

### Nginx Configuration (Alternative)

```nginx
server {
    listen 80;
    server_name your-grocery-domain.com www.your-grocery-domain.com;
    return 301 https://your-grocery-domain.com$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-grocery-domain.com;
    root /var/www/grocery-store/public;
    index index.php index.html;

    ssl_certificate /etc/letsencrypt/live/your-grocery-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-grocery-domain.com/privkey.pem;

    # Security Headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Deny hidden and sensitive files
    location ~ /\.(?!well-known) {
        deny all;
    }
    location ~* \.(sql|log|bak|key|pem|env)$ {
        deny all;
    }

    # Clean SEO URL rewrites
    location /product/ {
        rewrite ^/product/([a-zA-Z0-9_-]+)/?$ /product.php?slug=$1 last;
    }
    location /category/ {
        rewrite ^/category/([a-zA-Z0-9_-]+)/?$ /products.php?category=$1 last;
    }
    location /brand/ {
        rewrite ^/brand/([a-zA-Z0-9_-]+)/?$ /products.php?brand=$1 last;
    }
    location /search {
        rewrite ^/search/?$ /search.php last;
    }
    location = /sitemap.xml {
        rewrite ^ /sitemap.php last;
    }
    location = /robots.txt {
        rewrite ^ /robots.php last;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 6. Scheduled Cron Jobs

Configure automated cron tasks for backups and queue processing:

```bash
sudo crontab -e -u www-data
```

Add the following jobs:
```text
# Daily Database Backup at 02:00 AM (Retain 7 days, compress with Gzip)
0 2 * * * /usr/bin/php /var/www/grocery-store/tools/backup_database.php --compress --keep=7 --quiet

# Background Queue & Email worker (Every minute)
* * * * * /usr/bin/php /var/www/grocery-store/tools/queue_worker.php --quiet 2>&1 > /dev/null
```

---

## 7. Post-Deployment Verification

Execute the automated test suite on the server to verify complete operational integrity:

```bash
php tests/production_readiness_test.php
php tests/seo_cloudinary_upgrade_verification_test.php
php tests/public_seo_audit_test.php
```

All tests must pass with 0 errors.

---

## 8. Rollback Procedure

In the event of an unexpected issue during deployment:

1. **Restore Codebase**:
   ```bash
   git checkout <previous-stable-tag-or-commit>
   ```
2. **Restore Database** (if schema migration failed):
   ```bash
   gunzip -c /var/www/grocery-store/storage/backups/<backup-file>.sql.gz | mysql -u groco_prod_user -p grocery_store
   ```
3. **Restart Web Services**:
   ```bash
   sudo systemctl restart php8.2-fpm
   sudo systemctl reload apache2 # or nginx
   ```
