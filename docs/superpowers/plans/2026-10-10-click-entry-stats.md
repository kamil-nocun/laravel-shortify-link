# Statystyki kliknięć według użytego adresu — plan implementacji

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Zapisywać, czy kliknięcie przyszło przez kod, czy przez alias, i pokazać podział na stronie statystyk linku (użytkownika i admina).

**Architecture:** Nowa nullable kolumna `link_statistics.entry` (`Code`/`Alias`, etykiety wyświetlane jak w pozostałych kolumnach). `RedirectLinkController` ustala wejście i przekazuje je do `RecordLinkClick`. `LinkStatsReport` dostaje sekcję `entries` według istniejącego wzorca tabel, a `Stats.vue` nową kartę.

**Tech Stack:** Laravel, Pest (MySQL w testach), Inertia v3 + Vue, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-10-10-click-entry-stats-design.md`

## Global Constraints

- Gałąź `feature/custom-alias-link-filters`, nowe commity; każdy kończy się linią `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- Wartości kolumny `entry`: dokładnie `Code` albo `Alias`; `NULL` oznacza `Unknown` (stare kliknięcia i zadania z kolejki bez argumentu).
- `RecordLinkClick`: nowy argument `?string $entry = null` jako właściwość niepromowana (jak `userAgent`), żeby zadania już w kolejce nadal się odtwarzały; kolejność istniejących argumentów konstruktora i wywołań `dispatchSync($linkId, $ip, $clickedAt, $userAgent)` nie zmienia się.
- Sekcja raportu `entries`, tabela `entries`, filtr `entry`, klucz opcji `entries` — nazwy dokładnie takie; etykiety UI: karta "Short URL used", kolumna "Short URL".
- Po zmianie PHP: `vendor/bin/pint --dirty --format agent`. Testy: `php artisan test --compact <plik>`. Frontend: `npm run build`, `npm run types:check` (błędy w ignorowanym `resources/js/actions/.../RedirectController.ts` są znane i pomijane); bez zmian formatowania w niezmienianych liniach (apostrofy, 4 spacje).
- Dev baza `shortify_link` wymaga `php artisan migrate`.

## Review Focus

- Zadanie z kolejki zserializowane przed zmianą (bez właściwości `entry`) odtwarza się i zapisuje `NULL`, bez błędu. (Task 1)
- Kliknięcie przez alias nie jest oznaczone jako `Code` mimo OR-a w zapytaniu przekierowania (porównanie `$link->code === $code`, nie kolejność trafienia). (Task 1)
- Filtr `entry=Unknown` zwraca kliknięcia z `NULL`, a wartość spoza `Code|Alias|Unknown` zwraca pusty wynik zamiast błędu. (Task 2)
- Sekcja `entries` obejmuje tylko okres i filtry z żądania, tak jak pozostałe tabele (np. filtr `browser` zawęża też `entries`). (Task 2)
- Zmiana filtra `entry` na stronie statystyk odświeża pozostałe tabele i wykres tak jak filtry kraju czy przeglądarki. (Task 3)

---

### Task 1: Zapis wejścia przy kliknięciu

**Files:**
- Create: `app/Enums/LinkEntry.php`; migracja przez `php artisan make:migration add_entry_to_link_statistics_table --no-interaction`
- Modify: `app/Models/LinkClick.php`, `app/Jobs/RecordLinkClick.php`, `app/Http/Controllers/RedirectLinkController.php`
- Test: `tests/Feature/RecordLinkClickTest.php`, `tests/Feature/LinkRedirectTest.php` (lub `CustomAliasTest.php`, tam gdzie są testy aliasu)

**Interfaces:**
- Produces: `enum LinkEntry: string { Code = 'Code'; Alias = 'Alias'; }`; `link_statistics.entry` (`string(5)`, nullable, po `device_type`); `LinkClick::$entry` (`string|null`, w `#[Fillable]`); `RecordLinkClick::__construct(string $linkId, string $ip, CarbonInterface $clickedAt, ?string $userAgent = null, ?string $entry = null)` z `entry` jako właściwością niepromowaną `public ?string $entry = null`.

