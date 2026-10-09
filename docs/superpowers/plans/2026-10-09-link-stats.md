# Statystyki linków: plan implementacji

> **Dla agentów wykonawczych:** WYMAGANY SUB-SKILL: użyj superpowers:subagent-driven-development (rekomendowane) lub superpowers:executing-plans, żeby realizować plan zadanie po zadaniu. Kroki mają checkboxy (`- [ ]`).

**Cel:** Każde udane przekierowanie zapisuje kliknięcie (czas, kraj, miasto) przez job w kolejce, a właściciel linku widzi statystyki z filtrem 7/30/90 dni i liczbę kliknięć w liście linków.

**Architektura:** `RedirectLinkController` dispatchuje `RecordLinkClick` (IP + czas żądania). Job pyta `GeoLocator` (MaxMind GeoLite2-City) i tworzy `LinkClick`; IP nie jest zapisywane. `LinkStatsController` agreguje `link_clicks` zapytaniami `GROUP BY` i renderuje stronę Inertia `links/Stats`.

**Stack:** Laravel 13, Inertia v3 + Vue, Wayfinder, Pest, `geoip2/geoip2`.

**Spec:** `docs/superpowers/specs/2026-10-09-link-stats-design.md`

## Ograniczenia globalne

- Nowe tabele z UUID PK i FK. `link_clicks` bez `created_at`/`updated_at` (`$timestamps = false`).
- Adres IP nigdy nie trafia do tabeli `link_clicks` ani do modelu.
- `clicked_at` ustawia kontroler w chwili żądania i przekazuje do joba.
- Brak geolokalizacji (brak pliku bazy, IP lokalne/nieznane) daje `null` kraj/miasto i klik jest zapisany.
- `days` ∈ `7|30|90`, domyślnie 30.
- Jedyna nowa zależność: `geoip2/geoip2` (zaakceptowana). Wykres bez biblioteki (CSS/SVG).
- Plik `.mmdb` poza gitem: `/storage/app/geoip/` w `.gitignore`.
- Zasady projektu: Pest, `vendor/bin/pint --dirty --format agent` po zmianach PHP, teksty UI jako angielskie literały (`__()`), Wayfinder do tras we froncie, `php artisan make:*` z `--no-interaction`.
- Specyfikacje i plany po polsku.

## Fokus przeglądu

1. Link usunięty między dispatchem a wykonaniem joba: job kończy się bez błędu (test w Zadaniu 3).
2. Plik bazy `.mmdb` nie istnieje: redirect i zapis kliknięcia działają, geo `null` (Zadanie 2).
3. Dzień bez kliknięć w zakresie: pojawia się w `daily` z zerem (Zadanie 5).
4. Kliknięcia innego linku i kliknięcia starsze niż zakres nie wchodzą do sum (Zadanie 5).
5. Geo `null` w tabelach top krajów/miast: etykieta "Unknown", nie pusty wiersz (Zadanie 5).

---

### Zadanie 1: Dane `link_clicks` i zależność

**Files:**
- Modify: `composer.json`, `composer.lock` (przez `composer require geoip2/geoip2`)
- Create: migracja `create_link_clicks_table`, `app/Models/LinkClick.php`, `database/factories/LinkClickFactory.php`
- Modify: `app/Models/Link.php`, `.gitignore`
- Test: `tests/Feature/LinkClickModelTest.php`

**Interfaces:**
- Produces: `App\Models\LinkClick` (pola `link_id`, `clicked_at` datetime, `country_code`, `country`, `city`; relacja `link(): BelongsTo`); `Link::clicks(): HasMany`; `LinkClick::factory()`.

- [ ] **Krok 1: Zainstaluj zależność** — `composer require geoip2/geoip2 --no-interaction`; dopisz `/storage/app/geoip` do `.gitignore`.
- [ ] **Krok 2: Napisz test** `LinkClickModelTest`: `link has many clicks` (utworzone przez factory klikniecia są w `$link->clicks`); `clicks are deleted with their link` (po `$link->delete()` `LinkClick::count()` równe 0); `clicked_at is cast to datetime` (instancja `Carbon`); `country_code, country and city may be null`.
- [ ] **Krok 3: Uruchom** `php artisan test --compact tests/Feature/LinkClickModelTest.php`. Oczekiwane: FAIL (brak klasy `LinkClick`).
- [ ] **Krok 4: Wygeneruj** `php artisan make:model LinkClick -mf --no-interaction`. Migracja: kolumny i indeks `(link_id, clicked_at)` ze specu, `foreignUuid('link_id')->constrained()->cascadeOnDelete()`. Model: `HasUuids`, `HasFactory`, `$timestamps = false`, `#[Fillable]` na `link_id`, `clicked_at`, `country_code`, `country`, `city`, cast `clicked_at` => `datetime`, PHPDoc `@property` jak w `Link`. Factory: `link_id` => `Link::factory()`, `clicked_at` => `now()`, `country_code` 'PL', `country` 'Poland', `city` 'Warsaw'. Dodaj `Link::clicks()` z PHPDoc generics jak `user()`.
- [ ] **Krok 5: Uruchom test ponownie.** Oczekiwane: PASS.
- [ ] **Krok 6: Pint i commit** — `vendor/bin/pint --dirty --format agent`; `git add -A`; commit `Add link_clicks table and LinkClick model`.

