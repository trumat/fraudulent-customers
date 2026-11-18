# Fraud Scanner

A Laravel 13 + Vue 3 application that fetches every customer from the customers API, flags the fraudulent ones, and keeps a history of every scan.

- **Scan page (`/`):** a "Run fraud scan" button, then a table of all customers with the fraudulent ones highlighted and the reason for each flag. Click a row to see every recorded field. The last scan is restored when you refresh the page.
- **History page (`/scans`):** every scan with its date/time, customer count and fraudulent count. Expand a scan to see all of its customers.

Stack: Laravel 13 (PHP 8.5), SQLite, Vue 3.5 + TypeScript + `vue-router`, Tailwind 4, Vite, PHPUnit, Larastan (level 8), Pint. Everything runs in Docker.

## Setup

### Prerequisites

- Docker with Docker Compose.
- The customers API, running on the host at port 8080:

  ```sh
  docker run -d -p 8080:80 vzdeveloper/customers-api
  ```

  The app reaches it at `http://host.docker.internal:8080/api/v1` (`CUSTOMERS_API_URL` in `.env`).

### Run

```sh
git clone git@github.com:trumat/fraudulent-customers.git
cd fraudulent-customers
docker compose up -d --build
```
On Linux with a user with ID not 1000, run this instead:
```sh
UID=$(id -u) GID=$(id -g) docker compose up -d --build
```

Open <http://localhost:8000>.

## API

| Method | Path | Response |
| --- | --- | --- |
| `POST` | `/api/scans` | Runs a scan. `201` with the scan and its customers, or `503 {message}` if the customers API fails. |
| `GET` | `/api/scans` | Every scan, newest first, without customers. |
| `GET` | `/api/scans/latest` | The newest scan with its customers, served from the cache. `404` when there are no scans yet. |
| `GET` | `/api/scans/{id}` | One scan with its customers. `404` if it doesn't exist. |

Success responses are wrapped in `{"data": …}`; errors are `{"message": "…"}`.

## Architecture

```
customers API ──▶ CustomersApiClient ──▶ FraudDetector ──▶ RunFraudScanAction ──▶ JSON API ──▶ Vue SPA
                  (retries, validation,   (pure rules,      (saves Scan +          (resources,
                   DTOs)                   reasons)          Customers in a         last-scan
                                                             transaction)           cache)
```

1. `CustomersApiClient` fetches the customers. Each attempt has a 5 s timeout. Connection errors and 5xx responses are retried up to 3 attempts; 4xx responses aren't. It validates the payload and maps each customer to a `CustomerData` DTO. On failure it throws a `CustomersApiException` with a message that is safe to show to users.
2. `FraudDetector` applies the rules. It has no I/O, so it is tested with plain PHPUnit.
3. `RunFraudScanAction` runs both and saves a `Scan` with all its `Customer` rows in one transaction. If the API fails, nothing is saved.
4. The API controllers return resources. `bootstrap/app.php` turns a `CustomersApiException` into `503 {message}`.
5. The Vue SPA calls the API through a typed `fetch` wrapper and shows loading, empty and error states, with a "Retry" button on errors.

```
app/
  Actions/RunFraudScanAction.php       fetch → detect → save
  Http/Controllers/Api/                one single-action controller per route
  Http/Resources/                      ScanResource, CustomerResource
  Models/                              Scan hasMany Customer
  Services/CustomersApi/               API client, CustomerData DTO, exception
  Services/Fraud/FraudDetector.php     the fraud rules
  Support/                             LatestScanCache, CustomerName
resources/js/
  api.ts                               typed fetch wrapper
  pages/                               ScanPage, ScansHistoryPage
  components/                          AppLayout, CustomerTable, ErrorAlert
  types/models.ts                      Scan and Customer types
tests/
  Unit/                                FraudDetector, CustomersApiClient
  Feature/                             the scan action, one test class per API route, web routes
docker/                                PHP and Node images
```

## What did I implement?

- A Laravel 13 application with a Vue 3 scan page: a button starts the scan, which fetches all customers, checks them, and shows them all with the fraudulent ones highlighted.
- `Scan` and `Customer` models. Every completed scan is stored with its date/time and all its customers, each marked fraudulent or not.
- A Vue 3 history page that shows every scan and all its recorded data.
- Error handling: retries for the unstable customers API, a clear message when it fails, and loading, empty and error states in the UI. A failed scan keeps the previous result visible.
- JSON API endpoints that serve the data.
- Styling with Tailwind.
- The reason each customer was flagged, e.g. "Same IP as John Doe (127.0.0.1)" or "Non-NL phone number (+4915123456789)".
- The last scan is cached and restored when the page is refreshed.
- PHPUnit tests: unit tests for the fraud rules and the API client (mapping, retries, every kind of failure), and feature tests for the scan action and every route.

