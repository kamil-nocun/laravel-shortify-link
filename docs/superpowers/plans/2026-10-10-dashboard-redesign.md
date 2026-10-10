# Przebudowa dashboardów – plan implementacji

> **Dla agentów wykonawczych:** WYMAGANY SUB-SKILL: użyj superpowers:subagent-driven-development (zalecane) albo superpowers:executing-plans, żeby wykonać plan zadanie po zadaniu. Kroki używają checkboxów (`- [ ]`).

**Cel:** Dwa osobne widoki (Klient / Admin) z przełącznikiem trybu w sidebarze, prawdziwy dashboard klienta (statystyki własnych linków + karta pakietu) i start administratora w `/admin` po zalogowaniu.

**Architektura:** Tryb wynika z adresu (`/admin*` = Admin), bez zapisu stanu. Dashboard klienta to nowy kontroler korzystający z istniejącego `AdminStatsReport::build($user, …)` i z wyodrębnionego `PackagePageData::current()`. Widoki Vue dzielą trzy komponenty wyciągnięte z `admin/Dashboard.vue`. Sidebar filtruje pozycje wg trybu, a `ModeSwitcher` pojawia się tylko dla administratorów.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia v3 + Vue, Wayfinder, Fortify, Pest, Pint, Tailwind.

**Spec:** `docs/superpowers/specs/2026-10-10-dashboard-redesign-design.md`

## Global Constraints

- Nie zmieniamy zależności i nie dodajemy nowych folderów bazowych. Nowe pliki trafiają do istniejących katalogów (`resources/js/components`, `resources/js/composables`, `app/Http/Controllers`).
- Konwencje projektu: typy zwrotne, Form Requests, Wayfinder (`@/routes/...`), Pest, Pint. Teksty UI po angielsku; spec i plany po polsku.
- Po zmianach PHP: `vendor/bin/pint --dirty --format agent`. Po zmianie tras: `php artisan wayfinder:generate --with-form --no-interaction`.
- Testy uruchamiamy wąsko: `php artisan test --compact <plik>`; pełny zestaw uruchamia użytkownik na końcu.
- Strony CRUD (linki, domeny, użytkownicy, role, pakiety, billing) zostają bez zmian.
- Superadmin nie ma pakietu: `package = null`, brak karty pakietu i pozycji „My package”.
- Tryb nie jest zapisywany (ani baza, ani cookie). Przełącznik widoczny wyłącznie przy `auth.isAdmin`.

## Review Focus

- Klient z cudzymi linkami i kliknięciami w bazie: liczniki i topy liczą tylko jego linki (Zadanie 2).
- Użytkownik bez przypisanego pakietu: karta pakietu pokazuje pakiet domyślny, nie błąd (Zadanie 1, 2).
- Zablokowany/nieweryfikowany użytkownik i gość: `/dashboard` nadal przekierowuje do logowania (Zadanie 2).
- Admin logujący się z zapamiętanym `intended` URL: trafia pod zapamiętany adres, nie na `/admin` (Zadanie 3).
- Okres spoza dozwolonych (`?period=bogus`) na `/dashboard`: błąd walidacji jak w adminie (Zadanie 2).

---

### Task 1: `PackagePageData::current()`

**Files:**
- Modify: `app/Billing/PackagePageData.php`
- Test: `tests/Feature/PackagePageDataTest.php` (nowy; `php artisan make:test --pest PackagePageDataTest`)

**Interfaces:**
- Produces: `PackagePageData::current(User $user): array` (publiczna) – dokładnie ten blok, który `for()` dziś zwraca pod kluczem `current` (klucze: `package_name`, `link_limit`, `domain_limit`, `domains_used`, `billing_period`, `expires_at`, `cancellable`, `can_buy`, `next_package_name`, `next_starts_at`, `one_off`, `cancels`). `for()` wywołuje `current()`; wynik `for()` się nie zmienia.

