# Publiczny redirect: plan implementacji

> **Dla agentów wykonawczych:** WYMAGANY SUB-SKILL: użyj superpowers:subagent-driven-development (rekomendowane) lub superpowers:executing-plans, żeby realizować plan zadanie po zadaniu. Kroki mają checkboxy (`- [ ]`).

**Cel:** `GET /{code}` przekierowuje (302) na `original_url` linku; link nieaktywny, wygasły lub nieistniejący pokazuje jedną stronę 404 w Inertia.

**Architektura:** Jednoakcyjny `RedirectLinkController` znajduje link przez nowy scope `Link::active()`. `firstOrFail()` rzuca `ModelNotFoundException`, który callback `Inertia::handleExceptionsUsing` renderuje jako stronę Inertia `errors/NotFound` ze statusem 404, tylko dla trasy `links.redirect`.

**Stack:** Laravel, Inertia v3 + Vue, Pest, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-08-public-redirect-design.md`

## Ograniczenia globalne

- Redirect to 302, nigdy 301.
- Trasa jest publiczna (bez `auth`), nazywa się `links.redirect`, jest rejestrowana **na końcu** `routes/web.php`, z `where('code', '[0-9A-Za-z]+')`.
- Każda odmowa (nieaktywny, wygasły, nieistniejący, zła wielkość liter) daje ten sam 404, bez osobnych komunikatów.
- `code` rozróżnia wielkość liter (`utf8mb4_bin`).
- Dostosowujemy tylko 404 z `links.redirect`; pozostałe 404 bez zmian.
- Poza zakresem: liczniki kliknięć, komunikaty per powód, cache.
- Zasady projektu: testy Pest, `vendor/bin/pint --dirty --format agent` po zmianach PHP, teksty UI jako angielskie literały, Wayfinder do importu tras we froncie.
- Specyfikacje i plany piszemy po polsku.

## Fokus przeglądu

- Link wygasający dokładnie teraz (`expires_at = now()`): traktowany jako wygasły (404). Test w zadaniu 1.
- `expires_at` w przyszłości: nadal przekierowuje. Test w zadaniu 1.
- Kod z inną wielkością liter (`abc123` vs `Abc123`): 404. Test w zadaniu 2.
- Kod ze znakami spoza alfabetu (np. `ab-12`): 404 przez ograniczenie trasy, nie 500. Test w zadaniu 2.
- `/login`, `/dashboard`, `/links` nie są przesłaniane przez trasę catch-all. Test w zadaniu 2.

---

### Zadanie 1: scope `Link::active()`

**Pliki:**

- Zmiana: `app/Models/Link.php`
- Test: `tests/Feature/LinkScopeTest.php` (utworzyć przez `php artisan make:test --pest LinkScopeTest`)

**Interfejsy:**

- Dostarcza: scope `Link::active()` (`scopeActive(Builder $query): void`), używany w zadaniu 2.

- [ ] **Krok 1: Napisz testy, które padają** w `LinkScopeTest`: `active scope returns only active, non-expired links`. Utwórz fabrykami: aktywny, `inactive()`, `expired()`, aktywny z `expires_at = now()->addDay()`, aktywny z `expires_at = now()` (czas zamrożony przez `$this->freezeTime()`). Asercja: `Link::active()->pluck('id')` zawiera dokładnie pierwszy link i link z przyszłym `expires_at`.
- [ ] **Krok 2: Uruchom** `php artisan test --compact --filter=LinkScopeTest`. Oczekiwane: FAIL (brak `active`).
- [ ] **Krok 3: Zaimplementuj** `scopeActive(Builder $query): void` w `Link` (z atrybutem `#[Scope]`, jeśli zainstalowana wersja Laravela tak robi, w przeciwnym razie `scopeActive`; sprawdź sąsiedni kod i dokumentację): `is_active = true` oraz (`expires_at` null lub `expires_at > now()`).
- [ ] **Krok 4: Uruchom** to samo polecenie. Oczekiwane: PASS.
- [ ] **Krok 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Link.php tests/Feature/LinkScopeTest.php
git commit -m "Add Link active scope"
```

### Zadanie 2: trasa i kontroler redirectu

**Pliki:**

- Utworzenie: `app/Http/Controllers/RedirectLinkController.php` (przez `php artisan make:controller RedirectLinkController --invokable --no-interaction`)
- Zmiana: `routes/web.php` (trasa jako ostatnia instrukcja trasy)
- Test: `tests/Feature/LinkRedirectTest.php` (przez `php artisan make:test --pest LinkRedirectTest`)

**Interfejsy:**

- Używa: `Link::active()` z zadania 1.
- Dostarcza: trasę `links.redirect` (`GET {code}`), `RedirectLinkController::__invoke(string $code): RedirectResponse`.

- [ ] **Krok 1: Napisz testy, które padają** w `LinkRedirectTest`:
    - `active link redirects with 302 to original_url`: `get(route('links.redirect', $link->code))->assertStatus(302)->assertRedirect($link->original_url)`.
    - `link expiring in the future still redirects`.
    - `inactive, expired and unknown codes return 404` (dataset: `inactive()`, `expired()`, nieistniejący kod `zzzzzz`) → `assertNotFound()`.
    - `code lookup is case sensitive`: kod linku `Abc123`, żądanie `abc123` → 404.
    - `code outside the alphabet returns 404`: żądanie `ab-12` → 404.
    - `catch-all route does not shadow existing pages`: gość na `get('/login')` dostaje 200; `get('/dashboard')` przekierowuje na `route('login')`.
      Stałe kody: `Link::factory()->create(['code' => ...])`.
- [ ] **Krok 2: Uruchom** `php artisan test --compact --filter=LinkRedirectTest`. Oczekiwane: FAIL (brak trasy).
- [ ] **Krok 3: Zaimplementuj** kontroler (`__invoke(string $code): RedirectResponse`, `Link::active()->where('code', $code)->firstOrFail()`, `redirect()->away($link->original_url)`) oraz trasę `Route::get('{code}', RedirectLinkController::class)->where('code', '[0-9A-Za-z]+')->name('links.redirect');` jako ostatnią w `routes/web.php`.
- [ ] **Krok 4: Uruchom** to samo polecenie. Oczekiwane: PASS (testy 404 przechodzą z domyślną stroną błędu; render Inertia to zadanie 3).
- [ ] **Krok 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/RedirectLinkController.php routes/web.php tests/Feature/LinkRedirectTest.php
git commit -m "Add public redirect route for short links"
```

