# Public Redirect Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `GET /{code}` redirects (302) to the link's `original_url`; inactive, expired or unknown codes show one Inertia 404 page.

**Architecture:** A single-action `RedirectLinkController` resolves the link through a new `Link::active()` scope. `firstOrFail()` throws `ModelNotFoundException`, which an `Inertia::handleExceptionsUsing` callback renders as the Inertia page `errors/NotFound` with status 404, only for the `links.redirect` route.

**Tech Stack:** Laravel, Inertia v3 + Vue, Pest, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-08-public-redirect-design.md`

## Global Constraints

- Redirect is 302, never 301.
- Route is public (no `auth`), named `links.redirect`, registered **last** in `routes/web.php`, with `where('code', '[0-9A-Za-z]+')`.
- Every refusal (inactive, expired, unknown, wrong case) gives the same 404; no distinct messages.
- `code` is case-sensitive (`utf8mb4_bin`).
- Only 404s from `links.redirect` are customised; other 404s unchanged.
- Out of scope: click counters, per-reason messages, cache.
- Follow project rules: Pest feature tests, `vendor/bin/pint --dirty --format agent` after PHP changes, UI strings are English literals, Wayfinder for frontend route imports.

## Review Focus

- Link expiring exactly now (`expires_at = now()`): treated as expired (404). Test in Task 1.
- `expires_at` in the future: still redirects. Test in Task 1.
- Code with different letter case (`abc123` vs `Abc123`): 404. Test in Task 2.
- Code with characters outside the alphabet (e.g. `ab-12`): 404 via route constraint, not a 500. Test in Task 2.
- `/login`, `/dashboard`, `/links` unaffected by the catch-all route. Test in Task 2.

---

### Task 1: `Link::active()` scope

**Files:**
- Modify: `app/Models/Link.php`
- Test: `tests/Feature/LinkScopeTest.php` (create via `php artisan make:test --pest LinkScopeTest`)

**Interfaces:**
- Produces: `Link::active()` query scope (`scopeActive(Builder $query): void`), used by Task 2.

- [ ] **Step 1: Write failing tests** in `LinkScopeTest`: `active scope returns only active, non-expired links`. Create with factories: active, `inactive()`, `expired()`, active with `expires_at = now()->addDay()`, active with `expires_at = now()` (frozen via `$this->freezeTime()`). Assert `Link::active()->pluck('id')` contains exactly the first and the future-expiry link.
- [ ] **Step 2: Run** `php artisan test --compact --filter=LinkScopeTest`. Expected: FAIL (`active` undefined).
- [ ] **Step 3: Implement** `scopeActive(Builder $query): void` in `Link` (with `#[Scope]` attribute if the installed Laravel version uses it, otherwise `scopeActive`; check sibling code/docs): `is_active = true` and (`expires_at` null or `expires_at > now()`).
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit**
```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Link.php tests/Feature/LinkScopeTest.php
git commit -m "Add Link active scope"
```

### Task 2: Redirect route and controller

**Files:**
- Create: `app/Http/Controllers/RedirectLinkController.php` (via `php artisan make:controller RedirectLinkController --invokable --no-interaction`)
- Modify: `routes/web.php` (append route after the auth group, before `require settings.php` is fine only if it stays last among web routes; place it as the final route statement)
- Test: `tests/Feature/LinkRedirectTest.php` (via `php artisan make:test --pest LinkRedirectTest`)

**Interfaces:**
- Consumes: `Link::active()` from Task 1.
- Produces: route `links.redirect` (`GET {code}`), `RedirectLinkController::__invoke(string $code): RedirectResponse`.

- [ ] **Step 1: Write failing tests** in `LinkRedirectTest`:
  - `active link redirects with 302 to original_url`: `get(route('links.redirect', $link->code))->assertStatus(302)->assertRedirect($link->original_url)`.
  - `link expiring in the future still redirects`.
  - `inactive, expired and unknown codes return 404` (dataset: `inactive()`, `expired()`, unknown code `zzzzzz`) → `assertNotFound()`.
  - `code lookup is case sensitive`: link code `Abc123`, request `abc123` → 404.
  - `code outside the alphabet returns 404`: request `ab-12` → 404.
  - `catch-all route does not shadow existing pages`: guest `get('/login')` is 200; `get('/dashboard')` redirects to `route('login')`.
  Use `Link::factory()->create(['code' => ...])` for fixed codes.
- [ ] **Step 2: Run** `php artisan test --compact --filter=LinkRedirectTest`. Expected: FAIL (route not defined).
- [ ] **Step 3: Implement** the controller (`__invoke(string $code): RedirectResponse`, `Link::active()->where('code', $code)->firstOrFail()`, `redirect()->away($link->original_url)`) and the route `Route::get('{code}', RedirectLinkController::class)->where('code', '[0-9A-Za-z]+')->name('links.redirect');` as the last route in `routes/web.php`.
- [ ] **Step 4: Run** the same command. Expected: PASS (404 tests pass with default error page; Inertia rendering is Task 3).
- [ ] **Step 5: Commit**
```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/RedirectLinkController.php routes/web.php tests/Feature/LinkRedirectTest.php
git commit -m "Add public redirect route for short links"
```

