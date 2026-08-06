# Backend Assessment

## Overview

This project implements a secure NGN/CNY wallet swap engine using **PHP 8.3**, **Laravel 11**, **MySQL**, and **Redis**.

The architecture is designed around four main requirements:

* clear separation of responsibilities through Services, Actions, and typed domain data;
* action-bound Step-Up authentication using short-lived Elevated Action Tokens;
* deterministic Redis and database locking to prevent deadlocks and double spending;
* indexed ledger-history queries for efficient pagination.

## Repository Contents

```text
├── app/
├── database/
├── tests/
├── TuPay Backend Assessment (Ledger & Settlement Engine).postman_collection.json
├── README.md
├── composer.json
└── phpstan.neon
```

---

# Architectural Decisions

## Request Flow

```text
HTTP Request
    │
    ▼
Request Validation
    │
    ▼
Controller
    │
    ▼
Action
    │
    ▼
Domain Services
    │
    ├── Redis
    └── MySQL
```

Controllers are intentionally thin. They receive validated requests, invoke the relevant Action or Service, and return a standardized API response.

Business rules are not implemented directly inside controllers.

---

## Service Classes

Service classes encapsulate reusable domain and infrastructure logic.

### `LedgerService`

Responsible for:

* validating ledger postings;
* confirming that every transaction contains at least two entries;
* ensuring entries are balanced independently for each currency;
* calculating wallet balances from posted ledger entries;
* preventing user-wallet overdrafts;
* inserting ledger transactions and entries atomically;
* applying pessimistic row locking to participating wallets.

The wallet table does not contain a mutable balance column. The ledger remains the source of truth.

### `ElevatedActionTokenService`

Responsible for:

* issuing signed Elevated Action Tokens;
* validating token signatures;
* checking token expiration;
* binding a token to the authenticated user;
* comparing the intended action hash with the submitted swap payload;
* consuming the Redis token record atomically;
* preventing token replay.

### `ExchangeRateService`

Responsible for:

* selecting the configured exchange-rate provider;
* retrieving NGN/CNY exchange rates;
* applying the Stale-While-Revalidate cache policy;
* serving fresh or permitted stale values from Redis;
* coordinating refresh locks to prevent cache stampedes.

### `RedisLockService`

Responsible for creating Redis-backed distributed locks.

It is used by the swap Action to ensure that competing requests cannot execute against the same user and wallets simultaneously.

### `SettlementService`

Responsible for:

* processing settlement events dispatched through Laravel Queues;
* enforcing monotonic settlement-state transitions;
* ignoring duplicate or older provider events;
* using the unique `provider_reference` constraint for SQL-level idempotency.

### `CacheService`

Provides generic cache operations such as:

* `get`;
* `put`;
* `remember`;
* `forget`.

Distributed locking is intentionally kept outside this service because Laravel's generic cache repository contract does not expose the lock API required by PHPStan Level 8.

---

## Actions

Actions represent complete business use cases.

The main Action is:

```text
App\Actions\Swap\ExecuteSwap
```

`ExecuteSwap` coordinates the complete protected swap flow:

1. consume and validate the Elevated Action Token;
2. load and validate the source and destination wallets;
3. build the Redis lock names;
4. sort and acquire all distributed locks;
5. configure `REPEATABLE READ` transaction isolation;
6. open the SQL transaction;
7. retrieve the exchange rate;
8. calculate conversion, spread, and Banker's Rounding;
9. post the balanced ledger transaction;
10. commit the database transaction;
11. release Redis locks in a `finally` block.

The Action coordinates the operation while specialized rules remain inside the relevant services.

---

## Domain Structures

Typed domain objects are used instead of passing loosely structured arrays through the application.

Examples include:

```text
LedgerPostingData
LedgerEntryData
ElevatedActionPayload
ElevatedActionTokenData
ExchangeRateData
SwapQuoteData
```

These objects provide:

* explicit constructor requirements;
* predictable data structures;
* reduced runtime ambiguity;
* stronger PHPStan analysis;
* separation between HTTP payloads and domain operations.

The main domain organization is:

```text
app/
├── Actions/
│   └── Swap/
├── Data/
│   ├── Ledger/
│   ├── Security/
│   ├── ExchangeRate/
│   └── Swap/
├── Enums/
├── Models/
├── Services/
│   ├── Ledger/
│   ├── Security/
│   ├── ExchangeRate/
│   ├── Settlement/
│   ├── Swap/
│   ├── Cache/
│   └── Locks/
├── Responses/
└── Support/
```

---

# Step-Up Token Hashing and Invalidation Mechanism