- [ ] **Step 1: Napisz test `current returns the package block without calling Stripe`**: `PackageBilling` zmockowany tak, by każde wywołanie rzucało wyjątek (`$this->mock(PackageBilling::class)->shouldNotReceive('pendingInvoiceUrl')` i analogicznie pozostałe metody); użytkownik z przypisanym pakietem (`PackageAssignment` przez factory); asercje: `package_name`, `link_limit`, `domain_limit`, `domains_used` zgodne z pakietem i liczbą domen.
- [ ] **Step 2: Napisz test `current falls back to the default package for a user without assignment`**: `package_name` równe nazwie pakietu `is_default`, `billing_period` i `expires_at` równe `null`.
- [ ] **Step 3: Uruchom** `php artisan test --compact tests/Feature/PackagePageDataTest.php`. Oczekiwane: FAIL (`current` nie istnieje).
- [ ] **Step 4: Wyodrębnij `public function current(User $user): array`** w `app/Billing/PackagePageData.php`: przenieś budowę bloku `current` (z `activeAssignment`, `subscription`, `queuedAssignment`) bez zmian treści; `for()` ustawia `'current' => $this->current($user)`. Docblock z array shape.
- [ ] **Step 5: Uruchom** `php artisan test --compact tests/Feature/PackagePageDataTest.php tests/Feature/PackagePurchaseTest.php tests/Feature/PaymentHistoryTest.php`. Oczekiwane: PASS (refaktor nie zmienia strony billing).
- [ ] **Step 6: Commit** `refactor: extract PackagePageData::current` (po `vendor/bin/pint --dirty --format agent`).

---

### Task 2: Kontroler dashboardu klienta

**Files:**
- Create: `app/Http/Controllers/DashboardController.php` (`php artisan make:controller DashboardController --invokable --no-interaction`)
- Modify: `routes/web.php:35` (zamiast `Route::inertia('dashboard', 'Dashboard')`)
- Modify: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: `AdminStatsReport::build(?User, LinkStatsRequest): array`, `AdminStatsRequest`, `PackagePageData::current(User): array` (Zadanie 1).
- Produces: trasa `dashboard` (GET `/dashboard`, middleware `auth`, `verified`) → `Inertia::render('Dashboard', …)` z propsami `period`, `granularity`, `total`, `series`, `counters`, `topLinks` (jak raport) oraz `package`: `null` dla superadmina, w przeciwnym razie `array{package_name: ?string, link_limit: ?int, links_used: int, domain_limit: ?int, domains_used: int, billing_period: ?string, expires_at: ?string}`. `topUsers` jest wycinane (nieużywane).

- [ ] **Step 1: Rozszerz `tests/Feature/DashboardTest.php`** (zostaw dwa istniejące testy). Dodaj testy:
  - `client dashboard counts only the client's own links and clicks`: klient i inny użytkownik, każdy z linkiem i kliknięciami; `assertInertia` – `component('Dashboard')`, `counters.links` = 1, `counters.clicks` = liczba kliknięć klienta, `topLinks` zawiera tylko link klienta.
  - `client dashboard exposes the package card data`: `package.package_name`, `package.link_limit`, `package.links_used` (liczba linków klienta), `package.domains_used`.
  - `client without an assigned package sees the default package`: `package.package_name` = nazwa pakietu domyślnego.
  - `super admin gets no package card`: `User::factory()->superAdmin()` → `package` równe `null`.
  - `period parameter is validated`: `?period=7d` → OK, `?period=bogus` → `assertSessionHasErrors('period')`.
- [ ] **Step 2: Uruchom** `php artisan test --compact tests/Feature/DashboardTest.php`. Oczekiwane: FAIL (komponent/propsy się nie zgadzają).
- [ ] **Step 3: Zaimplementuj `DashboardController::__invoke(AdminStatsRequest $request, AdminStatsReport $report, PackagePageData $packages): Response`**: raport z `$report->build($request->user(), $request)` bez klucza `topUsers`; `package` = `null` dla `isSuperAdmin()`, inaczej `Arr::only($packages->current($user), [...klucze z Interfaces bez links_used]) + ['links_used' => $user->links()->count()]`. Docblock jak w `Admin\DashboardController`.
- [ ] **Step 4: W `routes/web.php`** zarejestruj `Route::get('dashboard', DashboardController::class)->name('dashboard')` w tej samej grupie `auth`+`verified`; dodaj `use`.
- [ ] **Step 5: Uruchom** `php artisan wayfinder:generate --with-form --no-interaction`, potem `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/AuthRedirectTest.php`. Oczekiwane: PASS.
- [ ] **Step 6: Commit** `feat: add client dashboard controller`.

---

### Task 3: Przekierowanie administratora po zalogowaniu

**Files:**
- Create: `app/Http/Responses/LoginResponse.php` (generyczna klasa: `php artisan make:class Http/Responses/LoginResponse --no-interaction`)
- Modify: `app/Providers/FortifyServiceProvider.php` (`register()`)
- Test: `tests/Feature/Auth/AuthenticationTest.php`

