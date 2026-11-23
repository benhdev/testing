# AJ Bell Technical Task — Implementation Plan

## The task, distilled

Build a local-only service that:

1. **Fetches** fund JSON from four AJ Bell endpoints.
2. **Stores** it durably so consumers don't re-fetch on every request.
3. **Exposes** a well-structured API that lets web/app consumers query, filter and sort funds (ongoing charge, analyst rating, etc.).
4. **Keeps the store fresh** — the upstream data can change; we need a periodic refresh.
5. **Has tests** and good error handling.

Explicit constraints: runs locally; no external access beyond the AJ Bell CDN.

---

## Guiding principles

Everything below follows the benhdev Laravel conventions (the ones in `~/repos/docs`). The short version:

- **Actions** for business logic (not Services), `handle()` + private helpers.
- **Connectors** for all external HTTP, using the `Http::request` macro, endpoints in `config/connectors.php`.
- **FormRequests** for validation — controllers stay skinny.
- **Resources** for every API response.
- **Eloquent** everywhere (Attributes for format transformations).
- **Correct HTTP codes** (never 200 for an error).
- **`config()` not `env()`**, Pint-clean, 4-space indent, typed params + returns, named args when >1 param, UK spelling in the DB, kebab-case routes, snake_case request params.

Don't over-engineer: show taste and judgement, not scaffolding for its own sake. The brief explicitly says _"clean, testable and reusable"_ and _"another engineer can understand and work with"_ — readability wins over cleverness.

---

## Architecture at a glance

```
            ┌──────────────────────────────────────────────┐
            │                                              │
            │     AJ Bell CDN (4 JSON endpoints)           │
            │                                              │
            └──────────────┬───────────────────────────────┘
                           │   (parallel GET via Http::pool)
                           ▼
            ┌──────────────────────────────────────────────┐
            │  App\Http\Connectors\AjBellFundConnector    │
            └──────────────┬───────────────────────────────┘
                           │
                           ▼
            ┌──────────────────────────────────────────────┐
            │  App\Actions\RefreshFunds                   │
            │   - fetches via connector                   │
            │   - processes: normalises upstream shape    │
            │     into DB schema, derives is_active,      │
            │     stamps fetched_at                       │
            │   - upserts Fund + related rows             │
            │   - marks missing SEDOLs inactive           │
            │   - logs partial failures, re-raises fatal  │
            └──────────────┬───────────────────────────────┘
                           │
          invoked by       │                  used by
     ┌─────────────────────┴──────────────────────────────┐
     │                                                     │
     ▼                                                     ▼
 Artisan command                                   Laravel scheduler
 `php artisan funds:refresh`                       (every hour, overlap-safe)

                           ▼
            ┌──────────────────────────────────────────────┐
            │   MySQL 8 (funds + fund_documents +          │
            │   fund_holdings + fund_asset_allocations)    │
            └──────────────┬───────────────────────────────┘
                           │
                           ▼
            ┌──────────────────────────────────────────────┐
            │   GET /api/funds   /api/funds/{sedol}        │
            │   FundController -> FundResource             │
            └──────────────────────────────────────────────┘
                           │
                           ▼
            ┌──────────────────────────────────────────────┐
            │   React + TS SPA (Vite)                      │
            │   Served from a thin Blade shell;            │
            │   consumes /api/funds via fetch              │
            └──────────────────────────────────────────────┘
```

---

## Data model

I inspected `BYW8RV9.json` to ground the schema. Each fund JSON has: a `quote` block (price, currency, ongoing charge, sector), a `profile` (prose objective), `ratings` (analystRating + SRRI), a `documents` list (Factsheet / KIID / Prospectus), and a `portfolio` (asset allocation + top-10 holdings).

### Tables

- **`funds`** — one row per SEDOL. Scalar fields that we need to filter/sort on.

    | column                      | type                   | notes                                                               |
    | --------------------------- | ---------------------- | ------------------------------------------------------------------- |
    | `id`                        | bigint pk              |                                                                     |
    | `sedol`                     | varchar(10) unique     | natural key from upstream (`marketCode` → `BYW8RV9`)                |
    | `market_code`               | varchar(32)            | e.g. `FUND:BYW8RV9`                                                 |
    | `name`                      | varchar(255)           |                                                                     |
    | `category`                  | varchar(32)            | `growth` \| `responsible` — derived from our mapping (not upstream) |
    | `risk_label`                | varchar(32) nullable   | `cautious` \| `balanced` \| `adventurous` \| `responsible`          |
    | `sector_name`               | varchar(255) nullable  |                                                                     |
    | `currency`                  | varchar(10) nullable   |                                                                     |
    | `last_price`                | decimal(14,4) nullable | bcmath territory                                                    |
    | `last_price_date`           | date nullable          |                                                                     |
    | `ongoing_charge`            | decimal(6,4) nullable  | key filter field, indexed                                           |
    | `analyst_rating`            | tinyint nullable       | key filter field, indexed                                           |
    | `analyst_rating_label`      | varchar(32) nullable   |                                                                     |
    | `srri`                      | tinyint nullable       |                                                                     |
    | `objective`                 | varchar(5000) nullable | long prose — still varchar per benhdev convention                   |
    | `is_active`                 | boolean                | toggled off if SEDOL disappears upstream                            |
    | `fetched_at`                | timestamp              | last successful upstream fetch                                      |
    | `created_at` / `updated_at` | timestamps             |                                                                     |