Sensitive financial operations cannot accept a raw TOTP code directly.

The Step-Up flow is:

```text
Sanctum Authentication
        │
        ▼
POST /api/2fa/challenge
        │
        ▼
Verify TOTP
        │
        ▼
Hash Exact Action Payload
        │
        ▼
Issue 60-second EAT
        │
        ▼
POST /api/swap
        │
        ▼
Atomically Consume EAT
```

## Action-Payload Hashing

The challenge request includes the exact operation to authorize:

```json
{
  "action": "swap",
  "source_wallet_id": "11111111-1111-4111-8111-111111111111",
  "destination_wallet_id": "22222222-2222-4222-8222-222222222222",
  "source_amount_subunits": 10000000
}
```

The action payload is canonicalized before hashing.

Canonicalization ensures that associative key ordering does not change the result. Floating-point values are rejected so financial payloads remain deterministic.

A SHA-256 hash is generated from the exact canonical action payload.

Changing any of the following produces a different hash:

* action;
* source wallet;
* destination wallet;
* source amount.

An EAT created for one payload cannot authorize another payload.

---

## Token Contents and Signature

The Elevated Action Token contains:

* token identifier;
* authenticated user identifier;
* action;
* action hash;
* issued timestamp;
* expiration timestamp.

The token payload is cryptographically signed using:

```dotenv
ELEVATED_ACTION_TOKEN_SECRET
```

The signature protects the token against modification. A client cannot alter the user, action, amount, or expiration without invalidating the signature.

---

## Redis Storage

The signed token alone is not sufficient to authorize the swap.

A corresponding server-side record is stored in Redis with the configured TTL:

```dotenv
ELEVATED_ACTION_TOKEN_TTL_SECONDS=60
```

The Redis record represents the token's active, single-use state.

The application verifies:

* token signature;
* token expiration;
* authenticated user identifier;
* expected action;
* exact action hash;
* existence of the Redis record.

---

## Atomic Invalidation

The EAT is atomically consumed from Redis during the swap request.

Consumption performs a read-and-delete operation as one Redis operation. This ensures that two concurrent requests cannot both successfully use the same token.

After the first successful consumption:

```text
Redis token record = deleted
```

A second request using the same EAT is rejected even when the token's 60-second cryptographic expiration has not yet elapsed.

The implementation rejects:

* expired tokens;
* replayed tokens;
* tokens with invalid signatures;
* tokens issued for another user;
* tokens issued for another action;
* tokens whose payload parameters were changed.

---

# Deadlock Prevention and Lock Acquisition Order

The swap flow uses two layers of concurrency protection:

1. Redis distributed locks;
2. MySQL pessimistic row locks.

---

## Non-Blocking Redis Locks

Before opening the SQL transaction, the swap Action creates three lock names:

```text
swap:user:{user_id}
swap:wallet:{source_wallet_id}
swap:wallet:{destination_wallet_id}
```

The locks are non-blocking.

If another operation already owns one of the required locks, the request fails immediately with a conflict response instead of waiting indefinitely.

This protects against:

* simultaneous swaps by the same user;
* concurrent spending from the same source wallet;
* competing operations involving the same destination wallet.

---

## Deterministic Lock Ordering

The complete list of lock names is sorted using strict string ordering before acquisition.

Conceptually:

```php
$lockNames = [
    'swap:user:'.$userId,
    'swap:wallet:'.$sourceWalletId,
    'swap:wallet:'.$destinationWalletId,
];

sort($lockNames, SORT_STRING);
```

Every competing request therefore attempts to acquire shared resources in the same order.

Without deterministic ordering, two requests could behave like this:

```text
Request A:
locks Wallet A
waits for Wallet B

Request B:
locks Wallet B
waits for Wallet A
```

This creates a circular wait.

Sorting the lock names forces both requests to request Wallet A and Wallet B in the same sequence, removing that circular dependency.

---

## Partial Acquisition Cleanup

If the Action acquires one or two locks but fails to acquire the next lock, all previously acquired locks are released before the exception is rethrown.

This prevents leaked partial locks.

---

## Database Transaction Order

The sequence is intentionally:

```text
1. Acquire sorted Redis locks
2. Set REPEATABLE READ isolation
3. Open SQL transaction
4. Lock participating wallet rows
5. Validate balances
6. Insert ledger transaction and entries
7. Commit
8. Release Redis locks
```

Redis locks are acquired before the database transaction so the application does not hold database locks while waiting for external distributed locks.

---

## Pessimistic Row Locking

Inside the SQL transaction, participating wallet records are selected using pessimistic locking:

```sql
SELECT ...
FROM wallets
WHERE id IN (...)
FOR UPDATE
```

Wallet rows are processed in deterministic identifier order.

This prevents another database transaction from validating and spending the same balance before the first transaction commits.

---

## Guaranteed Lock Release

Redis locks are released inside a `finally` block.

Locks are therefore released whether the swap:

* succeeds;
* fails validation;
* encounters insufficient balance;
* encounters an exchange-rate error;
* fails during ledger posting;
* rolls back the SQL transaction.

Locks are released in reverse acquisition order.

The configured lock TTL also provides automatic recovery if the PHP process terminates unexpectedly.

---

# Database Indexing Rationale for Ledger Pagination

The endpoint:

```text
GET /api/ledger/{wallet_id}
```

returns ledger entries for one authenticated user's wallet using Laravel's native pagination.

The query pattern is:

```sql
SELECT ledger_entries.*
FROM ledger_entries
INNER JOIN ledger_transactions
    ON ledger_transactions.id =
        ledger_entries.ledger_transaction_id
WHERE ledger_entries.wallet_id = ?
  AND ledger_transactions.status = 'posted'
ORDER BY ledger_entries.created_at DESC,
         ledger_entries.id DESC
LIMIT ? OFFSET ?;
```

---

## Ledger-History Composite Index

The ledger-entry history index is:

```sql
(wallet_id, created_at, id)
```

Each index column serves a specific purpose.

### `wallet_id`

The endpoint always filters by one wallet:

```sql
WHERE wallet_id = ?
```

Placing `wallet_id` first allows MySQL to restrict the index scan to entries belonging to the requested wallet instead of scanning the complete ledger table.

### `created_at`

Ledger history is returned newest first:

```sql
ORDER BY created_at DESC
```

Including `created_at` after `wallet_id` allows MySQL to retrieve entries for the wallet in chronological order with reduced sorting work.

### `id`

Multiple ledger entries can share the same timestamp.

The additional ordering:

```sql
ORDER BY created_at DESC, id DESC
```

provides deterministic pagination. Without it, rows sharing the same timestamp could appear in an inconsistent order between page requests.

The composite index supports both the wallet filter and the complete ordering used by the endpoint.

---

## Ledger-Transaction Indexes

The `ledger_transactions` table includes:

```sql
(status, created_at)
```

and:

```sql
(type, status)
```

The history query joins ledger entries to transactions and restricts results to posted transactions.

The status-based index supports filtering posted transactions, while the type/status index supports transaction-type reporting and administrative queries.

---

## Pagination Choice

The endpoint uses Laravel's native `LengthAwarePaginator`.

This provides:

* current page;
* total entries;
* per-page count;
* last page;
* next and previous page URLs;
* consistent Laravel pagination metadata.

The composite index limits the cost of each wallet-history page by avoiding scans over entries belonging to unrelated wallets.

---

# Setup

## Requirements

Install:

* PHP 8.2 or later;
* Composer;
* MySQL 8.0 or later;
* Redis;
* BCMath PHP extension;
* PDO MySQL PHP extension.

---

## Install Dependencies

```bash
composer install
```

Create the environment file:

```bash
cp .env.example .env
```

Generate the Laravel application key:

```bash
php artisan key:generate
```

Configure the MySQL and Redis connection values in `.env`.

Run migrations and seed the assessment data:

```bash
php artisan migrate:fresh --seed
```

Clear cached configuration:

```bash
php artisan optimize:clear
```

Start the application:

```bash
php artisan serve
```

Start the Redis queue worker in another terminal:

```bash
php artisan queue:work redis
```

---

# Environment Variables

## Application

```dotenv
APP_TIMEZONE=Africa/Lagos
```

| Variable       | Purpose                                                                                                                                                                                                   |
| -------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_TIMEZONE` | Controls application timestamp and date interpretation. Token issuance and expiry are stored as Unix timestamps, while application logs and persisted timestamps use the configured application timezone. |

---

## Cache, Queue, and Redis

```dotenv
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

| Variable           | Purpose                                                                                                   |
| ------------------ | --------------------------------------------------------------------------------------------------------- |
| `CACHE_STORE`      | Uses Redis for exchange-rate caching and other cache-backed application operations.                       |
| `QUEUE_CONNECTION` | Sends settlement-processing jobs to Redis so webhook requests can return immediately with `202 Accepted`. |

A Redis server must be running before testing locks, EAT consumption, caching, queues, or concurrency.

---

## Elevated Action Tokens

