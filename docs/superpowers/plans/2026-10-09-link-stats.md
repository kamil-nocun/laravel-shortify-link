# Statystyki linków: plan implementacji

> **Dla agentów wykonawczych:** użyj superpowers:subagent-driven-development (rekomendowane) lub superpowers:executing-plans. Kroki mają checkboxy (`- [ ]`).

**Spec:** `docs/superpowers/specs/2026-10-09-link-stats-design.md`

## Cel w jednym zdaniu

Każdy udany redirect zapisuje kliknięcie (czas, kraj, miasto, przeglądarka, system, typ urządzenia) przez job w kolejce; właściciel widzi statystyki 7/30/90 dni i licznik w liście linków.

## Mapa postępu

Odhaczaj po zakończeniu zadania. Jedno zadanie = jeden commit.

- [x] **1.** Tabela `link_statistics` + model
- [x] **2.** `GeoLocator`
- [x] **3.** Job `RecordLinkClick`
- [x] **4.** Dispatch z redirectu
- [x] **5.** Backend strony statystyk
- [x] **6.** Frontend + E2E
- [ ] **7.** Weryfikacja końcowa (zostaje ręczny test geo, krok 7.3)
- [x] **8.** Zależność, kolumny klienta + `ClientDetector`
- [x] **9.** Job i redirect przekazują User-Agent
- [x] **10.** Statystyki klienta w backendzie i UI + E2E
- [ ] **11.** Filtry statystyk w backendzie
- [ ] **12.** Filtry statystyk w UI + E2E
- [ ] **13.** `StatsPeriod` i walidacja okresu
- [ ] **14.** Okres w kontrolerze (`period`, `series`, `granularity`)
- [ ] **15.** Sortowanie i paginacja tabel w backendzie
- [ ] **16.** UI: okres, sortowanie, paginacja i limity + E2E

**Zależności:** 1 → 2 → 3 → 4. Zadanie 5 wymaga tylko 1. Zadanie 6 wymaga 5. Zadania 8–10 dobudowują statystyki klienta na gotowych 1–6: 8 → 9 → 10. Zadania 11–12 dodają filtry na gotowych 5, 6 i 10: 11 → 12. Zadania 13–16 dodają okres, sortowanie i paginację na gotowych 11 i 12: 13 → 14 → 15 → 16 (spec: `docs/superpowers/specs/2026-10-09-link-stats-period-tables-design.md`).

## Przepływ danych

```
GET /{code} → RedirectLinkController → RecordLinkClick (kolejka)
                                         → GeoLocator + ClientDetector → LinkClick (DB)
GET links/{link}/stats → LinkStatsController → links/Stats.vue
```

## Zasady (obowiązują w każdym zadaniu)

- UUID PK/FK. `link_statistics` bez `created_at`/`updated_at`.
- **Nigdy** nie zapisuj IP ani surowego User-Agent w `link_statistics`. Zapisujemy tylko `browser`, `os`, `device_type` (nazwy, bez wersji).
- `clicked_at` ustawia kontroler (czas żądania), nie job.
- Brak geo = `null` kraj/miasto, klik i tak zapisany.
- `days` ∈ `7|30|90`, domyślnie `30`. Od zadania 14 `days` zastępuje `period` (domyślnie `30d`).
- Zależności tylko `geoip2/geoip2` i `matomo/device-detector` (zaakceptowane). Wykres bez biblioteki.
- Po zmianach PHP: `vendor/bin/pint --dirty --format agent`.
- Teksty UI: angielskie literały przez `__()`. Trasy we froncie przez Wayfinder.
- Artisan zawsze z `--no-interaction`.

## Przypadki brzegowe (każdy ma test w wymienionym zadaniu)

| # | Sytuacja | Oczekiwane | Zadanie |
|---|---|---|---|
| 1 | Link usunięty przed wykonaniem joba | Job kończy się bez błędu | 3 |
| 2 | Brak pliku `.mmdb` | Redirect działa, geo `null` | 2 |
| 3 | Dzień bez kliknięć | Jest w `daily` z zerem | 5 |
| 4 | Kliknięcia starsze niż zakres / cudzego linku | Nie wchodzą do sum | 5 |
| 5 | Geo `null` w tabelach | Etykieta `Unknown` | 5 |
| 6 | Brak lub pusty User-Agent | Trzy pola klienta `null`, klik zapisany | 8, 9 |
| 7 | Bot | `device_type = 'bot'`, `browser`/`os` `null` | 8 |
| 8 | Klient `null` w tabelach | Etykieta `Unknown` | 10 |
| 9 | Filtr `Unknown` | Zwraca kliknięcia z `null` w tej kolumnie | 11 |
| 10 | Filtr miasta | Działa po samej nazwie miasta, nie po `City, Country` | 11 |
| 11 | Filtr cudzego linku lub spoza zakresu | Nie ujawnia cudzych kliknięć, `options` tylko z tego linku i zakresu | 11 |
| 12 | `period=all` bez kliknięć | Pusty zakres, `total` = 0, `series` puste | 13, 14 |
| 13 | `period=day` bez `date` lub zły format | Błąd walidacji | 13 |
| 14 | `range` z `from > to` lub dłuższy niż 5 lat | Błąd walidacji | 13 |
| 15 | Zakres dłuższy niż 92 dni, `year`, `all` | `granularity = month` | 13 |
| 16 | Zakres w przyszłości | Zera, bez błędu | 14 |
| 17 | Ostatnia strona niepełna, strona poza zakresem | Ostatnia strona z resztą wierszy | 15 |
| 18 | `per_page` spoza listy, zła `sort`, `page` < 1 | Błąd walidacji | 15 |
| 19 | Sortowanie po nazwie z `Unknown` | `Unknown` sortowane jak nazwa | 15 |
| 20 | Remisy w liczbie kliknięć | Ta sama kolejność na kolejnych stronach | 15 |
| 21 | Zmiana okresu, filtra lub sortowania | Strony wracają do 1, limity zostają | 16 |

