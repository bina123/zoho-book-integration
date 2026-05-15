# Zoho Books — Profit & Loss Comparison

A Laravel application that fetches live Profit & Loss data from Zoho Books, compares two months side-by-side against locally-stored budgets, and lets you drill into account transactions with PDF attachment downloads. Implements the practical test brief in `Zoho Books Practical Test.pdf`.

## Features

- OAuth 2.0 connection to Zoho Books (India / US / EU data centers supported) with CSRF-protected `state` parameter
- Side-by-side P&L for any two months with budget vs actual variance columns
- Hyperlinked Zoho values that open a transaction drill-down (matches Page 20 of the brief)
- One-click PDF download for each invoice/bill attachment
- Editable per-month budgets stored locally in MySQL
- 60-second cached Zoho calls with a "Refresh from Zoho" button (targeted invalidation, not `Cache::flush`)

## Stack

| Layer       | Choice                                          |
| ----------- | ----------------------------------------------- |
| Framework   | Laravel 13 (PHP 8.3+)                           |
| Database    | MySQL 5.7+ / 8.x                                |
| Cache/Queue | Database driver (no Redis required)             |
| HTTP        | Guzzle 7                                        |
| Frontend    | Server-rendered Blade + vanilla JS, Vite-bundled CSS/JS, Tailwind 4 available |
| Tests       | PHPUnit 12                                      |
| Lint        | Laravel Pint (PSR-12 + Laravel preset)          |

## Prerequisites

