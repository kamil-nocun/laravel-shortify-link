# Statystyki linków: plan implementacji

> **Dla agentów wykonawczych:** użyj superpowers:subagent-driven-development (rekomendowane) lub superpowers:executing-plans. Kroki mają checkboxy (`- [ ]`).

**Spec:** `docs/superpowers/specs/2026-10-09-link-stats-design.md`

## Cel w jednym zdaniu

Każdy udany redirect zapisuje kliknięcie (czas, kraj, miasto) przez job w kolejce; właściciel widzi statystyki 7/30/90 dni i licznik w liście linków.

## Mapa postępu

Odhaczaj po zakończeniu zadania. Jedno zadanie = jeden commit.

- [ ] **1.** Tabela `link_clicks` + model
- [ ] **2.** `GeoLocator`
- [ ] **3.** Job `RecordLinkClick`
- [ ] **4.** Dispatch z redirectu
- [ ] **5.** Backend strony statystyk
- [ ] **6.** Frontend + E2E
- [ ] **7.** Weryfikacja końcowa

**Zależności:** 1 → 2 → 3 → 4. Zadanie 5 wymaga tylko 1. Zadanie 6 wymaga 5.

## Przepływ danych

```
GET /{code} → RedirectLinkController → RecordLinkClick (kolejka)
                                         → GeoLocator → LinkClick (DB)
GET links/{link}/stats → LinkStatsController → links/Stats.vue
```

## Zasady (obowiązują w każdym zadaniu)

- UUID PK/FK. `link_clicks` bez `created_at`/`updated_at`.
- **Nigdy** nie zapisuj IP w `link_clicks`.
- `clicked_at` ustawia kontroler (czas żądania), nie job.
- Brak geo = `null` kraj/miasto, klik i tak zapisany.
- `days` ∈ `7|30|90`, domyślnie `30`.
- Nowa zależność tylko `geoip2/geoip2`. Wykres bez biblioteki.
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

---

## Zadanie 1: Tabela `link_clicks` + model

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
- [ ] **1.11** Pint, potem commit: `Add link_clicks table and LinkClick model`

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
- [ ] **7.3** Ręczny test geo (robi użytkownik): pobierz GeoLite2-City do `storage/app/geoip/` (konto MaxMind), kliknij krótki link z publicznego IP, uruchom `php artisan queue:work`, sprawdź wiersz w `link_clicks`

**Gotowe gdy:** testy zielone, a wiersz z krajem i miastem pojawia się w bazie.
