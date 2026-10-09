# Skrócone linki: model i CRUD w panelu klienta

Data: 2026-10-08

## W skrócie

- Zalogowany użytkownik zarządza **własnymi** linkami: lista, dodawanie, edycja, usuwanie.
- Kod linku jest **generowany**. Użytkownik go nie wpisuje i nie edytuje.
- Zwykły resource controller + Form Requests + Policy. Bez service/repository.
- Usuwanie **twarde**.

## Zakres

| W zakresie                      | Poza zakresem                                                                                |
| ------------------------------- | -------------------------------------------------------------------------------------------- |
| Tabela `links`                  | Publiczny redirect po kodzie                                                                 |
| Model `Link` + factory + seeder | Liczniki i statystyki kliknięć                                                               |
| CRUD w panelu (Inertia/Vue)     | Egzekwowanie `is_active` i `expires_at` przy redirectcie (pola tylko zapisujemy i edytujemy) |
| Generator kodu                  |                                                                                              |
| Autoryzacja właściciela         |                                                                                              |
| Testy                           |                                                                                              |

## Założenia

- Link należy do jednego użytkownika. Widzi i zmienia go tylko on.
- Nowe tabele używają UUID jako PK i FK (konwencja projektu, `users` już tak ma).
- Usuwanie twarde, bez soft delete.
- Architektura: resource controller, Form Requests, Policy.

## Dane: tabela `links`

| Kolumna                    | Typ                   | Uwagi                                |
| -------------------------- | --------------------- | ------------------------------------ |
| `id`                       | UUID, PK              |                                      |
| `user_id`                  | UUID, FK → `users.id` | `cascadeOnDelete`                    |
| `title`                    | string(255), nullable | etykieta w panelu                    |
| `original_url`             | string(2048)          | adres docelowy                       |
| `code`                     | string, unique        | generowany, niezmienny po utworzeniu |
| `is_active`                | boolean               | domyślnie `true`                     |
| `expires_at`               | timestamp, nullable   |                                      |
| `created_at`, `updated_at` | timestamps            |                                      |

### Pełny adres skróconego linku

- Pełny adres (np. `https://example.com/aB3xY9`) to **wartość wyliczana**: adres aplikacji (`config('app.url')`) + `/` + `code`, ze schematem z `APP_URL`.
- **Nie zapisujemy go w bazie.** Nie ma na to kolumny.
- Wyliczamy go w modelu `Link` (accessor `short_url`) i dołączamy do danych przekazywanych do frontendu.
- Zmiana domeny aplikacji nie wymaga migracji danych.

### Wielkość liter w `code`

- Unikalność `code` musi rozróżniać wielkość liter (`abc` ≠ `ABC`).
- Baza (lokalna, deweloperska, produkcyjna) to MySQL. Jej domyślna kolacja wielkości liter **nie** rozróżnia.
- Dlatego kolumna `code` dostaje kolację `utf8mb4_bin` **bezwarunkowo**, bez rozgałęzień po sterowniku.

## Generowanie kodu

Jedna mała klasa: `App\Support\LinkCodeGenerator`. Jej jedyne zadanie to zwrócić unikalny kod. Używana przy tworzeniu linku.

### Reguły kodu

- Alfabet: `0-9A-Za-z` (62 znaki). Wielkość liter ma znaczenie.
- Długość startowa: 6.
- Kod nie jest edytowalny przez użytkownika (ani przy tworzeniu, ani przy edycji).

### Jak wybieramy długość `L`

1. Zacznij od `L = 6`.
2. Policz zajęte kody o długości `L` (`LENGTH(code) = L`).
3. Wolne kombinacje = `62^L` minus zajęte.
4. Wolnych jest ≤ 1% z `62^L` (zajętych ≥ 99%)? Zwiększ `L` o 1 i wróć do kroku 2.
5. W przeciwnym razie zostań przy `L`.

### Jak losujemy kod

1. Wylosuj kod długości `L` z własnego alfabetu (`random_int`).
2. Kolizja? Losuj ponownie.
3. Wyścig? Unikalny indeks w bazie jest ostateczną ochroną. Naruszenie unikalności też kończy się ponowieniem.

### Konfiguracja i ograniczenia

- Długość wynika z danych, nie z konfiguracji.
- Próg 1% to stała w klasie generatora. W testach można ją wstrzyknąć.
- Koszt: dodatkowe zapytanie `COUNT` przy każdym tworzeniu. Dla przewidywanej skali wystarcza. Gdy przestanie: indeks funkcyjny na `LENGTH(code)` albo licznik w cache.

## Backend