- **`fund_documents`** — hasMany off funds. `{type, external_id, url}`.
- **`fund_asset_allocations`** — hasMany off funds. `{label, value}`.
- **`fund_holdings`** — hasMany off funds. `{name, weighting}`, plus `position` for the top-10 ordering.

Indexes on `funds.ongoing_charge`, `funds.analyst_rating`, `funds.category`, `funds.is_active`.

### Why normalise over a single JSON column

Shows Eloquent relationships and lets `FundResource` compose cleanly. Not slower at this scale (a few dozen funds max), and makes the shape of the data obvious to anyone reading the migrations. If the brief had implied thousands of nested blobs I'd keep the portfolio as a JSON column — judgement call, worth noting in Q&A.

### Category / risk mapping

The brief tells us which SEDOLs are Cautious / Balanced / Adventurous / Responsible, but that info is **not** in the upstream JSON. A `database/seeders/FundSeeder` creates partial rows `{sedol, category, risk_label}` for the four known funds. The refresh Action then `upsert`s on `sedol` and only writes upstream-sourced columns, so `category` / `risk_label` survive every refresh.

Workflow:

```
php artisan migrate:fresh --seed   # seeds the four SEDOLs with their categories
php artisan funds:refresh          # pulls prices/ratings/etc. from AJ Bell
```

---

## Fetching layer

### `App\Http\Connectors\AjBellFundConnector`

Static class following the benhdev pattern. One public method:

```php
public static function all(): Collection
{
    $sedols = array_keys(config('funds.catalogue'));

    return Http::parallel(
        array_map(fn (string $sedol) => [
            'key'    => $sedol,
            'method' => 'GET',
            'via'    => 'aj_bell_funds',
            'uri'    => $sedol . '.json',
            'body'   => [],
        ], $sedols),
    );
}
```

- `config/connectors.php` → `'aj_bell_funds' => 'https://cdn.core3-dev.ajbbuild.uk/interview/'`
- Parallel pool for performance (the brief lists performance as a criterion).
- Default `throw: true` — if any individual request fails, the exception bubbles up and the Action decides how to react.

### `App\Actions\RefreshFunds`

Handles the orchestration:

```
handle():
  1. fetchAll()                 – call the connector
  2. upsertFunds($payloads)     – idempotent writes, per-SEDOL
  3. deactivateMissing()        – anything in DB but not upstream → is_active=false
  4. logSummary()               – fetched / updated / failed counts
```

Each sub-step is a private method. Partial failures (one SEDOL bad JSON, others fine) are caught, logged, and the overall action completes — this keeps the refresh resilient. A fully failed fetch (CDN unreachable) does throw, so the scheduler surfaces it.

### Trigger surfaces

- `php artisan funds:refresh` — manual / seeding / first run.
- Scheduler entry in `routes/console.php`:
    ```php
    Schedule::command('funds:refresh')
        ->hourly()
        ->withoutOverlapping()
        ->runInBackground();
    ```
- The hourly refresh only fires while Laravel's scheduler is running (`php artisan schedule:work` in a separate terminal, or crontab in production). For local evaluation the reviewer will typically just call `php artisan funds:refresh` manually once — documented in the README.

---

## API design

Routes in `routes/api.php`, deliberately minimal:

| Method | Path                      | Action                               |
| ------ | ------------------------- | ------------------------------------ |
| GET    | `/api/funds`              | List + filter + sort + paginate      |
| GET    | `/api/funds/{fund:sedol}` | Single fund with documents/portfolio |

Refresh is artisan-only (`php artisan funds:refresh`) + the scheduler. No HTTP surface for it — keeps the public API read-only and less to reason about.

### Filtering

Rather than hand-rolling `if ($request->has(...))` branches, I'll implement a tiny `FundFilter` class driven by the `ListFundsRequest` validated input. Supported filters:

