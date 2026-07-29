# NEP Programme API Backend

Backend API for the **NEP Programme Mapping System** — built with **Laravel 12**, using **Sanctum** for token authentication, **Laravel Reverb** for real-time WebSocket broadcasting, **Groq AI** for advisory note generation, **DomPDF** for PDF exports, and **L5-Swagger** for OpenAPI documentation.

---

## 🛠️ Technology Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12.x (PHP ^8.2) |
| Database | MySQL (production) / SQLite (development) |
| Authentication | Laravel Sanctum (token-based) |
| Real-time | Laravel Reverb (WebSocket) |
| Queue / Cache | Redis via `predis/predis` |
| AI | Groq API (`llama-3.3-70b-versatile`) |
| PDF Export | DomPDF (`barryvdh/laravel-dompdf`) |
| File Storage | ImageKit CDN |
| API Docs | Swagger UI (`darkaonline/l5-swagger`) |
| Asset Bundler | Vite + Tailwind CSS v4 |

---

## 💻 Local Development Setup

### 1. Prerequisites

- PHP >= 8.2 (with `sqlite3`, `mbstring`, `openssl`, `xml`, `zip`, `curl`, `pcntl` extensions)
- Composer
- Node.js & NPM
- Redis Server
- MySQL or SQLite

### 2. Install Redis

**Windows (WSL2):**
```bash
wsl -d Ubuntu
sudo apt update && sudo apt install redis-server
sudo service redis-server start
```

**macOS:**
```bash
brew install redis && brew services start redis
```

**Ubuntu/Debian:**
```bash
sudo apt install redis-server && sudo systemctl enable --now redis
```

Verify: `redis-cli ping` → should return `PONG`

### 3. Installation

```bash
# 1. Clone and enter the project
git clone <repository-url>
cd backend-api

# 2. Install dependencies
composer install
npm install

# 3. Configure environment
cp .env.example .env

# 4. Generate app key
php artisan key:generate

# 5. Run migrations and seed lookup data
php artisan migrate --seed

# 6. Generate Swagger docs
php artisan l5-swagger:generate
```

Open `.env` and fill in the required values (see [Environment Variables](#-environment-variables) below).

### 4. Running the Development Server

```bash
composer run serve
```

This starts three concurrent processes:
- **Laravel server** → `http://127.0.0.1:8000`
- **Queue worker** → `php artisan queue:listen`
- **Reverb WebSocket server** → `ws://localhost:8081`

> Redis must be running before starting the application.

---

## 🔑 User Roles

| Role | Description |
|---|---|
| `nep_admin` | Full system access — manages users, organisations, taxonomy, advisory workflow |
| `nep_coordinator` | Creates programme entries for member orgs, manages advisory notes |
| `member_org` | Manages their own organisation's programme entries |

---

## 📡 Real-time Broadcasting (Laravel Reverb)

Private channels require Sanctum token authentication via `/api/broadcasting/auth`.

### Channels

| Channel | Access | Purpose |
|---|---|---|
| `App.Models.User.{id}` | Authenticated user | Per-user notifications |
| `nep-admin` | `nep_admin`, `nep_coordinator` | Staff-wide notifications |

### Broadcast Events

| Event Class | Broadcast Name | Channel | Trigger |
|---|---|---|---|
| `ProgrammeEntryCreatedForOrg` | `programme-entry.created` | `App.Models.User.{id}` | Coordinator creates entry for a member org |
| `AdviserSubmissionAssigned` | `submission.assigned` | `App.Models.User.{id}` | Advisory note assigned to coordinator |
| `AdviceDelivered` | `advice.delivered` | `App.Models.User.{id}` | Advisory note delivered to member org |
| `TaxonomyOtherQueueCreated` | `other.queue.created` | `nep-admin` | Member org submits an "Other" taxonomy entry |

All notification classes use `ShouldBroadcastNow` (bypasses queue — fires immediately).

### Frontend Echo Configuration (`resources/js/echo.js`)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: {
            Authorization: 'Bearer ' + (localStorage.getItem('auth_token') ?? ''),
            Accept: 'application/json',
        },
    },
});
```

---

## 🤖 Groq AI Integration

Used by coordinators to auto-generate advisory notes from uploaded programme documents.

- **Model:** `llama-3.3-70b-versatile` (configurable via `GROQ_MODEL`)
- **Flow:** Upload PDF → parse text → send to Groq → structured advisory note with 4 sections (A–D)
- **Rate limiting:** `throttle:10,1` on AI endpoints

Configure via `.env`:
```env
GROQ_API_KEY=your-key
GROQ_MODEL=llama-3.3-70b-versatile
GROQ_TIMEOUT=30
GROQ_RETRY_ATTEMPTS=2
GROQ_RETRY_DELAY=500
```

---

## 📄 PDF Export (DomPDF)

Two PDF export endpoints:

| Endpoint | Description |
|---|---|
| `GET /api/adviser/submissions/{id}/export-pdf` | Advisory note PDF (coordinator/admin) |
| `GET /api/map/entries/export/pdf` | Programme entries map export PDF |

**DomPDF CSS limitations** (important for template development):
- No `linear-gradient` support
- `display:inline-block` stretches to full cell width — use `<table>` for multi-column layouts
- No `border-radius` on `<span>` inside table cells

---

## 🖼️ ImageKit Integration

Organisation logos are uploaded to ImageKit CDN. The stored value is `fileId|filePath`.

```env
IMAGEKIT_PUBLIC_KEY=your-public-key
IMAGEKIT_PRIVATE_KEY=your-private-key
IMAGEKIT_URL_ENDPOINT=https://ik.imagekit.io/your-id
```

---

## 📖 API Documentation (Swagger)

- **URL:** `http://localhost:8000/api/documentation`
- Regenerate after controller annotation changes:
  ```bash
  php artisan l5-swagger:generate
  ```