### Model i dane

- `Link`: `HasUuids`, `HasFactory`, casty `is_active` (bool) i `expires_at` (datetime), relacja `user()`, wyliczany accessor `short_url` (bez kolumny w bazie).
- `User::links()` (hasMany).
- `LinkFactory`: poprawny `code` i `original_url`. Stany pomocnicze: nieaktywny i wygasły link.
- Seeder dla `Link`.

### Trasy i kontroler

- `LinkController` (resource): index, create, store, edit, update, destroy. Bez show.
- Trasy `links.*` w grupie `auth` + `verified`.
- Lista: tylko linki bieżącego użytkownika, paginowana, najnowsze pierwsze.

### Walidacja (`StoreLinkRequest` / `UpdateLinkRequest`)

| Pole           | Reguła                                         |
| -------------- | ---------------------------------------------- |
| `original_url` | required, `url` (`http` lub `https`), max 2048 |
| `title`        | nullable, string, max 255                      |
| `is_active`    | boolean                                        |
| `expires_at`   | nullable, date, po bieżącej chwili             |
| `code`         | **nie jest przyjmowany z wejścia**             |

### Autoryzacja (`LinkPolicy`)

- `update` i `delete`: tylko właściciel (`user_id`).
- Pozostałe operacje: dostępne dla zalogowanego.
- Odmowa (cudzy link) **nie** kończy się 403: `AccessDeniedHttpException` na trasach `links.*` jest przechwytywana w `bootstrap/app.php`. Użytkownik trafia na `links.index` z ogólnym toastem błędu („You are not allowed to do that.”). Żądania JSON i trasy spoza `links.*` zachowują domyślne 403.

## Frontend

### Strony (`resources/js/pages/links/`)

- `Index.vue`: tabela (tytuł, adres docelowy, **pełny adres skróconego linku**, kod, status, wygaśnięcie, akcje), paginacja, potwierdzenie usunięcia.
- `Create.vue`
- `Edit.vue`
- Wspólny komponent formularza.

### Formularz

- Pola: `original_url`, `title`, `is_active`, `expires_at`.
- Kod tylko do odczytu: na liście i na stronie edycji.
- Pełny adres skróconego linku (`short_url`) wyświetlany tylko do odczytu: na liście i w formularzu (strona edycji; przy tworzeniu kod jeszcze nie istnieje).

### Konwencje

- Trasy przez Wayfinder (`@/actions/...`).
- Formularze przez `<Form>` / `useForm`, tak jak na istniejących stronach ustawień.
- Układ i komponenty UI: reużyć z `resources/js/components` i `layouts`.
- Nawigacja panelu: pozycja "Linki".

## Obsługa błędów

| Sytuacja                                             | Efekt                                                                                                                           |
| ---------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Błąd walidacji                                       | Standardowo przez Inertia z powrotem do formularza                                                                              |
| Cudzy link (dowolna akcja: edycja, zapis, usunięcie) | Przekierowanie na listę linków (`links.index`) z ogólnym komunikatem błędu (toast), bez 403 i bez ujawniania, czy link istnieje |
| Gość                                                 | Przekierowanie na login (istniejący middleware `auth`)                                                                          |

## Testy (Pest, feature)

**Właściciel**

- Tworzenie, edycja, usuwanie.
- Lista zawiera tylko własne linki.

**Dostęp**

- Cudzy link: edycja (`GET`), aktualizacja i usunięcie przekierowują na `links.index`; link pozostaje bez zmian.
- Gość jest przekierowany na login dla tras `links.*`.

**Walidacja**

- Niepoprawny URL.
- Adres docelowy inny niż `http` lub `https` (`javascript:`, `ftp:`).
- Za długi URL lub tytuł.
- `expires_at` w przeszłości.

**Kod**

- Długość startowa 6.
- Alfabet `0-9A-Za-z`.
- Unikalność.
- Niezmienność po update.

**Pełny adres**

- `short_url` = adres aplikacji + `/` + `code`, ze schematem z `APP_URL`.
- Lista i strona edycji przekazują `short_url` do frontendu.
- Tabela `links` nie ma kolumny na ten adres.

**Generator**

- Skok na długość 7, gdy wolne kombinacje dla 6 spadną do progu 1%. Próg i/lub liczność wstrzyknięte w teście, bez tworzenia miliardów rekordów.
- Rozróżnianie wielkości liter.

## Dziennik decyzji

- Kod jest wyłącznie generowany. Użytkownik go nie wpisuje.
- Bez warstwy service/repository. Zwykły resource controller.
- Twarde usuwanie, bez soft delete.
