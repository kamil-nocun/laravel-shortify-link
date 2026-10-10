# Prywatność odwiedzających — plan implementacji

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dzienna agregacja kliknięć z crona do wierszy podsumowań oraz usunięcie z bazy (jobs, failed_jobs, sessions) IP i User-Agent odwiedzających.

**Architecture:** `link_statistics` dostaje kolumny `clicks` i `is_summary`; raporty liczą `sum(clicks)`, więc surowe kliknięcia i podsumowania są wymienne. Akcja `AggregateDailyLinkStatistics` zwija każdy zakończony dzień do podsumowań (bez miasta), wywoływana komendą `stats:aggregate` ze schedulera. Kontroler przekierowania sam wyznacza lokalizację i klienta, a job dostaje tylko wyniki; trasa `{code}` działa bez sesji i cookies.

**Tech Stack:** Laravel (scheduler, Eloquent), Pest, MySQL.

**Spec:** `docs/superpowers/specs/2026-10-10-visitor-privacy-design.md`

## Global Constraints

- Nowe kolumny: `clicks` unsigned integer domyślnie 1, `is_summary` boolean domyślnie false; wiersz podsumowania ma `city = null`, `clicked_at` = początek dnia w `config('app.timezone')`.
- Wymiary podsumowania: `link_id`, `country_code`, `country`, `browser`, `os`, `device_type`, `entry`, `source`. Wartości NULL pozostają NULL.
- Harmonogram: `Schedule::command('stats:aggregate')->dailyAt('00:10')->withoutOverlapping()` w `routes/console.php`.
- Komenda: `stats:aggregate`, klasa `App\Console\Commands\AggregateLinkStatistics`; akcja `App\Actions\AggregateDailyLinkStatistics`.
- Job `RecordLinkClick` nie ma żadnej własności z IP ani User-Agent.
- Trasa `links.redirect` wyłączona z `StartSession`, `AddQueuedCookiesToResponse`, `ShareErrorsFromSession`, `PreventRequestForgery` (jak `domains.check`).
- Nazwa pola frontendu `clicks_count` zostaje; dla linków bez kliknięć wartość 0.
- Konwencje repo: Pest feature tests, UUID PK, `vendor/bin/pint --dirty --format agent` po zmianach PHP, brak nowych zależności.

## Review Focus

- Kliknięcia o NULL-owych wymiarach (brak GeoIP, brak User-Agent) grupują się razem i po agregacji nadal mają NULL, a nie pusty napis.
- Kliknięcie datowane wczoraj, ale zapisane po agregacji (opóźniony job) trafia przy następnym przebiegu do istniejącego podsumowania (sumy się łączą, nic nie ginie).
- Przebieg bez żadnych wierszy do agregacji kończy się sukcesem z wynikiem 0; dzień dzisiejszy nigdy nie jest ruszany, także o 23:59:59.
- Link bez kliknięć ma `clicks_count` 0 (nie null) na liście klienta i w panelu admina.
- Nieznany kod w `{code}` nadal renderuje stronę NotFound mimo braku sesji (HandleInertiaRequests może wymagać sesji; patrz Task 4).
- Brak bazy GeoIP: przekierowanie działa, lokalizacja same NULL.

---

### Task 1: Kolumny `clicks` i `is_summary`

**Files:**
- Create: migracja `add_clicks_and_is_summary_to_link_statistics_table` (przez `php artisan make:migration`)
- Modify: `app/Models/LinkClick.php`, `database/factories/LinkClickFactory.php`
- Test: `tests/Feature/LinkClickModelTest.php`

**Interfaces:**
- Produces: kolumny `link_statistics.clicks` (unsigned int, default 1), `link_statistics.is_summary` (bool, default false); `LinkClick` z `clicks`, `is_summary` w `Fillable`, PHPDoc i castach (`int`, `bool`); `LinkClickFactory::summary(int $clicks = 5): static` (stan: `is_summary = true`, `city = null`, `clicks`, `clicked_at` = początek dnia).

- [ ] **Step 1: Padające testy** w `LinkClickModelTest.php`: `a new click counts as one and is not a summary` (`clicks` 1, `is_summary` false po `LinkClick::factory()->create()->fresh()`); `summary state has no city and a day start time` (`city` null, `clicked_at` równe `startOfDay()`, `clicks` 5, `is_summary` true).
- [ ] **Step 2:** Uruchom `php artisan test --compact tests/Feature/LinkClickModelTest.php`; oczekiwane: FAIL.
- [ ] **Step 3:** Zaimplementuj migrację (down usuwa kolumny), uzupełnij model i fabrykę (`summary()`).
- [ ] **Step 4:** `php artisan migrate`, uruchom test; oczekiwane: PASS.
- [ ] **Step 5: Commit**: `Add click count and summary flag to link statistics`.

