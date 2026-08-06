# TuPay Backend Assessment

## Overview

This project is my submission for the TuPay Backend Engineering Assessment.

The solution implements a secure wallet-to-wallet currency swap service using Laravel 12, Redis, MySQL and a double-entry ledger architecture. The implementation focuses on correctness, transactional integrity, concurrency safety and production-oriented design while keeping the codebase simple and maintainable.

---

# Tech Stack

* PHP 8.4
* Laravel 12
* MySQL
* Redis
* Laravel Queues
* Laravel Sanctum
* Google2FA
* Pest
* PHPStan (Level 8)
* Laravel Pint

---

# Features Implemented

## Authentication

* Email/password authentication
* Laravel Sanctum API tokens

## Step-Up Authentication

* TOTP verification using Google Authenticator
* Elevated Action Token (EAT)
* One-time use EAT
* Payload binding
* Expiration support
* Replay attack protection

## Wallet Swap

* Currency validation
* Balance validation
* Exchange rate service
* Atomic ledger posting
* Double-entry accounting
* Transaction rollback on failure

## Ledger

* Double-entry ledger
* Immutable posted transactions
* Ledger history endpoint
* Wallet balance endpoint
* Per-currency balancing

## Settlement Webhook

* HMAC-SHA256 signature verification
* SQL-based idempotency
* Out-of-order webhook handling
* Asynchronous processing using Laravel Queues

## Concurrency Protection

* Redis distributed locking
* Prevents double spending
* Atomic swap execution

---

# Project Structure

```
app/
 ├── Actions
 ├── Data
 ├── Enums
 ├── Exceptions
 ├── Http
 ├── Jobs
 ├── Models
 ├── Responses
 ├── Services
 │     ├── Cache
 │     ├── ExchangeRate
 │     ├── Ledger
 │     ├── Locks
 │     ├── Settlement
 │     └── Swap
 └── Support
```

---

# Installation

```bash
composer install

cp .env.example .env

php artisan key:generate
```

Configure:

* MySQL
* Redis

Then run:

```bash
php artisan migrate --seed
```

Start the application:

```bash
php artisan serve
```

Start the queue worker:

```bash
php artisan queue:work
```

---

# Environment Variables

```
CACHE_STORE=redis

QUEUE_CONNECTION=redis

EXCHANGE_RATE_DRIVER=mock

SETTLEMENT_WEBHOOK_SECRET=local-settlement-secret
```

---

# Test User

```
Email:
davsong@gmail.com

Password:
password
```

The TOTP secret seeded into the database is:

```
JBSWY3DPEHPK3PXP
```

Import this secret into Google Authenticator to generate valid TOTP codes.

---

# API Walkthrough

The repository includes a Postman collection demonstrating the complete flow.

Sequence:

1. Login
2. Generate Elevated Action Token
3. Execute Swap
4. View Ledger History
5. View Wallet Balance
6. Submit Settlement Webhook

---

# Concurrency Test

The project includes a concurrency test demonstrating protection against double spending using Redis distributed locking.

Before running the test:

```bash
php artisan migrate:fresh --seed

php artisan db:seed --class=ConcurrencyTestSeeder
```

Start the application with multiple PHP workers:

```bash
PHP_CLI_SERVER_WORKERS=10 php artisan serve
```

Run the concurrency test:

```bash
php artisan test tests/Concurrency/SwapConcurrencyTest.php
```

Expected result:

* Exactly one request succeeds
* Nine requests fail
* Wallet balance never becomes negative
* Ledger remains balanced

---

# Running Tests

Run the complete test suite:

```bash
php artisan test
```

Run static analysis:

```bash
vendor/bin/phpstan analyse
```

Run code style checks:

```bash
vendor/bin/pint
```

Or run everything together:

```bash
composer quality
```

---

# Design Decisions

### Double-entry Ledger

Every financial transaction creates equal debit and credit entries, ensuring that each currency always balances to zero.

### Redis Distributed Locking

Swap execution uses Redis locks to prevent concurrent requests from spending the same balance.

### Elevated Action Token

Sensitive financial actions require a short-lived signed token issued after successful TOTP verification.

### Settlement Processing

Settlement webhooks are authenticated using HMAC-SHA256 signatures and processed asynchronously through Laravel Queues.

Duplicate webhook deliveries are handled safely using a unique `provider_reference`, while out-of-order events are ignored through state transition checks.

### Exchange Rate Provider

The exchange rate service is provider-driven and configurable via environment variables. A mock provider is supplied for the assessment.

---

# Future Improvements

Potential production enhancements include:

* Real exchange-rate provider integration
* Multi-provider settlement support
* Dead-letter queue handling
* Settlement retry policies
* Redis-backed idempotency keys
* Event sourcing
* Metrics and observability
* OpenAPI / Swagger documentation

---

Thank you for reviewing my submission.