### Zadanie 2: `GeoLocator`

**Files:**
- Create: `app/Support/GeoLocator.php`
- Modify: `config/services.php` (klucz `geoip.database` = `env('GEOIP_DATABASE', storage_path('app/geoip/GeoLite2-City.mmdb'))`), `.env.example` (`GEOIP_DATABASE=`)
- Test: `tests/Unit/GeoLocatorTest.php`

**Interfaces:**
- Produces: `App\Support\GeoLocator` z `locate(string $ip): array` o kształcie `array{country_code: ?string, country: ?string, city: ?string}` (PHPDoc). Klasa nie jest `final` (mockowana w testach).

- [ ] **Krok 1: Napisz test**: `returns nulls when the database file is missing` (config `services.geoip.database` wskazuje nieistniejący plik, `locate('8.8.8.8')` zwraca trzy `null`); `returns nulls for an invalid ip` (`locate('not-an-ip')`).
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Unit/GeoLocatorTest.php`. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj `locate`**: jeśli plik nie istnieje, zwróć trzy `null`; w przeciwnym razie `GeoIp2\Database\Reader` + `->city($ip)`; `AddressNotFoundException` i `InvalidArgumentException` łapane i dające `null`-e. Mapowanie: `country->isoCode`, `country->name`, `city->name`. Reader tworzony leniwie i trzymany we właściwości. Prawdziwy lookup z plikiem `.mmdb` nie jest testowany jednostkowo (plik jest poza repo).
- [ ] **Krok 4: Uruchom test.** Oczekiwane: PASS.
- [ ] **Krok 5: Pint i commit** — `Add GeoLocator wrapping MaxMind GeoLite2`.

### Zadanie 3: Job `RecordLinkClick`

**Files:**
- Create: `app/Jobs/RecordLinkClick.php` (przez `php artisan make:job RecordLinkClick --no-interaction`)
- Test: `tests/Feature/RecordLinkClickTest.php`

**Interfaces:**
- Consumes: `GeoLocator::locate(string): array`, `LinkClick`.
- Produces: `RecordLinkClick::__construct(public string $linkId, public string $ip, public CarbonInterface $clickedAt)`, `ShouldQueue`, `handle(GeoLocator $geoLocator): void`.

- [ ] **Krok 1: Napisz test** (fake `GeoLocator` zbindowany w kontenerze przez Mockery): `records a click with country and city` (po `RecordLinkClick::dispatchSync(...)` jest jeden `LinkClick` z `country_code` 'PL', `country` 'Poland', `city` 'Warsaw' i `clicked_at` równym przekazanemu czasowi, nie `now()`); `records a click with null geo when lookup finds nothing`; `does nothing when the link no longer exists` (losowy UUID, brak wyjątku, `LinkClick::count()` równe 0); `does not persist the ip` (`array_keys` atrybutów `LinkClick` nie zawierają `ip`).
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Feature/RecordLinkClickTest.php`. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj `handle`**: `Link::find($linkId)` — brak linku: `return`; `LinkClick::create([... geo ..., 'clicked_at' => $this->clickedAt, 'link_id' => ...])`. Wykorzystaj `$link->clicks()->create(...)`.
- [ ] **Krok 4: Uruchom test.** Oczekiwane: PASS.
- [ ] **Krok 5: Pint i commit** — `Add RecordLinkClick job`.

### Zadanie 4: Dispatch z redirectu

**Files:**
- Modify: `app/Http/Controllers/RedirectLinkController.php`
- Modify: `tests/Feature/LinkRedirectTest.php`

**Interfaces:**
- Consumes: `RecordLinkClick::__construct(string $linkId, string $ip, CarbonInterface $clickedAt)`.