- `category` — `growth` or `responsible`
- `risk` — `cautious` / `balanced` / `adventurous` / `responsible`
- `min_analyst_rating` — integer 1–5
- `max_ongoing_charge` — decimal
- `sort` — `ongoing_charge` / `analyst_rating` / `name` (prefix `-` for desc)
- `per_page` — pagination, capped at 100

`ListFundsRequest::rules()` validates everything, including allowed sort values via `Rule::in([...])`. Invalid filters return 422 with Laravel's default shape — no exception handler fuss needed.

### Responses

- `FundResource` — single fund (basic + relationships).
- `FundCollection` (optional) — wraps the paginated list with meta. Usually the default collection is fine; can decide at implementation time.
- 200 on success, 404 on unknown SEDOL (route model binding), 422 on validation failure.

### Performance

- List endpoint eager-loads the three child relationships (`->with(['documents', 'assetAllocations', 'holdings'])`) to avoid N+1 — intentional, not accidental.
- Filter columns (`ongoing_charge`, `analyst_rating`, `category`, `is_active`) are indexed at migration time.
- `per_page` capped at 100 so a malicious client can't force a huge payload.

---

## Error handling

- **Upstream failures** — connector throws; Action logs + continues for per-fund failures, re-throws for all-fund failure.
- **Validation** — FormRequest handles it, Laravel returns 422 with field errors.
- **Unknown fund** — route model binding returns 404 automatically.
- **Unexpected exceptions** — Laravel's default exception renderer returns a well-formed JSON error for API routes. No custom handler needed for the test.
- **Logging** — `Log::warning()` for per-fund refresh failures (we want these visible but not fatal). `Log::error()` for connector failures.

---

## Testing strategy