---

## Zadanie 1: Tabela `link_statistics` + model

**Po co:** miejsce na kliknięcia. Reszta zależy od tego modelu.

**Pliki**
- Nowe: migracja `create_link_clicks_table`, `app/Models/LinkClick.php`, `database/factories/LinkClickFactory.php`, `tests/Feature/LinkClickModelTest.php`
- Zmiana: `app/Models/Link.php`, `.gitignore`, `composer.json`, `composer.lock`

**Daje dalej:** `LinkClick` (pola `link_id`, `clicked_at` datetime, `country_code`, `country`, `city`; `link(): BelongsTo`), `Link::clicks(): HasMany`, `LinkClick::factory()`.

- [ ] **1.1** Zainstaluj: `composer require geoip2/geoip2 --no-interaction`
- [ ] **1.2** Dopisz `/storage/app/geoip` do `.gitignore`
- [ ] **1.3** Napisz test `tests/Feature/LinkClickModelTest.php`:
  - `link has many clicks`
  - `clicks are deleted with their link` (po `$link->delete()` → `LinkClick::count()` = 0)
  - `clicked_at is cast to datetime` (instancja `Carbon`)
  - `country_code, country and city may be null`
- [ ] **1.4** Uruchom: `php artisan test --compact tests/Feature/LinkClickModelTest.php`. Oczekiwane: **FAIL** (brak klasy)
- [ ] **1.5** Wygeneruj: `php artisan make:model LinkClick -mf --no-interaction`
- [ ] **1.6** Migracja: kolumny ze specu; `foreignUuid('link_id')->constrained()->cascadeOnDelete()`; indeks `(link_id, clicked_at)`
- [ ] **1.7** Model: `HasUuids`, `HasFactory`, `$timestamps = false`, `#[Fillable]` na wszystkie kolumny poza `id`, cast `clicked_at` → `datetime`, PHPDoc `@property` jak w `Link`
- [ ] **1.8** Factory: `link_id` → `Link::factory()`, `clicked_at` → `now()`, `PL` / `Poland` / `Warsaw`
- [ ] **1.9** Dodaj `Link::clicks()` (PHPDoc generics jak w `user()`)
- [ ] **1.10** Uruchom test ponownie. Oczekiwane: **PASS**
- [ ] **1.11** Pint, potem commit: `Add link_statistics table and LinkClick model`

**Gotowe gdy:** test przechodzi, migracja działa, `geoip2` jest w `composer.json`.

---

## Zadanie 2: `GeoLocator`

**Po co:** zamiana IP na kraj i miasto, odporna na błędy.

**Pliki**
- Nowe: `app/Support/GeoLocator.php`, `tests/Unit/GeoLocatorTest.php`
- Zmiana: `config/services.php`, `.env.example`

**Daje dalej:** `GeoLocator::locate(string $ip): array` o kształcie `array{country_code: ?string, country: ?string, city: ?string}` (w PHPDoc). Klasa **nie** jest `final` (mockowana w zadaniu 3).

- [ ] **2.1** Config: `services.geoip.database` = `env('GEOIP_DATABASE', storage_path('app/geoip/GeoLite2-City.mmdb'))`. Dopisz `GEOIP_DATABASE=` do `.env.example`
- [ ] **2.2** Napisz test `tests/Unit/GeoLocatorTest.php`:
  - `returns nulls when the database file is missing` (`locate('8.8.8.8')` → trzy `null`)
  - `returns nulls for an invalid ip` (`locate('not-an-ip')`)
- [ ] **2.3** Uruchom: `php artisan test --compact tests/Unit/GeoLocatorTest.php`. Oczekiwane: **FAIL**
- [ ] **2.4** Zaimplementuj `locate`:
  - brak pliku → trzy `null`
  - inaczej `GeoIp2\Database\Reader` + `->city($ip)`
  - `AddressNotFoundException` i `InvalidArgumentException` → trzy `null`
  - mapowanie: `country->isoCode`, `country->name`, `city->name`
  - Reader tworzony leniwie, trzymany we właściwości
- [ ] **2.5** Uruchom test. Oczekiwane: **PASS**
- [ ] **2.6** Pint, potem commit: `Add GeoLocator wrapping MaxMind GeoLite2`

**Gotowe gdy:** oba testy przechodzą. Prawdziwy lookup z `.mmdb` testujemy ręcznie w zadaniu 7.

---

## Zadanie 3: Job `RecordLinkClick`

**Po co:** zapis kliknięcia poza ścieżką redirectu.

**Pliki**
- Nowe: `app/Jobs/RecordLinkClick.php` (`php artisan make:job RecordLinkClick --no-interaction`), `tests/Feature/RecordLinkClickTest.php`

**Bierze:** `GeoLocator::locate`, `LinkClick`.
**Daje dalej:** `RecordLinkClick::__construct(public string $linkId, public string $ip, public CarbonInterface $clickedAt)`, `ShouldQueue`, `handle(GeoLocator $geoLocator): void`.

- [ ] **3.1** Napisz testy (fake `GeoLocator` zbindowany w kontenerze przez Mockery, wywołanie `RecordLinkClick::dispatchSync(...)`):
  - `records a click with country and city` (jeden `LinkClick` z `PL`/`Poland`/`Warsaw`; `clicked_at` = przekazany czas, nie `now()`)
  - `records a click with null geo when lookup finds nothing`
  - `does nothing when the link no longer exists` (losowy UUID, brak wyjątku, `LinkClick::count()` = 0) → przypadek #1
  - `does not persist the ip` (atrybuty `LinkClick` nie zawierają `ip`)