### Zadanie 3: strona 404 w Inertia

**Pliki:**

- Utworzenie: `resources/js/pages/errors/NotFound.vue`
- Zmiana: `resources/js/app.ts` (resolver layoutu), `app/Providers/AppServiceProvider.php` (obsługa wyjątków)
- Test: `tests/Feature/LinkRedirectTest.php`

**Interfejsy:**

- Używa: trasy `links.redirect` (zadanie 2).
- Dostarcza: komponent Inertia `errors/NotFound`, status 404, renderowany tylko gdy zawodzi trasa `links.redirect`.

- [ ] **Krok 1: Napisz testy, które padają** w `LinkRedirectTest`: `refused redirects render the NotFound page with 404`: dla nieaktywnego linku `get(route('links.redirect', $code))->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('errors/NotFound'))`. Dodatkowo `other 404s are not rendered as NotFound`: `get('/no/such/page')->assertNotFound()` i asercja, że odpowiedź nie jest stroną Inertia (`assertDontSee('errors\/NotFound')` lub odpowiednik dla zainstalowanych helperów testowych Inertia).
- [ ] **Krok 2: Uruchom** `php artisan test --compact --filter=LinkRedirectTest`. Oczekiwane: nowe testy FAIL.
- [ ] **Krok 3: Zaimplementuj** render 404 przez `Inertia::handleExceptionsUsing(callable $callback)` (inertia-laravel v3.5.1), zarejestrowane w `AppServiceProvider::boot()` (potrzebuje podpiętego handlera wyjątków). Callback dostaje `Inertia\ExceptionResponse $response` i zwraca `$response->render('errors/NotFound')`, gdy `$response->request->routeIs('links.redirect') && ! $response->request->expectsJson() && $response->statusCode() === 404`; w przeciwnym razie zwraca `null` (zostaje oryginalna odpowiedź). `ExceptionResponse::toResponse()` dokłada współdzielone propsy z `HandleInertiaRequests` i zachowuje status, więc nie trzeba ręcznie `setStatusCode(404)`. Sprawdzone w vendorze: `Handler::prepareException()` zamienia `ModelNotFoundException` na `NotFoundHttpException` przed callbackami render, a callback nie jest ograniczony do requestów Inertia, więc warunki trasy i JSON są wymagane. Nie używać do tego `$exceptions->render(...)` w `bootstrap/app.php`.
- [ ] **Krok 4: Zaimplementuj** `errors/NotFound.vue` wzorowany na `resources/js/pages/auth/VerifyEmail.vue`: `defineOptions({ layout: { title: 'Link not found', description: 'This link does not exist or is no longer available.' } })`, `<Head title="Link not found" />`, `TextLink`/`Button` do `/` przez trasę Wayfinder `front_app` (`@/routes`). W resolverze layoutu w `resources/js/app.ts` dodaj `name.startsWith('errors/')` → `AuthLayout` (publiczna strona nie może używać `AppLayout` z sidebarem).
- [ ] **Krok 5: Uruchom** `npm run build`, potem `php artisan test --compact --filter=LinkRedirectTest`. Oczekiwane: build OK, PASS.
- [ ] **Krok 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/js/pages/errors/NotFound.vue resources/js/app.ts app/Providers/AppServiceProvider.php tests/Feature/LinkRedirectTest.php
git commit -m "Render Inertia 404 page for refused short links"
```

### Zadanie 4: przepływ E2E

**Pliki:**

- Utworzenie: `tests/e2e/redirect.spec.ts`

**Interfejsy:**

- Używa: tras i strony z zadań 2-3. Wzorzec helpera `run()` (tinker) z `tests/e2e/links.spec.ts`; bez stanu logowania: `test.use({ storageState: undefined })`.

- [ ] **Krok 1: Napisz** `redirect.spec.ts` z dwoma testami; linki tworzone przez tinker z unikalnymi kodami, sprzątane w `afterAll`:
    - `active short link redirects to the target`: link z `original_url` `https://example.com/`; `page.goto('/<code>')`, potem `await expect(page).toHaveURL('https://example.com/')` (w razie potrzeby przechwyć zewnętrzny host przez `page.route`, żeby nie wychodzić w sieć).
    - `inactive short link shows the not found page`: `goto`, potem `expect(page.getByText('This link does not exist or is no longer available.')).toBeVisible()` oraz status odpowiedzi 404.
- [ ] **Krok 2: Uruchom** `npx playwright test tests/e2e/redirect.spec.ts`. Oczekiwane: PASS (wymaga serwera aplikacji wg `playwright.config.ts`).
- [ ] **Krok 3: Commit**

```bash
git add tests/e2e/redirect.spec.ts
git commit -m "Add E2E tests for public redirect"
```

### Zadanie 5: weryfikacja końcowa

- [ ] **Krok 1:** Uruchom `php artisan test --compact tests/Feature/LinkRedirectTest.php tests/Feature/LinkScopeTest.php tests/Feature/LinkCrudTest.php tests/Feature/AuthRedirectTest.php`. Oczekiwane: wszystko PASS.
- [ ] **Krok 2:** Poproś użytkownika o uruchomienie pełnego zestawu `php artisan test --compact` (zasada projektu).