- [ ] **Step 1: Napisz testy.**
  - `RecordLinkClickTest.php`: `dispatchSync($link->id, '203.0.113.7', now(), 'UA', 'Alias')` zapisuje `entry = 'Alias'`; wywołanie bez argumentu zapisuje `NULL`; zadanie zserializowane i odtworzone bez właściwości `entry` (zbuduj obiekt, `unserialize` z usuniętą właściwością albo `ReflectionProperty` na niezainicjowanej — wybierz najprostszy sposób użyty już w teście dla `userAgent`, jeśli istnieje) zapisuje `NULL`.
  - Przekierowanie: `get('/'.$link->code)` kolejkuje `RecordLinkClick` z `entry = 'Code'`; `get('/'.$link->alias)` z `entry = 'Alias'` (wzoruj się na teście "successful redirect dispatches RecordLinkClick" w `LinkRedirectTest.php`, `Queue::fake()`/`Bus::fake()` i asercja na właściwościach zadania).

- [ ] **Step 2: Uruchom testy, upewnij się, że padają.**

- [ ] **Step 3: Enum, migracja, model.** Migracja dodaje `entry` jako `string(5)` nullable po `device_type`, `down()` usuwa kolumnę; `php artisan migrate --no-interaction` na dev bazie. `LinkClick`: `entry` w `#[Fillable]` i docblocku.

- [ ] **Step 4: `RecordLinkClick`** — dodaj `public ?string $entry = null;` z komentarzem (zadania z kolejki sprzed zmiany nie mają tej właściwości), argument `?string $entry = null` na końcu konstruktora przypisywany do właściwości, a `handle()` dopisuje `'entry' => $this->entry` do tworzonego rekordu.

- [ ] **Step 5: `RedirectLinkController`** — po znalezieniu linku wylicz `$entry = $link->code === $code ? LinkEntry::Code : LinkEntry::Alias` i przekaż `$entry->value` jako piąty argument `RecordLinkClick::dispatch(...)`.

- [ ] **Step 6: Uruchom `RecordLinkClickTest`, `LinkRedirectTest`, `DomainRedirectTest`, `CustomAliasTest`, `LinkClickModelTest` — przechodzą.** Pint.

- [ ] **Step 7: Commit** — `Record whether a click came through the code or the alias`.

### Task 2: Sekcja `entries` w raporcie statystyk

**Files:**
- Modify: `app/Support/LinkStatsReport.php`, `app/Http/Requests/Links/LinkStatsRequest.php`
- Test: `tests/Feature/LinkStatsEntryTest.php` (nowy plik, bo `LinkStatsTest.php` ma 25 kB); drobne poprawki istniejących testów, jeśli wyliczają listę sekcji lub tabel

**Interfaces:**
- Consumes: `link_statistics.entry` i `LinkClick::$entry` (Task 1).
- Produces: sekcja payloadu `entries` w kształcie istniejących tabel (`{rows: [{name, count}], page, lastPage, total, perPage}`); filtr żądania `entry`; `options.entries: list<string>`; `LinkStatsRequest::FILTERS` zawiera `entry`, `TABLES` i `SECTIONS` zawierają `entries`; `sort` i `filters` w payloadzie obejmują `entries`/`entry`.

