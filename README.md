# NEP Programme API Backend

Welcome to the backend API for the **NEP Programme System**. This application is built using the **Laravel 12** framework, utilizing **Sanctum** for secure API token-based authentication, **L5-Swagger** for OpenAPI documentation, and **Laravel Reverb** for real-time WebSocket broadcasting.

---

## 🛠️ Technology Stack

- **Framework:** Laravel 12.x
- **PHP Version:** ^8.2
- **Database:** SQLite (default for development), support for MySQL / PostgreSQL / MariaDB
- **Authentication:** Laravel Sanctum (Token Authentication)
- **API Documentation:** Swagger UI (via `darkaonline/l5-swagger`)
- **Real-time Broadcasting:** Laravel Reverb (WebSocket server)
- **Cache & Queue Driver:** Redis (via `predis/predis`)
- **Frontend Broadcasting:** Laravel Echo + Pusher-js
- **Asset Bundler:** Vite with Tailwind CSS v4.0 (for frontend components/assets)

---

## 💻 Local Development Setup

Follow these steps to set up the backend application locally:

### 1. Prerequisites

Ensure you have the following installed on your local environment:
- PHP >= 8.2 (with sqlite3, mbstring, openssl, xml, zip, curl, and pcntl extensions enabled)
- Composer
- Node.js & NPM
- **Redis Server** (required for broadcasting and caching)
- SQLite (or another database server like MySQL if preferred)

### 2. Install Redis

Redis is required for Laravel Reverb broadcasting and can also be used for caching and queues.