- [ ] **3.2** Uruchom: `php artisan test --compact tests/Feature/RecordLinkClickTest.php`. Oczekiwane: **FAIL**
- [ ] **3.3** Zaimplementuj `handle`: `Link::find($this->linkId)`; brak → `return`; inaczej `$link->clicks()->create([...geo, 'clicked_at' => $this->clickedAt])`
- [ ] **3.4** Uruchom test. Oczekiwane: **PASS**
- [ ] **3.5** Pint, potem commit: `Add RecordLinkClick job`

**Gotowe gdy:** 4 testy przechodzą.

---

## Zadanie 4: Dispatch z redirectu

**Po co:** podpięcie zapisu pod prawdziwe kliknięcie.

**Pliki**
- Zmiana: `app/Http/Controllers/RedirectLinkController.php`, `tests/Feature/LinkRedirectTest.php`

**Bierze:** `RecordLinkClick::__construct(string $linkId, string $ip, CarbonInterface $clickedAt)`.

- [ ] **4.1** Dopisz testy w `LinkRedirectTest`:
  - `successful redirect dispatches RecordLinkClick` (`Queue::fake()`, `Queue::assertPushed(RecordLinkClick::class, fn ($job) => $job->linkId === $link->id)`)
  - `refused redirects dispatch nothing` (dataset: nieaktywny, wygasły, nieznany; `Queue::assertNothingPushed()`)
- [ ] **4.2** Uruchom: `php artisan test --compact tests/Feature/LinkRedirectTest.php`. Oczekiwane: nowe testy **FAIL**
- [ ] **4.3** Kontroler: `__invoke(Request $request, string $code)`; po `firstOrFail()` → `RecordLinkClick::dispatch($link->id, $request->ip(), now())`; dalej bez zmian
- [ ] **4.4** Uruchom cały plik. Oczekiwane: **PASS**, także stare testy. Sprawdź `QUEUE_CONNECTION` w `phpunit.xml`; przy `sync` job wykona się z geo `null`, to w porządku
- [ ] **4.5** Pint, potem commit: `Record a click on every successful redirect`

**Gotowe gdy:** `LinkRedirectTest` w całości zielony.

---

## Zadanie 5: Backend strony statystyk

**Po co:** dane dla widoku, z autoryzacją.

**Pliki**
- Nowe: `app/Http/Controllers/LinkStatsController.php`, `app/Http/Requests/Links/LinkStatsRequest.php`, `tests/Feature/LinkStatsTest.php`
- Zmiana: `app/Policies/LinkPolicy.php` (metoda `view`, warunek właściciela jak w `update`), `routes/web.php`, `app/Http/Controllers/LinkController.php` (`index` → `withCount('clicks')`), `tests/Feature/LinkCrudTest.php`

**Trasa:** `Route::get('links/{link}/stats', LinkStatsController::class)->name('links.stats')` w grupie `auth`+`verified`, **przed** `Route::resource`.

**Daje dalej (props strony `links/Stats`):**

| Prop | Typ |
|---|---|
| `link` | `Link` |
| `days` | `int` |
| `total` | `int` |
| `daily` | `list<array{date: string, count: int}>`: dokładnie `days` pozycji, rosnąco, ostatnia = dziś |
| `countries` | `list<array{name: string, count: int}>`: top 10, malejąco |
| `cities` | j.w., nazwa jako `City, Country` |

Geo `null` → `Unknown`. Lista linków dostaje `clicks_count` na każdym linku.

- [ ] **5.1** Napisz testy w `LinkStatsTest`:
  - `owner sees stats with total, daily series, countries and cities`
  - `days defaults to 30`
  - `daily contains every day of the range with zeros` → #3
  - `clicks older than the range and clicks of other links are excluded` → #4
  - `null geo is shown as Unknown` → #5
  - `invalid days value fails validation` (`days=5` → `assertSessionHasErrors('days')`)
  - `other users cannot see stats` (403)
  - `guests are redirected to login`
- [ ] **5.2** W `LinkCrudTest` dodaj `index exposes clicks_count`
- [ ] **5.3** Uruchom: `php artisan test --compact tests/Feature/LinkStatsTest.php`. Oczekiwane: **FAIL**
- [ ] **5.4** `LinkStatsRequest`: `days` → `['sometimes', 'integer', Rule::in([7, 30, 90])]`; metoda `days(): int` (wartość lub 30)
- [ ] **5.5** Kontroler:
  - `Gate::authorize('view', $link)`
  - początek zakresu: `now()->subDays($days - 1)->startOfDay()`
  - dzienne: `selectRaw('DATE(clicked_at) as date, count(*) as count')->groupBy('date')`
  - kraje i miasta: `groupBy` + `orderByDesc('count')->limit(10)`
  - `daily` uzupełnione zerami w PHP; `total` = suma `daily`
- [ ] **5.6** Uruchom `LinkStatsTest` i `LinkCrudTest`. Oczekiwane: **PASS**
- [ ] **5.7** Pint, potem commit: `Add link stats endpoint with 7/30/90 day filter`

**Gotowe gdy:** 8 testów statsów + test `clicks_count` zielone.

---

## Zadanie 6: Frontend + E2E

**Po co:** użytkownik widzi dane.

**Pliki**
- Nowe: `resources/js/pages/links/Stats.vue`, `tests/e2e/stats.spec.ts`
- Zmiana: `resources/js/pages/links/Index.vue`

**Bierze:** propsy z zadania 5; trasa `links.stats` z Wayfinder (`php artisan wayfinder:generate`).

- [ ] **6.1** Napisz `tests/e2e/stats.spec.ts` (wzór: `tests/e2e/links.spec.ts`): użytkownik z linkiem i kliknięciami otwiera statystyki z listy, widzi sumę, przełącza na 7 dni (URL zawiera `days=7`)
- [ ] **6.2** Zbuduj `Stats.vue`:
  - typowane propsy
  - przełącznik 7/30/90 jako `Link` z `preserveState`
  - suma `total`
  - wykres słupkowy w CSS/SVG (wysokość względem maksimum)
  - pusty stan przy `total` = 0
  - dwie tabele: kraje, miasta
  - styl i komponenty jak w `Index.vue`
