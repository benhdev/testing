# AJ Bell Senior Full Stack — Technical Task

A small Laravel + React service that fetches fund data from the AJ Bell CDN,
stores it, and exposes it as a filterable JSON API consumed by a typed
React SPA.

The brief lives in `docs/` alongside `implementation-plan.md` (the up-front
design) and `phase-log.md` (a chronological record of every phase mapped
back to the brief's evaluation criteria).

---

## What this does, in one paragraph

Four fund JSON endpoints (Cautious / Balanced / Adventurous Growth + a
Responsible Growth fund) are pulled from the AJ Bell CDN in parallel,
validated through a DTO, and stored in a normalised MySQL schema. A
read-only REST API (`GET /api/funds`, `GET /api/funds/{sedol}`) lets a
consumer filter by category, risk, minimum analyst rating, maximum ongoing
charge, with configurable sort + pagination. A React 19 + TypeScript SPA
drives the whole thing from the browser. A scheduled hourly artisan
command (`funds:refresh`) keeps the store current.

---

## Prerequisites

- PHP 8.4+ with the usual Laravel extensions (`pdo_mysql`, `mbstring`,
  `bcmath`, …)
- Composer 2
- Node 20+ / npm 10+
- A local MySQL 8 instance

---

## Quick start

```bash
# 1. Install dependencies
composer install
npm install

# 2. Create .env and generate an APP_KEY
cp .env.example .env
php artisan key:generate

# 3. Create the database (any name — set DB_DATABASE in .env to match)
mysql -u root -e "CREATE DATABASE technical_test"

# 4. Migrate + seed the four known SEDOL / category mappings
php artisan migrate:fresh --seed

# 5. Populate the funds from AJ Bell
php artisan funds:refresh

# 6. Serve the app (in two terminals)
php artisan serve                      # http://localhost:8000
npm run dev                            # Vite dev server
```

Then open `http://localhost:8000` — the React UI loads and immediately hits
`/api/funds`.

### Serving via Laravel Valet / Herd (optional)

Everything also works through [Laravel Valet](https://laravel.com/docs/valet)
or [Herd](https://herd.laravel.com) — the project needs no extra config:

```bash
cd ~/repos && valet park     # one-time; makes every Laravel app in ~/repos
                             # available at <directory-name>.test
```

Then update `.env`:

```dotenv
APP_URL=http://technical-test.test
```

Vite still runs in a separate terminal:

```bash
npm run dev
```

Now visit `http://technical-test.test`. No need to `php artisan serve` —
Valet / Herd handle the PHP runtime. The React SPA uses relative URLs
(`/api/funds`), so it works regardless of host.

If you've run `valet secure technical-test`, use `https://technical-test.test`
and set `APP_URL` to match.

### Running the scheduler

`funds:refresh` is wired to run hourly. For evaluation you can either call
it manually (fastest) or run the scheduler in a terminal:

```bash
php artisan schedule:work
```

---

## Architecture at a glance

```
AJ Bell CDN (4 JSON endpoints)
         │
         │  parallel GET (Http::parallel macro)
         ▼
App\Http\Connectors\AjBellFundConnector
         │
         ▼
App\Actions\RefreshFunds            ─→   validates each payload through
   - pulls connector                     App\DataTransferObjects\FundPayload
   - persists in a transaction           (rejects malformed upstream shape)
         │
         ▼
MySQL  (funds + fund_documents + fund_holdings + fund_asset_allocations)
         │
         ▼
GET /api/funds, GET /api/funds/{sedol}
   - App\Http\Requests\ListFundsRequest validates query params
   - App\Filters\FundFilter maps validated input onto the query builder
   - App\Http\Resources\FundResource shapes the response
         │
         ▼
React SPA  (resources/js — Vite + TypeScript + Tailwind)
```

For a deeper dive see `docs/implementation-plan.md`.

---

## Design decisions worth knowing

A handful of non-obvious choices, each justified so you don't have to guess
what I was thinking:

**Normalised schema rather than a single JSON column.** The brief implies
real filtering ("low ongoing charge", "certain analyst rating"), so the
fields a consumer filters on need to be indexed columns. Nested blobs
(portfolio, documents) could reasonably live as JSON but normalising them
too makes the Eloquent relationships, Resources, and tests more uniform.

**Category / risk are seeded, not fetched.** The brief tells us which SEDOL
is Cautious / Balanced / Adventurous / Responsible, but the upstream JSON
doesn't carry that info. A `FundSeeder` establishes the mapping once; the
refresh Action deliberately leaves those two columns alone on every run so
the mapping survives. This separates "data owned by AJ Bell" from
"metadata owned by us".

**Prices stored + returned as strings.** `decimal:4` casts mean every
money-adjacent value round-trips as a string, so arithmetic stays in
`bcmath` territory and we never silently touch floats. The API returns
`"0.3100"`, not `0.31`.

**All external HTTP lives behind one Connector + two Http macros.**
`config/connectors.php` is the only place URLs are hard-coded;
`App\Http\Connectors\AjBellFundConnector` is the only code that talks to
the CDN. The `Http::request` and `Http::parallel` macros in
`AppServiceProvider` apply a 5-second timeout and consistent JSON handling
so individual Connectors don't need to.

**Typed exception + DTO at the fetch boundary.** `FundFetchException`
signals an all-fund failure (CDN unreachable); `InvalidFundPayloadException`
signals a per-fund shape failure. The latter is caught per-SEDOL inside the
Action so one malformed response doesn't kill the whole refresh; the former
is re-thrown so the scheduler surfaces it visibly.

**Refresh is artisan + scheduler only, no HTTP endpoint.** Keeping the
public API read-only means less surface to secure and less behaviour to
reason about. The hourly cadence in `routes/console.php` uses
`withoutOverlapping()` so a slow run doesn't double-fire.

**No React Router, no state library.** The list + detail view live on one
page — clicking a row expands an inline detail panel. Filter state round-
trips through `window.location.search` via a tiny `useFilters` hook so the
page is bookmarkable. Fewer moving parts, less to review.

**The React SPA consumes `/api/funds` like any external consumer would.**
Rather than Inertia, which would couple the SPA to Laravel's render
pipeline, a thin `app.blade.php` shell mounts the React app which then
talks to the public API via `fetch`. That keeps the API's shape honest —
"websites and apps" (per the brief) both see the same JSON.

---

## A note on the "no external access" constraint

The brief says *"the supplied solution should run locally and not require any
external access apart from the fund data hosted by AJ Bell."* Two things
are worth calling out here so you don't have to guess at the interpretation:

- **Document URLs** on each fund (Factsheet / KIID / Prospectus) point at
  `doc.morningstar.com`. These URLs are part of what AJ Bell publishes —
  our Connector stores them verbatim and the API returns them unchanged.
  Nothing in the backend or the React frontend ever fetches Morningstar;
  the URLs are only followed if a user clicks one in the UI, which is
  user-initiated navigation from outside the running app's scope.
- **Bunny Fonts** (shipped by the default Laravel 13 Vite config) are
  downloaded at build time by `laravel-vite-plugin`'s fonts helper and
  bundled into `public/build/assets/*.woff2`. Confirm by running
  `npm run build` and inspecting the output — no external font request at
  runtime.

Summary: at runtime the Laravel app talks to the AJ Bell CDN only, and the
React app talks to the Laravel app only.

---

## Testing

```bash
php artisan test        # 19 tests, PHPUnit 12 (Laravel 13 default)
npm test                # 6 tests, Vitest + React Testing Library
```

**Test database:** PHPUnit uses in-memory SQLite (configured in
`phpunit.xml`) so tests are zero-setup and millisecond-fast. The schema
has no MySQL-specific features so the SQLite / MySQL divergence risk is
negligible for this scope; happy to discuss the trade-off.

Breakdown:

| Suite | What it covers |
| --- | --- |
| `tests/Feature/Actions/RefreshFundsTest` | Happy path, seeded `category` / `risk_label` preserved across refreshes, child relationships rewritten, per-fund invalid JSON doesn't poison the run, CDN unreachable raises `FundFetchException`. |
| `tests/Feature/Api/ListFundsTest` | Pagination structure, inactive exclusion, category / min rating / max charge filters, descending sort, `per_page` honoured, invalid category / sort / per-page each return 422. |
| `tests/Feature/Api/GetFundTest` | 200 with eager-loaded documents, 404 on unknown SEDOL. |
| `resources/js/src/components/FilterSidebar.test.tsx` | Selecting a category calls `onChange`; existing filters survive a partial change; "Clear all" resets. |
| `resources/js/src/components/FundRow.test.tsx` | Headline figures render; clicking the row expands the detail panel; fallback to SEDOL when name is null (the pre-refresh state). |

---

## Project tour

```
app/
├── Actions/RefreshFunds.php               ← orchestrates a refresh run
├── Console/Commands/FundsRefreshCommand.php
├── DataTransferObjects/FundPayload.php    ← upstream-contract validation
├── Exceptions/                            ← FundFetchException, InvalidFundPayloadException
├── Filters/FundFilter.php                 ← validated input → query builder
├── Http/
│   ├── Connectors/AjBellFundConnector.php
│   ├── Controllers/Api/FundController.php
│   ├── Requests/ListFundsRequest.php
│   └── Resources/                         ← Fund + 3 child resources
├── Models/                                ← Fund, FundDocument, FundHolding, FundAssetAllocation
└── Providers/AppServiceProvider.php       ← Http::request / Http::parallel macros
config/connectors.php                      ← external endpoints
database/
├── factories/
├── migrations/
└── seeders/FundSeeder.php                 ← SEDOL → category/risk mapping
resources/
├── js/                                    ← React SPA
│   ├── app.tsx                            ← React mount point
│   ├── types.ts                           ← mirrors FundResource
│   └── src/
│       ├── App.tsx
│       ├── api/funds.ts
│       ├── components/                    ← FilterSidebar, FundList, FundRow, …
│       └── hooks/                         ← useFilters, useFunds
└── views/app.blade.php                    ← thin shell + @vite mount
routes/
├── api.php                                ← 2 routes
├── console.php                            ← scheduler entry
└── web.php                                ← serves the React shell
tests/
├── Feature/Actions/RefreshFundsTest.php
└── Feature/Api/{ListFunds,GetFund}Test.php
docs/
├── implementation-plan.md                 ← up-front design + trade-offs
└── phase-log.md                           ← what each phase added, mapped to the brief's criteria
```

---

## What I'd extend given more time

Called out so the Q&A conversation starts informed, not from scratch:

- **Point-in-time price history** — a `fund_snapshots` table captured on
  each refresh. Trivial addition; left out because the brief didn't ask.
- **OpenAPI spec** — the current API is small enough that the Resource +
  Request classes are the de facto contract, but auto-generated docs would
  be a sensible next step for real consumers.
- **HTTP response caching** — ETag / Cache-Control on `/api/funds`. For 4
  funds + hourly refresh the TTL is effectively "indefinite until refresh";
  worth having for a real deployment.
- **Automatic new-SEDOL discovery** — right now we only pull the four
  SEDOLs seeded in the DB. The brief implies the *contents* of the known
  URLs change, not that URLs appear/disappear, but a real integration might
  have an index endpoint we'd poll instead.
- **Retry/backoff on the connector** — one retry on a transient 5xx would
  be cheap resilience; skipped because the CDN is stable in practice.
- **Observability** — structured logging is in place with context (`sedol`,
  `reason`); shipping those logs somewhere (Sentry / CloudWatch) is the
  real next step.
- **Richer frontend** — React Router with a `/funds/:sedol` page, TanStack
  Query for request de-duplication and caching, a design system. The
  single-page expand-in-row shape is intentional for a tech test; a real
  product would grow beyond it.

---

## Running against the actual AJ Bell CDN

The default `.env.example` points at the CDN URL from the brief
(`AJ_BELL_FUNDS_URL=https://cdn.core3-dev.ajbbuild.uk/interview/`). No
other external access is required.