#### On Windows:
1. Download Redis for Windows from [Microsoft Archive](https://github.com/microsoftarchive/redis/releases) or use [WSL2](https://learn.microsoft.com/en-us/windows/wsl/install)
2. Or install via WSL2:
   ```bash
   wsl -d Ubuntu
   sudo apt update
   sudo apt install redis-server
   sudo service redis-server start
   ```

#### On macOS:
```bash
brew install redis
brew services start redis
```

#### On Ubuntu/Debian:
```bash
sudo apt update
sudo apt install redis-server
sudo systemctl start redis
sudo systemctl enable redis
```

#### On CentOS/RHEL/Fedora:
```bash
sudo dnf install redis
sudo systemctl start redis
sudo systemctl enable redis
```

#### Verify Redis Installation:
```bash
redis-cli ping
# Should return: PONG
```

### 3. Installation Steps

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
   
   Open the `.env` file and configure your settings. By default, it is configured for SQLite:
   ```env
   APP_NAME="NEP Programme API"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000
   
   # Database Configuration (SQLite by default)
   DB_CONNECTION=sqlite
   
   # Redis Configuration
   REDIS_CLIENT=phpredis
   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379
   
   # Broadcasting Configuration (Laravel Reverb)
   BROADCAST_CONNECTION=reverb
   
   # Reverb Configuration
   REVERB_APP_ID=your-app-id
   REVERB_APP_KEY=your-app-key
   REVERB_APP_SECRET=your-app-secret
   REVERB_HOST=localhost
   REVERB_PORT=8080
   REVERB_SCHEME=http
   
   # Cache Configuration (optional - can use Redis)
   CACHE_STORE=redis
   # CACHE_PREFIX=
   
   # Queue Configuration (optional - can use Redis)
   QUEUE_CONNECTION=redis
   ```

5. **Generate Application Key:**
   ```bash
   php artisan key:generate
   ```

6. **Run Database Migrations & Seeders:**
   This command creates the tables and seeds the database with initial lookup tables:
   ```bash
   # For SQLite, ensure the file database/database.sqlite exists
   php artisan migrate --seed
   ```

7. **Generate API Documentation (Swagger):**
   ```bash
   php artisan l5-swagger:generate
   ```

### 4. Running the Server

To start the local development environment, run:
```bash
composer run dev
```

This custom composer script runs the following services concurrently:
- **Server:** Laravel local development server (accessible at `http://127.0.0.1:8000`)
- **Queue:** Laravel queue listener (`php artisan queue:listen`)
- **Logs:** Laravel Pail interactive log viewer
- **Vite:** Vite dev server for asset compilation

**Note:** Ensure Redis server is running before starting the application.

### 5. Running Laravel Reverb (WebSocket Server)

Laravel Reverb provides real-time WebSocket broadcasting. To start the Reverb server:

```bash
php artisan reverb:start
```

This will start the WebSocket server on `ws://localhost:8080` by default.

**Running Reverb in Production:**
```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

For production deployments, it's recommended to run Reverb as a daemon using Supervisor (see Production Deployment section below).

---

## 🔴 Redis Configuration

### Redis Usage in This Project

Redis is used for:
1. **Broadcasting:** Laravel Reverb uses Redis for scaling and pub/sub messaging
2. **Caching:** Optional cache driver for improved performance
3. **Queue:** Optional queue driver for background job processing

### Redis Environment Variables

Add these to your `.env` file:

```env
# Redis Connection
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# For Redis Cluster (optional)
# REDIS_CLIENT=phpredis
# REDIS_HOST=127.0.0.1
# REDIS_PASSWORD=null
# REDIS_PORT=6379
# REDIS_DB=0

# For Sentinel (optional)
# REDIS_CLIENT=phpredis
# REDIS_SENTINEL=127.0.0.1:26379
# REDIS_SENTINEL_MASTER=mymaster
```

### Redis Configuration File

Update `config/database.php` if needed (default configuration should work for most cases):

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', 'laravel_database_'),
    ],
    
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],
    
    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],
],
```

---

## 📡 Laravel Reverb (WebSocket Broadcasting)

### What is Laravel Reverb?

Laravel Reverb is a WebSocket server built specifically for Laravel applications. It enables real-time event broadcasting to connected clients.

### Broadcasting Events

This project includes the following broadcast event:

**Event:** `TaxonomyOtherQueueCreated`
- **Channel:** `nep-admin` (private channel)
- **Purpose:** Notifies NEP Admin and Coordinator users when a taxonomy "Other" queue entry is created
- **Broadcast Name:** `other.queue.created`

### Broadcasting Channels

Defined in `routes/channels.php`:

1. **`App.Models.User.{id}`** - Private user channel for individual user notifications
2. **`nep-admin`** - Private channel for staff-only notifications (NEP Admin and Coordinator roles)

### Reverb Environment Variables

```env
# Reverb Server Configuration
REVERB_APP_ID=1
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# For production with TLS
# REVERB_SCHEME=https
# REVERB_PORT=443
```

### Starting Reverb Server

**Development:**
```bash
php artisan reverb:start
```

**Production (with specific host/port):**
```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

**Run Reverb in background:**
```bash
php artisan reverb:start &
```

---

## 🎧 Laravel Echo & Pusher-js (Frontend)

### Installation

The following NPM packages are already included in `package.json`:
- `laravel-echo` - Laravel Echo client for JavaScript
- `pusher-js` - Pusher JavaScript client (compatible with Reverb)

### Frontend Configuration

Create a JavaScript/TypeScript configuration file for Laravel Echo:

**Example: `resources/js/echo.js`**
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/api/broadcasting/auth'
});
```

**Example: `resources/js/app.js`**
```javascript
import './echo';
// Your other JavaScript code
```

### Listening to Events (Frontend Example)

```javascript
// Listen for the 'other.queue.created' event on the 'nep-admin' channel
Echo.private('nep-admin')
    .listen('.other.queue.created', (e) => {
        console.log('New taxonomy queue entry:', e);
        // Handle the event
        // e.id, e.other_text, e.programme_entry_id
    });
```

### Vite Environment Variables

Add these to your `.env` file for Vite:

```env
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

---

## 🚀 Production Deployment Guide

When deploying this backend to a production server (Ubuntu/Debian, CentOS, etc.), follow this guide to ensure security, performance, and reliability.

### 1. Server Prerequisites

- Web Server: Nginx (recommended) or Apache
- PHP >= 8.2 with FPM
- Database: MySQL, PostgreSQL, MariaDB, or SQLite
- **Redis Server** (for broadcasting, caching, and queues)
- Process Manager: Supervisor (for running queue workers and Reverb server)

### 2. Install Redis on Production

#### Ubuntu/Debian:
```bash
sudo apt update
sudo apt install redis-server
sudo systemctl start redis
sudo systemctl enable redis
```

#### CentOS/RHEL/Fedora:
```bash
sudo dnf install redis
sudo systemctl start redis
sudo systemctl enable redis
```

#### Verify Redis:
```bash
redis-cli ping
# Should return: PONG
```

### 3. Deploying the Code

1. Clone your repository into your web root directory (e.g., `/var/www/nep-backend`).
2. Set correct directory ownership and permissions:
   ```bash
   sudo chown -R www-data:www-data /var/www/nep-backend
   sudo chmod -R 775 /var/www/nep-backend/storage /var/www/nep-backend/bootstrap/cache
   ```

### 4. Setup Production Environment

1. Create and configure your production `.env` file:
   ```bash
   cp .env.example .env
   ```
2. Set production-specific variables:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://api.yourdomain.com
   
   # Database Configuration
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nep_production
   DB_USERNAME=nep_user
   DB_PASSWORD=secure_password
   
   # Redis Configuration
   REDIS_CLIENT=phpredis
   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379
   
   # Broadcasting Configuration
   BROADCAST_CONNECTION=reverb
   CACHE_STORE=redis
   QUEUE_CONNECTION=redis
   
   # Reverb Configuration
   REVERB_APP_ID=1
   REVERB_APP_KEY=your-production-app-key
   REVERB_APP_SECRET=your-production-app-secret
   REVERB_HOST=api.yourdomain.com
   REVERB_PORT=443
   REVERB_SCHEME=https
   ```
3. Generate the application key if not already set:
   ```bash
   php artisan key:generate
   ```

### 5. Install Production Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 6. Compile Frontend Assets (Vite)

```bash
npm install
npm run build
```

### 7. Run Production Migrations

```bash
php artisan migrate --force
```

*(Optional) If this is the initial deployment and you need to seed lookup data:*
```bash
php artisan db:seed --force
```

### 8. Performance Optimizations

For production, cache configuration and routes:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan l5-swagger:generate
```

> **Note:** Whenever you change your `.env` file or add new routes, you must clear and regenerate the cache using these commands.

### 9. Queue Worker Setup (Supervisor)

This application processes tasks using queues. To keep the queue process running continuously, configure Supervisor.

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

### 10. Laravel Reverb Server Setup (Supervisor)

To run the Reverb WebSocket server as a daemon in production:

1. Create a configuration file at `/etc/supervisor/conf.d/nep-reverb.conf`:
   ```ini
   [program:nep-reverb]
   process_name=%(program_name)s
   command=php /var/www/nep-backend/artisan reverb:start --host=0.0.0.0 --port=8080
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   redirect_stderr=true
   stdout_logfile=/var/www/nep-backend/storage/logs/reverb.log
   stopwaitsecs=3600
   ```
2. Update Supervisor:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start nep-reverb
   ```

### 11. Web Server Configuration (Nginx)

Create an Nginx server block pointing to the `public` directory:

```nginx
server {
    listen 80;
    listen [::]:80;
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api.yourdomain.com;
    
    # SSL Configuration (optional - for HTTPS)
    # ssl_certificate /path/to/certificate.crt;
    # ssl_certificate_key /path/to/private.key;
    
    root /var/www/nep-backend/public;
    
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    
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
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # Increase upload size for API
    client_max_body_size 50M;
}
```

### 12. SSL Certificate (Let's Encrypt)

For HTTPS in production:
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d api.yourdomain.com
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

---

## 🔧 Troubleshooting

### Redis Connection Issues

**Problem:** Cannot connect to Redis

**Solution:**
1. Verify Redis is running: `redis-cli ping`
2. Check Redis configuration in `.env`
3. Ensure Redis server is accessible from your application
4. Check firewall rules if Redis is on a remote server

### Reverb WebSocket Connection Issues

**Problem:** WebSocket connection fails

**Solution:**
1. Ensure Reverb server is running: `php artisan reverb:start`
2. Verify `REVERB_HOST` and `REVERB_PORT` in `.env`
3. Check that WebSocket port (default: 8080) is open in firewall
4. For production with HTTPS, ensure `REVERB_SCHEME=https` and proper SSL certificates
5. Check Reverb logs: `storage/logs/reverb.log`

### Broadcasting Events Not Received

**Problem:** Events are broadcast but not received on frontend

**Solution:**
1. Verify `BROADCAST_CONNECTION=reverb` in `.env`
2. Ensure user is authenticated (private channels require authentication)
3. Check that frontend Echo configuration matches backend Reverb configuration
4. Verify CORS settings in `config/cors.php` allow WebSocket connections
5. Check browser console for WebSocket connection errors

### Queue Jobs Not Processing

**Problem:** Queue jobs are stuck

**Solution:**
1. Ensure queue worker is running: `php artisan queue:work`
2. Check queue connection in `.env` (should be `redis` or `database`)
3. Verify Redis connection if using `redis` queue driver
4. Check failed jobs: `php artisan queue:failed`
5. Retry failed jobs: `php artisan queue:retry all`

---

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Laravel Reverb Documentation](https://laravel.com/docs/12.x/reverb)
- [Laravel Echo Documentation](https://laravel.com/docs/12.x/broadcasting#installing-laravel-echo)
- [Laravel Sanctum Documentation](https://laravel.com/docs/12.x/sanctum)
- [L5-Swagger Documentation](https://github.com/darkaonline/l5-swagger)
- [Redis Documentation](https://redis.io/docs/)

---

## 🤝 Contributing

Please read the contributing guidelines before submitting pull requests.

---

## 📄 License

This project is licensed under the MIT License.