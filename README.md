
# Backend Assessment

## Overview

This solution implements a secure wallet-to-wallet currency swap platform using **Laravel 11**, **PHP 8.3**, **MySQL**, and **Redis**. The implementation focuses on transactional integrity, double-entry accounting, step-up authentication, concurrency safety, idempotent settlement processing, and clean domain-oriented architecture.

---

# Technology Stack

- PHP 8.3
- Laravel 11
- MySQL
- Redis
- Laravel Sanctum
- Google2FA
- Laravel Queues
- Pest
- PHPStan (Level 8)
- Laravel Pint

---

# Features

- Sanctum Authentication
- Step-Up Authentication (TOTP + Elevated Action Token)
- Wallet-to-Wallet Currency Swap
- Double-Entry Ledger
- Exchange Rate Provider Abstraction
- Redis Distributed Locking
- Idempotent Settlement Webhook
- Queue-based Settlement Processing
- Ledger History API
- Wallet Balance API
- Concurrency Protection

---

# Solution Architecture

```text
                 Client
                    │
                    ▼
             API Controllers
                    │
                    ▼
                 Actions
                    │
                    ▼
             Domain Services
     ┌──────────┬──────────┬──────────┐
     │          │          │          │
  Ledger     Swap     Settlement  ExchangeRate
     │          │          │
     └──────────┴──────────┘
             Redis / MySQL
```

Controllers only coordinate requests. Business logic lives inside Actions and Services.

---

# Architectural Decisions

## Service Classes

- LedgerService – Double-entry posting
- ExchangeRateService – Exchange rate retrieval
- SettlementService – Settlement state transitions
- CacheService – Generic cache abstraction
- RedisLockService – Distributed locking
- ElevatedActionTokenService – EAT lifecycle

## Actions

Actions encapsulate business use-cases.

Example:

- ExecuteSwap

This keeps controllers thin while making business logic independently testable.

## Domain Structure

```text
app/
    Actions/
    Data/
    Enums/
    Http/
    Models/
    Services/
        Ledger/
        Settlement/
        ExchangeRate/
        Locks/
        Cache/
        Swap/
```

---

# Step-Up Authentication

Financial operations require:

1. Sanctum authentication
2. Elevated Action Token (EAT)

Flow:

```text
Login
 ↓
Sanctum Token
 ↓
Verify TOTP
 ↓
Issue Elevated Action Token
 ↓
Execute Swap
```

## Token Hashing

The Elevated Action Token is never stored in plaintext.

The implementation:

1. Generates a random token identifier.
2. Hashes the identifier using SHA-256.
3. Stores only the hash in Redis together with the action metadata.
4. Returns the signed token to the client.

During verification the incoming identifier is hashed again before comparison.

## Token Invalidation

Immediately after successful verification:

- Redis entry is deleted.
- Replay attacks are prevented.
- Tokens remain one-time use.
- Redis TTL expires unused tokens automatically.

---

# Swap Execution

```text
Acquire Redis Lock
 ↓
Validate Wallets
 ↓
Validate Balance
 ↓
Fetch Exchange Rate
 ↓
Calculate Quote
 ↓
Begin Transaction
 ↓
Create Ledger Posting
 ↓
Commit
 ↓
Release Lock
```

---

# Double-Entry Ledger

Every movement creates balanced debit and credit entries.

Balances are derived from immutable ledger entries rather than mutable balance columns.

---

# Deadlock Prevention

Redis distributed locks protect swap execution.

Locks are acquired before the database transaction begins.

Wallet identifiers are ordered deterministically before generating the lock key, ensuring identical lock acquisition order regardless of request direction and preventing circular wait conditions.

---

# Settlement Processing

Webhook flow:

```text
Provider
 ↓
Verify HMAC Signature
 ↓
Dispatch Queue
 ↓
SettlementService
 ↓
Persist State
```

Features:

- HMAC-SHA256 verification
- SQL-backed idempotency
- Queue processing
- Out-of-order event handling
- Monotonic state transitions

---

# Database Indexing

## Ledger Entries

Composite index:

```sql
(wallet_id, created_at)
```

Optimises:

```sql
SELECT ...
FROM ledger_entries
WHERE wallet_id = ?
ORDER BY created_at DESC
LIMIT ...
```