### Task 2: Raporty liczą `sum(clicks)`

**Files:**
- Modify: `app/Support/StatsPeriod.php:80`, `app/Support/LinkStatsReport.php:102`, `app/Support/AdminStatsReport.php:45,53`, `app/Http/Controllers/LinkController.php:29`, `app/Http/Controllers/Admin/UserController.php:127`
- Test: `tests/Feature/SummaryStatisticsTest.php`

**Interfaces:**
- Consumes: kolumna `clicks` i `LinkClickFactory::summary()` (Task 1).
- Produces: wszystkie raporty zwracają te same liczby dla surowych kliknięć i dla podsumowań; `clicks_count` jest liczbą całkowitą (0 bez kliknięć).

- [ ] **Step 1: Padające testy** w `SummaryStatisticsTest.php`, każdy porównuje stan "N surowych kliknięć" ze stanem "jedno podsumowanie z `clicks = N`" dla tego samego linku i dnia:
  - `link stats totals and daily series count summary clicks` (endpoint danych statystyk linku: trasa `links.stats.data` — sprawdź w `LinkStatsDataTest.php` format i użyj go),
  - `link stats breakdown counts summary clicks` (rozbicie po kraju: `count` = `clicks`),
  - `admin report totals, top links and top users count summary clicks` (`admin.dashboard`),
  - `link lists expose summed clicks_count including zero` (`links.index` i `admin.users.show`; link bez kliknięć daje 0, link z podsumowaniem `clicks=7` daje 7, a `clicks_count` jest `int`).
- [ ] **Step 2:** Uruchom `php artisan test --compact tests/Feature/SummaryStatisticsTest.php`; oczekiwane: FAIL (podsumowanie liczone jako 1).
- [ ] **Step 3:** Zamień `count(*)` na `sum(clicks)` w wymienionych miejscach (seria, rozbicie, top linki, top użytkownicy), a `withCount('clicks')` na `withSum('clicks as clicks_count', 'clicks')` z rzutowaniem do `int` i wartością 0 przy braku wierszy (np. akcesor/`coalesce`, bez zmiany nazwy pola). Liczba grup w `LinkStatsReport::table()` nadal liczona jako liczba wierszy.
- [ ] **Step 4:** Uruchom nowy test oraz istniejące `LinkStatsTest`, `LinkStatsDataTest`, `LinkStatsEntryTest`, `LinkStatsSourceTest`, `AdminStatsReportTest`, `LinkCrudTest`, `Admin/UserManagementTest`; oczekiwane: PASS.
- [ ] **Step 5: Commit**: `Count summed clicks in statistics reports`.

### Task 3: Agregacja dzienna, komenda i harmonogram

**Files:**
- Create: `app/Actions/AggregateDailyLinkStatistics.php`, `app/Console/Commands/AggregateLinkStatistics.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/AggregateLinkStatisticsTest.php`

**Interfaces:**
- Consumes: kolumny z Task 1.
- Produces: `AggregateDailyLinkStatistics::__invoke(): int` (liczba przetworzonych dni); komenda `stats:aggregate` (wypisuje liczbę dni, kod 0); zaplanowane zadanie `stats:aggregate` dziennie o 00:10.

- [ ] **Step 1: Padające testy** w `AggregateLinkStatisticsTest.php`:
  - `collapses a past day into one summary row per dimension set` (kilka kliknięć z różnymi kombinacjami kraj/przeglądarka/OS/urządzenie/entry/source i różnymi miastami; po akcji: tyle wierszy ile kombinacji, `is_summary` true, `city` null, `clicked_at` = początek dnia, `clicks` = suma kombinacji, surowych wierszy brak),
  - `keeps null dimensions null and groups them together`,
  - `leaves today untouched` (kliknięcia dziś o 00:00:01 i 23:59:59 nadal surowe),
  - `is idempotent` (drugi przebieg zwraca 0 i nie zmienia wierszy),
  - `catches up several missed days and returns how many days it processed`,
  - `merges a late click into an existing summary` (do już zagregowanego dnia dodaj surowy wiersz, przebieg łączy sumy w jednym wierszu),
  - `does not touch other links` (dwa linki, sumy osobno),
  - `command prints the processed day count` (`artisan('stats:aggregate')->expectsOutput(...)`, wynik 0 bez danych),
  - `aggregation is scheduled daily at 00:10 without overlapping` (znajdź zdarzenie w `app(Schedule::class)->events()` po komendzie `stats:aggregate`, sprawdź wyrażenie cron `10 0 * * *` i `withoutOverlapping`).
