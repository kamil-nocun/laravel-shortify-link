# Kody QR dla linków — design

## Kontekst i cel

Link ma standardowy kod i opcjonalny alias (`2026-10-10-custom-alias-and-link-filters-design.md`). Użytkownik ma móc wygenerować kod QR osobno dla kodu i osobno dla aliasu, pobrać go i zobaczyć w statystykach, ile wejść pochodzi ze skanów QR.

Poza zakresem: PNG (wymaga rozszerzenia `gd` lub `imagick`), własne kolory i logo w QR, zapis obrazów na dysku, statystyki zbiorcze panelu admina (`AdminStatsReport`).

## Decyzje

- **Obraz QR generuje backend** (`chillerlan/php-qrcode`, nowa zależność composer zatwierdzona przez użytkownika). Odrzucone: biblioteka npm po stronie frontendu oraz `bacon/qr-code`.
- **Tylko SVG.** Nie wymaga rozszerzeń PHP, jest skalowalny. Obrazu nie zapisujemy ani nie cache'ujemy, bo generowanie jest tanie.
- **Osobny kod QR dla kodu i dla aliasu.** Każdy koduje własny adres.
- **Skany liczymy w osobnej kolumnie `source`**, niezależnej od `entry` (Code/Alias). Dzięki temu skan QR zachowuje informację, którym adresem wszedł odwiedzający, a w statystykach działa osobna sekcja "Source". Odrzucone: dodatkowe wartości `entry` ("QR Code", "QR Alias") oraz kolumna boolean (raport grupuje po tekście).
- **Skan rozpoznajemy po parametrze `?qr=1`** w zakodowanym adresie. Parametr nie zmienia celu przekierowania.
- **Kliknięcia bez zapisanego źródła liczą się jako `Link`** (te sprzed zmiany nie mogły pochodzić z QR), tak jak przy `entry`.

## Dane

Migracja: `link_statistics.source` — `string(4)`, nullable, po kolumnie `entry`, bez indeksu. Migracja uzupełniająca ustawia `source = 'Link'` dla wierszy z `NULL` (nieodwracalna, jak backfill `entry`).

`App\Enums\LinkSource` (backed string): `Link = 'Link'`, `Qr = 'QR'`. `LinkClick`: `source` w `#[Fillable]` i docblocku `@property`.

## Generowanie QR

- Trasa `GET links/{link}/qr/{type}` (`links.qr`), `type` ∈ `code|alias`, w grupie tras zalogowanego użytkownika. Kontroler jednoakcyjny `LinkQrController`.
- Autoryzacja: `Gate::authorize('update', $link)`.
- `alias` dla linku bez aliasu → 404. Nieznany `type` → 404 (ograniczenie trasy `->whereIn('type', [...])`).
- Zakodowany adres: `https://{host}/{code lub alias}?qr=1`, gdzie host to domena linku albo `Link::mainHost()`. Schemat i budowanie adresu reużywają istniejącej logiki pełnego adresu linku, jeśli taka jest w `Link` (do sprawdzenia przy planie), w przeciwnym razie dochodzi mała metoda w modelu.
- Odpowiedź: `image/svg+xml`. `?download=1` dodaje `Content-Disposition: attachment; filename="{code lub alias}.svg"`.

## Zapis kliknięcia

- `RedirectLinkController`: `LinkSource::Qr`, gdy `$request->boolean('qr')`, w przeciwnym razie `Link`; przekazuje do `RecordLinkClick::dispatch(...)` jako kolejny argument.
- `RecordLinkClick`: opcjonalny `?string $source = null` jako właściwość niepromowana (jak `userAgent` i `entry`), z komentarzem o zadaniach z kolejki sprzed zmiany; `handle()` zapisuje `'source' => $this->source ?? LinkSource::Link->value`.

## Raport statystyk

Według wzorca sekcji `entries`:
- `LinkStatsReport`: `TABLE_COLUMNS['sources'] = ['source']`; `orderSql()` dostaje `source` (`COALESCE(source, 'Unknown')`); `options()` dostaje `source => sources`.
- `LinkStatsRequest`: `source` w `FILTERS`, `sources` w `TABLES` i `SECTIONS`.
- Filtr `source=Link|QR|Unknown` działa istniejącą ścieżką.

## Interfejs

- `links/Index.vue`: przycisk "QR" przy linku otwiera modal (reużyj istniejący komponent modala, jeśli jest). Modal ma zakładki "Code" i "Alias" (zakładka Alias tylko gdy link ma alias), podgląd `<img>` z `links.qr` (route przez Wayfinder) i przycisk pobrania (`download=1`).
- `links/Stats.vue` i `resources/js/types/link.ts`: nowa karta "Source" (klucz `sources`, filtr `source`, opcje `sources`) wyglądająca i działająca jak karta "Short URL used". Strona admina korzysta z tego samego komponentu.

## Testy (Pest, feature)

- Endpoint QR: właściciel dostaje SVG dla `code`; dla `alias` przy ustawionym aliasie; `alias` bez aliasu → 404; obcy użytkownik → 403; gość → przekierowanie do logowania; `download=1` ustawia nagłówek; zakodowany adres zawiera `?qr=1`, właściwy kod/alias i domenę (asercja na zawartości SVG niemożliwa, więc test sprawdza adres budowany przez metodę pomocniczą).
- Przekierowanie z `?qr=1` zapisuje `source = QR`, bez parametru `Link`; `entry` zostaje poprawne w obu przypadkach; `RecordLinkClick` bez argumentu zapisuje `Link`.
- `LinkStatsReport`: sekcja `sources` zwraca wiersze z poprawnymi liczbami; sortowanie, paginacja i filtr `source` (w tym `Unknown`) działają; łączy się z innym filtrem (np. `entry`) i z okresem; `options.sources` zawiera istniejące wartości.
- Ścieżka `data` z `sections[]=sources` zwraca tylko tę sekcję; admin widzi sekcję w statystykach cudzego linku.

Zmiany czysto wizualne (modal, układ karty) nie wymagają osobnych testów.
