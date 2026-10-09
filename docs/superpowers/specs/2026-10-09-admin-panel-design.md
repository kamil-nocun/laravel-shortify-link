# Panel administratora

Data: 2026-10-09

## W skrócie

- Trzy role użytkowników: `User`, `Admin`, `SuperAdmin`. Panel pod `/admin` jest dostępny dla `Admin` i `SuperAdmin`.
- Admin zarządza użytkownikami (lista, podgląd, blokada, usunięcie), moderuje ich linki (wyłączenie i włączenie osobną flagą) i widzi statystyki: globalne, per użytkownik oraz per link.
- `SuperAdmin` dodatkowo zmienia role (`User` ↔ `Admin`). Pierwszego `SuperAdmin` nadaje komenda artisan.
- Właściciel linku dostaje szybki przełącznik włącz/wyłącz w liście swoich linków.

## Zakres

| W zakresie | Poza zakresem |
|---|---|
| Enum `UserRole`, kolumna `role` | Osobny guard i tabela adminów |
| Blokada i odblokowanie konta, usunięcie konta | Gotowe pakiety panelu (Filament, Nova) |
| Wyłączanie i włączanie linku przez admina (`disabled_by_admin_at`) | Globalna lista wszystkich linków (linki widać w kontekście użytkownika) |
| Statystyki globalne i per użytkownik: liczniki, kliknięcia w czasie, top linki, top użytkownicy | Rozbicia kraj/przeglądarka/urządzenie w skali globalnej |
| Statystyki dowolnego linku dla admina (reużycie obecnej strony) | Nadawanie `SuperAdmin` w panelu |
| Zmiana roli `User` ↔ `Admin` przez `SuperAdmin` | Dziennik audytu działań admina |
| Przełącznik `is_active` dla właściciela w liście linków | Powiadomienia e-mail o blokadzie |
| Komenda `user:make-super-admin` | Testy frontu (Vue) |

## Założenia

- Panel jest częścią istniejącej aplikacji (Laravel, Inertia, Vue, Fortify, klucze UUID), pod prefiksem `/admin`, z istniejącym `AppLayout`.
- Nowe kolumny to nullable timestampy (`blocked_at`, `disabled_by_admin_at`) oraz string z castem na enum (`role`, domyślnie `User`).
- Admin nie przełącza `is_active` cudzego linku. Ma jedną, jasną akcję: blokadę (`disabled_by_admin_at`). Dzięki temu widać, kto link wyłączył, a właściciel nie cofnie decyzji admina.
- Nowe zależności: brak.
- Konwencje projektu: nazwy wartości enuma w TitleCase, Form Requests, policy, Wayfinder dla tras we froncie.

## Model danych

Osobne migracje (wcześniejsze są już uruchomione):

| Tabela | Kolumna | Typ |
|---|---|---|
| `users` | `role` | string, domyślnie `user`, cast na `UserRole` |
| `users` | `blocked_at` | timestamp, nullable |
| `links` | `disabled_by_admin_at` | timestamp, nullable |

Enum `UserRole` (backed string): `User`, `Admin`, `SuperAdmin`. Factory: `UserFactory` dostaje stany `admin()`, `superAdmin()`, `blocked()`, a `LinkFactory` stan `disabledByAdmin()`.

## Role i uprawnienia

- `Admin` zarządza użytkownikami z rolą `User`: blokada, odblokowanie, usunięcie, moderacja linków, statystyki.
- `SuperAdmin` robi to samo wobec wszystkich oprócz siebie i dodatkowo zmienia role `User` ↔ `Admin`.
- Nikt nie blokuje ani nie usuwa samego siebie. `Admin` nie rusza kont `Admin` ani `SuperAdmin`. `SuperAdmin` nie może zmienić własnej roli, a rola `SuperAdmin` nie jest nadawalna w panelu.
- Reguły siedzą w `UserPolicy` (`block`, `delete`, `changeRole`). Dostęp do `/admin` daje middleware `admin` (rola `Admin` lub `SuperAdmin`, inaczej 403).
- Komenda `user:make-super-admin {email}` nadaje rolę istniejącemu użytkownikowi. Dla nieistniejącego e-maila kończy się błędem i niezerowym kodem wyjścia.
- Backend zawsze egzekwuje reguły. Flagi `can: {block, delete, changeRole}` serwowane do frontu służą wyłącznie do ukrywania akcji.

