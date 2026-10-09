# Statystyki linków: kliknięcia i geolokalizacja

Data: 2026-10-09

## W skrócie

- Każde udane przekierowanie `GET /{code}` zapisuje kliknięcie (czas, kraj, miasto).
- Zapis idzie przez job w kolejce `database`, więc redirect nie czeka na lookup geo ani insert.
- Adres IP nie jest zapisywany w statystykach. Job używa go tylko do lookupu.
- Właściciel linku widzi stronę statystyk (wykres dzienny, top kraje, top miasta) z filtrem 7/30/90 dni oraz liczbę kliknięć w liście linków.

## Zakres

| W zakresie | Poza zakresem |
|---|---|
| Tabela `link_clicks`, model, factory | Unikalni odwiedzający (wymagałoby IP) |
| Job `RecordLinkClick` i `GeoLocator` (MaxMind GeoLite2-City) | Wstępna agregacja dzienna |
| Strona statystyk z filtrem 7/30/90 dni | Referrer, urządzenie, przeglądarka |
| Kolumna z liczbą kliknięć w liście linków | Automatyczne pobieranie i aktualizacja bazy `.mmdb` |
| Testy Pest | Retencja i czyszczenie starych kliknięć |

## Założenia

- Surowe kliknięcia plus agregacja zapytaniami `GROUP BY`. Indeks `(link_id, clicked_at)` wystarcza na obecną skalę. Agregaty dzienne dodajemy, gdy zmierzymy problem.
- `clicked_at` ustawia kontroler w chwili żądania i przekazuje do joba. Opóźnienie kolejki nie zmienia czasu kliknięcia.
- Błąd lub brak geolokalizacji nie blokuje zapisu. Kraj i miasto zostają `null`.
- Nowa zależność: `geoip2/geoip2`. Wymaga zgody przed instalacją.

## Dane

Migracja `link_clicks` (UUID PK i FK, zgodnie z konwencją projektu):

| Kolumna | Typ |
|---|---|
| `id` | uuid, PK |
| `link_id` | uuid, FK do `links`, `cascadeOnDelete` |
| `clicked_at` | timestamp |
| `country_code` | char(2), nullable |
| `country` | string, nullable |
| `city` | string, nullable |

Indeks `(link_id, clicked_at)`. Brak `created_at` i `updated_at` (`$timestamps = false`), bo `clicked_at` jest jedyną datą.

Model `LinkClick` (`HasUuids`, `HasFactory`, relacja `link()`), factory z domyślnymi wartościami. `Link::clicks()` jako `HasMany`.

## Zapis kliknięcia

- `RedirectLinkController` po znalezieniu aktywnego linku dispatchuje `RecordLinkClick($link->id, $request->ip(), now())` i zwraca dotychczasowy redirect.
- `RecordLinkClick` (`ShouldQueue`): pyta `GeoLocator` o IP i tworzy `LinkClick`. Jeśli link został w międzyczasie usunięty, job kończy się bez błędu.
- `GeoLocator` (`app/Support`): cienka klasa owijająca `GeoIp2\Database\Reader`. Ścieżka do bazy w `config/services.php` (`geoip.database`, env `GEOIP_DATABASE`, domyślnie `storage/app/geoip/GeoLite2-City.mmdb`). Zwraca strukturę z krajem i miastem. Brak pliku, IP lokalne lub nieznane (`AddressNotFoundException`) daje same `null`.
- Plik `.mmdb` jest poza gitem (wpis w `.gitignore`).
- IP trafia do tabeli `jobs` tylko na czas kolejki i znika po przetworzeniu.

## Odczyt i UI

- Trasa `GET links/{link}/stats` (`links.stats`) w grupie `auth` + `verified`. Autoryzacja przez istniejącą `LinkPolicy` (cudzy link daje 403).
- `LinkStatsController` (jedna akcja). Parametr `days` walidowany do `7|30|90`, domyślnie 30.
- Props: `link`, `days`, `total`, `daily` (pełny szereg dni, brakujące dni z zerem), `countries` (top 10), `cities` (top 10, razem z krajem). Wartości `null` pokazywane jako "Nieznany".
- Strona `resources/js/pages/links/Stats.vue`: przełącznik zakresu, wykres słupkowy w CSS/SVG (bez nowej biblioteki), dwie tabele. Teksty przez istniejący mechanizm tłumaczeń.
- Lista linków: `withCount('clicks')` i kolumna z liczbą kliknięć oraz link do statystyk.
- Trasy do frontu przez Wayfinder.

## Testy

Pest:
- job zapisuje kliknięcie z krajem i miastem (fake `GeoLocator`);
- job zapisuje kliknięcie z `null` geo, gdy lookup nic nie zwraca;
- job dla usuniętego linku nie rzuca wyjątku;
- redirect dispatchuje `RecordLinkClick` (`Queue::fake`); odmowa (404) nie dispatchuje;
- strona statystyk: zakresy 7/30/90, domyślny 30, niepoprawny `days` daje błąd walidacji, klik poza zakresem niewliczony, pełny szereg dni z zerami;
- statystyki cudzego linku: 403; gość: przekierowanie do logowania;
- lista linków zawiera liczbę kliknięć.