- [ ] **6.3** `Index.vue`: kolumna `Clicks` z `clicks_count` i link do `links.stats`
- [ ] **6.4** Zbuduj front: `npm run build`
- [ ] **6.5** Uruchom: `npx playwright test tests/e2e/stats.spec.ts`. Oczekiwane: **PASS**
- [ ] **6.6** Sprawdź ręcznie w przeglądarce: link bez kliknięć i z kliknięciami
- [ ] **6.7** Commit: `Add link stats page and click counts in the links list`

**Gotowe gdy:** E2E zielony, oba stany wyglądają poprawnie.

---

## Zadanie 7: Weryfikacja końcowa

- [ ] **7.1** Poproś użytkownika o pełny zestaw: `php artisan test --compact`
- [ ] **7.2** Uruchom Pint i larastan (komenda z `composer.json`, jeśli jest)
- [ ] **7.3** Ręczny test geo (robi użytkownik): pobierz GeoLite2-City do `storage/app/geoip/` (konto MaxMind), kliknij krótki link z publicznego IP, uruchom `php artisan queue:work`, sprawdź wiersz w `link_statistics`

**Gotowe gdy:** testy zielone, a wiersz z krajem i miastem pojawia się w bazie.

---

## Zadanie 8: Zależność, kolumny klienta + `ClientDetector`

**Po co:** dane o kliencie i jedno miejsce, które zamienia User-Agent na trzy nazwy.

**Pliki**
- Nowe: migracja `add_client_columns_to_link_clicks_table`, `app/Support/ClientDetector.php`, `tests/Unit/ClientDetectorTest.php`
- Zmiana: `composer.json`, `composer.lock`, `app/Models/LinkClick.php`, `database/factories/LinkClickFactory.php`, `tests/Feature/LinkClickModelTest.php`

**Daje dalej:** `LinkClick` z polami `browser`, `os`, `device_type` (nullable string); `ClientDetector::detect(?string $userAgent): array` o kształcie `array{browser: ?string, os: ?string, device_type: ?string}` (w PHPDoc). Klasa **nie** jest `final` (mockowana w zadaniu 9).

- [ ] **8.1** Zainstaluj: `composer require matomo/device-detector --no-interaction`
- [ ] **8.2** Napisz test `tests/Unit/ClientDetectorTest.php` (`uses(TestCase::class)` nie jest potrzebne, klasa nie używa kontenera):
  - `detects browser, os and device type of a desktop browser` (Chrome na Windows → `Chrome` / `Windows` / `desktop`)
  - `detects a smartphone` (iPhone Safari → `Mobile Safari` lub `Safari`, `iOS`, `smartphone`; asercja na `os` i `device_type` oraz `browser` niepusty)
  - `marks bots as bot without browser and os` (np. `Googlebot` → `null` / `null` / `bot`) → #7
  - `returns nulls for a missing or empty user agent` (`null` i `''`) → #6
- [ ] **8.3** Uruchom: `php artisan test --compact tests/Unit/ClientDetectorTest.php`. Oczekiwane: **FAIL**
- [ ] **8.4** Zaimplementuj `detect`: `null` lub pusty (po `trim`) → trzy `null`; inaczej `DeviceDetector($userAgent)` + `parse()`; `isBot()` → `['browser' => null, 'os' => null, 'device_type' => 'bot']`; wpp `getClient('name')`, `getOs('name')`, `getDeviceName()`; wartość nierozpoznana (`UNK`, pusty string, tablica) mapuj na `null`
- [ ] **8.5** Uruchom test. Oczekiwane: **PASS**
- [ ] **8.6** Dopisz do `LinkClickModelTest` test `client fields may be null` (analogiczny do testu geo)
- [ ] **8.7** Migracja: `php artisan make:migration add_client_columns_to_link_clicks_table --table=link_statistics --no-interaction`; dodaj `string('browser')->nullable()`, `string('os')->nullable()`, `string('device_type')->nullable()`; `down()` usuwa kolumny
- [ ] **8.8** `LinkClick`: dopisz pola do `#[Fillable]` i `@property`. Factory: `Chrome` / `Windows` / `desktop`
- [ ] **8.9** Uruchom `LinkClickModelTest` i `ClientDetectorTest`. Oczekiwane: **PASS**
- [ ] **8.10** Pint, potem commit: `Add ClientDetector and client columns to link_statistics`

**Gotowe gdy:** `ClientDetectorTest` (4 testy) i `LinkClickModelTest` zielone.

---

## Zadanie 9: Job i redirect przekazują User-Agent

**Po co:** zapis klienta przy każdym kliknięciu.

**Pliki**
- Zmiana: `app/Jobs/RecordLinkClick.php`, `app/Http/Controllers/RedirectLinkController.php`, `tests/Feature/RecordLinkClickTest.php`, `tests/Feature/LinkRedirectTest.php`

**Bierze:** `ClientDetector::detect(?string): array`.
**Daje dalej:** `RecordLinkClick::__construct(public string $linkId, public string $ip, public CarbonInterface $clickedAt, public ?string $userAgent = null)`.

- [ ] **9.1** Dopisz testy w `RecordLinkClickTest` (fake `ClientDetector` zbindowany w kontenerze):
  - `records browser, os and device type of the client`
  - `records null client fields when there is no user agent` → #6
  - `does not persist the user agent` (atrybuty `LinkClick` nie zawierają surowego UA)