- PHP **8.3+** with extensions: `mbstring`, `openssl`, `pdo_mysql`, `curl`, `json`
- Composer 2.x
- Node 18+ and npm (for Vite asset compilation)
- MySQL running locally (any port) with a database you can create tables in
- A Zoho Books account containing the test data from the brief (TATA SONS customer + 5 vendors + invoices INV001/002 + bills BILL001–010)
- A Zoho OAuth client registered in your [Zoho API Console](https://api-console.zoho.com)

## 1. Clone & install

```bash
git clone <your-repo-url> zoho-books-backend
cd zoho-books-backend
composer install
npm install
cp .env.example .env
php artisan key:generate
```

## 2. Register the Zoho OAuth client

1. Open the Zoho API Console for **your** data center:
   - India: <https://api-console.zoho.in>
   - US/Global: <https://api-console.zoho.com>
   - EU: <https://api-console.zoho.eu>
2. **Add Client → Server-based Applications**.
3. Authorized Redirect URI: `http://localhost:8000/auth/zoho/callback`
4. Save and copy the generated **Client ID** and **Client Secret**.

## 3. Configure `.env`

Edit `.env` and update at minimum:

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306                          # use 3307/8889 for MAMP/parallel installs
DB_DATABASE=zoho_integration
DB_USERNAME=root
DB_PASSWORD=root

# Zoho — set these to YOUR values
ZOHO_CLIENT_ID=1000.xxxxxxxxxxxxxxxxx
ZOHO_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
ZOHO_REDIRECT_URI=http://localhost:8000/auth/zoho/callback
# ZOHO_ORGANIZATION_ID is optional — you'll pick it from a UI list after first auth

# Pick the data center your Zoho account lives in:
ZOHO_ACCOUNTS_URL=https://accounts.zoho.in
ZOHO_API_URL=https://www.zohoapis.in/books/v3
# US:  https://accounts.zoho.com    + https://www.zohoapis.com/books/v3
# EU:  https://accounts.zoho.eu     + https://www.zohoapis.eu/books/v3

# Cache and queue — keep as 'database' unless you've installed Redis + phpredis ext
CACHE_STORE=database
QUEUE_CONNECTION=database
```

> **Tip — data center mismatch is the #1 cause of `invalid_client` errors at the OAuth step.** The data center of your OAuth client must match the data center of your Zoho Books organization.

## 4. Create the database & run migrations

```bash
mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS zoho_integration CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate --seed
```

Seeded budgets match Page 19 of the brief:

| Month   | Sales Budget | COGS Budget |
| ------- | -----------: | ----------: |
| 2026-04 |      115,000 |      80,000 |
| 2026-05 |      225,000 |      50,000 |

## 5. Build front-end assets

```bash
npm run build       # production build
# OR
npm run dev         # dev server with HMR while developing
```

Vite compiles `resources/css/app.css`, `resources/css/report.css`, `resources/js/app.js`, and `resources/js/report.js` to `public/build/` with hashed filenames.

## 6. Run

```bash
php artisan serve
```

Open <http://localhost:8000/report> and you'll be guided through:

1. **Connect to Zoho** → click the button → consent on Zoho's screen
2. **Select organization** → pick your test company → save
3. **Report loads** with live P&L data for May 2026 vs April 2026

## 7. Use

- **Compare different months**: change "Column A month" / "Column D month" at the top → Load
- **Drill into transactions**: click any underlined Zoho-sourced number → modal opens with the account-transactions list matching Page 20
- **Download attachments**: each transaction row has a `Download` link that streams the PDF you attached to that invoice/bill
- **Edit budgets**: scroll to the "Budgets (stored locally)" card → change values → Save
- **Pick up new Zoho transactions**: click "Refresh from Zoho" (clears only Zoho-prefixed cache entries — never the entire app cache)
- **Disconnect**: click "Disconnect" → revokes the refresh token at Zoho and clears local credentials

## Architecture

The codebase follows SOLID principles end-to-end:

```
app/
  Contracts/                       Interfaces (DIP)
    BudgetStore.php                  read/write budgets
    OrganizationStore.php            read/write selected Zoho org
    ZohoAuthClient.php               OAuth client
    ZohoBooksClient.php              API client
    ZohoTokenStore.php               read/write OAuth tokens

  Enums/                           Type-safe replacements for magic strings
    AttachmentType.php               'invoice' | 'bill'
    BudgetCategory.php               'sales' | 'cogs' (+ section/account lookup)
    TransactionType.php              Bill, Invoice, VendorPayment, ...

  Exceptions/
    ZohoApiException.php             renderable JSON error response

  Http/
    Controllers/                   Single-responsibility controllers
      AttachmentController.php       streams PDF attachments
      BudgetController.php           upserts budget rows
      ReportController.php           page render + cache invalidation
      TransactionsController.php     account-transactions JSON endpoint
      ZohoAuthController.php         OAuth flow + org picker
    Middleware/
      ForceJsonResponse.php          forces Accept: application/json on /api/*
    Requests/                      Validation lives here, not in controllers
      DownloadAttachmentRequest.php
      LoadReportRequest.php          (defaults to current/previous month)
      TransactionsRequest.php
      UpdateBudgetRequest.php
    Resources/                     API response shaping
      AccountBalanceResource.php
      TransactionListResource.php
      TransactionResource.php

  Models/                          Eloquent
    Budget.php
    ZohoToken.php

  Providers/
    ZohoServiceProvider.php          binds contracts to concretes, singleton Guzzle Client

  Repositories/                    Data-access concretes implementing the Store contracts
    BudgetRepository.php
    ZohoTokenRepository.php

  Rules/
    MonthRule.php                    reusable YYYY-MM validator

  Services/
    OrganizationService.php          selected-org persistence (separated from books client)
    ReportService.php                merges two months + budgets, computes variances
    TransactionsAssembler.php        normalises Zoho txn responses
    ZohoAuthService.php              OAuth flow implementation
    ZohoBooksService.php             Zoho Books API implementation
    Zoho/
      ProfitLossParser.php           recursive parser for Zoho's nested P&L tree

  Support/
    ReportPresenter.php              view-model — Blade holds zero decision logic

config/zoho.php                    Zoho config (client_id, urls, scopes)
database/
  migrations/                      tokens, budgets, cache, jobs
  seeders/BudgetSeeder.php         default budgets for Apr/May 2026
resources/
  css/{app,report}.css             Vite-compiled stylesheets
  js/report.js                     drill-down modal logic (extracted from Blade)
  views/                           Blade templates, no inline styles or scripts
routes/
  web.php                          /report, /auth/zoho/*
  api.php                          /api/report/*, /api/zoho/status
tests/
  fixtures/                        snapshot JSON of real Zoho responses
  Unit/                            parser, assembler, enum tests
```

### Why interfaces?

Every service and repository implements a `Contracts\*` interface. This makes the code testable: in `TransactionsAssemblerTest.php` we pass a `createMock(ZohoBooksClient::class)` instead of hitting the network. Swapping the HTTP client, database, or token store in production requires only a new binding in `ZohoServiceProvider`.

## HTTP routes (cheat sheet)

| Method | Path                                       | Purpose                                    |
| ------ | ------------------------------------------ | ------------------------------------------ |
| GET    | `/report`                                  | Main P&L comparison page                   |
| POST   | `/report/refresh`                          | Clear Zoho-only cache entries              |
| POST   | `/report/budgets`                          | Upsert monthly budget                      |
| GET    | `/report/transactions?account_id&month`    | Account drill-down (JSON)                  |
| GET    | `/report/attachments/{type}/{id}`          | Stream invoice/bill PDF                    |
| GET    | `/auth/zoho`                               | Redirect to Zoho consent screen            |
| GET    | `/auth/zoho/callback`                      | OAuth callback (validates `state`)         |
| GET    | `/auth/zoho/organizations`                 | Org picker view                            |
| POST   | `/auth/zoho/organizations`                 | Save selected organization                 |
| POST   | `/auth/zoho/logout`                        | Revoke token and disconnect                |
| GET    | `/auth/zoho/status` (or `/api/zoho/status`)| JSON: connected / org / expires_at         |

All `/api/*` endpoints return JSON for any error (validation, auth failure, Zoho API error) in a uniform shape:

```json
{
  "success": false,
  "message": "The month must be a valid YYYY-MM month.",
  "errors": { "month": ["..."] }
}
```

## Tests

```bash
./vendor/bin/phpunit                    # all suites
./vendor/bin/phpunit --testsuite Unit   # just unit tests
```

Current suite: **23 tests, 54 assertions** in ~100 ms.

| Test                                | What it covers                                                              |
| ----------------------------------- | --------------------------------------------------------------------------- |
| `ProfitLossParserTest`              | Parses live Zoho fixture; handles empty + malformed payloads                 |
| `TransactionsAssemblerTest`         | Filters to requested account; opening/closing balances; meta; attachment mapping |
| `BudgetCategoryTest`                | Section/account-name → enum resolution; DB column derivation                |
| `AttachmentTypeTest`                | Endpoint construction; transaction-type → attachment-type mapping            |

Live Zoho payloads are captured under `tests/fixtures/` so the parser is tested against real-world shapes without hitting the network.

## Linting

```bash
./vendor/bin/pint                       # format
./vendor/bin/pint --test                # check only, no fixes (use in CI)
```

`pint.json` uses the Laravel preset with strict trailing-comma rules, alphabetised imports, and PSR-12-aligned PHPDoc.

## Verifying the end-to-end flow

After authorizing and loading the report, run a final acceptance test from Page 21:

1. Open Zoho Books → Purchases → Bills → **Create a Bill**
2. Vendor: `TATA CAPITAL`, Bill #: `BILL011`, date `01/05/2026`, item `TEST`, qty `6`, rate `5,000`. Total `30,000`. Save and mark as Open.
3. Back in the app, click **Refresh from Zoho**.
4. May COGS should now read `1,17,500` and clicking it shows **6** bill rows (the new `BILL011` row appears).

## Troubleshooting

| Symptom                                                          | Fix                                                                                     |
| ---------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| OAuth → `invalid_client`                                         | Data-center mismatch: change `ZOHO_ACCOUNTS_URL` + `ZOHO_API_URL` to your region.       |
| OAuth → `redirect_uri_mismatch`                                  | The URL in `.env` must exactly match the one registered in your Zoho API Console.       |
| OAuth → `OAuth state mismatch`                                   | Session was lost between redirect and callback (e.g. session driver switched). Click Connect again. |
| `Class "Redis" not found`                                        | `phpredis` PHP extension isn't installed. Default in `.env` is `database` — no action needed. |
| `SQLSTATE[HY000] [2002] Connection refused`                      | MySQL isn't running, or `DB_PORT` is wrong (MAMP uses 8889, parallel installs often 3307). |
| Modal says "No Zoho organization selected"                       | Visit `/auth/zoho/organizations` to pick one, or set `ZOHO_ORGANIZATION_ID` in `.env`.  |
| `The media type is not supported` on attachment download          | Already handled (`downloadAttachment` sets `Accept: */*`). Pull latest.                 |
| Token expired and `refreshAccessToken` fails                     | Click **Disconnect** then **Connect to Zoho** again — gets a fresh refresh token.       |
| Page loads but no CSS                                             | Run `npm run build` (or `npm run dev` during development).                              |

## Production notes

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Move secrets (`ZOHO_CLIENT_SECRET`, `DB_PASSWORD`) into a secret manager or `.env` outside the repo.
- Update `ZOHO_REDIRECT_URI` to the public HTTPS URL and re-register it in the Zoho API Console.
- Run `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
- Run `npm run build` and serve `public/` via your web server.
- Consider switching `CACHE_STORE` and `QUEUE_CONNECTION` to `redis` for higher throughput (install the `phpredis` PHP extension first).
- Run a long-running worker if you add async jobs: `php artisan queue:work`.

## License

MIT.