- [ ] **Step 1: Napisz `LinkStatsEntryTest.php`** (fabryka `LinkClick`, wzoruj się na `LinkStatsTest.php`, użytkownik jako właściciel i jako admin):
  - sekcja `entries` zwraca wiersze `Code`, `Alias`, `Unknown` z poprawnymi liczbami, domyślnie sortowane malejąco po liczbie;
  - sortowanie po nazwie rosnąco/malejąco, paginacja (`entries_per_page`, `entries_page`);
  - `entry=Alias` zawęża pozostałe tabele i serię; `entry=Unknown` zwraca tylko kliknięcia z `NULL`; `entry=Bogus` daje puste wyniki bez błędu;
  - `entry` łączy się z `browser` (oba zawężają `entries` i inne tabele) i z okresem;
  - `options.entries` zawiera istniejące wartości alfabetycznie, `Unknown` na końcu, gdy są kliknięcia bez wejścia;
  - ścieżka JSON `links.stats.data` z `sections[]=entries` zwraca tylko tę sekcję; niepoprawny `sort[entries]` lub `entries_per_page` jest odrzucany (422);
  - admin: `admin.links.stats` cudzego linku zawiera `entries`.

- [ ] **Step 2: Uruchom testy, upewnij się, że padają.**

- [ ] **Step 3: `LinkStatsRequest`** — `entry` w `FILTERS`, `entries` w `TABLES` i w `SECTIONS`; `AdminStatsRequest` dziedziczy zmiany.

- [ ] **Step 4: `LinkStatsReport`** — `TABLE_COLUMNS['entries'] = ['entry']`; `orderSql()` obsługuje `entry` (`COALESCE(entry, 'Unknown')`); `options()` mapuje kolumnę `entry` na klucz `entries`.

- [ ] **Step 5: Uruchom `LinkStatsEntryTest`, `LinkStatsTest`, `LinkStatsDataTest`, `AdminStatsReportTest` — przechodzą** (popraw istniejące testy tylko, jeśli twierdzą dokładną listę sekcji/kluczy). Pint.

- [ ] **Step 6: Commit** — `Add a "Short URL used" breakdown to the link statistics`.

### Task 3: Karta "Short URL used" na stronie statystyk

**Files:**
- Modify: `resources/js/pages/links/Stats.vue`, `resources/js/types/link.ts`
- Test: weryfikacja `npm run build`, `npm run types:check`, `php artisan test --compact tests/Feature/LinkStatsEntryTest.php`; kontrakt propsów pokrywa Task 2

**Interfaces:**
- Consumes: props `entries` (tabela), `filters.entry`, `options.entries`, `sort.entries` (Task 2).

- [ ] **Step 1: Typy** — w `link.ts` dodaj `'entry'` do `StatsFilterKey`, `'entries'` do `StatsTableKey`, `entries: string[]` do `StatsOptions`; pozostałe rekordy typów (`StatsFilters`, `StatsSorts`) obejmą nowy klucz przez te unie.

- [ ] **Step 2: `Stats.vue`** — dodaj `entries` do propsów, `tableKeys` (po `devices`), `tableLabels` ("Short URL"), `tableTitles` ("Short URL used"), stanu `tables`, listy `filterFields` (`key: 'entry'`, etykieta "Short URL", opcje `options.value.entries`) i wartości początkowej `filters` (`entry: null`), tak aby karta renderowała się jak pozostałe (wykres kołowy / tabela, wybór `entry` w liście filtrów, czyszczenie filtra, zmiana filtra odświeża pozostałe sekcje).

- [ ] **Step 3: Weryfikacja** — `npm run build`; `npm run types:check` bez nowych błędów w zmienionych plikach; `grep` potwierdza, że każde użyte API Vue jest zaimportowane; `php artisan test --compact tests/Feature/LinkStatsEntryTest.php`. Kontrola w przeglądarce jest po stronie użytkownika (implementer nie ma przeglądarki): strona statystyk linku z kliknięciami przez kod i alias pokazuje kartę, wybór filtra Short URL zawęża resztę; ta sama karta na stronie admina.

- [ ] **Step 4: Commit** — `Show the Short URL used card on the link statistics page`.

---

Po zakończeniu: końcowy przegląd, a następnie prośba do użytkownika o pełny zestaw: `php artisan test --compact`.