## Blokada konta

- Middleware w grupie `web` sprawdza `blocked_at`. Zablokowany użytkownik z aktywną sesją jest wylogowywany i odsyłany na logowanie z komunikatem.
- Fortify (`authenticateUsing`) odrzuca logowanie zablokowanego konta, bez ujawniania szczegółów.
- Scope `Link::active()` jest rozszerzony o `disabled_by_admin_at IS NULL` oraz o warunek, że właściciel nie ma `blocked_at`. Wszystko, co używa `active()` (w tym redirect `/{code}`), działa spójnie: linki zablokowanego użytkownika dają 404, a po odblokowaniu znów przekierowują.
- Usunięcie konta kasuje użytkownika, jego linki i `link_statistics`. Kaskada na `link_statistics` jest pokryta testem. Jeśli klucz obcy jej nie zapewnia, migracja to uzupełnia.

## Trasy i kontrolery

Grupa `auth`, `verified`, `admin`, prefiks `admin`, nazwy `admin.*`:

| Trasa | Kontroler | Cel |
|---|---|---|
| `GET admin` | `Admin\DashboardController` | liczniki i statystyki globalne |
| `GET admin/users` | `Admin\UserController@index` | lista, wyszukiwanie po nazwie i e-mailu, paginacja |
| `GET admin/users/{user}` | `Admin\UserController@show` | dane użytkownika, jego linki, jego statystyki |
| `DELETE admin/users/{user}` | `Admin\UserController@destroy` | usunięcie konta |
| `PATCH admin/users/{user}/block`, `DELETE admin/users/{user}/block` | `Admin\UserBlockController` | blokada i odblokowanie |
| `PATCH admin/users/{user}/role` | `Admin\UserRoleController` | zmiana roli (tylko `SuperAdmin`) |
| `PATCH admin/links/{link}/disable`, `DELETE admin/links/{link}/disable` | `Admin\LinkDisableController` | ustawienie i zdjęcie `disabled_by_admin_at` |
| `GET admin/links/{link}/stats`, `GET admin/links/{link}/stats/data` | `Admin\LinkStatsController` | statystyki dowolnego linku |

Poza grupą admina, w grupie użytkownika: `PATCH links/{link}/toggle` (przełącznik `is_active`, autoryzacja przez `LinkPolicy::update`). Zmiana nie dotyka `disabled_by_admin_at`.

Walidacja przez Form Requests: zmiana roli (`Rule::enum`, dozwolone tylko `User` i `Admin`) oraz parametry okresu i wyszukiwania.

## Statystyki

- Nowa klasa `App\Support\AdminStatsReport` z opcjonalnym `?User`. Zwraca: liczniki (użytkownicy, linki aktywne i wyłączone, kliknięcia), kliknięcia w czasie (reużycie `StatsPeriod` i logiki szeregu dziennego), top 10 linków po kliknięciach oraz top 10 użytkowników (pomijane w widoku per użytkownik). Z podanym użytkownikiem wszystko zawęża się do jego linków, a kliknięcia innych nie przeciekają.
- Wspólny kod szeregu dziennego i okresu jest wyciągany z `LinkStatsReport`, zamiast kopiowany. Refaktor nie zmienia zachowania: istniejące testy statystyk muszą przejść bez edycji.
- `Admin\LinkStatsController` używa istniejącego `LinkStatsReport`, bez sprawdzania właściciela. Strona `links/Stats.vue` dostaje propsy z nazwami tras (zwykła i admina), więc jeden komponent obsługuje oba widoki.

## UI

Layout i nawigacja:
- Istniejący `AppLayout`. W `AppSidebar` pozycja "Admin" widoczna dla `Admin` i `SuperAdmin` (współdzielony prop `auth.user.role`). Pod `/admin` podnawigacja: Przegląd, Użytkownicy.