### Task 3: Inertia 404 page

**Files:**
- Create: `resources/js/pages/errors/NotFound.vue`
- Modify: `resources/js/app.ts` (layout resolver), `app/Providers/AppServiceProvider.php` (exception handling)
- Test: `tests/Feature/LinkRedirectTest.php`

**Interfaces:**
- Consumes: route `links.redirect` (Task 2).
- Produces: Inertia component `errors/NotFound`, status 404, rendered only when the failing route is `links.redirect`.

- [ ] **Step 1: Write failing test** in `LinkRedirectTest`: `refused redirects render the NotFound page with 404`: for an inactive link, `get(route('links.redirect', $code))->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('errors/NotFound'))`. Also `other 404s are not rendered as NotFound`: `get('/no/such/page')->assertNotFound()` and assert the response is not an Inertia page (`assertDontSee('errors\/NotFound')` or equivalent for the installed Inertia testing helpers).
- [ ] **Step 2: Run** `php artisan test --compact --filter=LinkRedirectTest`. Expected: new tests FAIL.
- [ ] **Step 3: Implement** the 404 rendering with Inertia v3's `Inertia::handleExceptionsUsing(callable $callback)` (inertia-laravel v3.5.1), registered in `AppServiceProvider::boot()` (it needs the bound exception handler). The callback receives `Inertia\ExceptionResponse $response` and returns `$response->render('errors/NotFound')` when `$response->request->routeIs('links.redirect') && ! $response->request->expectsJson() && $response->statusCode() === 404`; otherwise it returns `null` (the original response is kept). `ExceptionResponse::toResponse()` shares the `HandleInertiaRequests` props and keeps the original status code, so no manual `setStatusCode(404)`. Verified in vendor: `ModelNotFoundException` is converted to `NotFoundHttpException` by `Handler::prepareException()` before render callbacks run, and the callback is not limited to Inertia requests, so the route and JSON guards are required. Do not use `$exceptions->render(...)` in `bootstrap/app.php` for this.
- [ ] **Step 4: Implement** `errors/NotFound.vue` modelled on `resources/js/pages/auth/VerifyEmail.vue`: `defineOptions({ layout: { title: 'Link not found', description: 'This link does not exist or is no longer available.' } })`, `<Head title="Link not found" />`, a `TextLink`/`Button` to `/` using the Wayfinder route for `front_app` (`@/routes`). In `resources/js/app.ts` layout resolver add `name.startsWith('errors/')` → `AuthLayout` (public page must not use the sidebar `AppLayout`).
- [ ] **Step 5: Run** `npm run build`, then `php artisan test --compact --filter=LinkRedirectTest`. Expected: build OK, PASS.
- [ ] **Step 6: Commit**
```bash
vendor/bin/pint --dirty --format agent
git add resources/js/pages/errors/NotFound.vue resources/js/app.ts app/Providers/AppServiceProvider.php tests/Feature/LinkRedirectTest.php
git commit -m "Render Inertia 404 page for refused short links"
```

### Task 4: E2E flow

**Files:**
- Create: `tests/e2e/redirect.spec.ts`

**Interfaces:**
- Consumes: routes and page from Tasks 2-3. Reuse the `run()` tinker helper pattern from `tests/e2e/links.spec.ts` (no auth state needed: `test.use({ storageState: undefined })`).

- [ ] **Step 1: Write** `redirect.spec.ts` with two tests, creating links via tinker with unique codes and cleaning them in `afterAll`:
  - `active short link redirects to the target`: link with `original_url` `https://example.com/`; `page.goto('/<code>')` then `await expect(page).toHaveURL('https://example.com/')` (route-intercept the external host with `page.route` to avoid real network if needed).
  - `inactive short link shows the not found page`: `goto` → `expect(page.getByText('This link does not exist or is no longer available.')).toBeVisible()` and response status 404.
- [ ] **Step 2: Run** `npx playwright test tests/e2e/redirect.spec.ts`. Expected: PASS (needs the app server per `playwright.config.ts`).
- [ ] **Step 3: Commit**
```bash
git add tests/e2e/redirect.spec.ts
git commit -m "Add E2E tests for public redirect"
```

### Task 5: Final verification

- [ ] **Step 1:** Run `php artisan test --compact tests/Feature/LinkRedirectTest.php tests/Feature/LinkScopeTest.php tests/Feature/LinkCrudTest.php tests/Feature/AuthRedirectTest.php`. Expected: all PASS.
- [ ] **Step 2:** Ask the user to run the full suite `php artisan test --compact` (project rule).
