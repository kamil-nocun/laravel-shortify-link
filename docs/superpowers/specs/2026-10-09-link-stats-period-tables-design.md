# Statystyki linków: okres, sortowanie i paginacja tabel

Rozszerzenie strony `links/Stats` (spec bazowy: `2026-10-09-link-stats-design.md`, plan: `docs/superpowers/plans/2026-10-09-link-stats.md`).

## Cel

Właściciel linku:

1. wybiera okres statystyk: 7/30/90 dni, cały okres, dzień, miesiąc, rok lub zakres od/do,
2. przegląda wszystkie wiersze tabel (kraje, miasta, przeglądarki, systemy, urządzenia) dzięki paginacji z wyborem limitu per tabela,
3. sortuje każdą tabelę po nazwie lub liczbie kliknięć.

Filtry kraj/miasto/przeglądarka/system/urządzenie pozostają bez zmian i działają razem z nowym okresem.

## Decyzje

- Presety 7/30/90 zostają obok nowych trybów (jeden selektor okresu).
- Paginacja i sortowanie są po stronie serwera, stan siedzi w URL (da się go linkować). Nie ma osobnego "Show all".
- Limit wierszy jest wybierany osobno dla każdej tabeli.

## Okres

Parametr `period` zastępuje `days`. Wartości: `7d`, `30d`, `90d`, `all`, `day`, `month`, `year`, `range`. Domyślnie `30d`.

| `period`           | Wymagane parametry   | Zakres                                                                |
| ------------------ | -------------------- | --------------------------------------------------------------------- |
| `7d`, `30d`, `90d` | brak                 | ostatnie N dni włącznie z dzisiejszym, od początku dnia               |
| `all`              | brak                 | od pierwszego kliknięcia linku do teraz; brak kliknięć = pusty zakres |
| `day`              | `date` (Y-m-d)       | cały dzień                                                            |
| `month`            | `month` (Y-m)        | cały miesiąc                                                          |
| `year`             | `year` (4 cyfry)     | cały rok                                                              |
| `range`            | `from`, `to` (Y-m-d) | od początku `from` do końca `to`                                      |

Reguły:

- Walidacja w `LinkStatsRequest`: poprawny format, `from <= to`, zakres `range` najwyżej 5 lat. Brak wymaganego parametru dla wybranego trybu to błąd walidacji.
- Koniec zakresu nie wychodzi poza teraz (przyszłość jest pusta, więc nie zawyża serii).
- Rozwiązaniem parametrów zajmuje się `app/Support/StatsPeriod` (nie `final`, bez zależności od kontenera). Zwraca `from`, `to` i `granularity`.
- `granularity`: `day`, gdy zakres ma najwyżej 92 dni; w przeciwnym razie `month`. Dla `year` i `all` zawsze `month`.
- Zakres filtruje `clicked_at` (`>= from`, `<= to`) w sumie, wykresie, tabelach i `options`.

Propsy:

| Prop          | Typ                                                                                                   |
| ------------- | ----------------------------------------------------------------------------------------------------- |
| `period`      | `array{period: string, date: ?string, month: ?string, year: ?int, from: ?string, to: ?string}` (echo) |
| `granularity` | `'day'\|'month'`                                                                                      |
| `series`      | `list<array{date: string, count: int}>` uzupełniona zerami; `date` w formacie `Y-m-d` lub `Y-m`       |
| `total`       | suma `series`                                                                                         |

Prop `daily` zostaje zastąpiony przez `series`, a `days` przez `period`.

## Sortowanie i paginacja tabel

Klucz tabeli: `countries`, `cities`, `browsers`, `systems`, `devices`.

| Parametr            | Wartości                                           | Domyślnie    |
| ------------------- | -------------------------------------------------- | ------------ |
| `sort[<tabela>]`    | `count_desc`, `count_asc`, `name_asc`, `name_desc` | `count_desc` |
| `<tabela>_per_page` | `10`, `25`, `50`, `100`                            | `10`         |
| `<tabela>_page`     | liczba całkowita >= 1                              | `1`          |

Reguły:

- Sortowanie po nazwie używa `COALESCE(kolumna, 'Unknown')`. Remis rozstrzyga wtórne sortowanie po nazwie rosnąco, dla miast po mieście, a potem po kraju (wynik deterministyczny między stronami).
- Pobranie: `toBase()` + `groupBy` + `paginate($perPage, pageName: '<tabela>_page')`.
- Strona poza zakresem zwraca ostatnią stronę. Wartość spoza listy (`per_page`, `sort`) lub nieliczbowa strona nie przechodzi walidacji.
- Zmiana okresu, filtra lub sortowania resetuje wszystkie strony do 1, limity zostają. Zmiana limitu tabeli resetuje stronę tej tabeli. Przejście na inną stronę zachowuje wszystkie pozostałe parametry.
- Zasady z planu bazowego nadal obowiązują: `null` -> `Unknown`, cudze linki i klikanie poza zakresem nie wchodzą do sum.

Propsy każdej tabeli (`countries`, `cities`, `browsers`, `systems`, `devices`):
`array{rows: list<array{name: string, count: int}>, page: int, lastPage: int, total: int, perPage: int}`.
Dodatkowo prop `sort`: `array<string, string>` (echo wybranych sortowań). Nazwa miasta w `rows` to `City, Country`.

## UI (`Stats.vue`)

- Selektor okresu: przyciski 7/30/90 days i "All time", plus select `Day | Month | Year | Range`. Wybór pokazuje natywny `<input type="date">`, `<input type="month">`, pole roku albo dwa pola `date`. Zmiana robi `router.get` z zachowaniem filtrów, sortowań i limitów.
- Wykres: przy `granularity=month` etykiety (tooltip) w formacie miesiąca.
- Tabele: nagłówki "Name" i "Clicks" są klikalne (strzałka kierunku, klik przełącza asc/desc). Pod tabelą: select "Rows" (10/25/50/100), przyciski Previous/Next (ukryte przy jednej stronie) i tekst "Page 2 of 5 (47 rows)". Kontrolka limitu jest widoczna zawsze.
- Jedna funkcja `visit` składa zapytanie ze wszystkich części stanu (okres, filtry, sortowania, limity, strony), `preserveState`, `preserveScroll`. Teksty przez `__()`, trasy przez Wayfinder.

## Przypadki brzegowe (każdy ma test)

| #   | Sytuacja                                       | Oczekiwane                                       |
| --- | ---------------------------------------------- | ------------------------------------------------ |
| 12  | `period=all` bez kliknięć                      | pusty zakres, `total` = 0, `series` puste        |
| 13  | `period=day` bez `date` lub zły format         | błąd walidacji                                   |
| 14  | `range` z `from > to` lub dłuższy niż 5 lat    | błąd walidacji                                   |
| 15  | Zakres dłuższy niż 92 dni, `year`, `all`       | `granularity=month`                              |
| 16  | Zakres w przyszłości                           | zera, bez błędu                                  |
| 17  | Ostatnia strona niepełna, strona poza zakresem | ostatnia strona z resztą wierszy                 |
| 18  | `per_page` spoza listy, zła `sort`             | błąd walidacji                                   |
| 19  | Sortowanie po nazwie z `Unknown`               | `Unknown` sortowane jak zwykła nazwa `Unknown`   |
| 20  | Remisy w liczbie kliknięć                      | kolejność identyczna między stronami             |
| 21  | Zmiana okresu                                  | strony wracają do 1, limity i sortowania zostają |

## Testy

- Feature (`LinkStatsTest`): każdy tryb okresu i jego granice, walidacja, granularność, sortowanie (4 warianty), każdy limit, niezależność stron i limitów tabel, zachowanie filtrów, autoryzacja bez zmian. Istniejące testy przechodzą z `days` na `period` i na nowy kształt propsów tabel.
- Jednostkowy: `StatsPeriod` (granice, granularność, `all`).
- E2E (`stats.spec.ts`): zmiana okresu, sortowanie po kliknięciu nagłówka, zmiana limitu i przejście na kolejną stronę.

## Zadania w planie

- **13.** `StatsPeriod` i `LinkStatsRequest` (okres)
- **14.** Okres w kontrolerze (`series`, `granularity`, zakres w `options`)
- **15.** Sortowanie i paginacja tabel w backendzie
- **16.** UI: selektor okresu, sortowanie, paginacja, limity + E2E

Zależności: 13 -> 14 -> 15 -> 16. Bez nowych zależności i tabel.