```dotenv
ELEVATED_ACTION_TOKEN_SECRET=
ELEVATED_ACTION_TOKEN_TTL_SECONDS=60
```

| Variable                            | Purpose                                                                                                                                                                       |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ELEVATED_ACTION_TOKEN_SECRET`      | Secret used to cryptographically sign and verify Elevated Action Tokens. Generate a strong environment-specific value and do not commit production secrets to source control. |
| `ELEVATED_ACTION_TOKEN_TTL_SECONDS` | Number of seconds an unused EAT remains valid. The assessment requires a 60-second TTL.                                                                                       |

Generate a development secret with:

```bash
php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
```

Copy the generated value into:

```dotenv
ELEVATED_ACTION_TOKEN_SECRET=generated-value
```

---

## Exchange-Rate Service

```dotenv
EXCHANGE_RATE_DRIVER=mock

FX_RATE_ENDPOINT=http://127.0.0.1:8001/rates
FX_RATE_TIMEOUT_SECONDS=3
FX_RATE_FRESH_SECONDS=30
FX_RATE_STALE_SECONDS=300
FX_RATE_REFRESH_LOCK_SECONDS=15
```

| Variable                       | Purpose                                                                                         |
| ------------------------------ | ----------------------------------------------------------------------------------------------- |
| `EXCHANGE_RATE_DRIVER`         | Selects the configured provider. Use `mock` for the assessment or `http` for the HTTP provider. |
| `FX_RATE_ENDPOINT`             | URL called by the HTTP exchange-rate provider. It is not used when the `mock` driver is active. |
| `FX_RATE_TIMEOUT_SECONDS`      | Maximum time the HTTP provider may take before the request fails.                               |
| `FX_RATE_FRESH_SECONDS`        | Period during which a cached rate is considered fresh and returned directly.                    |
| `FX_RATE_STALE_SECONDS`        | Maximum stale period during which the previous value may be served while refresh occurs.        |
| `FX_RATE_REFRESH_LOCK_SECONDS` | Redis refresh-lock TTL used to ensure only one process refreshes a stale rate.                  |

The assessment defaults to:

```dotenv
EXCHANGE_RATE_DRIVER=mock
```

This removes dependence on an external FX service while retaining a configurable HTTP provider implementation.

---

## Clearing Wallets

```dotenv
NGN_CLEARING_WALLET_ID=33333333-3333-4333-8333-333333333333
CNY_CLEARING_WALLET_ID=44444444-4444-4444-8444-444444444444
```

| Variable                 | Purpose                                                                                         |
| ------------------------ | ----------------------------------------------------------------------------------------------- |
| `NGN_CLEARING_WALLET_ID` | Identifies the platform NGN clearing account used as the counterparty for NGN ledger movements. |
| `CNY_CLEARING_WALLET_ID` | Identifies the platform CNY clearing account used as the counterparty for CNY ledger movements. |

The seeders create wallets using these identifiers.

---

## Swap Locks

```dotenv
SWAP_LOCK_SECONDS=10
```

| Variable            | Purpose                                                                                                                                                         |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `SWAP_LOCK_SECONDS` | Sets the Redis distributed-lock TTL for swaps. It protects against double spending while ensuring abandoned locks expire automatically if a process terminates. |

---

## Settlement Webhook

```dotenv
SETTLEMENT_WEBHOOK_SECRET=local-settlement-secret
```

| Variable                    | Purpose                                                                                                                  |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| `SETTLEMENT_WEBHOOK_SECRET` | Shared secret used to verify the `X-Settlement-Signature` HMAC-SHA256 header against the exact raw webhook request body. |

Use a secure environment-specific value outside local development.

---

## Required Example Configuration

```dotenv
APP_TIMEZONE=Africa/Lagos

CACHE_STORE=redis
QUEUE_CONNECTION=redis

ELEVATED_ACTION_TOKEN_SECRET=replace-with-a-generated-secret
ELEVATED_ACTION_TOKEN_TTL_SECONDS=60

EXCHANGE_RATE_DRIVER=mock
FX_RATE_ENDPOINT=http://127.0.0.1:8001/rates
FX_RATE_TIMEOUT_SECONDS=3
FX_RATE_FRESH_SECONDS=30
FX_RATE_STALE_SECONDS=300
FX_RATE_REFRESH_LOCK_SECONDS=15

NGN_CLEARING_WALLET_ID=33333333-3333-4333-8333-333333333333
CNY_CLEARING_WALLET_ID=44444444-4444-4444-8444-444444444444

SWAP_LOCK_SECONDS=10

