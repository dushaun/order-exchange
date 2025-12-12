# Limit Order Exchange

A limit order exchange application built with Laravel 12 and Vue.js 3.

## Prerequisites

- PHP 8.4+
- Composer
- PostgreSQL 16+
- Node.js 20+ & npm
- Pusher account (free tier available)

## Backend Setup

### 1. Install Dependencies

```bash
cd backend
composer install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Setup

Configure your PostgreSQL connection in `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Run migrations and seeders:

```bash
php artisan migrate
php artisan db:seed
```

### 4. Demo Users

After seeding, the following demo users are available:

| Email | Password | Starting Balance |
|-------|----------|------------------|
| user1@test.com | password | $10,000 |
| user2@test.com | password | $10,000 |

## Real-time Broadcasting Setup

This application uses Pusher for real-time event broadcasting (order matches, updates).

### 1. Create a Pusher Account

1. Go to https://dashboard.pusher.com and create a free account
2. Create a new Channels app
3. Select a cluster closest to your location

### 2. Obtain Credentials

From your Pusher app dashboard, navigate to "App Keys" to find:

- **App ID** - Your unique app identifier
- **Key** - Public key for client-side connections
- **Secret** - Private key for server-side authentication
- **Cluster** - Regional cluster (e.g., `mt1`, `eu`, `ap1`)

### 3. Configure Environment

Add your Pusher credentials to `backend/.env`:

```env
BROADCAST_CONNECTION=pusher

PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1
```

### 4. Verify Broadcasting Works

1. Open the Pusher Debug Console at https://dashboard.pusher.com (select your app → "Debug Console")

2. In another terminal, authenticate and trigger a test broadcast:

```bash
# Using curl (replace with your session cookie after logging in)
curl -X GET http://localhost/api/test-broadcast \
  -H "Accept: application/json" \
  --cookie "your-session-cookie"
```

3. Expected output in Pusher Debug Console:

```
Channel: test-channel
Event: test.event
Data: {"message":"Test broadcast successful!"}
```

## Running Tests

```bash
cd backend
php artisan test
```

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | User login |
| POST | `/api/logout` | User logout (authenticated) |
| GET | `/api/user` | Get current user (authenticated) |

### Broadcasting

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/broadcasting/auth` | Private channel authentication |
| GET | `/api/test-broadcast` | Trigger test broadcast event (authenticated) |

## Tech Stack

- **Backend:** Laravel 12, PHP 8.4+
- **Database:** PostgreSQL 16+
- **Authentication:** Laravel Sanctum
- **Real-time:** Pusher Channels
- **Frontend:** Vue.js 3, TypeScript, Tailwind CSS
