
# TuPay Backend Assessment

## Overview

This repository is my submission for the TuPay Backend Engineering Assessment.

The solution implements a secure wallet-to-wallet currency swap platform using **Laravel 11**, **PHP 8.2+**, **MySQL**, and **Redis**. The implementation focuses on transactional integrity, double-entry accounting, step-up authentication, concurrency safety, idempotent settlement processing, and clean domain-oriented architecture.

---

# Technology Stack

- PHP 8.2+
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

# Environment

```dotenv
CACHE_STORE=redis
QUEUE_CONNECTION=redis
EXCHANGE_RATE_DRIVER=mock
SETTLEMENT_WEBHOOK_SECRET=local-settlement-secret
```

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