---

## 🌍 Environment Variables

See `.env.example` for the full list. Key variables:

| Variable | Description |
|---|---|
| `APP_URL` | Backend base URL |
| `FRONTEND_URL` | Frontend URL (used for CORS and invitation emails) |
| `DB_*` | Database connection settings |
| `REDIS_*` | Redis connection settings |
| `REVERB_*` | WebSocket server settings |
| `GROQ_API_KEY` | Groq AI API key (required for advisory note generation) |
| `IMAGEKIT_*` | ImageKit CDN credentials (required for org logo uploads) |
| `MAIL_*` | SMTP settings for invitation and password reset emails |
| `SANCTUM_STATEFUL_DOMAINS` | Comma-separated list of frontend domains for Sanctum |

---

## 🚀 Production Deployment

### Server Requirements

- Nginx + PHP 8.2-FPM
- MySQL / PostgreSQL / MariaDB
- Redis
- Supervisor (for queue worker and Reverb daemon)

### Deploy Steps

```bash
composer install --no-dev --optimize-autoloader
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan l5-swagger:generate
```

> After any `.env` change, run `php artisan config:clear && php artisan route:clear` to bust the cache.

### Supervisor: Queue Worker (`/etc/supervisor/conf.d/nep-worker.conf`)

```ini
[program:nep-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/nep-backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/nep-backend/storage/logs/worker.log
stopwaitsecs=3600
```

### Supervisor: Reverb Server (`/etc/supervisor/conf.d/nep-reverb.conf`)

```ini
[program:nep-reverb]
process_name=%(program_name)s
command=php /var/www/nep-backend/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/nep-backend/storage/logs/reverb.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start nep-worker:* nep-reverb
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/nep-backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* { deny all; }

    client_max_body_size 50M;
}
```

### SSL (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d api.yourdomain.com
```

---

## 🧪 Testing

```bash
php artisan test
```

---

## 🔧 Troubleshooting

**`Class not found` after route changes**
```bash
php artisan route:clear && php artisan config:clear && php artisan cache:clear
```

**Redis connection error**
- Verify Redis is running: `redis-cli ping`
- Check `REDIS_CLIENT=predis` and `REDIS_HOST`/`REDIS_PORT` in `.env`

**WebSocket auth fails silently**
- Ensure `authEndpoint: '/api/broadcasting/auth'` is set in Echo config
- Ensure `Authorization: Bearer <token>` header is sent in Echo `auth.headers`
- Verify `BROADCAST_CONNECTION=reverb` in `.env`

**Queue jobs not processing**
- Check worker is running: `php artisan queue:work`
- Check failed jobs: `php artisan queue:failed`
- Retry: `php artisan queue:retry all`

**Groq AI returns 429**
- Rate limit hit — increase `GROQ_RETRY_DELAY` or reduce request frequency

**PDF rendering issues**
- DomPDF does not support CSS gradients, `border-radius` on inline elements, or `display:inline-block` in table cells — use `<table>` layouts

---

## 📚 Resources

- [Laravel 12 Docs](https://laravel.com/docs/12.x)
- [Laravel Reverb](https://laravel.com/docs/12.x/reverb)
- [Laravel Sanctum](https://laravel.com/docs/12.x/sanctum)
- [Groq API](https://console.groq.com/docs)
- [DomPDF](https://github.com/barryvdh/laravel-dompdf)
- [L5-Swagger](https://github.com/darkaonline/l5-swagger)
- [ImageKit PHP SDK](https://github.com/imagekit-developer/imagekit-php)

---

## 📄 License

MIT License