**Interfaces:**
- Produces: `App\Http\Responses\LoginResponse implements Laravel\Fortify\Contracts\LoginResponse`; `toResponse($request)`: JSON `['two_factor' => false]` gdy `wantsJson()`, w przeciwnym razie `redirect()->intended(route($request->user()->isAdmin() ? 'admin.dashboard' : 'dashboard'))`. Związana w kontenerze: `$this->app->singleton(LoginResponseContract::class, LoginResponse::class)`.

- [ ] **Step 1: Dopisz testy w `AuthenticationTest.php`**:
  - `admins are redirected to the admin dashboard after login` (`User::factory()->admin()`, POST `login` → `assertRedirect(route('admin.dashboard', absolute: false))`).
  - `super admins are redirected to the admin dashboard after login`.
  - `login still honours the intended url for admins`: najpierw GET chronionej strony (np. `route('links.index')`) jako gość, potem login → redirect na `links.index`.
  - istniejący test klienta dalej oczekuje `dashboard`.
- [ ] **Step 2: Uruchom** `php artisan test --compact tests/Feature/Auth/AuthenticationTest.php`. Oczekiwane: FAIL (admin ląduje na `dashboard`).
- [ ] **Step 3: Zaimplementuj `LoginResponse`** wg Interfaces i zbinduj w `FortifyServiceProvider::register()`. Zweryfikuj, że `isAdmin()` obejmuje superadmina (`app/Models/User.php:139`); jeśli nie, użyj `isAdmin() || isSuperAdmin()`.
- [ ] **Step 4: Uruchom** `php artisan test --compact tests/Feature/Auth`. Oczekiwane: PASS (w tym dwufazowe logowanie, jeśli pokryte).
- [ ] **Step 5: Commit** `feat: send admins to the admin dashboard after login`.

---

### Task 4: Wspólne komponenty dashboardu i refaktor widoku admina

**Files:**
- Create: `resources/js/components/StatCards.vue`, `PeriodSelector.vue`, `TopLinksTable.vue`
- Modify: `resources/js/pages/admin/Dashboard.vue`
- Modify: `resources/js/types/link.ts` (typ `StatCard`, jeśli potrzebny)

**Interfaces:**
- Produces:
  - `StatCards.vue` – props `cards: { label: string; value: string | number }[]`; ta sama siatka `sm:grid-cols-2 lg:grid-cols-4` i kafelki co dziś w admin.
  - `PeriodSelector.vue` – props `current: string` (np. `'7d'`), `url: string`; przyciski 7/30/90 dni, `router.get(url, { period }, { preserveScroll: true })`, `aria-pressed`, wariant `default`/`outline` jak dziś.
  - `TopLinksTable.vue` – props `links: TopLink[]`, `href: (id: string) => NonNullable<InertiaLinkProps['href']>`; sekcja „Top links” z tabelą i stanem „No data”, markup z dzisiejszego admin.
- Brak testów PHP (zmiana tylko widoku); weryfikacja w kroku 4.

- [ ] **Step 1: Wyciągnij komponenty** z `admin/Dashboard.vue` bez zmiany wyglądu (przenieś markup i klasy).
- [ ] **Step 2: Przepisz `admin/Dashboard.vue`** na nowe komponenty: `PeriodSelector :url="dashboard().url"`, `TopLinksTable :href="linkStats"`. Usuń przyciski „Users” i „My package” z nagłówka oraz nieużywane importy (`usePage`, `billingPackage`, `usersIndex`, `Button` jeśli zbędny). Tabela „Top users” zostaje w pliku.
- [ ] **Step 3: Uruchom** `npm run build` (typy/lint) – bez błędów; opcjonalnie `npm run lint` jeśli skrypt istnieje w `package.json`.
- [ ] **Step 4: Sprawdź ręcznie** w przeglądarce `/admin`: wygląd i działanie wyboru okresu bez zmian względem poprzedniej wersji.
- [ ] **Step 5: Commit** `refactor: share dashboard components between views`.

---

### Task 5: Widok dashboardu klienta

**Files:**
- Create: `resources/js/components/PackageCard.vue`
- Modify: `resources/js/pages/Dashboard.vue`
- Modify: `resources/js/types/link.ts` (typ `DashboardPackage`)

**Interfaces:**
- Consumes: `StatCards`, `PeriodSelector`, `TopLinksTable` (Zadanie 4); `ClicksChart`; propsy z Zadania 2; trasy `@/routes` (`dashboard`), `@/routes/links` (`create`, `stats`), `@/routes/billing/package` (`show`).
- Produces: `PackageCard.vue` – prop `package: DashboardPackage` (`package_name`, `link_limit`, `links_used`, `domain_limit`, `domains_used`, `billing_period`, `expires_at`); pokazuje nazwę, paski wykorzystania linków i domen (`role="progressbar"`, `aria-valuenow/max`; limit `null` = „unlimited”, bez paska), datę wygaśnięcia (gdy nie `null`) i link „Manage package” do `billing/package`.