- [ ] **9.2** W `LinkRedirectTest` dopisz `redirect passes the user agent to the job` (`Queue::fake()`, żądanie z nagłówkiem `User-Agent`, `Queue::assertPushed(... fn ($job) => $job->userAgent === '...')`)
- [ ] **9.3** Uruchom oba pliki. Oczekiwane: nowe testy **FAIL**
- [ ] **9.4** Job: dodaj parametr `userAgent`; w `handle(GeoLocator $geoLocator, ClientDetector $clientDetector)` dołącz `...$clientDetector->detect($this->userAgent)` do `create`
- [ ] **9.5** Kontroler: przekaż `$request->userAgent()` jako czwarty argument
- [ ] **9.6** Uruchom oba pliki. Oczekiwane: **PASS** (także dotychczasowe testy joba, mają dostać fake `ClientDetector` albo działać na realnym z `null` UA)
- [ ] **9.7** Pint, potem commit: `Record browser, os and device type on every click`

**Gotowe gdy:** `RecordLinkClickTest` i `LinkRedirectTest` w całości zielone.

---

## Zadanie 10: Statystyki klienta w backendzie i UI + E2E

**Po co:** właściciel widzi przeglądarki, systemy i urządzenia.

**Pliki**
- Zmiana: `app/Http/Controllers/LinkStatsController.php`, `resources/js/pages/links/Stats.vue`, `tests/Feature/LinkStatsTest.php`, `tests/e2e/stats.spec.ts`

**Bierze:** pola `browser`, `os`, `device_type` z zadania 8; format `StatsRow` z zadania 5.
**Daje:** nowe propsy `links/Stats`: `browsers`, `systems`, `devices`, każdy `list<array{name: string, count: int}>`, top 10, malejąco, `null` → `Unknown`.

- [ ] **10.1** Dopisz testy w `LinkStatsTest`:
  - `browsers, systems and devices are counted and ordered` (kilka kliknięć z różnymi wartościami)
  - `null client fields are shown as Unknown` → #8
  - `client tables are capped at the top 10`
  - `client stats respect the range and the link` (poza zakresem i cudzy link niewliczone)
- [ ] **10.2** Uruchom: `php artisan test --compact tests/Feature/LinkStatsTest.php`. Oczekiwane: nowe testy **FAIL**
- [ ] **10.3** Kontroler: wydziel prywatną metodę `topBy(string $column): Collection` (zapytanie `groupBy` + `orderByDesc('total')` + `limit(10)`, `null` → `Unknown`) i użyj jej dla `browsers` (`browser`), `systems` (`os`), `devices` (`device_type`). Kraje zostają bez zmian (inna etykieta nie jest potrzebna)
- [ ] **10.4** Uruchom `LinkStatsTest`. Oczekiwane: **PASS**
- [ ] **10.5** `Stats.vue`: nowe propsy; trzy kolejne tabele w tej samej siatce, ta sama pętla co kraje i miasta
- [ ] **10.6** `tests/e2e/stats.spec.ts`: seed kliknięć zawiera klienta z factory (`Chrome`, `Windows`, `desktop`); asercje, że widać komórki `Chrome`, `Windows`, `desktop`
- [ ] **10.7** `npm run build`, potem `npx playwright test`. Oczekiwane: **PASS**
- [ ] **10.8** Pint, `npx prettier --single-quote --write` na zmienionych plikach frontu, potem commit: `Show browser, os and device stats on the link stats page`

**Gotowe gdy:** `LinkStatsTest` i cały zestaw E2E zielone, a pełny `php artisan test --compact` bez regresji.

---

## Zadanie 11: Filtry statystyk w backendzie

**Po co:** zawężanie wszystkich statystyk linku po kraju, mieście, przeglądarce, systemie i urządzeniu.

**Pliki**
- Zmiana: `app/Http/Requests/Links/LinkStatsRequest.php`, `app/Http/Controllers/LinkStatsController.php`, `tests/Feature/LinkStatsTest.php`

**Bierze:** zapytanie bazowe `$clicks` i metodę `topBy` z zadania 10.
**Daje dalej (nowe propsy `links/Stats`):**

| Prop | Typ |
|---|---|
| `filters` | `array{country: ?string, city: ?string, browser: ?string, os: ?string, device_type: ?string}` |
| `options` | `array{countries: list<string>, cities: list<string>, browsers: list<string>, systems: list<string>, devices: list<string>}`, sortowane rosnąco, `Unknown` na końcu, limit 200 |

Mapowanie pól: `country` → kolumna `country`, `city` → `city`, `browser` → `browser`, `os` → `os`, `device_type` → `device_type`. Klucze `options` odpowiadają polom w liczbie mnogiej (`countries`, `cities`, `browsers`, `systems`, `devices`).

- [ ] **11.1** Dopisz testy w `LinkStatsTest`:
  - `each filter narrows the total and the tables` (dataset: pięć pól, kliknięcia z różnymi wartościami)
  - `filters combine with AND`
  - `the Unknown filter matches clicks with null values` → #9
  - `the city filter matches the plain city name` (np. `Warsaw` zawęża, a `Warsaw, Poland` nie) → #10
  - `options list the distinct values of the link within the range` (poza zakresem i cudzy link niewliczone, `Unknown` na końcu) → #11
  - `options do not depend on the other filters`
  - `filters echo the current values and default to null`
  - `a filter value longer than 255 characters fails validation`
- [ ] **11.2** Uruchom: `php artisan test --compact tests/Feature/LinkStatsTest.php`. Oczekiwane: nowe testy **FAIL**
- [ ] **11.3** `LinkStatsRequest`: reguły `nullable|string|max:255` dla pięciu pól; metoda `filters(): array` zwracająca pięć kluczy (brak lub pusty string → `null`)
- [ ] **11.4** Kontroler: zmapuj niepuste filtry na `where` (`Unknown` → `whereNull`) i dołóż je do `$clicks`, tak by objęły `daily`, `topBy` i miasta. Do `options` użyj osobnej bazy zapytania z samym zakresem dni (bez filtrów): `select($column)->distinct()->orderBy($column)->limit(200)`, `null` zamienione na `Unknown` i przeniesione na koniec
- [ ] **11.5** Uruchom `LinkStatsTest`. Oczekiwane: **PASS**
- [ ] **11.6** Pint i PHPStan (`vendor/bin/phpstan analyse --memory-limit=1G`, dozwolony tylko znany błąd `UserFactory`), potem commit: `Add filters to link stats`

