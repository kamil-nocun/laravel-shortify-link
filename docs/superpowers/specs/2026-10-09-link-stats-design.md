# Statystyki linków: kliknięcia i geolokalizacja

Data: 2026-10-09

## W skrócie

- Każde udane przekierowanie `GET /{code}` zapisuje kliknięcie (czas, kraj, miasto, przeglądarka, system operacyjny, typ urządzenia).
- Zapis idzie przez job w kolejce `database`, więc redirect nie czeka na lookup geo ani insert.
- Adres IP i surowy nagłówek User-Agent nie są zapisywane w statystykach. Job używa ich tylko do lookupu geo i rozpoznania klienta.
- Właściciel linku widzi stronę statystyk (wykres dzienny, top kraje, top miasta, top przeglądarki, systemy i typy urządzeń) z filtrem 7/30/90 dni i filtrami po kraju, mieście, przeglądarce, systemie i urządzeniu oraz liczbę kliknięć w liście linków.

## Zakres

| W zakresie | Poza zakresem |
|---|---|
| Tabela `link_statistics`, model, factory | Unikalni odwiedzający (wymagałoby IP) |
| Job `RecordLinkClick` i `GeoLocator` (MaxMind GeoLite2-City) | Wstępna agregacja dzienna |
| Rozpoznawanie przeglądarki, systemu i typu urządzenia z User-Agent (`matomo/device-detector`) | Referrer |
| Strona statystyk z filtrem 7/30/90 dni | Wersje przeglądarek i systemów, model urządzenia |
| Filtry po kraju, mieście, przeglądarce, systemie i urządzeniu (jedna wartość na pole, AND) | Wielokrotny wybór wartości w jednym polu, wyszukiwanie tekstowe, wyszukiwanie w liście linków |
| Kolumna z liczbą kliknięć w liście linków | Automatyczne pobieranie i aktualizacja bazy `.mmdb` |
| Testy Pest | Retencja i czyszczenie starych kliknięć |

## Założenia

- Surowe kliknięcia plus agregacja zapytaniami `GROUP BY`. Indeks `(link_id, clicked_at)` wystarcza na obecną skalę. Agregaty dzienne dodajemy, gdy zmierzymy problem.
- `clicked_at` ustawia kontroler w chwili żądania i przekazuje do joba. Opóźnienie kolejki nie zmienia czasu kliknięcia.
- Błąd lub brak geolokalizacji nie blokuje zapisu. Kraj i miasto zostają `null`.
- Nowe zależności: `geoip2/geoip2` oraz `matomo/device-detector` (obie zaakceptowane).
- Zapisujemy tylko nazwy: `browser`, `os`, `device_type`. Bez wersji i bez surowego User-Agent.
- Boty (`isBot()`) są zapisywane z `device_type = 'bot'`, a `browser` i `os` jako `null`, żeby właściciel widział ruch botów osobno.
- Brak User-Agent lub nierozpoznany klient daje `null` we wszystkich trzech polach.
- Tabela nazywa się `link_statistics` (model nadal `LinkClick`, `$table` ustawione jawnie). Zmiana nazwy z `link_clicks` idzie osobną migracją `rename_link_clicks_to_link_statistics`, bo wcześniejsze migracje są już uruchomione, i zmienia też nazwy klucza obcego (`link_statistics_link_id_foreign`) oraz indeksu (`link_statistics_link_id_clicked_at_index`).
- Kolumny klienta dochodzą osobną migracją `add_client_columns_to_link_clicks_table`, bo pierwotna migracja jest już uruchomiona.

## Dane

Migracja `link_statistics` (UUID PK i FK, zgodnie z konwencją projektu):

| Kolumna | Typ |
|---|---|
| `id` | uuid, PK |
| `link_id` | uuid, FK do `links`, `cascadeOnDelete` |
| `clicked_at` | timestamp |
| `country_code` | char(2), nullable |
| `country` | string, nullable |
| `city` | string, nullable |
| `browser` | string, nullable |
| `os` | string, nullable |
| `device_type` | string, nullable (np. `desktop`, `smartphone`, `tablet`, `bot`) |

Indeks `(link_id, clicked_at)`. Brak `created_at` i `updated_at` (`$timestamps = false`), bo `clicked_at` jest jedyną datą.

Model `LinkClick` (`HasUuids`, `HasFactory`, relacja `link()`), factory z domyślnymi wartościami. `Link::clicks()` jako `HasMany`.

## Zapis kliknięcia

