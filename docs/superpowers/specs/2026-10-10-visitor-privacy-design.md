# Prywatność odwiedzających — projekt

Data: 2026-10-10

## Kontekst i cel

Krok 2 wdrożenia RODO (po podstronach CMS). Odwiedzający skróconych linków nie mają kont, a aplikacja zapisuje statystyki kliknięć. Cel: żadne dane pozwalające zidentyfikować odwiedzającego nie zostają w bazie, a historyczne statystyki istnieją wyłącznie w postaci dziennych podsumowań.

Stan obecny (z kodu):
- IP i User-Agent nie są zapisywane w `link_statistics`; job `RecordLinkClick` używa ich do lokalnego odczytu MaxMind (`GeoLocator`) i parsowania klienta (`ClientDetector`).
- Job dostaje surowe IP i User-Agent jako własności, więc trafiają do tabel `jobs` i `failed_jobs` (w tej drugiej bezterminowo).
- Trasa `{code}` jest w grupie `web` ze `SESSION_DRIVER=database`, więc każdy anonimowy odwiedzający dostaje cookie i wiersz w `sessions` z IP i User-Agent.
- Pojedyncze kliknięcia (z miastem, przeglądarką, OS, urządzeniem i dokładnym czasem) rosną bez limitu.

Poza zakresem: opis przetwarzania w Polityce prywatności (krok 4), prawa użytkowników (krok 3), usuwanie danych kont.

## Założenia

- Agregacja dzienna uruchamiana z crona (scheduler Laravela).
- Po agregacji zostają wymiary: kraj, przeglądarka, OS, urządzenie, `entry`, `source`. Miasto i dokładny czas są usuwane.
- Raporty historyczne liczą z podsumowań; rozbicie po mieście obejmuje tylko kliknięcia z bieżącego dnia (starsze są w grupie "Unknown").
- Lokalizacja pochodzi z lokalnej bazy MaxMind, bez zewnętrznych usług.
- Zaokrąglanie `clicked_at` do godziny jest zbędne, bo zastępuje je agregacja.

## Model danych

`link_statistics` otrzymuje dwie kolumny:
- `clicks` (unsigned integer, domyślnie 1): liczba kliknięć reprezentowana przez wiersz.
- `is_summary` (boolean, domyślnie false): wiersz jest dziennym podsumowaniem.

Wiersz podsumowania: `city = null`, `clicked_at` = początek dnia (strefa `config('app.timezone')`), `clicks` = suma. `LinkClick` dostaje te pola w `Fillable` i PHPDoc oraz cast `is_summary`.

## Agregacja

- `App\Actions\AggregateDailyLinkStatistics`: dla każdego dnia wcześniejszego niż dziś, który ma wiersze z `is_summary = false`, w jednej transakcji: grupuje wiersze dnia po `link_id`, `country_code`, `country`, `browser`, `os`, `device_type`, `entry`, `source`, sumuje `clicks`, wstawia wiersze podsumowań (`is_summary = true`, `city = null`, `clicked_at` = początek dnia), usuwa dotychczasowe wiersze tego dnia. Zwraca liczbę przetworzonych dni.
- Idempotentna i nadrabiająca: ponowne uruchomienie nic nie zmienia, przegapione dni zostaną przetworzone przy następnym przebiegu.
- `App\Console\Commands\AggregateLinkStatistics` (`stats:aggregate`) wywołuje akcję i wypisuje liczbę dni.
- `routes/console.php`: `Schedule::command('stats:aggregate')->dailyAt('00:10')->withoutOverlapping()`.

## Raporty

Wszędzie, gdzie liczone są kliknięcia, `count(*)` zastępuje `sum(clicks)`:
- `AdminStatsReport`: top linki, top użytkownicy, suma i liczniki.
- `StatsPeriod::series`: suma dziennych wartości.
- `LinkStatsReport`: rozbicia i suma (liczba grup nadal liczona jako liczba wierszy).
- `LinkController` i `Admin\UserController`: `withCount('clicks')` zastąpione `withSum('clicks', 'clicks')` z zachowaniem nazwy pola dla frontendu (`clicks_count` z wartością 0 zamiast null).

## Brak zapisu IP i User-Agent

- `RedirectLinkController` wyznacza lokalizację (`GeoLocator::locate`) i klienta (`ClientDetector::detect`), a do `RecordLinkClick` przekazuje tylko wyniki: `linkId`, `clickedAt`, `location`, `client`, `entry`, `source`. Job nie zna IP ani User-Agent, więc nie trafiają one do `jobs` ani `failed_jobs`.
- Trasa `links.redirect` jest wyłączona z `StartSession`, `AddQueuedCookiesToResponse`, `ShareErrorsFromSession` i `PreventRequestForgery` (jak `domains.check`), więc nie powstaje cookie ani wiersz w `sessions`.
- Wyszukiwanie lokalizacji odbywa się w żądaniu (lokalny plik MaxMind), co dodaje pomijalny koszt do przekierowania.

## Testy (Pest, feature)

- agregacja: sumuje kliknięcia i wymiary, usuwa miasto, ustawia `is_summary` i początek dnia, nie rusza dnia dzisiejszego, jest idempotentna, nadrabia kilka dni, każdy dzień w osobnej transakcji (błąd jednego nie psuje pozostałych),
- schedule: `stats:aggregate` jest zaplanowane codziennie,
- raporty: wynik jest taki sam dla surowych kliknięć i po agregacji (suma, serie, rozbicia, top linki, `clicks_count`),
- przekierowanie: `RecordLinkClick` nie ma własności z IP ani User-Agent, odpowiedź nie zawiera `Set-Cookie`, a po przekierowaniu z pełnym IP i User-Agent żadna tabela (`link_statistics`, `jobs`, `failed_jobs`, `sessions`) ich nie zawiera,
- istniejące testy `RecordLinkClickTest` i `LinkRedirectTest` dostosowane do nowej sygnatury joba.
