# Limit Order Exchange

A limit order exchange application built with Laravel 12 and Vue.js 3.

## Quick Start (Docker/Sail)

The recommended way to run this application is using Docker with Laravel Sail.

### Prerequisites

- Docker Desktop
- Node.js 20+ & npm
- Pusher account (free tier available at https://pusher.com)

### 1. Backend Setup

```bash
cd backend

# Install PHP dependencies
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# Copy environment file
cp .env.example .env

# Configure Pusher credentials in .env
# Edit PUSHER_APP_ID, PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_APP_CLUSTER

# Start Docker containers
./vendor/bin/sail up -d

# Generate application key
./vendor/bin/sail artisan key:generate

# Run migrations and seed demo data
./vendor/bin/sail artisan migrate --seed
```

### 2. Frontend Setup

```bash
cd frontend

# Install dependencies
npm install

# Copy environment file
cp .env.example .env

# Configure Pusher key in .env
# Edit VITE_PUSHER_APP_KEY and VITE_PUSHER_APP_CLUSTER

# Start development server
npm run dev
```

### 3. Access the Application

- **Frontend:** http://localhost:5173
- **Backend API:** http://localhost/api

### Demo Users

| Email | Password | Starting Balance |
|-------|----------|------------------|
| user1@test.com | password | $10,000 USD, 1 BTC, 10 ETH |
| user2@test.com | password | $10,000 USD, 1 BTC, 10 ETH |

---

## Alternative: Local Setup (Without Docker)

### Prerequisites

- PHP 8.4+
- Composer
- PostgreSQL 16+
- Node.js 20+ & npm

### Backend Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

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
php artisan migrate --seed
php artisan serve
```

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

### With Docker/Sail

```bash
cd backend

# Run all tests
./vendor/bin/sail artisan test

# Run specific test suite
./vendor/bin/sail artisan test --testsuite=Feature
./vendor/bin/sail artisan test --testsuite=Unit
```

### Without Docker

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

## Testing the Application

### Demo User Credentials

| User | Email | Password | Initial Balance | Initial Assets |
|------|-------|----------|-----------------|----------------|
| Test User 1 | `user1@test.com` | `password` | $10,000 USD | 1 BTC, 10 ETH |
| Test User 2 | `user2@test.com` | `password` | $10,000 USD | 1 BTC, 10 ETH |

### Order Matching Scenario Walkthrough

Follow these steps to test the complete order matching flow:

#### Step 1: Setup Two Browser Sessions

1. Open `http://localhost:5173` in **Browser 1** (regular window)
2. Open `http://localhost:5173` in **Browser 2** (incognito/private window)

#### Step 2: Login Both Users

1. **Browser 1:** Login as `user1@test.com` / `password`
2. **Browser 2:** Login as `user2@test.com` / `password`
3. Both dashboards should show: $10,000 USD, 1 BTC, 10 ETH

#### Step 3: Place a Buy Order (Browser 1)

1. In **Browser 1** (user1), fill in the order form:
   - Symbol: **BTC**
   - Side: **Buy** (green button)
   - Price: **45000**
   - Amount: **0.1**
2. Click **Place Order**

**Expected Results:**
- Order appears in Orderbook under "Buy Orders"
- Wallet shows USD balance reduced to **$5,500** ($4,500 locked for the order)
- Order History shows the order with status **Open** (blue)

#### Step 4: Place a Matching Sell Order (Browser 2)

1. In **Browser 2** (user2), fill in the order form:
   - Symbol: **BTC**
   - Side: **Sell** (red button)
   - Price: **45000**
   - Amount: **0.1**
2. Click **Place Order**

**Expected Results:**
- Orders disappear from Orderbook (matched and filled)
- Order History shows both orders as **Filled** (green)
- "Updated" indicator flashes briefly

#### Step 5: Verify Final Balances

| User | USD Balance | BTC | Calculation |
|------|-------------|-----|-------------|
| User 1 (Buyer) | $5,500.00 | 1.1 | Paid $4,500, received 0.1 BTC |
| User 2 (Seller) | $14,432.50 | 0.9 | Received $4,500 - $67.50 commission |

### Commission Calculation

- **Commission Rate:** 1.5% (0.015)
- **Applied To:** Trade value (price × amount)
- **Deducted From:** Seller's proceeds

**Example Calculation:**
```
Trade: Sell 0.1 BTC at $45,000
Trade Value: $45,000 × 0.1 = $4,500
Commission: $4,500 × 0.015 = $67.50
Seller Receives: $4,500 - $67.50 = $4,432.50
```

### Order Cancellation Test

1. Login as any user
2. Place a SELL order (e.g., BTC, $50,000, 0.2 amount)
3. Observe BTC locked in wallet (available decreases, locked increases)
4. Click **Cancel** on the order in Order History
5. Verify order status changes to **Cancelled** (red)
6. Verify BTC is released back to available balance

### Error Handling Test

1. **Insufficient Balance:** Try to buy more than your USD balance allows
   - Expected: Error message "Insufficient USD balance..."
2. **Insufficient Assets:** Try to sell more crypto than you own
   - Expected: Error message "Insufficient BTC..."

## Tech Stack

- **Backend:** Laravel 12, PHP 8.4+
- **Database:** PostgreSQL 16+
- **Authentication:** Laravel Sanctum
- **Real-time:** Pusher Channels
- **Frontend:** Vue.js 3, TypeScript, Tailwind CSS
