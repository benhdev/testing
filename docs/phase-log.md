# Phase Log

A chronological record of what each implementation phase added, so the
finished project can be audited against the original brief
(`~/Downloads/AJBL - Senior Full Stack Engineer - Tech Task.pdf`) and the
implementation plan (`implementation-plan.md`).

Format per phase:

- **Scope** — the one-line goal.
- **Files changed** — the actual deliverables.
- **Key choices** — anything a reviewer might reasonably question, with the
  answer pre-prepared.
- **Brief coverage** — which of the six evaluation criteria this phase touches
  (Clarity, Standardisation, Frameworks, Performance, Error handling, Tests).

The brief's criteria are referenced by a one-letter code:
`C` Clarity · `S` Standardisation · `F` Frameworks · `P` Performance ·
`E` Error handling · `T` Tests.

---

## Phase 1 — Scaffold

**Scope:** Lay the foundation (code style, external-service config, env
defaults, HTTP macros) with zero application logic.

**Files changed:**

- `pint.json` *(new)* — single key `"preset": "laravel"`. PSR-12 + Laravel
  defaults, 4-space indent.
- `config/connectors.php` *(new)* — single entry
  `aj_bell_funds => env('AJ_BELL_FUNDS_URL', <CDN default>)`. All external
  URLs live here so Connectors never hard-code them.
- `.env.example` — flipped `DB_CONNECTION` from `sqlite` to `mysql`,
  uncommented the full `DB_*` block with `DB_DATABASE=technical_test`, added
  `AJ_BELL_FUNDS_URL`.
- `app/Providers/AppServiceProvider.php` — added `registerHttpMacros()`
  called from `boot()`:
  - `Http::request(via, uri, method='get', body=[], throw=true, headers=[])`
    — endpoint lookup via `config('connectors.'.$via)`, `->timeout(5)`, returns
    `$response->json()`.
  - `Http::parallel(requests, headers=[], throw=true)` — pools multiple calls,
    returns a `Collection` keyed by each request's `key`.

**Key choices:**

- **No `X-Api-Key` / bearer header** in the macros — benhdev's doc example
  includes them for internal microservice auth, but we're hitting a public
  CDN. Omitted deliberately.
- **Required params before optional** in the macro signatures. The benhdev doc
  has `(string $method = 'get', string $via, …)` which triggers a PHP 8
  deprecation. Reordered so the signatures are compliant; call sites still
  use named args per the "Named Function Parameters" rule.
- **`timeout(5)`** baked into every outbound call. Low cost, high defence
  against a hung CDN silently blocking the refresh.

**Brief coverage:** `S` (Pint, named args, convention-faithful macros),
`F` (Laravel 13, Http client), `P` (parallel pooling ready to use),
`E` (timeout defence).

**Verification:** `php -l` clean, `vendor/bin/pint --test` passes,
`php artisan list` boots the service provider without error.

---

## Phase 2 — DB layer

**Scope:** Model the fund domain in MySQL — four tables, four Eloquent
models with relationships + casts, four factories, and the `FundSeeder` that
establishes the SEDOL → category/risk mapping.

**Files changed:**

- Four migrations:
  - `*_create_funds_table.php` — `sedol` unique, `category` indexed, `risk_label`
    nullable, upstream-sourced scalars all nullable (`name`,
    `last_price` decimal(14,4), `ongoing_charge` decimal(6,4) indexed,
    `analyst_rating` indexed, etc.), `is_active` boolean default true indexed,
    `fetched_at` timestamp nullable, timestamps.
  - `*_create_fund_documents_table.php` — `fund_id` FK cascade-on-delete,
    `type`, nullable `external_id`, `url` varchar(2000).
  - `*_create_fund_holdings_table.php` — `fund_id` FK, `position` tinyint,
    `name`, `weighting` decimal(8,5).
  - `*_create_fund_asset_allocations_table.php` — `fund_id` FK, `label`,
    `value` decimal(8,5).
- `app/Models/Fund.php` — `$fillable`, casts (`decimal:4` on price/charge so
  values come back as strings for bcmath), three `hasMany` relationships
  (`holdings` ordered by `position`), `getRouteKeyName() => 'sedol'` so URLs
  are `/api/funds/BYW8RV9`.
