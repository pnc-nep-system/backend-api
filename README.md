# NEP Programme API Backend

Welcome to the backend API for the **NEP Programme System**. This application is built using the **Laravel 12** framework, utilizing **Sanctum** for secure API token-based authentication and **L5-Swagger** for OpenAPI documentation.

---

## 🛠️ Technology Stack

- **Framework:** Laravel 12.x
- **PHP Version:** ^8.2
- **Database:** SQLite (default for development), support for MySQL / PostgreSQL / MariaDB
- **Authentication:** Laravel Sanctum (Token Authentication)
- **API Documentation:** Swagger UI (via `darkaonline/l5-swagger`)
- **Asset Bundler:** Vite with Tailwind CSS v4.0 (for frontend components/assets)

---

## 💻 Local Development Setup

Follow these steps to set up the backend application locally:

### 1. Prerequisites
Ensure you have the following installed on your local environment:
- PHP >= 8.2 (with sqlite3, mbstring, openssl, xml, zip, and curl extensions enabled)
- Composer
- Node.js & NPM
- SQLite (or another database server like MySQL if preferred)

### 2. Installation Steps

1. **Clone the Repository:**
   ```bash
   git clone <repository-url>
   cd backend-api
   ```

2. **Install PHP Dependencies:**
   ```bash
   composer install
   ```

3. **Install NPM Dependencies:**
   ```bash
   npm install
   ```

4. **Environment Configuration:**
   Copy the example environment file:
   ```bash
   cp .env.example .env
   ```
   Open the `.env` file and configure your database settings. By default, it is configured for SQLite:
   ```env
   DB_CONNECTION=sqlite
   ```

5. **Generate Application Key:**
   ```bash
   php artisan key:generate
   ```

6. **Run Database Migrations & Seeders:**
   This command creates the tables and seeds the database with initial lookup tables (Provinces, Districts, Education Levels, Budget Bands, and Taxonomy Categories):
   ```bash
   # For SQLite, ensure the file database/database.sqlite exists. If not, Laravel will prompt you to create it.
   php artisan migrate --seed
   ```

7. **Generate API Documentation (Swagger):**
   ```bash
   php artisan l5-swagger:generate
   ```

### 3. Running the Server

To start the local development environment, run:
```bash
composer run dev
```

This custom composer script runs the following services concurrently:
- **Server:** Laravel local development server (accessible at `http://127.0.0.1:8000`)
- **Queue:** Laravel queue listener (`php artisan queue:listen`)
- **Logs:** Laravel Pail interactive log viewer
- **Vite:** Vite dev server for asset compilation

---

## 🚀 Production Deployment Guide

When deploying this backend to a production server (Ubuntu/Debian, CentOS, etc.), follow this guide to ensure security, performance, and reliability.

### 1. Server Prerequisites
- Web Server: Nginx (recommended) or Apache
- PHP >= 8.2 with FPM
- Database: MySQL, PostgreSQL, MariaDB, or SQLite
- Process Manager: Supervisor (for running background queue workers)

### 2. Deploying the Code
1. Clone your repository into your web root directory (e.g., `/var/www/nep-backend`).
2. Set correct directory ownership and permissions. The web server user (e.g., `www-data`) needs write access to the `storage` and `bootstrap/cache` directories:
   ```bash
   sudo chown -R www-data:www-data /var/www/nep-backend
   sudo chmod -R 775 /var/www/nep-backend/storage /var/www/nep-backend/bootstrap/cache
   ```

### 3. Setup Production Environment
1. Create and configure your production `.env` file:
   ```bash
   cp .env.example .env
   ```
2. Set production-specific variables:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://api.yourdomain.com

   # Configure your production database
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nep_production
   DB_USERNAME=nep_user
   DB_PASSWORD=secure_password
   ```
3. Generate the application key if not already set:
   ```bash
   php artisan key:generate
   ```

### 4. Install Production Dependencies
Run Composer with flags to optimize autoloading and exclude dev dependencies:
```bash
composer install --no-dev --optimize-autoloader
```

### 5. Compile Frontend Assets (Vite)
If your backend requires frontend asset compilation:
```bash
npm install
npm run build
```

### 6. Run Production Migrations
Run your migrations with the `--force` flag so that Laravel runs them without confirmation prompts:
```bash
php artisan migrate --force
```
*(Optional) If this is the initial deployment and you need to seed lookup data:*
```bash
php artisan db:seed --force
```

### 7. Performance Optimizations
For production, cache configuration and routes to significantly speed up request processing:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan l5-swagger:generate
```
> **Note:** Whenever you change your `.env` file or add new routes, you must clear and regenerate the cache using these commands.

### 8. Web Server Configuration (Nginx)
Create an Nginx server block pointing to the `public` directory of the application:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.yourdomain.com;
    root /var/www/nep-backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
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

### 9. Queue Worker Setup (Supervisor)
This application processes tasks (e.g., mail sending, background imports) using queues. To keep the queue process running continuously, configure Supervisor.

1. Install Supervisor:
   ```bash
   sudo apt-get install supervisor
   ```
2. Create a configuration file at `/etc/supervisor/conf.d/nep-worker.conf`:
   ```ini
   [program:nep-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /var/www/nep-backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/var/www/nep-backend/storage/logs/worker.log
   stopwaitsecs=3600
   ```
3. Start and update Supervisor:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start nep-worker:*
   ```

---

## 📖 API Documentation (Swagger)

API routes and request/response specifications are documented interactively via Swagger.
- **URL to access UI:** `http://localhost:8000/api/documentation` (or `https://yourdomain.com/api/documentation` in production).
- To update the documentation after making changes to controller annotations, run:
  ```bash
  php artisan l5-swagger:generate
  ```

---

## 🧪 Testing

To run automated tests on the backend API:
```bash
php artisan test
```
