# Przebudowa dashboardów (klient / administrator) – design

Rozszerza `2026-10-09-admin-panel-design.md`. Tam, gdzie dokumenty się różnią, obowiązuje ten.

## Cel

Dwa osobne widoki aplikacji: **Klient** i **Admin**, z przełącznikiem trybu. Przełącznik zmienia stronę główną (dashboard) oraz zestaw pozycji w sidebarze. Dziś `/dashboard` klienta jest placeholderem, a sidebar miesza pozycje admina i klienta w jednej liście.

Poza zakresem: strony CRUD (linki, domeny, użytkownicy, role, pakiety, billing) – zostają bez zmian.

## Ustalenia

- Tryb wynika z adresu: `/admin/*` to tryb Admin, pozostałe adresy to tryb Klient. Tryb nie jest zapisywany (ani w bazie, ani w cookie).
- Przełącznik widzą wyłącznie administratorzy (`auth.isAdmin`). Zwykły klient widzi sidebar trybu Klient bez przełącznika.
- Administrator po zalogowaniu ląduje na `/admin` (`admin.dashboard`), zwykły klient na `/dashboard`.
- Dashboard klienta pokazuje statystyki własnych linków i kartę pakietu.
- Superadmin nie ma pakietu (jak dziś przy „My package”), więc w trybie Klient nie widzi karty pakietu.
- Nowe zapytania statystyk nie powstają: `AdminStatsReport::build($user, $request)` już obsługuje zakres jednego użytkownika.

## Backend

- `GET /dashboard` obsługuje nowy `App\Http\Controllers\DashboardController` (zamiast `Route::inertia`). Nazwa trasy `dashboard` zostaje.
- Kontroler zwraca `Inertia::render('Dashboard', ...)` z danymi `AdminStatsReport::build($request->user(), $request)` (walidacja okresu przez `AdminStatsRequest`) oraz kluczem `package`.
- `package` dla superadmina to `null`. Dla pozostałych: `package_name`, `link_limit`, `links_used`, `domain_limit`, `domains_used`, `expires_at`, `billing_period`.
- Dane pakietu pochodzą z istniejącego `PackagePageData`. Blok `current` jest dziś budowany razem z wywołaniami Stripe (`invoiceUrl`), więc wyodrębniamy go do publicznej metody `PackagePageData::current(User): array`, bez zapytań do Stripe. `for()` korzysta z tej samej metody. Dashboard dokłada `links_used`.
- Redirect po logowaniu: własna implementacja `Laravel\Fortify\Contracts\LoginResponse` w `FortifyServiceProvider`. Admin → `route('admin.dashboard')`, reszta → `config('fortify.home')`. Zachowuje `redirect()->intended(...)`.
- Odpowiedź na `topUsers` jest pusta przy zakresie użytkownika, więc klient jej nie używa.

## Frontend

- `components/ModeSwitcher.vue`: dwuprzyciskowy przełącznik Klient / Admin w `SidebarHeader`, widoczny gdy `auth.isAdmin`. Linkuje do `dashboard()` i `admin.dashboard()`. Aktywny tryb oznaczony `aria-current`. W trybie zwiniętego sidebara pokazuje same ikony.
- `AppSidebar.vue`: tryb liczony z `page.url` (`startsWith('/admin')`). Pozycje:
  - Admin: Dashboard (`admin.dashboard`), Users, Roles, Packages, Administrators (zachowują dzisiejsze warunki `can(...)`).
  - Klient: Dashboard, My links, My domains, My package (warunki jak dziś).
- Logo w nagłówku sidebara linkuje do dashboardu bieżącego trybu.
- `pages/Dashboard.vue` (klient): nagłówek z wyborem okresu 7/30/90 dni i przyciskiem „New link”; liczniki; `ClicksChart`; tabela top linków; karta pakietu (nazwa, pasek wykorzystania limitu linków, domeny, data wygaśnięcia, link do `billing/package`). Brak danych: komunikat „No clicks in this period yet.”.
- Wspólne komponenty wyodrębnione z `admin/Dashboard.vue`: `StatCards.vue`, `PeriodSelector.vue`, `TopLinksTable.vue`. Używają ich oba dashboardy. Link w `TopLinksTable` jest przekazywany jako prop (statystyki klienta lub admina).
- `pages/admin/Dashboard.vue`: korzysta ze wspólnych komponentów; przyciski „Users” i „My package” z nagłówka znikają (przejmuje je sidebar i przełącznik). Tabela top users zostaje.
- Nazwy w interfejsie po angielsku, jak dotychczas.
- Zmiany w Wayfinder: po dodaniu kontrolera regenerujemy akcje (`php artisan wayfinder:generate`).

## Obsługa błędów i dostęp

- `/admin/*` pozostaje za middleware `admin`; zwykły klient dostaje 403 (bez zmian).
- Okres spoza dozwolonych wartości odrzuca `AdminStatsRequest` (bez zmian).

## Testy (Pest)

- `/dashboard`: klient dostaje wyłącznie statystyki własnych linków (cudze linki nie wpływają na liczniki) i blok `package`.
- `/dashboard`: superadmin dostaje `package = null`.
- Logowanie: admin przekierowany na `admin.dashboard`, klient na `dashboard`.
- Przełącznik: `auth.isAdmin` jest `true` dla admina i `false` dla klienta (props Inertia).
- `PackagePageData::current` nie wywołuje Stripe.
- Regresja: dotychczasowe testy `admin/Dashboard` przechodzą.
- Interfejs (sidebar, przełącznik, wygląd) weryfikujemy ręcznie w przeglądarce; bez testów frontendowych.