SETTLEMENT_WEBHOOK_SECRET=local-settlement-secret
```

---

# Postman Collection

A ready-to-use Postman collection is included with this repository.

The collection file is:

```text
TuPay Backend Assessment (Ledger & Settlement Engine).postman_collection.json
```

It covers:

1. Login
2. Step-Up Authentication
3. Execute Swap
4. Replay Consumed EAT
5. Retrieve Wallet Balance
6. Retrieve Ledger History
7. Completed Settlement Webhook
8. Duplicate Settlement Webhook
9. Out-of-Order Settlement Webhook
10. Invalid-Signature Settlement Webhook

The collection automatically stores:

* the Sanctum access token;
* the Elevated Action Token;
* the successful swap reference.

It also generates the `X-Settlement-Signature` HMAC-SHA256 header from the resolved raw request body.

Before running the collection:

1. import the collection into Postman;
2. set `base_url` to `http://127.0.0.1:8000`;
3. ensure MySQL, Redis, Laravel, and the queue worker are running;
4. replace `totp_code` with a current code generated from the seeded TOTP secret;
5. run the requests in their listed order.

---

# Testing

The project includes unit, feature, integration, and concurrency tests.

## Run the Standard Test Suite

```bash
php artisan test
```

The standard suite covers:

* authentication;
* TOTP verification;
* EAT issuance and validation;
* token replay protection;
* canonical action hashing;
* ledger posting;
* balance validation;
* exchange-rate handling;
* swap quote calculation;
* settlement webhook signature verification.

---

## Run Quality Checks

```bash
composer quality
```

The Composer quality script runs:

* Laravel Pint;
* PHPStan Level 8;
* Pest tests.

The individual commands are:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
php artisan test
```

Automatically fix Pint formatting issues with:

```bash
vendor/bin/pint
```

---

## Assessment Verification

The assessment allows either a green CI workflow or localized instructions proving that PHPStan Level 8 and the parallel concurrency test pass.

This repository includes reproducible local verification commands.

### PHPStan Level 8

The PHPStan configuration is located at:

```text
phpstan.neon
```

Run:

```bash
vendor/bin/phpstan analyse --no-progress
```

Expected result:

```text
[OK] No errors
```

PHPStan is also included in:

```bash
composer quality
```

---

## Run a Specific Test File

Examples:

```bash
php artisan test tests/Feature/Auth/LoginTest.php
```

```bash
php artisan test tests/Feature/Security/TwoFactorChallengeTest.php
```

```bash
php artisan test tests/Integration/Ledger/LedgerServiceTest.php
```

```bash
php artisan test tests/Feature/Settlement/SettlementWebhookTest.php
```

```bash
php artisan test tests/Unit/Swap/SwapQuoteCalculatorTest.php
```

---

## Parallel Concurrency Test

The concurrency test sends ten simultaneous HTTP swap requests for a user whose NGN wallet contains enough funds for exactly one swap.

It verifies that:

* exactly one request returns `200 OK`;
* exactly nine requests return `409 Conflict` or `422 Unprocessable Entity`;
* the source wallet does not become negative;
* the destination wallet is credited once;
* exactly one swap ledger transaction is posted;
* every currency remains balanced.

### Prepare the Database

The test consumes the concurrency user's entire seeded NGN balance.

Reset and seed the database before every run:

```bash
php artisan migrate:fresh --seed
```

Seed the dedicated concurrency user:

```bash
php artisan db:seed --class=ConcurrencyTestSeeder
```

### Start a Multi-Worker Server

The default single-worker PHP development server processes requests sequentially and cannot demonstrate real HTTP concurrency.

Start Laravel with multiple workers:

```bash
PHP_CLI_SERVER_WORKERS=10 php artisan serve
```

Keep this terminal running.

### Run the Test

Open a second terminal:

```bash
php artisan test tests/Concurrency/SwapConcurrencyTest.php
```

Expected result:

```text
PASS  Tests\Concurrency\SwapConcurrencyTest
✓ it allows exactly one of ten concurrent swaps
```

The database must be reset and reseeded before rerunning the test because the successful request consumes the concurrency wallet's available balance.

---

## Settlement Queue Testing

The settlement webhook dispatches processing through Laravel Queues.

Ensure Redis is running, then start the worker:

```bash
php artisan queue:work redis
```

Send:

```text
POST /api/webhooks/settlement
```

The endpoint should immediately return:

```text
202 Accepted
```

The queue worker then processes the settlement state asynchronously.

---

## CI Workflow

The localized commands in this README remain the authoritative, reproducible process for verifying:

* PHPStan Level 8;
* the standard automated test suite;
* the real parallel concurrency test.
