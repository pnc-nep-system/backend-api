# Production Deployment Guide: Database & Laravel Backend

This document guides you through deploying the database server and Laravel backend API in production using the specific multi-tier network topology configured.

---

## 🌐 Network Topology Reference

- **Database VM Server:** `172.16.16.203` (Runs MySQL 8.0 + phpMyAdmin)
- **Application API Server:** `172.16.16.179` (Runs Nginx + PHP 8.3-FPM + Redis + Reverb + Supervisor)
- **API Domain:** `nep-server.org` (or `nep.org`)
- **Frontend Web Application Domain:** `nep-client.org` (or `nep.org`)

---

## 🗄️ Part 1: Database Server Deployment (`172.16.16.203`)

Run these commands inside your **Database VM (`172.16.16.203`)** to install MySQL, configure remote access, and set up phpMyAdmin.

### 1. Install MySQL Server & Web Server
```bash
sudo apt update
sudo apt install mysql-server apache2 php php-cli php-mysql php-mbstring php-xml php-zip php-gd php-curl libapache2-mod-php phpmyadmin -y
```
*During phpMyAdmin setup: Select `apache2` using the Spacebar, hit Tab, then select `Yes` for `dbconfig-common`.*

### 2. Configure MySQL for Remote Connections
Open the configuration file:
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```
Change `bind-address` to allow external connections:
```ini
bind-address = 0.0.0.0
```
Save (`Ctrl+O`, `Enter`) and exit (`Ctrl+X`). Restart MySQL:
```bash
sudo systemctl restart mysql
```

### 3. Open Firewall Ports
Allow database traffic and web server traffic:
```bash
sudo ufw allow 3306/tcp
sudo ufw allow 80/tcp
sudo ufw reload
```

### 4. Create Database and User Account
Log into MySQL:
```bash
sudo mysql -u root
```
Run the following SQL commands:
```sql
CREATE DATABASE `pnc-nep-system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'nep_user'@'%' IDENTIFIED BY '12345678';
GRANT ALL PRIVILEGES ON `pnc-nep-system`.* TO 'nep_user'@'%';
FLUSH PRIVILEGES;
EXIT;
```

### 5. Link phpMyAdmin and Enable PHP 8.3 in Apache
Link configuration files and reload Apache:
```bash
sudo ln -s /etc/phpmyadmin/apache.conf /etc/apache2/conf-available/phpmyadmin.conf
sudo a2enconf phpmyadmin
sudo a2enmod php8.3
sudo systemctl restart apache2
```
*Access phpMyAdmin in your browser at `http://172.16.16.203/phpmyadmin` (Log in as `nep_user` / `12345678`).*

---

## 🚀 Part 2: Application API Server Deployment (`172.16.16.179`)

Run these commands inside your **Application API Server (`172.16.16.179`)** to deploy the Laravel API.

### 1. Install Server Dependencies
```bash
sudo apt update
sudo apt install nginx git composer redis-server supervisor php8.3-fpm php8.3-mysql php8.3-redis php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-curl -y
```

### 2. Clone Repository using Personal Access Token (PAT)
```bash
sudo mkdir -p /var/www
sudo chown -R $USER:$USER /var/www
cd /var/www
git clone -b production https://github.com/pnc-nep-system/backend-api.git backend-api
cd backend-api
```

### 3. Install Packages & Set Permissions
```bash
composer install --no-interaction --no-dev --optimize-autoloader
npm install && npm run build-only

# Give ownership to current user and www-data web group
sudo chown -R $USER:www-data /var/www/backend-api
sudo chmod -R 775 /var/www/backend-api/storage
sudo chmod -R 775 /var/www/backend-api/bootstrap/cache
```

### 4. Environment Variables Configuration (`.env`)
Create and modify the `.env` file:
```bash
cp .env.example .env
nano .env
```

Use this production-optimized `.env` template:

```env
APP_NAME="NEP Portal API"
APP_ENV=production
APP_KEY=base64:I/t6V6E3ym9oTPeWaFyE2bzJ4F8/lcvhGLEQs52L0o0=
APP_DEBUG=false
APP_URL=http://nep-server.org
FRONTEND_URL=http://nep-client.org

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

# Remote Database Server (172.16.16.203)
DB_CONNECTION=mysql
DB_HOST=172.16.16.203
DB_PORT=3306
DB_DATABASE=pnc-nep-system
DB_USERNAME=nep_user
DB_PASSWORD=12345678

# Session Configuration
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=nep-server.org
SESSION_SAME_SITE=lax
SESSION_SECURE_COOKIE=false

# Stateful Authentication Domains
SANCTUM_STATEFUL_DOMAINS=nep-client.org,localhost:5173,127.0.0.1:5173

# Cache & Queue (Redis)
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MEMCACHED_HOST=127.0.0.1

# Realtime Broadcasting (Laravel Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=483304
REVERB_APP_KEY=zqxunmw8ld7bdiwzlrvj
REVERB_APP_SECRET=k0ay19alnwqt5zeiu1wl
REVERB_HOST="0.0.0.0"
REVERB_PORT=8081
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# Mail Configuration (Gmail SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=jame.khouen@student.passerellesnumeriques.org
MAIL_ENCRYPTION=tls
MAIL_PASSWORD="mgko bunp gffy kroh"
MAIL_FROM_ADDRESS="jame.khouen@student.passerellesnumeriques.org"
MAIL_FROM_NAME="NEP Cambodia"

# Third-Party API Services
IMAGEKIT_PUBLIC_KEY=public_LOQZxmOszSDVdlnD1Jboe1c2qRA=
IMAGEKIT_PRIVATE_KEY=private_y+Y6w12w6CeIHMnCBSg4muVekwM=
IMAGEKIT_URL_ENDPOINT=https://ik.imagekit.io/rn6hppesw

FILESYSTEM_DISK=local
VITE_APP_NAME="${APP_NAME}"
L5_SWAGGER_USE_ABSOLUTE_PATH=false
```

### 5. Finalize Installation, Swagger & Caching
```bash
php artisan key:generate
php artisan migrate --force
php artisan l5-swagger:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 6. Configure Supervisor for WebSockets (Reverb) and Queue Workers
Create `/etc/supervisor/conf.d/nep-backend.conf`:

```bash
sudo tee /etc/supervisor/conf.d/nep-backend.conf << 'EOF'
[program:reverb]
command=php /var/www/backend-api/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/backend-api/storage/logs/reverb.log

[program:nep-queue-worker]
command=php /var/www/backend-api/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/backend-api/storage/logs/worker.log
EOF
```

Start Supervisor daemons:
```bash
sudo systemctl start supervisor
sudo systemctl enable supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

### 7. Configure Nginx Virtual Host
Create the configuration file:
```bash
sudo nano /etc/nginx/sites-available/backend-api
```

Paste this configuration:

```nginx
server {
    listen 80;
    server_name nep-server.org;
    root /var/www/backend-api/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

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
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param HTTP_HOST $host;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site config, disable default site, and restart servers:
```bash
sudo ln -s /etc/nginx/sites-available/backend-api /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
```

---

## 🔍 Part 3: Verification

Verify that the backend API service is live by sending a request:
```bash
curl -I http://nep-server.org/api/login
```
*(Should return `HTTP/1.1 405 Method Not Allowed` with `allow: POST` headers).*
