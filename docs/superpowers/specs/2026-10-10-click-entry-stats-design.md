# Statystyki kliknięć według użytego adresu (kod vs alias) — design

## Kontekst i cel

Link ma standardowy kod i opcjonalny alias, a oba przekierowują na ten sam cel (`2026-10-10-custom-alias-and-link-filters-design.md`). Statystyki liczą jednak kliknięcia tylko po `link_id`, więc nie widać, którym adresem wszedł odwiedzający.

Cel: na stronie statystyk linku (użytkownika i admina) pojawia się nowy podział "Short URL used": ile kliknięć przyszło przez **kod**, a ile przez **alias**. Podział działa tak jak pozostałe (tabela lub wykres kołowy, sortowanie, paginacja, filtrowanie po kliknięciu wiersza, opcje filtrów) i łączy się z istniejącymi filtrami i okresem.

Poza zakresem: podział po konkretnych wartościach aliasu (kliknięcia przez wcześniejsze aliasy łączą się w "Alias"), statystyki zbiorcze panelu admina (`AdminStatsReport`), uzupełnianie starych kliknięć.

## Decyzje

- **Zapisujemy typ wejścia, nie wartość.** Kolumna `link_statistics.entry` przyjmuje `Code` albo `Alias`. Odrzucone: zapis dokładnej wartości (wiersz na każdy alias, tekst użytkownika w statystykach, więcej danych) — wybrano prostszy wariant A.
- **Wartości w bazie to etykiety wyświetlane** (`Code`, `Alias`), tak jak `country`, `browser` czy `os` przechowują nazwy wyświetlane. Dzięki temu istniejący kod raportu (grupowanie, filtr po wartości, `Unknown` dla `NULL`) działa bez specjalnych przypadków.
- **Stare kliknięcia mają `entry = NULL`** i są pokazywane jako `Unknown`. Nie da się ich odtworzyć wstecz, bo alias można zmieniać i usuwać.
- **Wejście ustala przekierowanie**, bo tylko ono wie, którą wartością dopasowano link. Kody i aliasy dzielą jedną przestrzeń nazw w domenie, więc porównanie `$link->code === $code` jest jednoznaczne (kolacja binarna).

## Dane

Migracja: `link_statistics.entry` — `string(5)`, nullable, po kolumnie `device_type`, bez indeksu (raport grupuje po zakresie czasu danego linku, tak jak po pozostałych kolumnach).

`App\Enums\LinkEntry` (backed string): `Code = 'Code'`, `Alias = 'Alias'`. `LinkClick`: `entry` w `#[Fillable]` i docblocku `@property`.

## Zapis kliknięcia

- `RedirectLinkController` wylicza `LinkEntry` (`$link->code === $code` → `Code`, w przeciwnym razie `Alias`) i przekazuje go do `RecordLinkClick::dispatch(...)`.
- `RecordLinkClick` dostaje opcjonalny argument `?string $entry = null` zapisywany tak jak `userAgent`: właściwość niepromowana, ustawiana w konstruktorze, z komentarzem, że zadania odłożone w kolejce przed zmianą są odtwarzane bez niej. `handle()` dopisuje `'entry' => $this->entry` do zapisywanej rekordu. Zadanie bez wartości zapisuje `NULL` (`Unknown`).

## Raport statystyk

W `LinkStatsReport` i `LinkStatsRequest` dochodzi sekcja `entries` według istniejącego wzorca:
- `LinkStatsReport::TABLE_COLUMNS`: `'entries' => ['entry']`; `orderSql()` dostaje `entry` (`COALESCE(entry, 'Unknown')`); `options()` dostaje klucz `entry` → `entries`.
- `LinkStatsRequest`: `entry` w `FILTERS`, `entries` w `TABLES` i `SECTIONS`.
- Filtr `entry=Code|Alias|Unknown` działa przez istniejącą ścieżkę filtrowania po kolumnie (`Unknown` → `whereNull`). Wartość spoza `Code|Alias|Unknown` zwraca puste wyniki, tak jak dla pozostałych filtrów tekstowych.
- Kontroler i `AdminStatsRequest` (rozszerza ten sam raport) nie wymagają osobnych zmian poza sprawdzeniem, że dziedziczą nowe reguły.

## Interfejs

`links/Stats.vue` i typy w `resources/js/types/link.ts`: nowy klucz tabeli `entries` ("Short URL used", etykieta kolumny "Short URL"), nowy filtr `entry` oraz opcje `entries`. Karta wygląda i zachowuje się jak karty przeglądarek i systemów (`StatsTable`, wykres kołowy / tabela, klik w wiersz ustawia filtr, czyszczenie filtra). Strona admina korzysta z tego samego komponentu.

## Testy (Pest, feature)

- Przekierowanie przez kod zapisuje `entry = Code`, przez alias `Alias`; `RecordLinkClick` bez argumentu zapisuje `NULL`.
- `LinkStatsReport`: sekcja `entries` zwraca wiersze `Code`, `Alias`, `Unknown` z poprawnymi liczbami; sortowanie, paginacja i filtr `entry` (w tym `Unknown`) działają; filtr łączy się z innym filtrem (np. `browser`) i z okresem; `options.entries` zawiera istniejące wartości, z `Unknown` na końcu, gdy są kliknięcia bez wejścia.
- Ścieżka `data` (JSON) z `sections[]=entries` zwraca tylko tę sekcję.
- Admin: strona statystyk cudzego linku zawiera sekcję `entries`.
- Niewłaściwy `entry` w `sort`/`per_page`/`page` jest odrzucany tak jak dla pozostałych tabel.

Zmiany czysto wizualne (układ karty) nie wymagają osobnych testów.
