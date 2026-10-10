# Kody QR dla linków — plan implementacji

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Użytkownik generuje i pobiera kod QR (SVG) osobno dla kodu i aliasu linku, a statystyki pokazują sekcję "Source" (Link / QR).

**Architecture:** Endpoint `links.qr` renderuje SVG z `chillerlan/php-qrcode` dla adresu linku z `?qr=1`. Redirect rozpoznaje `?qr=1` i zapisuje w nowej kolumnie `link_statistics.source` wartość `QR` lub `Link`. Raport statystyk dostaje sekcję `sources` według wzorca sekcji `entries`.

**Tech Stack:** Laravel 13 / PHP 8.5, Pest, Inertia v3 + Vue, Wayfinder, `chillerlan/php-qrcode` (MIT lub Apache-2.0).

**Spec:** `docs/superpowers/specs/2026-10-10-link-qr-code-design.md`

## Global Constraints

- Nowa zależność tylko `chillerlan/php-qrcode` (licencja MIT; sprawdź `composer show chillerlan/php-qrcode` po instalacji). Żadnych innych zależności.
- Tylko SVG, bez `gd` i `imagick`; obrazów nie zapisujemy ani nie cache'ujemy.
- Parametr skanu to `?qr=1`; wartości kolumny `source`: `Link`, `QR` (`string(4)`); wiersze bez źródła liczą się jako `Link`.
- Autoryzacja endpointu QR: `Gate::authorize('update', $link)`.
- Klucze UUID, kod po angielsku w stylu sąsiednich plików, Pint przed commitem (`vendor/bin/pint --dirty --format agent`).
- Testy: Pest feature, `php artisan test --compact <plik>`; po zielonych testach feature poproś użytkownika o pełny zestaw.

## Review Focus

- `?qr=0`, `?qr=abc` i brak parametru nie liczą się jako skan (test w Task 3).
- Skan QR aliasu zapisuje `entry = Alias` oraz `source = QR` (test w Task 3).
- Link na własnej domenie koduje adres z hostem tej domeny, nie głównej (test w Task 1).
- Link bez aliasu: `alias` → 404; nieznany `type` → 404 (test w Task 1).
- Admin i cudzy użytkownik nie pobiorą QR cudzego linku — 403 (test w Task 1).

---

### Task 1: Endpoint QR

**Files:**
- Modify: `composer.json`, `composer.lock` (przez `composer require chillerlan/php-qrcode`)
- Create: `app/Http/Controllers/LinkQrController.php`, `tests/Feature/LinkQrTest.php`
- Modify: `app/Models/Link.php`, `routes/web.php:38` (obok `links.toggle`)

**Interfaces:**
- Produces: `Link::qrUrl(LinkEntry $entry): ?string` — `short_url.'?qr=1'` dla `Code`, `alias_url.'?qr=1'` dla `Alias` (null bez aliasu); trasa `GET links/{link}/qr/{type}` o nazwie `links.qr`, `type` ∈ `code|alias`; kontroler `LinkQrController::__invoke(Request $request, Link $link, string $type): Response`.

- [ ] **Step 1: Zainstaluj bibliotekę.** `composer require chillerlan/php-qrcode`, potwierdź licencję MIT w `composer show chillerlan/php-qrcode`. Sprawdź w dokumentacji zainstalowanej wersji (`vendor/chillerlan/php-qrcode`), jak wyrenderować surowy SVG (nie data URI) bez rozszerzeń graficznych — opcje `QROptions` typu wyjścia SVG i wyłączenia base64.
- [ ] **Step 2: Napisz testy w `tests/Feature/LinkQrTest.php`** (właściciel: `User::factory()` z uprawnieniem `LinksManage` jak w `LinkCrudTest`; alias przez `Link::factory()->create(['alias' => 'moj-alias'])`):
  - `qrUrl` zwraca `$link->short_url.'?qr=1'` dla `LinkEntry::Code`, `$link->alias_url.'?qr=1'` dla `Alias`, `null` dla `Alias` bez aliasu; dla linku z `Domain::factory()` adres zaczyna się od `$domain->url()`.
  - właściciel `GET route('links.qr', [$link, 'code'])` → 200, `Content-Type` zaczyna się od `image/svg+xml`, treść zawiera `<svg`.
  - to samo dla `alias` przy linku z aliasem; `alias` bez aliasu → 404; `route('links.qr', [$link, 'other'])` → 404.
  - `?download=1` → nagłówek `Content-Disposition` zawiera `attachment` i `filename="{code}.svg"`; dla aliasu `filename="moj-alias.svg"`; bez parametru brak `attachment`.
  - cudzy użytkownik → 403; gość → redirect do `route('login')`.