Strony w `resources/js/pages/admin/`:
- `Dashboard.vue`: karty z licznikami, wykres kliknięć z przełącznikiem 7/30/90 dni, tabele top linków i top użytkowników. Reużywa `StatsTable.vue`, a wykres ze `Stats.vue` jest wyciągnięty do wspólnego komponentu.
- `users/Index.vue`: tabela (nazwa, e-mail, rola, status, liczba linków, data rejestracji), wyszukiwanie przez `router.get` z `preserveState`, paginacja.
- `users/Show.vue`: dane i status konta, akcje (Zablokuj/Odblokuj, Usuń z potwierdzeniem, Zmień rolę widoczna tylko dla `SuperAdmin`), karty i wykres statystyk użytkownika, tabela jego linków (tytuł, kod, kliknięcia, status: aktywny, wyłączony przez właściciela, wyłączony przez admina; przyciski "Wyłącz/Włącz (admin)" i "Statystyki").
- Statystyki linku: `links/Stats.vue` z trasami admina i linkiem powrotnym do użytkownika.

Widok właściciela (`links/Index.vue`): przełącznik `is_active` (natywny `<button role="switch">`, `router.patch`). Dla linku zablokowanego przez admina przełącznik jest nieaktywny i widnieje znacznik "Zablokowany przez administratora".

Ogólne: trasy przez Wayfinder, teksty przez istniejący mechanizm tłumaczeń, puste stany z komunikatem "Brak danych", dane jako zwykłe propsy (zapytania zagregowane, tanie przy obecnej skali), `aria-label` na przełącznikach i przyciskach, okno usunięcia z fokusem na akcji bezpiecznej.

## Testy

Pest, feature, na factory.

- **Dostęp:** gość trafia na logowanie, `User` dostaje 403 na każdej trasie `/admin/*` (test na zbiorze tras), `Admin` i `SuperAdmin` wchodzą. Prop roli trafia do frontu.
- **Blokada konta:** wylogowanie aktywnej sesji, odmowa logowania, 404 linków zablokowanego i powrót po odblokowaniu. Admin blokuje `User`, ale nie siebie ani innego `Admin` lub `SuperAdmin` (403). `SuperAdmin` blokuje `Admin`, ale nie siebie.
- **Usuwanie konta:** usuwa użytkownika, linki i `link_statistics`. Te same ograniczenia ról co przy blokadzie.
- **Zmiana roli:** `SuperAdmin` zmienia `User` ↔ `Admin`, `Admin` dostaje 403, nie da się nadać `SuperAdmin` ani zmienić własnej roli, wartość spoza `User` i `Admin` jest odrzucana. Komenda `user:make-super-admin`: sukces i błąd dla nieistniejącego e-maila.
- **Moderacja linków:** ustawienie i zdjęcie blokady, zablokowany link daje 404 mimo `is_active`. Właściciel nie zdejmie blokady admina. Przełącznik właściciela działa tylko na jego linku (cudzy: 403). `Link::active()` w pełnej tabeli (dataset: `is_active` × `disabled_by_admin_at` × `expires_at` × zablokowany właściciel).
- **Statystyki:** `AdminStatsReport` (liczniki, szereg dzienny z zerami, top linki i użytkownicy, brak przecieku między użytkownikami), admin widzi statystyki cudzego linku, `User` nadal dostaje 403 na cudzym linku, istniejące testy statystyk bez zmian.
- **Lista użytkowników:** wyszukiwanie, paginacja, poprawne flagi `can` zależnie od roli zalogowanego.
- Front (Vue) bez testów jednostkowych, zgodnie z konwencją projektu. Ścieżkę "admin wyłącza link" można pokryć jednym testem Playwright, jeśli konfiguracja na to pozwala.

## Kolejność prac

1. Migracje, enum, factory, `UserPolicy`, middleware, komenda.
2. Blokada konta i rozszerzony `Link::active()`.
3. Przełącznik `is_active` dla właściciela.
4. Refaktor `LinkStatsReport` i `AdminStatsReport`.
5. Kontrolery admina i trasy.
6. Frontend: wspólny komponent wykresu, strony admina, sidebar.
7. Przegląd i test ręczny.