- [ ] **Krok 1: Dopisz testy** w `LinkRedirectTest`: `successful redirect dispatches RecordLinkClick` (`Queue::fake()`, `Queue::assertPushed(RecordLinkClick::class, fn ($job) => $job->linkId === $link->id)`); `refused redirects dispatch nothing` (dataset nieaktywny/wygasły/nieznany, `Queue::assertNothingPushed()`).
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Feature/LinkRedirectTest.php`. Oczekiwane: nowe testy FAIL.
- [ ] **Krok 3: Zmień kontroler**: `__invoke(Request $request, string $code)`; po `firstOrFail()` wywołaj `RecordLinkClick::dispatch($link->id, $request->ip(), now())`, potem dotychczasowy redirect.
- [ ] **Krok 4: Uruchom cały plik testów.** Oczekiwane: PASS, w tym stare testy (przy `QUEUE_CONNECTION=sync` w testach job wykona się z brakiem bazy `.mmdb`, czyli z geo `null`; sprawdź w `phpunit.xml` i w razie potrzeby ustaw `QUEUE_CONNECTION=sync`).
- [ ] **Krok 5: Pint i commit** — `Record a click on every successful redirect`.

### Zadanie 5: Backend strony statystyk

**Files:**
- Create: `app/Http/Controllers/LinkStatsController.php`, `app/Http/Requests/Links/LinkStatsRequest.php`
- Modify: `app/Policies/LinkPolicy.php` (nowa metoda `view`, ten sam warunek właściciela co `update`), `routes/web.php` (w grupie `auth`+`verified`: `Route::get('links/{link}/stats', LinkStatsController::class)->name('links.stats')`, **przed** `Route::resource`), `app/Http/Controllers/LinkController.php` (`index`: `withCount('clicks')`)
- Test: `tests/Feature/LinkStatsTest.php`, `tests/Feature/LinkCrudTest.php` (jeden test licznika)

**Interfaces:**
- Produces: strona Inertia `links/Stats` z propsami: `link: Link`, `days: int`, `total: int`, `daily: list<array{date: string, count: int}>` (dokładnie `days` pozycji, rosnąco, ostatnia = dziś), `countries` i `cities`: `list<array{name: string, count: int}>` (top 10, malejąco; `null` mapowane na `Unknown`, miasto jako `City, Country`). Lista linków: atrybut `clicks_count` na każdym linku.

- [ ] **Krok 1: Napisz testy** `LinkStatsTest`: `owner sees stats with total, daily series, countries and cities` (kliknięcia z różnymi dniami/krajami; asercje Inertia na propsach); `days defaults to 30`; `daily contains every day of the range with zeros`; `clicks older than the range and clicks of other links are excluded`; `null geo is shown as Unknown`; `invalid days value fails validation` (np. `days=5`, `assertSessionHasErrors('days')`); `other users cannot see stats` (403); `guests are redirected to login`. W `LinkCrudTest`: `index exposes clicks_count`.
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Feature/LinkStatsTest.php`. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj.** `LinkStatsRequest`: `days` => `['sometimes', 'integer', Rule::in([7, 30, 90])]`; metoda `days(): int` zwracająca wartość lub 30. Kontroler: `Gate::authorize('view', $link)`; zakres od `now()->subDays($days - 1)->startOfDay()`; trzy zapytania (`clicks()` z filtrem zakresu): dzienne `selectRaw('DATE(clicked_at) as date, count(*) as count')->groupBy('date')`, kraje i miasta `groupBy` + `orderByDesc('count')->limit(10)`; `daily` uzupełniane zerami w PHP; `total` = suma `daily`.
- [ ] **Krok 4: Uruchom testy statsów i `LinkCrudTest`.** Oczekiwane: PASS.
- [ ] **Krok 5: Pint i commit** — `Add link stats endpoint with 7/30/90 day filter`.

### Zadanie 6: Frontend

**Files:**
- Create: `resources/js/pages/links/Stats.vue`
- Modify: `resources/js/pages/links/Index.vue`
- Test: `tests/e2e/stats.spec.ts`

**Interfaces:**
- Consumes: propsy ze Zadania 5; funkcje tras Wayfinder dla `links.stats` (generowane przez `php artisan wayfinder:generate`).

- [ ] **Krok 1: Napisz test E2E** (wzór: `tests/e2e/links.spec.ts`): zalogowany użytkownik z linkiem i kilkoma kliknięciami (seedowanie tak jak w istniejących specach) otwiera statystyki z listy, widzi sumę, przełącza zakres na 7 dni (URL zawiera `days=7`).
- [ ] **Krok 2: Zaimplementuj `Stats.vue`**: typowane propsy; przełącznik zakresu 7/30/90 jako `Link` z `preserveState`; podsumowanie `total`; wykres słupkowy dzienny w CSS/SVG (wysokość słupków względem maksimum; przy `total` 0 pusty stan z komunikatem); dwie tabele (kraje, miasta). Styl i komponenty jak `Index.vue`; teksty przez `__`-odpowiednik używany w projekcie.
- [ ] **Krok 3: Zmień `Index.vue`**: kolumna "Clicks" z `clicks_count` i link do `links.stats`.
- [ ] **Krok 4: Zbuduj front** `npm run build`, uruchom `npx playwright test tests/e2e/stats.spec.ts`. Oczekiwane: PASS. Ręcznie sprawdź w przeglądarce stronę dla linku bez kliknięć i z kliknięciami.
- [ ] **Krok 5: Commit** — `Add link stats page and click counts in the links list`.

### Zadanie 7: Weryfikacja końcowa

- [ ] **Krok 1:** `php artisan test --compact` (cały zestaw, wg zasad projektu poproś użytkownika o uruchomienie) oraz `vendor/bin/pint --dirty --format agent`, larastan (komenda z `composer.json`, jeśli skonfigurowana).
- [ ] **Krok 2:** Pobranie bazy GeoLite2-City do `storage/app/geoip/` (ręcznie przez użytkownika, konto MaxMind) i jedno ręczne kliknięcie krótkiego linku z publicznego IP; sprawdzenie wiersza w `link_clicks` po uruchomieniu workera (`php artisan queue:work`).