**Gotowe gdy:** `LinkStatsTest` zielony, brak nowych błędów PHPStan.

---

## Zadanie 12: Filtry statystyk w UI + E2E

**Po co:** użytkownik ustawia filtry na stronie statystyk.

**Pliki**
- Zmiana: `resources/js/pages/links/Stats.vue`, `tests/e2e/stats.spec.ts`

**Bierze:** propsy `filters` i `options` z zadania 11; trasa `stats` z Wayfinder.

- [ ] **12.1** Dopisz do `stats.spec.ts` przepływ: seed z klikami z dwóch przeglądarek (np. `Chrome` i `Firefox`, przez factory) i dwóch krajów; wybór `Firefox` w selecie "Browser" zmienia sumę i pokazuje znacznik `Browser: Firefox`; kliknięcie "7 days" zachowuje `browser=Firefox` w URL; ustawienie drugiego filtru (kraj) pokazuje dwa znaczniki; usunięcie znacznika przeglądarki zostawia filtr kraju, usuwa `browser` z URL i zmienia sumę; "Clear all" usuwa wszystkie filtry, przywraca pełną sumę i zostawia `days=7`; bez filtrów brak znaczników i przycisku "Clear all"
- [ ] **12.2** Uruchom: `npx playwright test tests/e2e/stats.spec.ts`. Oczekiwane: nowy przepływ **FAIL**
- [ ] **12.3** `Stats.vue`: propsy `filters`, `options`; pięć `<select>` z opcją "All"; jedna funkcja `visit(filters)` woła `router.get` z aktualnym `days` i niepustymi filtrami, `preserveState`, `preserveScroll`; zmiana selecta, usunięcie znacznika i reset korzystają z niej (usunięcie = filtr ustawiony na `null`, reset = wszystkie `null`); pod selectami znaczniki aktywnych filtrów (etykieta pola + wartość) z przyciskiem `×` i dostępną nazwą (np. `aria-label="Remove Browser filter"`); przycisk "Clear all" widoczny tylko gdy jakiś filtr jest ustawiony; linki 7/30/90 dni dołączają aktywne filtry do zapytania
- [ ] **12.4** `npm run build`, potem `npx playwright test`. Oczekiwane: **PASS**
- [ ] **12.5** `npx prettier --single-quote --write` na zmienionych plikach, pełne `php artisan test --compact`, potem commit: `Add filters to the link stats page`

**Gotowe gdy:** cały zestaw E2E i `php artisan test --compact` zielone.

---

## Zadanie 13: `StatsPeriod` i walidacja okresu

**Po co:** jedno miejsce, które zamienia parametry URL na zakres dat i ziarnistość wykresu.

**Pliki**
- Nowe: `app/Support/StatsPeriod.php`, `tests/Unit/StatsPeriodTest.php`
- Zmiana: `app/Http/Requests/Links/LinkStatsRequest.php`, `tests/Feature/LinkStatsTest.php` (tylko testy walidacji)

**Daje dalej:**
- `StatsPeriod::resolve(array $input, ?CarbonInterface $firstClick, CarbonInterface $now): self`. `$input` ma kształt `array{period: string, date: ?string, month: ?string, year: ?string, from: ?string, to: ?string}`. Klasa **nie** jest `final`, nie używa kontenera, czas dostaje w argumencie.
- Właściwości publiczne readonly: `?CarbonImmutable $from` (`null` = pusty zakres, tylko `all` bez kliknięć), `CarbonImmutable $to`, `string $granularity` (`'day'|'month'`).
- `LinkStatsRequest::period(): array` zwraca `$input` powyżej (brak parametru → `null`, `period` domyślnie `30d`). Stała `LinkStatsRequest::PERIODS = ['7d', '30d', '90d', 'all', 'day', 'month', 'year', 'range']`.

Reguły zakresu (spec, sekcja "Okres"): presety = ostatnie N dni włącznie z dzisiejszym od początku dnia; `all` = od `$firstClick` (początek dnia) do `$now`; `day`/`month`/`year` = cały dzień/miesiąc/rok; `range` = od początku `from` do końca `to`. Koniec zakresu = `min(koniec, $now)`. `granularity = 'month'` gdy zakres ma więcej niż 92 dni lub `period` to `year`/`all`, inaczej `'day'`.

- [ ] **13.1** Napisz `tests/Unit/StatsPeriodTest.php` (stały `$now`, np. `2026-10-09 12:00`):
  - `presets cover the last N days including today` (`30d` → `from` = `2026-09-10 00:00`, `to` = `$now`, `day`)
  - `all starts at the first click` oraz `all without clicks is empty` (`from` = `null`) → #12
  - `day, month and year cover the whole unit` (np. `month=2026-02` → `2026-02-01 00:00` … `2026-02-28 23:59:59`)
  - `the end never goes past now` (`month=2026-10` → `to` = `$now`) → #16
  - `range runs from the start of from to the end of to`
  - `granularity is month above 92 days and for year and all` (zakres 92 dni → `day`, 93 → `month`) → #15