## Why did I implement or skip certain things?

- **Implemented: a snapshot of every customer per scan.** The customers API returns different random customers, with different IDs, on every call. There is no stable customer to link scans to, so each scan stores its own copy of every customer instead of using a `customers` table plus a pivot.
- **Implemented: One single-action controller per route.** Each endpoint is a small class with one job, and it pairs one-to-one with its test class. Because the controllers can't share private helpers, the caching logic that two of them need lives in its own `LatestScanCache` class instead of in the HTTP layer.
- **Skipped: the OpenAPI specification.** Skipped for time. Generating a spec would add more dependencies and code and I wanted to avoid writing the spec JSON by hand to avoid inconsistencies.
- **Skipped: recording failed scans.** The task asks for a `Scan` per *completed* scan. A failed scan shows an error and keeps the previous result.


## What trade-offs did I make?

**Data**

- **Every upstream field is nullable; malformed values are treated as missing and logged.** Without this, two customers with `""` as their IP would be "Same IP". I log malformed values rather than storing them. The cost is that a scan alone can't tell "clean" from "unparsable date of birth"; only the log shows it.
- **Field contents aren't validated** (IP format, IBAN checksum, email). This seemed out of scope and based on the data from the Customers API validation was happening upstream. The cost: a placeholder such as `"unknown"` on two customers would match as "Same IP".
- **Dutch phone = `+31` plus exactly 9 digits, with no normalisation**, because the API only ever sends `+<country code><digits>`. The cost: national (`06…`) or formatted numbers would be flagged.
- **Scan counts are stored, not computed.** `total_customers` and `fraudulent_count` repeat what the customer rows say. Scans never change after they're created, so the copies can't drift, and the history list stays a plain query. The cost is two denormalised columns.
- **BSN is stored as an integer,** as the API spec types it. A leading zero is already lost upstream.
- **`last_login_at` keeps its time but no timezone.** The API sends a local time without a timezone, so it is stored and returned as-is. Clients can't tell which timezone it's in.
- **SQLite.** No extra service to run.

**Fraud reasons**

- **The backend sends ready-made messages, not reason codes.** Each reason needs extra data to become a message (the shared value, the other customers' names, the age). Codes would need a separate details structure per code, in the detector, the database, the API and the TypeScript types, and the "Unknown customer" name fallback would have to exist in both PHP and TypeScript. The cost: the wording lives in the backend and in stored scans, so changing a message doesn't update old scans and translating the UI means changing the backend.

**API and performance**

- **The scan runs synchronously in the `POST` request, not in a queue.** Simple, but the request can take about 15 s when the customers API needs retries. The button shows "Scanning…" meanwhile.
- **Last-scan cache.** The newest scan is cached as a ready-made payload, with a fallback to the database, so `cache:clear` loses nothing. With SQLite and the database cache store this saves little, since a cache hit is still a query on the same database. In production, with e.g. Redis in front of MySQL, it would take the most frequent read off the database. I rejected caching in the browser (`localStorage`): it would store customers' personal data unencrypted on the client, and the server already restores the last scan.
- **Responses are wrapped in `{data: …}`** (Laravel's default), so metadata such as pagination can be added later without breaking clients. The cost: the frontend unwraps `.data` everywhere.
- **No pagination on the history page; a scan's customers load only when it is expanded.** The list of scans is small, and the customer data is fetched on demand.


## What would I improve or add with more time?

- **An OpenAPI spec:** mentioned above
- **Queued scans** with progress in the UI, so the request doesn't wait for the customers API.
- **Recording failed scans,** so the history shows when the customers API was down.
- **Auth and rate limiting** on the API, since a scan stores personal data and calls an external service.
- **Pagination and search** on the history page and the customer table.
- **End-to-end tests** of the Vue pages (e.g. Playwright), and frontend unit tests.
- **Data validation** (IBAN mod-97 checksum, IP and phone formats), shown as data-quality warnings separate from fraud.

## AI usage

I used Claude Code for:

- Reviewing the initial step-by-step plan
- Generating code for each step
- Drafting this README