- [ ] **Step 1: Napisz `Dashboard.vue`**: nagłówek `Heading` + `PeriodSelector :url="dashboard().url"` + przycisk „New link” (`links.create`, widoczny tylko gdy `can('links.manage')` z `usePermissions`); `StatCards` (Links, Active / disabled, Clicks); `ClicksChart` lub „No clicks in this period yet.” gdy `total === 0`; układ dwukolumnowy `TopLinksTable :href="linkStats"` + `PackageCard` (`v-if="package"`). Usuń placeholder (`PlaceholderPattern`) i stary kafelek „My package and payments”.
- [ ] **Step 2: Uruchom** `npm run build` – bez błędów typów.
- [ ] **Step 3: Sprawdź ręcznie** `/dashboard` jako klient (liczniki, wykres, karta pakietu, pasek limitu, wybór okresu) i jako superadmin (brak karty pakietu); sprawdź ciemny motyw i szerokość mobilną.
- [ ] **Step 4: Commit** `feat: build the client dashboard`.

---

### Task 6: Przełącznik trybu i sidebar

**Files:**
- Create: `resources/js/composables/useViewMode.ts`, `resources/js/components/ModeSwitcher.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Test: `tests/Feature/DashboardTest.php` (dwa testy współdzielonych propsów)

**Interfaces:**
- Produces: `useViewMode(): { isAdminMode: ComputedRef<boolean> }` – `usePage().url.startsWith('/admin')`. `ModeSwitcher.vue` – bez propsów; dwa `Link`i (ikony + etykiety „Client” / „Admin”; w zwiniętym sidebarze same ikony z tooltipem), aktywny z `aria-current="page"`, renderowany `v-if="page.props.auth.isAdmin"`.

- [ ] **Step 1: Napisz testy** w `DashboardTest.php`: `shared props flag admins` (admin i superadmin dostają `auth.isAdmin === true`) oraz `shared props do not flag regular users` (`false`).
- [ ] **Step 2: Uruchom** `php artisan test --compact tests/Feature/DashboardTest.php`. Oczekiwane: PASS (prop już istnieje w `HandleInertiaRequests`); testy zabezpieczają kontrakt, na którym opiera się przełącznik.
- [ ] **Step 3: Zaimplementuj `useViewMode` i `ModeSwitcher`**; wstaw `ModeSwitcher` w `SidebarHeader` pod logo w `AppSidebar.vue`.
- [ ] **Step 4: Przebuduj `mainNavItems` w `AppSidebar.vue`** wg trybu (warunki `can(...)` zachowane):
  - Admin: Dashboard (`@/routes/admin` `dashboard`), Users, Roles, Packages, Administrators (etykieta zmieniona z „Admin”).
  - Klient: Dashboard (`@/routes` `dashboard`), My links, My domains, My package (`billing.manage` i nie superadmin).
  - Logo: `href` = dashboard bieżącego trybu.
- [ ] **Step 5: Uruchom** `npm run build`; sprawdź ręcznie jako admin (przełączanie trybów, sidebar, aktywna pozycja, zwinięty sidebar) i jako klient (brak przełącznika, `/admin` → 403).
- [ ] **Step 6: Commit** `feat: add client/admin mode switcher to the sidebar`.

---

### Task 7: Domknięcie

**Files:**
- Modify (jeśli potrzeba): `tests/e2e/links.spec.ts:26`, `tests/e2e/stats.spec.ts:24`

- [ ] **Step 1: Sprawdź e2e** – oba testy po logowaniu oczekują URL `/dashboard`. Jeśli logują administratora, zmień oczekiwanie na `/admin/` albo użyj klienta; zachowaj intencję testu.
- [ ] **Step 2: Uruchom** `vendor/bin/pint --dirty --format agent`, `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/Admin/AdminDashboardTest.php tests/Feature/Auth tests/Feature/AuthRedirectTest.php tests/Feature/PackagePageDataTest.php`. Oczekiwane: PASS.
- [ ] **Step 3: Poproś użytkownika** o pełny zestaw: `php artisan test --compact` oraz ewentualne e2e Playwright.
- [ ] **Step 4: Commit** zmian z kroku 1 (`test: follow admin login redirect`), jeśli powstały.