This supports efficient pagination for `GET /api/ledger/{wallet_id}`.

## Ledger Transactions

Indexes:

- (status, created_at)
- (type, status)

These support reporting and transaction history queries.

## Settlements

Unique index:

```
provider_reference
```

Provides SQL-level idempotency.

---

# Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Run:

```bash
php artisan serve
php artisan queue:work
```

---

# Environment Variables

The application exposes a number of configuration options through environment variables. These allow security, concurrency, exchange-rate behaviour, and settlement processing to be adjusted without modifying the application code.

---

## Application

```dotenv
APP_TIMEZONE=Africa/Lagos
```

| Variable | Description |
|----------|-------------|
| `APP_TIMEZONE` | Sets the default timezone used by the application for timestamps, logging, token expiration, and scheduled operations. |

---

## Step-Up Authentication

```dotenv
ELEVATED_ACTION_TOKEN_SECRET=6a68d986ce1be73cd12b15be8732ae610dffcd353d46f3f9861a75fd47627b7e
ELEVATED_ACTION_TOKEN_TTL_SECONDS=60
```

| Variable | Description |
|----------|-------------|
| `ELEVATED_ACTION_TOKEN_SECRET` | Secret key used to sign Elevated Action Tokens (EAT). The server verifies every incoming EAT using this secret, ensuring the token cannot be forged or modified by a client. Keeping the secret outside the codebase allows secure rotation without redeployment. |
| `ELEVATED_ACTION_TOKEN_TTL_SECONDS` | Defines how long an Elevated Action Token remains valid after issuance. A short lifetime reduces the window in which a stolen token could be abused while still allowing enough time for the client to complete the requested action. |

---

## Exchange Rate Service

```dotenv
EXCHANGE_RATE_DRIVER=mock

FX_RATE_ENDPOINT=http://127.0.0.1:8001/rates
FX_RATE_TIMEOUT_SECONDS=3
FX_RATE_FRESH_SECONDS=30
FX_RATE_STALE_SECONDS=300
FX_RATE_REFRESH_LOCK_SECONDS=15
```

| Variable | Description |
|----------|-------------|
| `EXCHANGE_RATE_DRIVER` | Selects the exchange-rate provider implementation. The assessment uses the `mock` driver to remove any dependency on external services while allowing a production provider to be substituted through configuration. |
| `FX_RATE_ENDPOINT` | Base URL of the HTTP exchange-rate provider. This is ignored when the mock provider is active but allows the same codebase to switch to a live provider without modification. |
| `FX_RATE_TIMEOUT_SECONDS` | Maximum amount of time the application waits for an exchange-rate provider before treating the request as failed. This prevents long-running external requests from blocking swap execution. |
| `FX_RATE_FRESH_SECONDS` | Duration for which a cached exchange rate is considered fresh. Requests within this period are served directly from cache, reducing latency and external API calls. |
| `FX_RATE_STALE_SECONDS` | Maximum age for serving a stale cached exchange rate while a background refresh is triggered (Stale-While-Revalidate). This improves availability if the provider is temporarily slow or unavailable. |
| `FX_RATE_REFRESH_LOCK_SECONDS` | Lifetime of the Redis lock used during cache refresh. It prevents multiple concurrent requests from refreshing the same exchange rate simultaneously, avoiding cache stampede problems. |

---

## Clearing Wallets

```dotenv
NGN_CLEARING_WALLET_ID=33333333-3333-4333-8333-333333333333
CNY_CLEARING_WALLET_ID=44444444-4444-4444-8444-444444444444
```

| Variable | Description |
|----------|-------------|
| `NGN_CLEARING_WALLET_ID` | Platform clearing wallet for NGN. During a swap, funds are debited from the user's NGN wallet and credited to this wallet before the corresponding CNY movement occurs. |
| `CNY_CLEARING_WALLET_ID` | Platform clearing wallet for CNY. This wallet acts as the balancing account when crediting the destination user's CNY wallet. Using clearing wallets ensures every currency movement remains balanced according to double-entry accounting principles. |

---

## Distributed Locking

```dotenv
SWAP_LOCK_SECONDS=10
```