- [ ] **13.2** Uruchom: `php artisan test --compact tests/Unit/StatsPeriodTest.php`. Oczekiwane: **FAIL**
- [ ] **13.3** Zaimplementuj `StatsPeriod::resolve` w `app/Support/StatsPeriod.php`
- [ ] **13.4** Uruchom test. Oczekiwane: **PASS**
- [ ] **13.5** Dopisz testy walidacji w `LinkStatsTest` (każdy: `assertSessionHasErrors('<pole>')`):
  - `period must be one of the allowed values` (`period=week` → `period`)
  - `day, month, year and range require their parameters` (dataset: `period=day` bez `date`, `month` bez `month`, `year` bez `year`, `range` bez `from`/`to`) → #13
  - `date formats are validated` (`date=09-10-2026`, `month=2026-13`, `year=26`) → #13
  - `range must not be reversed or longer than 5 years` (`from > to`, `from=2020-01-01&to=2026-01-01`) → #14
- [ ] **13.6** Uruchom `LinkStatsTest` (nowe testy **FAIL**; stare nadal `days`, dotykamy ich w zadaniu 14)
- [ ] **13.7** `LinkStatsRequest`: reguły `period` (`sometimes`, `Rule::in(PERIODS)`), `date` (`required_if:period,day`, `date_format:Y-m-d`), `month` (`required_if:period,month`, `date_format:Y-m`), `year` (`required_if:period,year`, `digits:4`), `from`/`to` (`required_if:period,range`, `date_format:Y-m-d`; `from` → `before_or_equal:to`; reguła-domknięcie na `to`: najwyżej 5 lat od `from`). Dopisz `period()`. **Nie usuwaj jeszcze** `days()`, kontroler z niego korzysta do zadania 14
- [ ] **13.8** Uruchom oba pliki. Oczekiwane: **PASS** nowe testy walidacji i jednostkowe; reszta bez regresji
- [ ] **13.9** Pint i PHPStan (jak w 11.6), potem commit: `Add StatsPeriod and period validation`

**Gotowe gdy:** `StatsPeriodTest` i testy walidacji zielone, brak nowych błędów PHPStan.

---

## Zadanie 14: Okres w kontrolerze

**Po co:** wszystkie liczby na stronie liczone dla wybranego okresu.

**Pliki**
- Zmiana: `app/Http/Controllers/LinkStatsController.php`, `app/Http/Requests/Links/LinkStatsRequest.php` (usunięcie `days()` i reguły `days`), `tests/Feature/LinkStatsTest.php`

**Bierze:** `StatsPeriod::resolve`, `LinkStatsRequest::period()`.
**Daje dalej (propsy `links/Stats`):**

| Prop | Typ |
|---|---|
| `period` | echo z `LinkStatsRequest::period()` (`period` zawsze ustawiony) |
| `granularity` | `'day'\|'month'` |
| `series` | `list<array{date: string, count: int}>`: `date` = `Y-m-d` (`day`) lub `Y-m` (`month`), rosnąco, bez dziur |
| `total` | suma `series` |

Znikają propsy `days` i `daily`. `inRange` filtruje `clicked_at >= from` i `<= to` (puste `from` → zawsze zero wyników), więc obejmuje też `options`.

- [ ] **14.1** Przepisz istniejące testy z `days` na `period` (`days=7` → `period=7d`, `days=5` → `period=week`, `daily` → `series`) i dopisz:
  - `period defaults to 30d`
  - `day, month, year and range limit the clicks` (kliknięcia na granicach: `00:00:00` i `23:59:59` wchodzą, sekunda obok nie)
  - `all covers every click of the link and ignores other links`
  - `all without clicks returns an empty series and zero total` → #12
  - `series is monthly above 92 days and for year and all` (`granularity`, klucze `Y-m`, zera dla miesięcy bez kliknięć) → #15
  - `a range in the future returns zeros` → #16
  - `period applies to totals, tables and options`
- [ ] **14.2** Uruchom: `php artisan test --compact tests/Feature/LinkStatsTest.php`. Oczekiwane: **FAIL**
- [ ] **14.3** Kontroler: `$period = StatsPeriod::resolve($request->period(), $link->clicks()->min('clicked_at') ... , now())`; `inRange` używa `from`/`to`. Seria: zapytanie dalej grupuje po `DATE(clicked_at)` (działa na każdym sterowniku); dla `month` dni sumuj do `Y-m` w PHP. Uzupełnij zerami od `from` do `to` w kroku `granularity`. Usuń `days()` i regułę `days` z `LinkStatsRequest`
- [ ] **14.4** Uruchom `LinkStatsTest`. Oczekiwane: **PASS**
- [ ] **14.5** `Stats.vue` i typy w `resources/js/types` przejdą na nowy kształt w zadaniu 16. Tymczasowo zmień tylko tyle, by `npm run build` przechodził (`daily` → `series`, `days` → `period.period`)
- [ ] **14.6** Pint i PHPStan, potem commit: `Resolve link stats range from a flexible period`

**Gotowe gdy:** `LinkStatsTest` zielony, build frontu przechodzi, brak nowych błędów PHPStan.

---

## Zadanie 15: Sortowanie i paginacja tabel w backendzie

**Po co:** wszystkie wiersze tabel dostępne stronami, z wyborem limitu i sortowania.

**Pliki**
- Zmiana: `app/Http/Requests/Links/LinkStatsRequest.php`, `app/Http/Controllers/LinkStatsController.php`, `tests/Feature/LinkStatsTest.php`

**Daje dalej:**
- `LinkStatsRequest::TABLES = ['countries', 'cities', 'browsers', 'systems', 'devices']`, `LinkStatsRequest::PER_PAGE = [10, 25, 50, 100]`, `LinkStatsRequest::SORTS = ['count_desc', 'count_asc', 'name_asc', 'name_desc']`.
- `LinkStatsRequest::sort(string $table): string` (domyślnie `count_desc`), `perPage(string $table): int` (domyślnie `10`), `page(string $table): int` (domyślnie `1`).
- Parametry URL: `sort[<tabela>]`, `<tabela>_per_page`, `<tabela>_page`.
- Propsy: każda z `countries`, `cities`, `browsers`, `systems`, `devices` ma kształt `array{rows: list<array{name: string, count: int}>, page: int, lastPage: int, total: int, perPage: int}` (zastępuje płaską listę). Nowy prop `sort`: `array<string, string>` z wartością dla wszystkich pięciu tabel.

