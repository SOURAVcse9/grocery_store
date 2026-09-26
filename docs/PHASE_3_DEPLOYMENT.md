# GroCo Grocery Store — Deployment & Infrastructure Guide

## 1. Co-Existence Deployment Architecture

The GroCo platform is engineered to operate either in a **hybrid edge configuration** or on a **single unified server instance**:

```mermaid
flowchart TD
    User["Customer / Staff"] -->|HTTPS (Port 443)| ReverseProxy["Nginx / Cloudflare Edge"]
    ReverseProxy -->|Storefront /| NextJS["Next.js Node.js Server (Port 3000)"]
    ReverseProxy -->|Admin / POS /api/v1| ApachePHP["Apache / PHP 8.x (Port 80 / 8080)"]
    NextJS -->|Internal API Calls| ApachePHP
    ApachePHP --> MySQL[("MySQL 8.0 InnoDB")]
```

---

## 2. Deployment Configurations

### Option A: Cloudflare / Vercel + Dedicated PHP Backend (Recommended for High Traffic)
- **Storefront**: Hosted on Vercel or Cloudflare Pages with Global Edge CDN.
- **Backend API & Admin**: Hosted on Linux VPS (Ubuntu 22.04 LTS / 24.04 LTS) running PHP 8.2+ and MySQL 8.0.
- **Environment Variables** (`frontend/.env.production`):
  ```env
  NEXT_PUBLIC_API_URL=https://api.grocostore.com/public/api/v1
  NEXT_PUBLIC_SITE_URL=https://grocostore.com
  NEXT_PUBLIC_CLOUDINARY_CLOUD_NAME=groco-store
  ```

### Option B: Unified Server (Self-Hosted on Single VPS / Localhost)
- **Next.js Service**: Managed by PM2:
  ```bash
  cd frontend
  npm run build
  pm2 start npm --name "groco-storefront" -- start -- -p 3000
  ```
- **Reverse Proxy Routing** (Nginx example):
  ```nginx
  server {
      listen 80;
      server_name grocostore.local;

      # Route API, Admin, and Legacy assets to Apache/PHP
      location /grocery-store/public/ {
          proxy_pass http://127.0.0.1:8080;
          proxy_set_header Host $host;
          proxy_set_header X-Real-IP $remote_addr;
      }

      # Route Root Storefront to Next.js
      location / {
          proxy_pass http://127.0.0.1:3000;
          proxy_set_header Host $host;
          proxy_set_header X-Real-IP $remote_addr;
      }
  }
  ```

---

## 3. Environment Variables Reference

### Backend (`.env` in project root)
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8080/grocery-store/public
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=grocery_db
DB_USER=root
DB_PASS=
CACHE_DRIVER=file
QUEUE_DRIVER=file
MAIL_DRIVER=log
CLOUDINARY_CLOUD_NAME=demo
```

### Frontend (`frontend/.env.local`)
```env
NEXT_PUBLIC_API_URL=http://localhost:8080/grocery-store/public/api/v1
NEXT_PUBLIC_SITE_URL=http://localhost:3000
```
