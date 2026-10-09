# Statystyki linków: pobieranie danych przez AJAX

Rozszerzenie strony `links/Stats` (specy bazowe: `2026-10-09-link-stats-design.md`, `2026-10-09-link-stats-period-tables-design.md`; plan: `docs/superpowers/plans/2026-10-09-link-stats.md`).

## Cel

Zmiana filtra, okresu, sortowania, strony lub limitu tabeli pobiera dane przez AJAX, a adres strony nie zmienia się (`/links/{id}/stats` bez query stringa). Tabele są wyświetlone w siatce 2 kolumn obok siebie (decyzja użytkownika po wdrożeniu zadania 18; pierwotnie: jedna pod drugą).

## Decyzje

- Po F5, po przycisku "wstecz" i w skopiowanym linku stan wraca do domyślnego (30 dni, bez filtrów, sortowanie `count_desc`, limit 10, strona 1). Stan nie jest zapisywany nigdzie poza komponentem.
- Dane pobiera `useHttp` z `@inertiajs/vue3` (Inertia v3) z osobnej trasy JSON. `router.get` nie jest używany, bo zawsze zmienia URL.
- Walidacja i logika liczenia pozostają te same (`LinkStatsRequest`, `StatsPeriod`, tabele z sortowaniem i paginacją).
- Bez nowych zależności i tabel.

## Backend

- Obliczenia przechodzą z `LinkStatsController` do `app/Support/LinkStatsReport`. Metoda `build(Link $link, LinkStatsRequest $request, ?array $sections = null): array` zwraca pakiet danych o kształcie propsów strony (poniżej), bez klucza `link`. Klasa nie jest `final`.
- Trasy (w grupie `auth` + `verified`, przed `Route::resource`):
    - `GET links/{link}/stats` (`links.stats`) renderuje `links/Stats` z `link` i pełnym pakietem. Query string jest nadal honorowany (to samo `LinkStatsRequest`), ale interfejs go nie tworzy.
    - `GET links/{link}/stats/data` (`links.stats.data`) zwraca pakiet jako JSON.
- Pakiet (identyczny w obu trasach): `period`, `granularity`, `series`, `total`, `filters`, `options`, `sort`, `countries`, `cities`, `browsers`, `systems`, `devices` w kształtach z poprzednich specyfikacji.
- Parametr `sections[]` (tylko trasa danych) ∈ `summary` (`period`, `granularity`, `series`, `total`, `filters`, `sort`), `options`, `countries`, `cities`, `browsers`, `systems`, `devices`. Endpoint liczy i zwraca tylko żądane sekcje. Brak parametru = wszystkie. Nazwa spoza listy to błąd walidacji. Sekcja tabeli zwraca tylko klucz tej tabeli (bez `sort`). Klient trzyma własny stan sortowania, więc `sort` jest tylko echem w `summary`.
- Odpowiedzi JSON: błąd walidacji = 422 z `errors`; brak uprawnień (cudzy link) = 403 JSON, a nie redirect z toastem. Obecna obsługa `AccessDeniedHttpException` dla tras `links.*` (redirect na `links.index` z toastem) ma dotyczyć tylko żądań, które nie oczekują JSON-a. Gość dostaje 401 JSON.
- Wayfinder wystawia funkcję dla `links.stats.data`.

## Frontend (`Stats.vue`)

- Stan w komponencie: okres (z `date`/`month`/`year`/`from`/`to`), filtry, sortowania i limity tabel, strony tabel. Dane początkowe pochodzą z propsów strony (bez dodatkowego żądania przy ładowaniu).
- Funkcja `load(sections?)` buduje zapytanie z całego stanu i woła `useHttp('get', <links.stats.data>, () => query)`.
    - Zmiana okresu lub filtra: wszystkie sekcje, strony tabel wracają do 1, limity zostają.
    - Zmiana sortowania lub limitu tabeli: tylko ta tabela (`sections[]=<tabela>`), strona tej tabeli wraca do 1.
    - Zmiana strony tabeli: tylko ta tabela.
- Poprzednie żądanie w locie jest anulowane przez nowe. Odpowiedź starszego żądania nigdy nie nadpisuje nowszych danych.
- Odpowiedź scala się do lokalnego stanu danych po kluczach sekcji.
- Ładowanie: sekcja w trakcie pobierania jest przygaszona lub ma szkielet (animowany placeholder), bez migania pustym stanem.
- Błędy 422 pokazywane są przy kontrolkach okresu i filtrów jak dziś (z `errors` odpowiedzi, nie z `usePage().props.errors`); po udanym żądaniu błędy znikają.
- Presety 7/30/90 i "All time" są `<button>`. Linki-przyciski, `router.get` i `preserveState` znikają.
- Układ: tabele w jednej kolumnie, jedna pod drugą na pełną szerokość, w kolejności Countries, Cities, Browsers, Operating systems, Devices. Każda ma własne ładowanie, paginację i wybór limitu.

## Przypadki brzegowe (każdy ma test)

| #   | Sytuacja                         | Oczekiwane                                                             |
| --- | -------------------------------- | ---------------------------------------------------------------------- |
| 22  | Wiele szybkich zmian pod rząd    | Widoczny wynik ostatniej zmiany, poprzednie żądania anulowane          |
| 23  | Zły parametr w żądaniu danych    | 422 JSON z `errors`, komunikat przy kontrolce, poprzednie dane zostają |
| 24  | Cudzy link w żądaniu danych      | 403 JSON, bez redirectu                                                |
| 25  | Nieznana nazwa w `sections[]`    | 422                                                                    |
| 26  | Żądanie tylko jednej tabeli      | Odpowiedź ma tylko klucz tej tabeli; zapytania tylko dla niej          |
| 27  | Filtr, okres, sortowanie, strona | `page.url()` bez query stringa                                         |
| 28  | F5 po zmianach                   | Stan domyślny, strona się ładuje bez błędu                             |

## Testy

- Feature (`LinkStatsTest` lub nowy plik dla trasy danych): zgodność kształtu JSON z propsami strony, 422/403/401 JSON, `sections[]` (zakres kluczy, zła nazwa), brak redirectu dla JSON. Istniejące testy trasy strony działają bez zmian dzięki wspólnemu builderowi.
- E2E (`stats.spec.ts`): URL bez query po zmianach, F5 resetuje stan, szybkie kolejne zmiany kończą się ostatnim wynikiem (np. wolna odpowiedź przechwycona przez `page.route`), komunikat walidacji przy złym zakresie, tabele ułożone pionowo. Istniejące testy używające linków 7/30/90 przechodzą na przyciski.

## Zadania w planie

- **17.** `LinkStatsReport`, trasa `links.stats.data`, `sections[]`, odpowiedzi JSON dla 403/422/401
- **18.** UI na `useHttp`, czysty URL, układ pionowy tabel, E2E

Zależności: 17 → 18, na gotowych 13–16.