**PHPUnit 12** (Laravel 13's `composer create-project` default). The fresh install already has `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` as class-based PHPUnit tests — I'll follow that shape.

Three layers, limited scope:

1. **Feature tests** (the most valuable for an evaluator to skim):
    - `Api/ListFundsTest` — filters, sorting, pagination, validation.
    - `Api/GetFundTest` — 200 + 404.
    - `Api/RefreshFundsTest` — mock the upstream (`Http::fake(...)`), assert DB state after.
2. **Unit tests** for the Action — the orchestration logic, with fakes.
3. **A single resource test** — `FundResource` shape doesn't drift.

**Test database:** SQLite in-memory via `RefreshDatabase` — Laravel 13's default `phpunit.xml` is already configured for it. Keeps tests fast and reviewer-friendly (no need for a separate MySQL test schema), at the cost of a small MySQL/SQLite divergence risk. The schema is simple enough (no MySQL-specific features) that this is a defensible trade-off; happy to discuss in Q&A.

I will NOT:

- Mock Eloquent — hit the real DB.
- Cover every filter combination exhaustively — pick representative cases.

---

## Frontend (React, deliberately contained)

React + TypeScript + Vite + Tailwind. The React app is served from a single Blade shell (`resources/views/app.blade.php`) and consumes the public API via `fetch` — the same way an external web consumer would. This keeps the "API is the product" message clean (no Inertia magic bypassing it).

**Shape:**

- Single page (no React Router). The list and the detail view share the same page — clicking a fund row expands an inline detail panel below it. Shows thoughtful UX without routing overhead.
- Left sidebar: filter form (`category`, `risk`, `min_analyst_rating`, `max_ongoing_charge`, `sort`). Filter state is held in React hooks and reflected into the URL as query params so the page is bookmarkable / shareable.
- Right content: paginated fund table (`name`, `category`, `ongoing charge`, `analyst rating`, `last price`), sortable column headers.
- Expanded row: `objective` prose, asset allocation (simple horizontal bar), top-10 holdings table, documents list.

**Libraries, minimal:**

- `react`, `react-dom` (obvious).
- `typescript` + `@vitejs/plugin-react` for the build.
- No state library, no TanStack Query, no UI kit. Hooks + a tiny `useFunds(filters)` fetcher is enough.
- Keep Tailwind as the only styling surface.

**File layout under `resources/js/`:**

```
app.tsx                 # mount point
src/
  api/funds.ts          # fetch wrappers, typed response shapes
  components/
    FundList.tsx
    FundRow.tsx
    FundDetailPanel.tsx
    FilterSidebar.tsx
    EmptyState.tsx
  hooks/
    useFunds.ts
    useFilters.ts       # reads/writes URL search params
  types.ts              # shared types mirroring FundResource
```

At Q&A I can talk about what a richer UI would add — React Router + a `/funds/:sedol` page, TanStack Query for caching/mutations, a design system, skeleton loaders, URL-driven state via a router — without needing to have built them.

---

## File structure preview

```
app/
  Actions/
    RefreshFunds.php
  Filters/
    FundFilter.php
  Http/
    Connectors/
      AjBellFundConnector.php
    Controllers/
      FundController.php
    Requests/
      ListFundsRequest.php
    Resources/
      FundResource.php
      FundDocumentResource.php
      FundHoldingResource.php
      FundAssetAllocationResource.php
  Models/
    Fund.php
    FundDocument.php
    FundHolding.php
    FundAssetAllocation.php
  Console/
    Commands/
      RefreshFundsCommand.php
  Providers/
    AppServiceProvider.php  (Http::request + Http::parallel macros)
config/
  connectors.php
database/
  migrations/
    *_create_funds_table.php
    *_create_fund_documents_table.php
    *_create_fund_holdings_table.php
    *_create_fund_asset_allocations_table.php
  seeders/
    DatabaseSeeder.php      # runs FundSeeder
    FundSeeder.php          # 4 known SEDOLs with category/risk_label
  factories/
    FundFactory.php
routes/
  api.php
  console.php              (scheduler entry)
resources/
  views/
    app.blade.php           # single Blade shell mounting the React app
  js/
    app.tsx                 # React entry point
    src/
      api/funds.ts
      components/*.tsx
      hooks/*.ts
      types.ts
tests/
  Feature/
    Api/
      ListFundsTest.php
      GetFundTest.php
      RefreshFundsTest.php
  Unit/
    Actions/
      RefreshFundsTest.php
    Resources/
      FundResourceTest.php
docs/
  implementation-plan.md   (this file — handy for the presentation)
README.md                  (how to run + design-decision notes)
```

---

## What I'm deliberately NOT doing (and why)

Called out explicitly because the interview's Q&A specifically asks _"what you would improve or extend"_. Having considered trade-offs ready beats being caught.

- **No historical snapshots** of fund data. We overwrite on refresh and track `fetched_at`. Trivial to add a `fund_snapshots` table if they want point-in-time price history.
- **No automatic discovery of new SEDOLs**. The refresh only pulls the four SEDOLs seeded in the DB — if AJ Bell publishes a fifth fund tomorrow, we won't see it until someone adds it to `FundSeeder` (or re-runs the seed). The brief implies the data at the known URLs changes, not that URLs appear/disappear, so this is a conscious scoping line — easy to replace the seed with an index-endpoint lookup if the real integration had one.
- **No auth** on the API. The brief is local-only; adding Sanctum would be noise.
- **No caching layer** beyond the DB. MySQL with indexed columns is more than fast enough for a few dozen funds. HTTP response caching / Redis would matter at scale, not here.
- **No OpenAPI spec**. Nice to have, not asked for. Mention in Q&A.
- **No Docker, no Sail, no Dockerfile**. The app runs via `php artisan serve` + `npm run dev` against a local MySQL. "Runs locally" → the simplest honest answer.
- **No queue workers**. Refresh is fast enough to be synchronous; no need to push to a job queue for a few parallel GETs.
- **No rate-limit / retry** on the connector. Upstream is a CDN; one shot is enough. Would add exponential backoff for a flaky integration.
- **No Inertia layer**. The React SPA consumes the public `/api/funds` directly — which is the API we're being asked to show off.

---

## Resolved decisions

1. **Frontend**: React + TypeScript + Vite + Tailwind, single page with inline expand for detail, hooks only (no router, no state library).
2. **Testing**: PHPUnit 12 (the fresh install's default).
3. **Refresh endpoint**: dropped — artisan command + scheduler only.
4. **Category mapping**: seeded into the `funds` table via `FundSeeder`; refresh Action's `upsert` only touches upstream-sourced columns so categories survive.
5. **Price precision**: `decimal(14,4)` in the DB, stored and read as strings, and **all arithmetic / comparisons go through bcmath** (`bcadd`, `bccomp`, etc. — or PHP 8.5's `BcMath\Number` where it reads cleaner). Eloquent attribute casts return strings so we never silently touch floats.

---

## Phased implementation order (for when we build)

Roughly in dependency order, each phase independently committable:

1. Scaffold — Pint config, `connectors.php`, `.env.example`, Http macros in AppServiceProvider.
2. Migrations + Models + Factories + `FundSeeder`.
3. Connector + Action + artisan command, behind feature tests with `Http::fake()`.
4. Scheduler wiring (`routes/console.php`).
5. API: Request, Filter, Resources, Controller — behind feature tests.
6. React frontend — Vite config + TypeScript + Tailwind, then the components.
7. README (how to run, design decisions, "what I'd improve next").
8. Pint + final test run.

Each phase is small enough to review in isolation. Rough total: a focused afternoon.