- [ ] **Step 3: Uruchom testy — mają zawieść.** `php artisan test --compact tests/Feature/LinkQrTest.php` → FAIL (brak trasy / metody).
- [ ] **Step 4: Zaimplementuj** `Link::qrUrl` (obok `urlFor`), `LinkQrController` (jednoakcyjny, `Gate::authorize('update', $link)`, 404 gdy `qrUrl` zwróci null, odpowiedź `response($svg, 200, ['Content-Type' => 'image/svg+xml'])`, przy `$request->boolean('download')` nagłówek `Content-Disposition` przez `Response::download`-owy odpowiednik dla stringa: `'attachment; filename="'.$name.'.svg"'`) oraz trasę w grupie `auth, verified`: `Route::get('links/{link}/qr/{type}', LinkQrController::class)->whereIn('type', ['code', 'alias'])->name('links.qr');`. Typ → `LinkEntry` przez `LinkEntry::from(ucfirst($type))`.
- [ ] **Step 5: Uruchom testy — mają przejść**, potem `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 6: Commit** — `git add composer.json composer.lock app routes tests && git commit -m "Add QR code endpoint for links"`.

---

### Task 2: Modal QR w liście linków

**Files:**
- Modify: `resources/js/pages/links/Index.vue` (kolumna akcji, obok przycisków Stats/Edit/Delete)

**Interfaces:**
- Consumes: trasa `links.qr` (Task 1); import z Wayfinder `qr` z `@/routes/links` (wygeneruj: `php artisan wayfinder:generate`, jeśli Vite nie działa); pola `link.code`, `link.alias`, `link.short_url`, `link.alias_url` z typu `Link`.

- [ ] **Step 1: Dodaj przycisk "QR" i `Dialog`** (komponenty `@/components/ui/dialog` już zaimportowane w pliku, wzorzec jak przy "Delete"). Modal: tytuł "QR code", przy linku z aliasem dwa przyciski przełączające "Code" / "Alias" (ref `type` per modal, brak komponentu Tabs w projekcie — nie dodawaj go), `<img>` z `qr(...).url` dla wybranego typu, pod nim adres kodowany (`short_url` lub `alias_url`) i link "Download SVG" (`<a :href>` z `?download=1`, `download`). Zakładka "Alias" tylko gdy `link.alias !== null`.
- [ ] **Step 2: Zweryfikuj w przeglądarce** (`composer run dev` lub `npm run build`): modal otwiera się, przełącza typy, obraz się ładuje, pobranie daje plik `.svg`. Zeskanuj lub zdekoduj kod telefonem/czytnikiem i sprawdź adres z `?qr=1`.
- [ ] **Step 3: Commit** — `git add resources/js/pages/links/Index.vue && git commit -m "Show link QR codes in a modal on the links list"`. (Zmiana czysto widokowa — bez nowych testów.)

---

### Task 3: Zapis źródła kliknięcia

**Files:**
- Create: `app/Enums/LinkSource.php`, dwie migracje (`php artisan make:migration --no-interaction`): `add_source_to_link_statistics_table`, `backfill_source_on_link_statistics_table`; `tests/Feature/BackfillClickSourceMigrationTest.php`
- Modify: `app/Models/LinkClick.php`, `app/Jobs/RecordLinkClick.php`, `app/Http/Controllers/RedirectLinkController.php`, `tests/Feature/LinkRedirectTest.php`, `tests/Feature/RecordLinkClickTest.php`

**Interfaces:**
- Produces: `enum LinkSource: string { case Link = 'Link'; case Qr = 'QR'; }`; kolumna `link_statistics.source` (`string(4)`, nullable, po `entry`); `RecordLinkClick::__construct(string $linkId, string $ip, CarbonInterface $clickedAt, ?string $userAgent = null, ?string $entry = null, ?string $source = null)` z niepromowaną `public ?string $source`.

- [ ] **Step 1: Napisz testy.**
  - `LinkRedirectTest`: `?qr=1` na kodzie → job z `entry === 'Code'` i `source === 'QR'`; `?qr=1` na aliasie → `entry === 'Alias'`, `source === 'QR'`; bez parametru, `?qr=0` i `?qr=abc` → `source === 'Link'`; przekierowanie nadal prowadzi na `original_url` z `?qr=1`.
  - `RecordLinkClickTest`: `dispatchSync` bez argumentu źródła zapisuje `source = 'Link'`; z `'QR'` zapisuje `QR`.
  - `BackfillClickSourceMigrationTest` (wzorzec `BackfillClickEntryMigrationTest`): `NULL` → `Link`, zapisane `QR` zostaje.
- [ ] **Step 2: Uruchom — mają zawieść.** `php artisan test --compact tests/Feature/LinkRedirectTest.php tests/Feature/RecordLinkClickTest.php tests/Feature/BackfillClickSourceMigrationTest.php`.
- [ ] **Step 3: Zaimplementuj** enum, migracje (`up` jak w migracjach `entry`; backfill `whereNull('source')->update(['source' => 'Link'])`, `down()` pusty z komentarzem jak w backfillu `entry`), `source` w `#[Fillable]` i `@property` w `LinkClick`, argument/właściwość w `RecordLinkClick` (komentarz jak przy `entry`: zadania z kolejki sprzed zmiany; `handle()` zapisuje `'source' => $this->source ?? LinkSource::Link->value`), w `RedirectLinkController` wyliczenie `$source = $request->boolean('qr') ? LinkSource::Qr : LinkSource::Link` i przekazanie `->value` do `dispatch`.
- [ ] **Step 4: Uruchom testy — mają przejść**, potem Pint.
- [ ] **Step 5: Commit** — `git add app database tests && git commit -m "Record whether a click came from a QR code"`.