| Variable | Description |
|----------|-------------|
| `SWAP_LOCK_SECONDS` | Maximum duration a Redis lock may be held while processing a swap. The lock prevents concurrent requests from spending the same funds more than once. A timeout ensures locks are automatically released if a process crashes unexpectedly. |

---

## Settlement Webhook

```dotenv
SETTLEMENT_WEBHOOK_SECRET=local-settlement-secret
```

| Variable | Description |
|----------|-------------|
| `SETTLEMENT_WEBHOOK_SECRET` | Shared secret used to verify incoming settlement webhooks using HMAC-SHA256. Only requests signed with the correct secret are accepted, protecting the endpoint from unauthorized or tampered webhook requests. |

---

## Configuration Philosophy

The application keeps operational behaviour configurable through environment variables rather than hardcoding values. This approach provides several benefits:

- **Security** – Secrets and signing keys are managed outside the source code and can be rotated without code changes.
- **Performance** – Cache freshness windows, timeouts, and lock durations can be tuned for different workloads.
- **Scalability** – Different exchange-rate providers can be enabled by changing configuration instead of modifying application logic.
- **Operational Flexibility** – Queue behaviour, locking strategy, and settlement security can be adjusted independently for development, staging, and production environments.

---

## Clearing Wallets

```dotenv
NGN_CLEARING_WALLET_ID=33333333-3333-4333-8333-333333333333
CNY_CLEARING_WALLET_ID=44444444-4444-4444-8444-444444444444
```

These wallets act as the platform clearing accounts used during double-entry ledger postings.

---

## Distributed Locking

```dotenv
SWAP_LOCK_SECONDS=10
```

Maximum duration a swap execution lock is held in Redis.

---

## Settlement Webhook

```dotenv
SETTLEMENT_WEBHOOK_SECRET=local-settlement-secret
```

Shared secret used to verify incoming settlement webhook signatures using HMAC-SHA256.

---

# Test User

Email:

davsong@gmail.com

Password:

password

TOTP Secret:

JBSWY3DPEHPK3PXP

---

# API Testing

A Postman collection is included.

Recommended order:

1. Login
2. Generate EAT
3. Execute Swap
4. Ledger
5. Wallet Balance
6. Settlement

---

# Concurrency Test

The project includes a concurrency test that demonstrates protection against
double spending using Redis distributed locks.

## Prerequisites

Ensure the following services are running:

- MySQL
- Redis

Reset the database:

```bash
php artisan migrate:fresh --seed
```

Seed the dedicated concurrency test data:

```bash
php artisan db:seed --class=ConcurrencyTestSeeder
```

## Start the Application

The built-in PHP development server must be started with multiple workers so
that concurrent requests can be processed simultaneously.

```bash
PHP_CLI_SERVER_WORKERS=10 php artisan serve
```

> **Note**
>
> The concurrency test will not behave correctly when the application is served
> with a single worker because requests are processed sequentially.

## Execute the Test

Open a second terminal and run:

```bash
php artisan test tests/Concurrency/SwapConcurrencyTest.php
```

## Expected Result

The test dispatches ten simultaneous swap requests against the same wallet.

Exactly one request should succeed while the remaining requests are rejected
because they cannot acquire the distributed lock.

Expected outcome:

- ✅ 1 successful swap
- ✅ 9 rejected swaps
- ✅ No negative wallet balance
- ✅ No duplicate ledger postings
- ✅ Ledger remains balanced

## Manual Verification (Optional)

After the test completes, verify:

- The source wallet balance has only been reduced once.
- The destination wallet has only been credited once.
- Only one ledger transaction exists for the executed swap.
- The ledger remains balanced (total debits equal total credits).

## Concurrency Test Note

The concurrency test is designed for the PHP built-in server started with
multiple workers (`PHP_CLI_SERVER_WORKERS=10`).

Running the test against a single-worker server will serialize requests and
cannot accurately demonstrate distributed locking behavior.

---


# Quality

```bash
composer quality
```

Runs:

- Pest
- PHPStan
- Pint

---

# Assumptions & Trade-offs

- Mock exchange-rate provider for assessment.
- Redis used for locks and EAT storage.
- SQL unique constraint provides webhook idempotency.
- Ledger is the source of truth for balances.

---