- [ ] **15.1** Przepisz testy tabel z zadań 5, 10 i 11 na nowy kształt (`countries` → `countries.rows`, limit „top 10” → `perPage` = 10 i `total`) i dopisz:
  - `each per page value changes rows and lastPage` (dataset `10|25|50|100`, 30 różnych miast)
  - `the last page holds the remainder` oraz `a page past the end returns the last page` → #17
  - `table pages and per page are independent` (`countries_page=2` nie rusza `cities`)
  - `rows are sorted by <variant>` (dataset 4 wartości `sort`, w tym `Unknown` sortowane po nazwie) → #19
  - `ties keep the same order across pages` (30 wierszy po 1 kliknięciu, strona 1 + 2 + 3 = pełna lista bez powtórek) → #20
  - `sort echoes the current values and defaults to count_desc`
  - `invalid sort, per page or page fails validation` (`sort[countries]=x`, `countries_per_page=7`, `countries_page=0`, `countries_page=abc`) → #18
  - `pagination respects period and filters` (`total` tabeli odpowiada zawężonym danym)
- [ ] **15.2** Uruchom: `php artisan test --compact tests/Feature/LinkStatsTest.php`. Oczekiwane: nowe testy **FAIL**
- [ ] **15.3** `LinkStatsRequest`: reguły dla `sort.<tabela>` (`Rule::in(SORTS)`), `<tabela>_per_page` (`Rule::in(PER_PAGE)`), `<tabela>_page` (`integer`, `min:1`) generowane z `TABLES`; metody `sort`, `perPage`, `page`
- [ ] **15.4** Kontroler: zastąp `topBy` metodą `table(Closure $clicks, string $table, string $column, LinkStatsRequest $request): array`, a miasta niech używają tej samej ścieżki (grupowanie `city, country`, etykieta `City, Country`). Sortowanie po nazwie: `orderByRaw("COALESCE(<kolumna>, 'Unknown') <kierunek>")`, dla miast potem `country`; zawsze wtórne sortowanie po nazwie rosnąco (deterministyczne strony). Paginacja: `paginate($perPage, page: <strona przycięta do lastPage>, pageName: "{$table}_page")`. Sortuj po kolumnie z białej listy, nigdy po surowym wejściu
- [ ] **15.5** Uruchom `LinkStatsTest`. Oczekiwane: **PASS**
- [ ] **15.6** Pint i PHPStan, potem commit: `Add sorting and pagination to link stats tables`

**Gotowe gdy:** `LinkStatsTest` zielony, brak nowych błędów PHPStan.

---

## Zadanie 16: UI okresu, sortowania, paginacji i limitów + E2E

**Po co:** użytkownik steruje okresem i tabelami na stronie statystyk.

**Pliki**
- Nowe: `resources/js/components/StatsTable.vue`
- Zmiana: `resources/js/pages/links/Stats.vue`, typy w `resources/js/types` (`StatsPeriod`, `StatsTableData`, `StatsSorts`), `tests/e2e/stats.spec.ts`

**Bierze:** propsy z zadań 14 i 15; trasa `stats` z Wayfinder (bez zmian).

- [ ] **16.1** Dopisz do `stats.spec.ts` (seed przez factory: 25 linków-klików z różnych miast, kliknięcia z różnych miesięcy i lat):
  - zmiana okresu: „All time” zmienia sumę; `Month` + wybór miesiąca zawęża sumę, URL ma `period=month&month=...`; `Range` z dwiema datami zawęża sumę
  - sortowanie: klik nagłówka „Name” w tabeli Cities zmienia kolejność wierszy, drugi klik ją odwraca; URL ma `sort[cities]=name_asc` (zakodowane)
  - paginacja: „Page 1 of 3”, „Next” pokazuje „Page 2 of 3”; zmiana „Rows” na 50 daje „Page 1 of 1” i znikają przyciski Previous/Next
  - reset: zmiana okresu lub sortowania wraca na „Page 1”, ale zachowuje wybrany limit → #21
- [ ] **16.2** Uruchom: `npx playwright test tests/e2e/stats.spec.ts`. Oczekiwane: nowe przepływy **FAIL**
- [ ] **16.3** `StatsTable.vue`: propsy `title`, `table: StatsTableData`, `sort: string`; emituje `sort(value)`, `page(n)`, `perPage(n)`. Nagłówki „Name” i „Clicks” z strzałką kierunku (klik przełącza asc/desc), pod tabelą select „Rows” (10/25/50/100, widoczny zawsze), Previous/Next (ukryte przy jednej stronie) i „Page X of Y (N rows)”
- [ ] **16.4** `Stats.vue`: selektor okresu (presety 7/30/90 i „All time”, select `Day | Month | Year | Range` z natywnymi `<input type="date|month">`, polem roku i dwoma polami `date`); jedna funkcja `visit(overrides)` składa całe zapytanie (okres, filtry, sortowania, limity, strony), `preserveState`, `preserveScroll`. Zmiana okresu, filtra lub sortowania zeruje strony wszystkich tabel, zmiana limitu zeruje stronę tej tabeli. Pięć sekcji używa `StatsTable`. Tooltip wykresu przy `granularity = 'month'` pokazuje `Y-m`
- [ ] **16.5** `npm run build`, potem `npx playwright test`. Oczekiwane: **PASS**
- [ ] **16.6** `npx prettier --single-quote --write` na zmienionych plikach frontu; poproś użytkownika o pełne `php artisan test --compact`, potem commit: `Add period selector, sorting and pagination to the link stats page`

**Gotowe gdy:** cały zestaw E2E i `php artisan test --compact` zielone.