- `app/Models/FundDocument.php`, `FundHolding.php`, `FundAssetAllocation.php`
  — `belongsTo(Fund)`, `$fillable`, decimal casts on `weighting`/`value`.
- `database/factories/FundFactory.php` — realistic random funds, plus
  `unfetched()` state (upstream fields null, mimics post-seed pre-refresh) and
  `inactive()` state (for deactivate-missing tests).
- Child factories for each related table.
- `database/seeders/FundSeeder.php` — `firstOrCreate` the four known SEDOLs
  with their `{category, risk_label, is_active=true}`.
- `database/seeders/DatabaseSeeder.php` — calls `FundSeeder`; removed the
  stock `User::factory()` since this app has no users.

**Key choices:**

- **Nullable upstream columns** rather than placeholder strings on seed.
  `fetched_at IS NULL` is the "never refreshed" signal.
- **`cascadeOnDelete`** on all three child FKs — no orphan rows.
- **`$fillable`** over `$guarded = []` — explicit, matches benhdev examples.
- **Route model binding via `sedol`** — the SEDOL is the stable public key;
  internal IDs should not leak into URLs.
- **`decimal:N` casts** everywhere money-adjacent — returns strings, keeps all
  arithmetic in bcmath territory.
- **`varchar(5000)` on `objective`** — long prose, but still varchar per
  benhdev migration convention (text column type avoided).

**Brief coverage:** `C` (clean relationships, small models), `S` (benhdev
migration + naming conventions: snake_case, UK spelling, indexed filter
columns), `F` (Eloquent, factories, Laravel 13 migration syntax).

**Verification:** `php -l` clean on all files. Pint auto-fixed two cosmetic
items (import ordering + fully-qualified docblock types), then clean.
Smoke-tested by running `migrate:fresh --seed` against an ephemeral SQLite
file — got the four expected rows with correct SEDOL/category/risk mapping.

---

## Phase 3 — Fetching + processing

**Scope:** Pull upstream fund JSON, validate it, and apply it to the DB —
behind tests. This is where the audit's error-handling tightening lands:
custom exception types, DTO contract validation, a connector timeout.

**Files changed:**

- `app/Exceptions/FundFetchException.php` *(new)* — raised when the CDN as a
  whole is unreachable; re-thrown so the scheduler surfaces failure.
- `app/Exceptions/InvalidFundPayloadException.php` *(new)* — raised when a
  single fund's JSON doesn't match the expected shape. Carries the offending
  SEDOL as a public readonly property for logging context.
- `app/DataTransferObjects/FundPayload.php` *(new)* — immutable DTO built via
  `FundPayload::fromArray($raw, $sedol)` which validates upstream contract,
  normalises the nested shape, and derives `position` for the top-10 holdings.
  Rejects missing `data`, `quote`, `marketCode`, `name`.
- `app/Http/Connectors/AjBellFundConnector.php` *(new)* — static `all($sedols)`
  uses the `Http::parallel` macro with `throw: true`. Returns a Collection of
  decoded JSON keyed by SEDOL.
- `app/Actions/RefreshFunds.php` *(new)* — handle() runs the full orchestration:
  pluck seeded SEDOLs, call connector (wraps Throwable in FundFetchException),
  per fund attempt `FundPayload::fromArray` (catch InvalidFundPayloadException
  per fund, log warning with sedol+reason, continue), persist inside a DB
  transaction, return `{ok, invalid}` summary. Only upstream columns are
  updated — `category` / `risk_label` are untouched so seeded values survive.
  Child collections are delete-and-recreate (tiny sets, keeps the code simple).
- `app/Console/Commands/FundsRefreshCommand.php` *(stub replaced)* —
  `funds:refresh` command, DI'd `RefreshFunds` action, returns SUCCESS/FAILURE,
  surfaces the summary to the console.
- `tests/Feature/Actions/RefreshFundsTest.php` *(new)* — five tests:
  1. Populates fund data from upstream (asserts cast strings via `decimal:4`).
  2. Preserves `category` / `risk_label` on refresh.
  3. Rewrites child relationships (replaces stale documents/holdings/allocations).
  4. Skips invalid fund but continues with others (partial-failure resilience).
  5. Total fetch failure raises `FundFetchException`.

