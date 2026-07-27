# Production Deployment Guide

This guide provides step-by-step instructions for deploying the **NEP Programme API Backend** to a production Linux environment (Ubuntu 22.04 LTS / Debian 12) using Nginx, MySQL, Redis, Laravel Reverb (WebSockets), and Supervisor.

- **Repository:** `https://github.com/pnc-nep-system/backend-api.git`
- **PHP Version:** 8.2+
- **Database:** MySQL 8.0+ (`pnc-nep-system`)
- **Queue & Cache:** Redis (`predis` client)
- **WebSockets / Realtime:** Laravel Reverb (Port `8081`)

---

## 📄 Complete Production Environment Configuration (`.env`)

Create your production `.env` file at `/var/www/backend-api/.env`:

```env
APP_NAME="NEP Programme API"
APP_ENV=production
APP_KEY=base64:CuHZ3wJhxbMsuIPS4M7hpMMWbuda24D8L1SX58YPh5k=
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
FRONTEND_URL=https://yourdomain.com

SESSION_SAME_SITE=lax
SESSION_SECURE_COOKIE=true

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Database (MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pnc-nep-system
DB_USERNAME=root
DB_PASSWORD=your_secure_db_password

# Session Configuration
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=

# Cache & Queue (Redis)
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MEMCACHED_HOST=127.0.0.1

# Mail Configuration (Gmail SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=jame.khouen@student.passerellesnumeriques.org
MAIL_ENCRYPTION=tls
MAIL_PASSWORD="mgko bunp gffy kroh"
MAIL_FROM_ADDRESS="jame.khouen@student.passerellesnumeriques.org"
MAIL_FROM_NAME="NEP Cambodia"

# Broadcast & Realtime (Laravel Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=483304
REVERB_APP_KEY=zqxunmw8ld7bdiwzlrvj
REVERB_APP_SECRET=k0ay19alnwqt5zeiu1wl
REVERB_HOST="localhost"
REVERB_PORT=8081
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# Filesystem
FILESYSTEM_DISK=local

# AWS S3 (Optional)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Third-Party Integrations
IMAGEKIT_PUBLIC_KEY=public_LOQZxmOszSDVdlnD1Jboe1c2qRA=
IMAGEKIT_PRIVATE_KEY=private_y+Y6w12w6CeIHMnCBSg4muVekwM=
IMAGEKIT_URL_ENDPOINT=https://ik.imagekit.io/rn6hppesw

# Frontend & Documentation
VITE_APP_NAME="${APP_NAME}"
L5_SWAGGER_USE_ABSOLUTE_PATH=false
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,api.yourdomain.com
```

---

## 🛠️ Step 1: Server Provisioning & Dependencies

Update packages and install PHP 8.2, Nginx, Redis, and required PHP extensions:

```bash
# Update server packages
sudo apt update && sudo apt upgrade -y

# Add PHP Repository
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.2, Nginx, Redis, Git, and extensions
sudo apt install -y nginx redis-server git unzip composer php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd php8.2-intl supervisor
```

---

## 🚀 Step 2: Deploying the Codebase

1. **Clone the Repository:**
   ```bash
   sudo mkdir -p /var/www
   sudo chown -R $USER:$USER /var/www
   git clone https://github.com/pnc-nep-system/backend-api.git /var/www/backend-api
   cd /var/www/backend-api
   ```

2. **Install PHP Dependencies (Optimized):**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Install Frontend Build Assets:**
   ```bash
   npm install
   npm run build
   ```

4. **Directory Permissions:**
   Grant proper write permissions to `www-data` for storage and cache:
   ```bash
   sudo chown -R www-data:www-data /var/www/backend-api/storage
   sudo chown -R www-data:www-data /var/www/backend-api/bootstrap/cache
   sudo chmod -R 775 /var/www/backend-api/storage
   sudo chmod -R 775 /var/www/backend-api/bootstrap/cache
   ```

5. **Run Production Migrations & Cache Optimization:**
   ```bash
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan l5-swagger:generate
   ```

---

## 🌐 Step 3: Nginx Server Block Configuration

Create `/etc/nginx/sites-available/api.yourdomain.com`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.yourdomain.com;
    root /var/www/backend-api/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    # Standard Laravel Route Handling
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Proxy WebSocket Traffic to Laravel Reverb (Port 8081)
    location /app {
        proxy_pass http://127.0.0.1:8081;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable Nginx configuration and reload:
```bash
sudo ln -s /etc/nginx/sites-available/api.yourdomain.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 🔒 Step 4: SSL Certificate (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d api.yourdomain.com
```

---

## ⚙️ Step 5: Supervisor Process Management (Queue & Reverb)

Create Supervisor configuration at `/etc/supervisor/conf.d/nep-backend.conf`:

```ini
[program:nep-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/backend-api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/backend-api/storage/logs/worker.log
stopwaitsecs=3600

[program:nep-reverb-websocket]
command=php /var/www/backend-api/artisan reverb:start
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/backend-api/storage/logs/reverb.log
stopwaitsecs=3600
```

Start and manage Supervisor processes:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

---

## ⏱️ Step 6: Cron Job (Laravel Scheduler)

Add the Laravel Task Scheduler to the server's crontab:

```bash
sudo crontab -e -u www-data
```

Add the following line:
```cron
* * * * * cd /var/www/backend-api && php artisan schedule:run >> /dev/null 2>&1
```

---

## ✅ Deployment Checklist

- [ ] **APP_ENV:** Set to `production`
- [ ] **APP_DEBUG:** Set to `false`
- [ ] **Database Connection:** MySQL operational on `pnc-nep-system`
- [ ] **Redis Running:** `sudo systemctl status redis-server` active
- [ ] **Queue Worker:** Supervisor running `nep-queue-worker`
- [ ] **Reverb Server:** Supervisor running `nep-reverb-websocket` on port `8081`
- [ ] **Swagger UI:** Accessible at `https://api.yourdomain.com/api/documentation`