- [ ] **Step 2:** Uruchom; oczekiwane: FAIL.
- [ ] **Step 3:** Zaimplementuj akcję: dni = `distinct DATE(clicked_at)` wierszy z `is_summary = false` i `clicked_at < now()->startOfDay()`; dla każdego dnia w `DB::transaction`: zapytanie grupujące (wymiary z Global Constraints, `sum(clicks)`) po wszystkich wierszach dnia (surowych i podsumowaniach), wstawienie podsumowań, usunięcie dotychczasowych wierszy dnia. Komenda woła akcję przez kontener i drukuje wynik. Dodaj `Schedule::command(...)` zgodnie z Global Constraints.
- [ ] **Step 4:** Uruchom; oczekiwane: PASS.
- [ ] **Step 5: Commit**: `Aggregate link statistics daily`.

### Task 4: Brak IP i User-Agent w bazie

**Files:**
- Modify: `app/Jobs/RecordLinkClick.php`, `app/Http/Controllers/RedirectLinkController.php`, `routes/web.php` (trasa `{code}`)
- Test: `tests/Feature/RecordLinkClickTest.php`, `tests/Feature/LinkRedirectTest.php`, `tests/Feature/VisitorPrivacyTest.php`

**Interfaces:**
- Produces: `RecordLinkClick::__construct(string $linkId, CarbonInterface $clickedAt, array $location, array $client, LinkEntry $entry = LinkEntry::Code, LinkSource $source = LinkSource::Link)` gdzie `$location` ma klucze `country_code`, `country`, `city`, a `$client` klucze `browser`, `os`, `device_type`; `handle()` tylko zapisuje wiersz (`link->clicks()->create([...])`). `RedirectLinkController::__invoke(Request $request, LinkResolver $resolver, GeoLocator $geoLocator, ClientDetector $clientDetector, string $code)`.

- [ ] **Step 1: Padające testy.**
  - `VisitorPrivacyTest.php`: `a redirect leaves no ip or user agent in any table` (przy `REMOTE_ADDR` `203.0.113.7` i User-Agent `SecretAgent/1.0`, zamockowany `GeoLocator`; po żądaniu przy kolejce `sync` zserializuj wszystkie wiersze wszystkich tabel z `Schema::getTableListing()` i sprawdź brak obu napisów); `the dispatched job carries neither ip nor user agent` (`Queue::fake()`, `serialize($job)` bez obu napisów); `the redirect sets no cookies` (`assertHeaderMissing('Set-Cookie')`); `unknown codes still render the NotFound page` (`errors/NotFound`).
  - Dostosuj `RecordLinkClickTest.php` do nowej sygnatury (usuń testy "does not persist" opisujące stary kontrakt, zachowaj sprawdzanie zapisu lokalizacji, klienta, entry, source, braku linku) i `LinkRedirectTest.php` (asercje na `$job->ip`/`$job->userAgent` zamień na lokalizację i klienta wyznaczone z zamockowanych `GeoLocator`/`ClientDetector`, w tym testy zaufanych proxy sprawdzające IP przekazane do `GeoLocator::locate`).
- [ ] **Step 2:** Uruchom te trzy pliki; oczekiwane: FAIL.
- [ ] **Step 3:** Zaimplementuj nową sygnaturę joba i kontroler (lokalizacja przez `GeoLocator::locate($request->ip())`, klient przez `ClientDetector::detect($request->userAgent())`). Na trasie `links.redirect` dodaj `->withoutMiddleware([...])` z listą z Global Constraints. Jeśli strona NotFound wymaga sesji (błąd "Session store not set"), wybierz najmniejsze obejście zachowujące brak zapisu sesji i cookies (np. wyłączenie tylko `StartSession` wraz z middleware zależnym od niego, albo obsługa wyjątku 404 bez sesji), zapisz wybór jako Ruling w ledgerze.
- [ ] **Step 4:** Uruchom `tests/Feature/VisitorPrivacyTest.php`, `RecordLinkClickTest.php`, `LinkRedirectTest.php`, `DomainRedirectTest.php`, `LinkQrTest.php`; oczekiwane: PASS.
- [ ] **Step 5: Commit**: `Stop storing visitor ip and user agent`.

### Task 5: Weryfikacja końcowa

- [ ] **Step 1:** `vendor/bin/pint --format agent`; skomituj ewentualne poprawki stylu.
- [ ] **Step 2:** `php artisan test --compact` (pełny zestaw w tym repo trwa ok. 45 s i jest zielony na starcie); oczekiwane: PASS bez nowych błędów.
- [ ] **Step 3:** Ręcznie: `php artisan stats:aggregate` na lokalnej bazie i sprawdzenie, że panel statystyk linku pokazuje te same sumy co przed agregacją (wykonaj tylko za zgodą użytkownika, bo zmienia dane lokalne).