**Key choices:**

- **One DTO, not four.** `FundPayload` carries its three child collections as
  typed arrays rather than nested DTOs. Less ceremony; the shape is private to
  the refresh flow so there's no API contract leakage risk.
- **Children stored as arrays-of-arrays, keyed with snake_case** (`external_id`,
  not `externalId`) so they drop straight into `$fund->documents()->createMany()`
  without an intermediate map.
- **Connector is all-or-nothing** (`Http::parallel(throw: true)`) — if one HTTP
  call fails, the whole run fails loudly. Partial resilience sits at the DTO
  layer (bad JSON for one SEDOL doesn't kill the others), not the HTTP layer.
  This maps a clear failure mode: "HTTP broke" vs "shape broke".
- **Delete-and-recreate for child rows** rather than diff-and-upsert. The sets
  are ≤10 rows, the whole thing is in a transaction, so the upside of a
  more elaborate diff doesn't justify the code.
- **No `deactivateMissing` in the Action** — the Connector always fetches the
  full seeded SEDOL list, and the only way a SEDOL "disappears upstream" is a
  404 which currently throws at the HTTP layer. Prepared for Q&A: a real
  integration would catch 404 specifically and flip `is_active=false`.
- **`InvalidFundPayloadException extends InvalidArgumentException`** — natural
  type hierarchy; still catchable by its concrete class for precise handling.
- **Structured logging** — every `Log::warning`/`Log::error` carries context
  (`sedol`, `reason`). Addresses the audit's "observability is thin" note.

**Brief coverage:** `C` (small focused classes, Action pattern), `S` (Connector
+ Action + DTO follow benhdev patterns; typed everywhere; UK spelling in
column names continues from phase 2), `F` (Http::parallel pooling, Eloquent
transactions), `P` (parallel fetch, 5-second timeout per leg), `E` (typed
exceptions, upstream contract validation, structured log context, per-fund
resilience + atomic total-failure), `T` (5 feature tests covering happy path,
seed preservation, child-rewrite, partial failure, and fatal failure).

**Verification:** `php artisan test` passes 7/7 (2 Laravel stock + 5 new).
Pint auto-fixed one import-ordering item, then clean.

---

## Phase 4 — Scheduler wiring

**Scope:** Fire `funds:refresh` hourly via Laravel's scheduler.

**Files changed:**

- `routes/console.php` — added `Schedule::command('funds:refresh')->hourly()
  ->withoutOverlapping()->runInBackground()` plus a block comment explaining
  when it actually fires (needs `schedule:work` locally or cron in prod).

**Key choices:**

- **`withoutOverlapping`** — a slow / stuck run won't double-fire the next
  hour. Cheap resilience.
- **`runInBackground`** — if we add more scheduler entries later, they don't
  block waiting for the fund refresh. No downside today.
- **Not every 5 / 15 minutes** — upstream fund data doesn't change
  intraminute. Hourly is honest about how fast the data actually moves and
  keeps CDN traffic low.

**Brief coverage:** `F` (Laravel scheduler), `E` (overlap guard is a form of
error-mode defence).

**Verification:** `php artisan schedule:list` shows the entry registered with
the correct cron expression. Pint clean.

---

## Phase 5 — API

**Scope:** Expose `GET /api/funds` (list + filter + sort + paginate) and
`GET /api/funds/{sedol}` (single fund), fully validated and tested.

**Files changed:**

- `bootstrap/app.php` — added `api: __DIR__.'/../routes/api.php'` to
  `withRouting()` (Laravel 11+ removed the API route file by default). Chose
  not to run `php artisan install:api` since that pulls Sanctum, which this
  public API doesn't need.
- `routes/api.php` *(new)* — two routes grouped under `funds`:
  ```php
  Route::prefix('funds')->group(function () {
      Route::get('', [FundController::class, 'index']);
      Route::get('{fund}', [FundController::class, 'show']);
  });
  ```
- `app/Http/Requests/ListFundsRequest.php` *(new)* — validates `category`,
  `risk`, `min_analyst_rating`, `max_ongoing_charge`, `sort`, `per_page`.
  Uses `Rule::in(...)` so unknown values 422 with a clear field error.
- `app/Filters/FundFilter.php` *(new)* — maps validated filters onto the
  Eloquent builder. Enforces `is_active = true` by default. Sort syntax:
  `?sort=-analyst_rating` for descending.
- `app/Http/Resources/FundResource.php` *(new)* — full fund shape, including
  `whenLoaded()` wrappers for the three child relationships so unloaded
  relations don't trigger extra queries.
- `app/Http/Resources/FundDocumentResource.php`, `FundHoldingResource.php`,
  `FundAssetAllocationResource.php` *(new)* — small per-child resources.
- `app/Http/Controllers/Api/FundController.php` *(new)* — `index()`
  eager-loads the three child relationships then paginates; `show()` relies
  on route model binding via `Fund::getRouteKeyName() = 'sedol'`.
- `tests/Feature/Api/ListFundsTest.php` *(new)* — 10 tests covering:
  pagination structure, inactive exclusion, category filter, min rating
  filter, max charge filter, descending sort, `per_page` honoured, invalid
  filter/sort/per-page values each return 422.
- `tests/Feature/Api/GetFundTest.php` *(new)* — 200 with fund + eager-loaded
  documents, 404 on unknown SEDOL.

**Key choices:**

- **No `install:api`** — Sanctum would be noise for a local, read-only
  public API. Added the route file directly instead.
- **Default sort = `name` ASC.** Honest default for an alphabetised directory.
- **`?sort=-col` convention** — mirrors JSON:API / Spring style; easier to
  explain than two separate `sort_by` + `sort_dir` params.
- **`is_active=true` baked into the filter**, not exposed as an input.
  Reviewers viewing the API shouldn't see empty-shell funds that haven't been
  refreshed yet.
- **Eager loading in the controller, not globally.** The index and show
  endpoints need the children; nothing else does, so no `$with` on the model.
  Avoids pulling children into every incidental `Fund::query()` elsewhere.
- **FundFilter is DI-free and takes the validated array by constructor.**
  Trivially unit-testable; no need to involve the container.
- **Per-page cap of 100** in the Request. Prevents a malicious client
  requesting an unbounded page.

**Brief coverage:** `C` (Controller is ~10 real lines; filter is pure
mapping), `S` (named-args-friendly API, snake_case params, routes grouped
with `->prefix()` per benhdev routing doc, Resource for every response per
responses doc), `F` (Laravel FormRequest + Rule + Resource + route model
binding), `P` (eager loading via `->with(...)`, indexed filter columns from
phase 2, `per_page` cap, default pagination 15), `E` (422 with field errors
for every invalid input, 404 via route model binding, `shouldRenderJsonWhen`
already configured for `/api/*`), `T` (12 feature tests).

**Verification:** `php artisan test` → 19/19 passing (2 stock + 5 refresh +
12 API), 64 assertions, 249ms. Pint auto-fixed four docblock import items,
then clean. `php artisan route:list` confirms both API endpoints registered.

---

## Phase 6 — React frontend

**Scope:** A single-page React + TypeScript SPA that consumes `/api/funds`
the same way any external web consumer would — no Inertia, no SPA router,
no state library. Vitest + React Testing Library cover the components the
audit called out as a gap.

**Dependencies added:**

- Runtime: `react`, `react-dom`
- Dev: `@types/react`, `@types/react-dom`, `@vitejs/plugin-react`,
  `typescript`, `vitest`, `@testing-library/react`,
  `@testing-library/user-event`, `@testing-library/jest-dom`, `jsdom`

**Files changed:**

- `tsconfig.json` *(new)* — strict, bundler resolution, JSX transform,
  Vitest + jest-dom types included.
- `vite.config.js` — added React plugin, switched entry from `app.js` to
  `app.tsx`, inline `test` config (jsdom env, globals, setup file).
- `package.json` — added `test` / `test:watch` scripts; React + Vitest
  deps.
- `resources/js/app.js` *(deleted)* — replaced by `app.tsx`.
- `resources/js/app.tsx` — React entry (createRoot + StrictMode).
- `resources/js/test-setup.ts` — imports `@testing-library/jest-dom/vitest`
  to add DOM matchers to Vitest's expect.
- `resources/js/types.ts` — TS types mirroring `FundResource`. No codegen;
  the mapping is small enough to maintain by hand and the error surface
  from a mismatch is small.
- `resources/js/src/App.tsx` — top-level layout; wires `useFilters` into
  `FilterSidebar` + `FundList`.
- `resources/js/src/api/funds.ts` — typed `listFunds(filters)` fetch
  wrapper; drops undefined / empty values so the URL stays tidy.
- `resources/js/src/hooks/useFilters.ts` — reads filter state from
  `window.location.search` and writes it back with `replaceState` (not
  `pushState`, so every tweak doesn't spam back-stack).
- `resources/js/src/hooks/useFunds.ts` — fetch-on-filter hook with
  cancellation guard; `JSON.stringify(filters)` as dep key (short comment
  explaining why).
- `resources/js/src/components/FilterSidebar.tsx` — category, risk, min
  rating, max charge, sort, clear-all button.
- `resources/js/src/components/FundList.tsx` — loading / error / empty /
  table states; shows "Showing N of M".
- `resources/js/src/components/FundRow.tsx` — clickable row; `aria-expanded`;
  renders `FundDetailPanel` inline when expanded.
- `resources/js/src/components/FundDetailPanel.tsx` — objective, asset
  allocation bars, top-10 holdings, document links.
- `resources/js/src/components/EmptyState.tsx` — "No funds match" panel.
- `resources/js/src/components/FilterSidebar.test.tsx` — 3 tests: selecting
  a category calls onChange, existing filters survive a partial change,
  clear button resets.
- `resources/js/src/components/FundRow.test.tsx` — 3 tests: headline
  renders, click expands + shows detail, SEDOL fallback when name is null.
- `routes/web.php` — `/` now returns `view('app')`.
- `resources/views/app.blade.php` *(new)* — thin shell:
  `@vite(['resources/css/app.css', 'resources/js/app.tsx'])` + `#app` mount.

**Key choices:**

- **No React Router.** The list + detail share one page via inline-expand
  rows. Fewer dependencies, less URL state to manage, and the detail panel
  is close enough to the row it belongs to that routing felt unnecessary.
- **No TanStack Query.** Two small hooks (`useFilters`, `useFunds`) cover
  the fetch needs. If filter-change caching or revalidation matter later
  Query is an obvious next step.
- **URL as source of truth for filters.** `?category=...` round-trips
  through `useFilters`, so bookmarks and shares work.
- **Thin Blade shell over Inertia.** The brief is about an API being
  consumed; serving the React page via `@vite` + a mount point makes it
  obvious that the SPA hits the same public `/api/funds` any consumer
  would.
- **Snake_case field names in the TS types** mirror the `FundResource`
  output rather than camelCase-ing on the client. Keeps the contract
  readable across the boundary.
- **Tailwind classes directly in components** — no abstractions, no
  variant soup. Readable at first sight for a reviewer.
- **One `userEvent` import style** (`@testing-library/user-event`)
  consistently in both tests; no `fireEvent` mixing.

**Brief coverage:** `C` (components are tiny — the largest is the sidebar
form), `S` (no mix of inline CSS vs Tailwind; one hook pattern; types mirror
the backend resource shape 1:1), `F` (React 19, Vite 8, Tailwind v4, Vitest
5, RTL), `P` (no router / state lib overhead; hooks only re-run on filter
change via memoised dep key; `fetch` cancellation guard prevents stale data
races), `E` (fetch errors render a dedicated error panel; empty state when
no results), `T` (6 Vitest tests covering the two most behaviour-heavy
components).

**Verification:**
`npm test` → 6/6 passing, ~900ms.
`npx tsc --noEmit` → clean.
`php artisan test` → 19/19 still passing (no PHP regressions).
`npm run build` → succeeds, 229 KB JS (71 KB gzip) / 56 KB CSS (12 KB gzip).
Pint clean.

---

## Phase 7 — README

**Scope:** Write the project README — the first thing a reviewer opens.
Deliberately given its own phase (not a bullet in phase 8) because it's
the primary vehicle for showing taste + judgement outside the code itself.

**Files changed:**

- `README.md` — replaced the stock Laravel README with a project-specific
  one.

**Shape:**

1. One-paragraph pitch + link to `docs/implementation-plan.md` and
   `docs/phase-log.md` for depth.
2. **Prerequisites** + **Quick start** (6 copy-pasteable commands from
   clone to running app).
3. **Running the scheduler** — flags the "needs `schedule:work`" caveat
   the audit called out.
4. **Architecture at a glance** — a smaller version of the plan's diagram,
   enough to orient without reading the plan.
5. **Design decisions worth knowing** — seven short paragraphs, each
   framed as "<decision>: <why>":
   - Normalised schema not JSON column
   - Category / risk seeded not fetched
   - Prices as strings + bcmath
   - One Connector + two Http macros
   - Typed exception + DTO at the fetch boundary
   - Refresh is artisan + scheduler only
   - No React Router, no state library
   - React SPA consumes `/api/funds` like any external consumer
6. **Testing** — PHP + JS commands, per-suite table.
7. **Project tour** — annotated file tree.
8. **What I'd extend given more time** — the non-goals reframed as a
   roadmap; prepares the Q&A conversation.
9. **Running against the actual AJ Bell CDN** — makes clear the default
   `.env.example` is already wired.

**Key choices:**

- **No name-drop of benhdev / Capsil / previous employers.** The README
  describes patterns on their own merits (Actions, Connectors, Resources)
  rather than as "company X conventions" — keeps it portable and honest.
- **Design decisions section is prose, not bullets.** Each decision gets
  enough room to explain *why* without turning into a wall of text. Bullets
  risk reading as a checklist; paragraphs read as a thought process.
- **Explicit test-DB caveat** flagged under Testing. Doesn't hide the
  SQLite-in-tests choice — surfaces it as an intentional trade-off.
- **"What I'd extend"** is a single flat list, not a priority ranking. In
  Q&A the reviewer picks what they want to dig into.
- **File tour is annotated**, not just a raw `tree` dump — each path has a
  one-line role.

**Brief coverage:** `C` (makes the solution's shape clear to another
engineer, which is the explicit evaluation line from the brief), `S` (
the file structure + convention references document the standards used).

**Verification:** Markdown preview looks clean; links to
`docs/implementation-plan.md` and `docs/phase-log.md` resolve to existing
files.

---

## Phase 8 — Final polish

**Scope:** Run Pint across the whole codebase, verify all test suites green,
and do one end-to-end smoke run against the real CDN to confirm the full
stack works against production-shaped data.

**Changes:** None — this phase is verification only.

**Verification:**

| Check | Result |
| --- | --- |
| `vendor/bin/pint` | Passed — nothing to fix |
| `php artisan test` | **19/19 passing**, 64 assertions, 305 ms |
| `npx tsc --noEmit` | Clean |
| `npm test` (Vitest) | **6/6 passing**, 858 ms |
| `npm run build` | Succeeded — 229 KB JS (71 KB gzip), 56 KB CSS (12 KB gzip), 129 ms |
| End-to-end smoke (SQLite + real CDN) | **4/4 funds fetched**, categories preserved, prices stored as strings. Names match the brief (VT AJ Bell Cautious / Balanced / Adventurous I Acc + VT AJ Bell Responsible Gr I GBP Acc). |

**Smoke-run output:**

```
Refresh complete. Successful: 4. Invalid: 0.

sedol    name                                  category     risk_label    rating  charge  price
BYW8RV9  VT AJ Bell Cautious I Acc             growth       cautious      4       0.3100  118.5610
BYW8RX1  VT AJ Bell Balanced I Acc             growth       balanced      4       0.3100  146.5880
BYW8VG2  VT AJ Bell Adventurous I Acc          growth       adventurous   4       0.3100  168.2020
BN0S2V9  VT AJ Bell Responsible Gr I GBP Acc   responsible  responsible   4       0.4500  1.2014
```

---

## Final coverage against the brief's evaluation criteria

A single table mapping every criterion to the phase(s) that landed it, so a
reviewer can trace any criterion from the brief to a specific piece of
work.

| Criterion | Phases | Where it lives |
| --- | --- | --- |
| **Clarity + simplicity** | 1 · 2 · 3 · 5 · 6 | Pint + 4-space indent (1); small focused models (2); Action/Connector/DTO separation (3); skinny controller + Filter (5); tiny React components (6) |
| **Standardisation** | 1 · 2 · 3 · 5 · 6 | Pint preset (1); naming + UK spelling + indexed filter columns (2); DTO + exception types + Action pattern (3); FormRequest + Resource for every response (5); TS types mirror server shape 1:1 (6) |
| **Relevant frameworks / technologies** | 1 · 2 · 3 · 4 · 5 · 6 | Laravel 13 (1); Eloquent + migrations (2); Http macros + pooled parallel requests (3); Laravel scheduler (4); FormRequest + Resource + route model binding (5); React 19 + Vite 8 + TypeScript + Tailwind v4 + Vitest (6) |
| **Performance** | 1 · 3 · 5 | 5-second timeout on every outbound call (1); `Http::parallel` for the refresh (3); eager loading + indexed filter columns + per_page cap (5) |
| **Error handling** | 1 · 3 · 5 | Connector throws by default (1); FundFetchException for total failures, InvalidFundPayloadException for per-fund, structured log context, DB transactions on persist (3); FormRequest returns 422 for every bad input, 404 via route model binding, `shouldRenderJsonWhen` for API paths (5) |
| **Tests** | 3 · 5 · 6 | 5 feature tests on the refresh Action (3); 12 feature tests on the API (5); 6 Vitest + RTL tests on React components (6). Total: **19 PHP + 6 JS = 25 tests.** |

---

**End of log.** The project is ready for submission.

---

## Addendum — Laravel Valet / Herd compatibility

**Scope:** Verify nothing in the stack blocks serving the app through
Laravel Valet or Herd (macOS dev envs that auto-serve Laravel projects at
`<directory>.test`), and document the setup in the README.

**Code changes:** None. Verified the following were already Valet-compatible:

- `APP_URL` is read from the environment only via `env()` in config files
  (`config/app.php`, `config/filesystems.php`, `config/mail.php`) — no code
  anywhere else references it.
- No hard-coded `localhost:8000` or `127.0.0.1:8000` anywhere.
- `vite.config.js` has no explicit `server.host`, so defaults play nicely
  with `laravel-vite-plugin`'s auto-detection of the Laravel host.
- React uses relative URLs (`/api/funds`) so the frontend is host-agnostic.

**Documentation changes:**

- `README.md` — added a "Serving via Laravel Valet / Herd (optional)"
  subsection under Quick Start, covering `valet park`, the `APP_URL` tweak,
  and the Vite dev server coexistence. Also mentions `valet secure` for
  HTTPS.

---

## Addendum — "no external access" constraint, documented

**Scope:** The AJ Bell JSON includes document URLs pointing at
`doc.morningstar.com` (Factsheet / KIID / Prospectus PDFs, which AJ Bell
sources via Morningstar's doc CDN). Worth confirming this isn't a
violation of the brief's "runs locally" rule and recording the reasoning.

**Reasoning:** At runtime the Laravel app only talks to the AJ Bell CDN;
the React app only talks to the Laravel app. The Morningstar URLs are
part of the data AJ Bell publishes — our app stores them as strings, the
API returns them as strings, the UI renders them as `<a>` tags. A user
clicking a link opens Morningstar in their own browser, which is outside
the running app's scope and user-initiated. Not a violation on a
reasonable reading of the constraint.

Also verified Bunny Fonts (shipped by Laravel 13's default Vite config)
are downloaded at `npm run build` time and bundled into `public/build/
assets/*.woff2` — no runtime external request.

**Documentation changes:**

- `README.md` — new "A note on the 'no external access' constraint"
  subsection positioned before Testing. Addresses both the Morningstar
  and Bunny Fonts cases explicitly, so a strict-reading reviewer has the
  answer in front of them without needing to ask in the Q&A.