---

### Task 4: Sekcja `sources` w raporcie

**Files:**
- Modify: `app/Support/LinkStatsReport.php` (`TABLE_COLUMNS`, `orderSql()`, klucze w `options()`), `app/Http/Requests/Links/LinkStatsRequest.php` (`FILTERS`, `TABLES`, `SECTIONS`)
- Test: `tests/Feature/LinkStatsSourceTest.php`

**Interfaces:**
- Consumes: kolumna `source` (Task 3).
- Produces: sekcja payloadu `sources` (kształt jak `entries`), filtr `source`, `options.sources`; `sort.sources`, parametry `sources_per_page`, `sources_page`.

- [ ] **Step 1: Napisz testy** wzorowane na `tests/Feature/LinkStatsEntryTest.php` (helpery o innych nazwach, żeby nie kolidować): sekcja `sources.rows` zwraca `Link`/`QR`/`Unknown` z poprawnymi liczbami posortowane `count_desc`; sortowanie `name_asc`/`name_desc` i paginacja z zaciśnięciem strony; filtr `source=QR` zawęża inne tabele i serię; `source=Unknown` filtruje `NULL`; filtr łączy się z `entry`; `options.sources` ma istniejące wartości, `Unknown` na końcu; `getJson` z `sections[]=sources` zwraca tylko tę sekcję; admin widzi sekcję w statystykach cudzego linku (wzorzec z `LinkStatsEntryTest`/`AdminStatsReportTest`).
- [ ] **Step 2: Uruchom — mają zawieść.** `php artisan test --compact tests/Feature/LinkStatsSourceTest.php`.
- [ ] **Step 3: Zaimplementuj** dokładnie jak `entries`: `'sources' => ['source']` w `TABLE_COLUMNS`, `'source' => "COALESCE(source, 'Unknown')"` w `orderSql()`, `'source' => 'sources'` w tablicy `$keys` w `options()`, `source` w `FILTERS`, `sources` w `TABLES` i `SECTIONS`.
- [ ] **Step 4: Uruchom testy — mają przejść** razem z istniejącymi `LinkStatsTest.php`, `LinkStatsEntryTest.php`, `LinkStatsDataTest.php`, `AdminStatsReportTest.php`; Pint.
- [ ] **Step 5: Commit** — `git add app tests && git commit -m "Add a click source section to link stats"`.

---

### Task 5: Karta "Source" w statystykach

**Files:**
- Modify: `resources/js/types/link.ts` (typy `StatsFilterKey`, `StatsOptions`, `StatsTableKey`), `resources/js/pages/links/Stats.vue` (props, `tableKeys`, `tableLabels`, `tableTitles`, `tables`, lista pól filtrów, `clearFilters`)

**Interfaces:**
- Consumes: payload z Task 4 (`sources`, filtr `source`, `options.sources`).

- [ ] **Step 1: Dodaj `source`/`sources` wszędzie tam, gdzie jest `entry`/`entries`** (miejsca wypisane w Files; etykieta kolumny "Source", tytuł karty "Source"). Karta zachowuje się jak "Short URL used".
- [ ] **Step 2: Sprawdź typy i widok.** `npm run build` (lub `npx vue-tsc --noEmit`, jeśli skrypt istnieje w `package.json`) bez błędów; w przeglądarce kliknij kilka razy adres z `?qr=1` i bez, odśwież statystyki — karta pokazuje `Link` i `QR`, filtr po `QR` działa, czyszczenie filtrów też. Sprawdź stronę admina.
- [ ] **Step 3: Commit** — `git add resources/js && git commit -m "Show the click source card on the stats page"`. (Zmiana widokowa — bez nowych testów.)

---

## Samoocena planu

- Pokrycie specu: generowanie (Task 1), UI modalu (Task 2), zapis `source`/migracje/backfill/job/redirect (Task 3), raport i request (Task 4), karta statystyk (Task 5).
- Odstępstwo od specu: test adresu zakodowanego w QR idzie przez `Link::qrUrl` (zawartości SVG nie da się wiarygodnie odczytać bez dekodera); spec mówił o „małej metodzie w modelu" — to ona.
- Zakładki w modalu to dwa przyciski, nie nowy komponent (brak Tabs w projekcie).