- `RedirectLinkController` po znalezieniu aktywnego linku dispatchuje `RecordLinkClick($link->id, $request->ip(), now(), $request->userAgent())` i zwraca dotychczasowy redirect.
- `RecordLinkClick` (`ShouldQueue`): pyta `GeoLocator` o IP pyta `ClientDetector` o User-Agent i tworzy `LinkClick`. Jeśli link został w międzyczasie usunięty, job kończy się bez błędu.
- `GeoLocator` (`app/Support`): cienka klasa owijająca `GeoIp2\Database\Reader`. Ścieżka do bazy w `config/services.php` (`geoip.database`, env `GEOIP_DATABASE`, domyślnie `storage/app/geoip/GeoLite2-City.mmdb`). Zwraca strukturę z krajem i miastem. Brak pliku, IP lokalne lub nieznane (`AddressNotFoundException`) daje same `null`.
- `ClientDetector` (`app/Support`): owija `DeviceDetector\DeviceDetector`. `detect(?string $userAgent): array{browser: ?string, os: ?string, device_type: ?string}`. Dla bota zwraca `device_type = 'bot'` i `null` pozostałe.
- Plik `.mmdb` jest poza gitem (wpis w `.gitignore`).
- IP i User-Agent trafiają do tabeli `jobs` tylko na czas kolejki i znikają po przetworzeniu.

## Odczyt i UI

- Trasa `GET links/{link}/stats` (`links.stats`) w grupie `auth` + `verified`. Autoryzacja przez istniejącą `LinkPolicy` (cudzy link daje 403).
- `LinkStatsController` (jedna akcja). Parametr `days` walidowany do `7|30|90`, domyślnie 30.
- Props: `link`, `days`, `total`, `daily` (pełny szereg dni, brakujące dni z zerem), `countries` (top 10), `cities` (top 10, razem z krajem), `browsers`, `systems` i `devices` (każdy: top 10, `list<array{name: string, count: int}>`). Wartości `null` pokazywane jako "Unknown".
- Strona `resources/js/pages/links/Stats.vue`: przełącznik zakresu, wykres słupkowy w CSS/SVG (bez nowej biblioteki), pięć tabel (kraje, miasta, przeglądarki, systemy, urządzenia). Teksty przez istniejący mechanizm tłumaczeń.
- Filtry: parametry `country`, `city`, `browser`, `os`, `device_type` obok `days`, walidowane w `LinkStatsRequest` (`nullable|string|max:255`). Łączą się przez AND i ograniczają wspólne zapytanie bazowe kontrolera, więc zawężają wykres, `total` i wszystkie tabele.
- Wartość filtra `Unknown` oznacza `whereNull(kolumna)`, czyli kliknięcia bez rozpoznanej wartości.
- Filtr miasta działa na samej nazwie miasta (np. `Wroclaw`), choć tabela miast pokazuje `City, Country`.
- Dodatkowe propsy: `filters` (ustawione wartości, brak = `null`) oraz `options`: dla każdego z pięciu pól lista różnych wartości z wybranego zakresu dni, malejąco po nazwie rosnąco alfabetycznie, `Unknown` na końcu, gdy występują `null`-e, limit 200 na pole. `options` nie zależy od pozostałych filtrów, żeby nie dało się zawęzić do pustego wyniku bez drogi powrotu.
- UI filtrów: pięć natywnych `<select>` (pierwsza opcja "All") nad wykresem, zmiana woła `router.get` z `preserveState`. Przycisk "Clear filters" widoczny, gdy jakiś filtr jest ustawiony. Przełącznik 7/30/90 dni zachowuje aktywne filtry.
- Lista linków: `withCount('clicks')` i kolumna z liczbą kliknięć oraz link do statystyk.
- Trasy do frontu przez Wayfinder.

## Testy

Pest:
- job zapisuje kliknięcie z krajem i miastem (fake `GeoLocator`);
- job zapisuje kliknięcie z `null` geo, gdy lookup nic nie zwraca;
- job zapisuje przeglądarkę, system i typ urządzenia (fake `ClientDetector`), a przy braku User-Agent same `null`;
- `ClientDetector`: Chrome na Windows to `desktop`, iPhone to `smartphone`, bot daje `device_type = 'bot'` z `null` browser/os, `null` i pusty User-Agent dają same `null`;
- job dla usuniętego linku nie rzuca wyjątku;
- redirect dispatchuje `RecordLinkClick` (`Queue::fake`); odmowa (404) nie dispatchuje;
- filtry: każdy z pięciu filtrów zawęża `total` i tabele; dwa filtry naraz (AND); `Unknown` zwraca kliknięcia z `null`; `options` zawiera tylko wartości tego linku z wybranego zakresu; zbyt długa wartość jest odrzucona walidacją; filtr nie ujawnia kliknięć cudzego linku;
- strona statystyk: `browsers`, `systems` i `devices` z licznikami i `Unknown` dla `null`; zakresy 7/30/90, domyślny 30, niepoprawny `days` daje błąd walidacji, klik poza zakresem niewliczony, pełny szereg dni z zerami;
- statystyki cudzego linku: 403; gość: przekierowanie do logowania;
- lista linków zawiera liczbę kliknięć.
