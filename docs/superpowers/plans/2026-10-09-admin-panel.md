# Panel administratora: plan implementacji

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Panel `/admin` z rolami `User`/`Admin`/`SuperAdmin`: zarządzanie użytkownikami, moderacja linków i statystyki globalne, per użytkownik oraz per link.

**Architecture:** Panel w istniejącej aplikacji (Laravel + Inertia/Vue). Dostęp przez enum `UserRole`, middleware `admin` i `UserPolicy`. Blokady to nullable timestampy (`users.blocked_at`, `links.disabled_by_admin_at`) uwzględnione w scope `Link::active()`. Statystyki: nowy `AdminStatsReport` współdzieli z `LinkStatsReport` budowę szeregu czasowego, a `links/Stats.vue` obsługuje oba widoki przez propsy URL.

**Tech Stack:** Laravel / PHP 8.5, Inertia v3 + Vue, Fortify, Wayfinder, Pest. Brak nowych zależności.

**Spec:** `docs/superpowers/specs/2026-10-09-admin-panel-design.md`

## Global Constraints

- Nowe zależności: brak (zmiana zależności wymaga zgody).
- Klucze UUID; nowe kolumny: `users.role` (string, domyślnie `user`, cast `UserRole`), `users.blocked_at` i `links.disabled_by_admin_at` (timestamp, nullable). Osobne migracje, wcześniejszych nie edytujemy.
- Wartości enuma w TitleCase: `User`, `Admin`, `SuperAdmin` (wartości string: `user`, `admin`, `super_admin`).
- W systemie najwyżej jeden `SuperAdmin`, tworzony wyłącznie komendą `user:make-super-admin {email}`. Panel nie nadaje `SuperAdmin`a.
- Admin nie przełącza `is_active` cudzego linku; moderuje tylko przez `disabled_by_admin_at`. Właściciel nie zdejmie blokady admina.
- Nikt nie blokuje ani nie usuwa siebie. `Admin` rusza tylko konta `User`. `SuperAdmin` rusza wszystkich oprócz siebie. Zmiana roli: tylko `SuperAdmin`, tylko `User` ↔ `Admin`, nie sobie.
- Backend zawsze egzekwuje reguły; flagi `can` na froncie służą tylko do ukrywania akcji.
- Teksty frontu po angielsku (tak jak reszta UI), komunikaty serwera przez `__()`. Trasy we froncie przez Wayfinder (`php artisan wayfinder:generate`; katalogi `resources/js/routes|actions` są w `.gitignore`).
- Konwencje: Form Requests, policy, `declare` zgodne z sąsiednimi plikami, `vendor/bin/pint --dirty --format agent` przed każdym commitem PHP, testy Pest (`php artisan test --compact <plik>`), istniejące testy statystyk przechodzą bez edycji.
- Commity: komunikaty po angielsku, trailer `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Review Focus

- Zablokowany użytkownik z aktywną sesją: następne żądanie wylogowuje (też żądania JSON/Inertia), a ponowne logowanie jest odrzucone. Test w Task 3.
- Admin otwiera `/admin/users/{id}` nieistniejącego użytkownika lub `/admin/links/{id}` nieistniejącego linku: 404, nie 500. Test w Task 6 i 7.
- `AdminStatsReport` dla użytkownika bez linków i dla systemu bez kliknięć: same zera, puste listy, bez dzielenia przez zero. Test w Task 5.
- Wyszukiwanie użytkowników z `%` i `_` w frazie traktuje je literalnie. Test w Task 6.
- Dwa równoległe uruchomienia komendy tworzącej `SuperAdmin`a nie dają dwóch kont (transakcja + `lockForUpdate`). Test jedyności w Task 1 (sekwencyjny; blokada jest w kodzie).

---

## Struktura plików

| Plik | Odpowiedzialność |
|---|---|
| `app/Enums/UserRole.php` | enum ról z helperami |
| `app/Models/User.php`, `app/Models/Link.php` | cast roli, `blocked_at`, `disabled_by_admin_at`, scope `active()` |
| `app/Console/Commands/MakeSuperAdmin.php` | komenda `user:make-super-admin` |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | alias `admin` |
| `app/Http/Middleware/EnsureAccountIsNotBlocked.php` | wylogowanie zablokowanych |
| `app/Policies/UserPolicy.php`, `app/Policies/LinkPolicy.php` | reguły panelu i `toggle` |
| `app/Support/StatsSeries.php` | wspólny szereg dzienny/miesięczny |
| `app/Support/AdminStatsReport.php` | statystyki globalne i per użytkownik |
| `app/Http/Controllers/Admin/*` | kontrolery panelu |
| `app/Http/Controllers/LinkToggleController.php` | przełącznik `is_active` właściciela |
| `resources/js/components/ClicksChart.vue` | wspólny wykres słupkowy |
| `resources/js/pages/admin/Dashboard.vue`, `users/Index.vue`, `users/Show.vue` | strony panelu |

---

### Task 1: Role, migracje, factory, komenda `user:make-super-admin`

**Files:**
- Create: `app/Enums/UserRole.php`, `app/Console/Commands/MakeSuperAdmin.php`, dwie migracje (`add_role_and_blocked_at_to_users_table`, `add_disabled_by_admin_at_to_links_table`; `php artisan make:migration`)
- Modify: `app/Models/User.php`, `app/Models/Link.php` (PHPDoc, cast), `database/factories/UserFactory.php`, `database/factories/LinkFactory.php`, `resources/js/types/auth.ts`, `resources/js/types/link.ts`
- Test: `tests/Feature/MakeSuperAdminCommandTest.php`, `tests/Feature/UserRoleTest.php`

**Interfaces:**
- Produces: `enum App\Enums\UserRole: string { case User = 'user'; case Admin = 'admin'; case SuperAdmin = 'super_admin'; public function isAdmin(): bool /* Admin lub SuperAdmin */ }`.
- Produces na `User`: `role: UserRole`, `blocked_at: ?Carbon`, `isAdmin(): bool`, `isSuperAdmin(): bool`, `isBlocked(): bool`. Na `Link`: `disabled_by_admin_at: ?Carbon`, `isDisabledByAdmin(): bool`.
- Produces factory: `UserFactory::admin()`, `superAdmin()`, `blocked()`; `LinkFactory::disabledByAdmin()`.
- Produces TS: `User.role: 'user' | 'admin' | 'super_admin'`, `User.blocked_at`; `Link.disabled_by_admin_at: string | null`.

- [ ] **Step 1: Write failing tests** w `UserRoleTest`: nowy użytkownik ma `role === UserRole::User`; `UserRole::isAdmin()` jest `false` dla `User` i `true` dla `Admin` i `SuperAdmin`; `User::factory()->blocked()->create()->isBlocked()` jest `true`; `Link::factory()->disabledByAdmin()->create()->isDisabledByAdmin()` jest `true`. W `MakeSuperAdminCommandTest` (artisan `expectsQuestion`/`expectsOutputToContain`, `assertExitCode`):
  - brak konta: komenda pyta o `Name` i `Password` (ukryte), tworzy konto z `role = SuperAdmin` i `email_verified_at` ustawionym, exit 0;
  - istniejące konto `User` lub `Admin` jest awansowane, bez pytań, hasło i imię niezmienione, exit 0;
  - gdy `SuperAdmin` już istnieje: exit 1, błąd, dla tego samego i dla innego e-maila, i żadne konto się nie zmienia ani nie powstaje;
  - ponowne uruchomienie dla e-maila obecnego `SuperAdmin`a: exit 1 (też odmowa), bez zmian;
  - e-mail niepoprawny albo słabe hasło (`Password::defaults()`): exit 1, bez konta.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/UserRoleTest.php tests/Feature/MakeSuperAdminCommandTest.php`. Expected: FAIL (brak klas/kolumn).
- [ ] **Step 3: Migracje i enum.** Kolumny z Global Constraints; `down()` usuwa je. `UserRole` w `app/Enums`.
- [ ] **Step 4: Model i factory.** Cast `'role' => UserRole::class` w `casts()`, `'blocked_at' => 'datetime'`, helpery na `User`; `disabled_by_admin_at` cast + `isDisabledByAdmin()` na `Link`; stany factory. Nie dodawaj `role`/`blocked_at` do `#[Fillable]` (mass assignment zabezpieczony; komenda ustawia je jawnie).
- [ ] **Step 5: `MakeSuperAdmin` (`protected $signature = 'user:make-super-admin {email}'`).** Całość w `DB::transaction`: `User::where('role', SuperAdmin)->lockForUpdate()->exists()` → błąd (`return self::FAILURE`). Dalej: konto o e-mailu istnieje → `forceFill(['role' => SuperAdmin])->save()`; nie istnieje → `$this->ask('Name')`, `$this->secret('Password')`, walidacja `Validator` z regułami `email` (`required|email|max:255`), `name` (`required|string|max:255`), `password` (`Password::defaults()`) i utworzenie konta przez `forceFill` z `email_verified_at => now()`.
- [ ] **Step 6: Typy TS** (`auth.ts`, `link.ts`) według bloku Interfaces.
- [ ] **Step 7: Run** testy z kroku 2. Expected: PASS. Pint.
- [ ] **Step 8: Commit** `Add user roles, block columns and the make-super-admin command`.

---

### Task 2: Middleware `admin`, `UserPolicy`, `LinkPolicy`

**Files:**
- Create: `app/Http/Middleware/EnsureUserIsAdmin.php`, `app/Policies/UserPolicy.php`
- Modify: `bootstrap/app.php` (alias `admin`), `app/Policies/LinkPolicy.php`
- Test: `tests/Feature/UserPolicyTest.php`, `tests/Feature/AdminAccessTest.php`

**Interfaces:**
- Consumes: Task 1 (`UserRole`, `User::isAdmin()`, `isSuperAdmin()`).
- Produces: middleware alias `admin` (403 dla nie-adminów, w tym `abort(403)` także dla JSON). `UserPolicy`: `block(User $actor, User $target): bool`, `delete(User $actor, User $target): bool`, `changeRole(User $actor, User $target): bool`. `LinkPolicy::view` zwraca `true` także dla `isAdmin()`; `LinkPolicy::toggle(User $user, Link $link): bool` = właściciel **i** `disabled_by_admin_at === null`. `update`/`delete` bez zmian (tylko właściciel).

- [ ] **Step 1: Write failing tests.** `UserPolicyTest` (dataset aktor × cel, `Gate::forUser($actor)->allows('block', $target)`):
  - `block` i `delete`: `Admin` → `User` tak; `Admin` → `Admin`/`SuperAdmin` nie; `SuperAdmin` → `User`/`Admin` tak; nikt → siebie nie; `User` → ktokolwiek nie.
  - `changeRole`: tylko `SuperAdmin` wobec `User`/`Admin`; nie wobec siebie; `Admin` nie.
  - `LinkPolicy`: admin ma `view` cudzego linku, nie ma `update`/`delete`; `toggle` false dla linku z `disabledByAdmin()` i dla nie-właściciela.
  - `AdminAccessTest`: lista tras `admin.*` z `Route::getRoutes()` (wypełni się w Tasks 6-7; test iteruje po wszystkich trasach o nazwie zaczynającej się od `admin.`): gość → redirect do logowania, `User` → 403, `Admin`/`SuperAdmin` → nie 403. Dodatkowo `$this->get('/admin')` kończy się 403 dla `User` (trasa powstaje w Task 7; do tego czasu test oznacz `->todo()` i zdejmij go w Task 7).
- [ ] **Step 2: Run** testy. Expected: FAIL.
- [ ] **Step 3: Implement** middleware (`handle(Request $request, Closure $next): Response`, `abort_unless($request->user()?->isAdmin(), 403)`), alias w `bootstrap/app.php` (`$middleware->alias(['admin' => EnsureUserIsAdmin::class])`), `UserPolicy` i zmiany `LinkPolicy`. Policy są wykrywane automatycznie (konwencja nazw).
- [ ] **Step 4: Run** testy. Expected: PASS. Pint.
- [ ] **Step 5: Commit** `Add admin middleware and user policy`.

---

### Task 3: Blokada konta i rozszerzony `Link::active()`

**Files:**
- Create: `app/Http/Middleware/EnsureAccountIsNotBlocked.php`
- Modify: `bootstrap/app.php` (dopisać do grupy `web`), `app/Providers/FortifyServiceProvider.php` (`Fortify::authenticateUsing`), `app/Models/Link.php` (scope `active`)
- Test: `tests/Feature/AccountBlockingTest.php`, rozszerz `tests/Feature/LinkScopeTest.php`, `tests/Feature/LinkRedirectTest.php`

**Interfaces:**
- Consumes: Task 1.
- Produces: `Link::active()` zwraca tylko linki z `is_active`, niewygasłe, z `disabled_by_admin_at IS NULL` i właścicielem bez `blocked_at` (`whereHas('user', fn ($q) => $q->whereNull('blocked_at'))`).

- [ ] **Step 1: Write failing tests.**
  - `LinkScopeTest`: dataset `is_active` × `disabled_by_admin_at` × `expires_at` × zablokowany właściciel; tylko kombinacja „wszystko w porządku" zwraca link.
  - `LinkRedirectTest`: link zablokowany przez admina i link zablokowanego użytkownika dają 404 pod `/{code}` bez kliknięcia w kolejce (`Queue::fake()`, `assertNothingPushed`); po zdjęciu blokady znów redirect.
  - `AccountBlockingTest`: zalogowany użytkownik, któremu ustawiono `blocked_at`, na następnym żądaniu (`get('/dashboard')` oraz `getJson`) jest wylogowany (`assertGuest()`) i dostaje redirect na `login` (JSON: 401); logowanie zablokowanego (`post('/login')` z poprawnym hasłem) kończy się błędem walidacji `email` i `assertGuest()`; odblokowany loguje się normalnie.
- [ ] **Step 2: Run** testy. Expected: FAIL.
- [ ] **Step 3: Implement** scope; middleware (`handle`: gdy `$request->user()?->isBlocked()` → `Auth::logout()`, unieważnij sesję i regeneruj token, JSON → `abort(401)`, inaczej `redirect()->route('login')` z `withErrors(['email' => __('Your account has been blocked.')])`); `Fortify::authenticateUsing` w `FortifyServiceProvider`: znajdź użytkownika po `Fortify::username()`, `Hash::check`, zablokowany lub błędne dane → `null`; zwróć użytkownika (zachowaj throttling). Middleware dopisz do `$middleware->web(append: [...])`.
- [ ] **Step 4: Run** testy oraz `php artisan test --compact tests/Feature/Auth tests/Feature/LinkRedirectTest.php`. Expected: PASS.
- [ ] **Step 5: Commit** `Block accounts and links disabled by an admin`.

---

### Task 4: Przełącznik `is_active` dla właściciela

**Files:**
- Create: `app/Http/Controllers/LinkToggleController.php`
- Modify: `routes/web.php`, `app/Http/Controllers/LinkController.php` (bez zmian logiki; tylko jeśli `index` wymaga pola), `resources/js/pages/links/Index.vue`
- Test: `tests/Feature/LinkToggleTest.php`

**Interfaces:**
- Consumes: `LinkPolicy::toggle` (Task 2).
- Produces: trasa `PATCH links/{link}/toggle` (`links.toggle`), akcja invokable `LinkToggleController::__invoke(Link $link): RedirectResponse` (`Gate::authorize('toggle', $link)`, odwraca `is_active`, flash toast, `back()`).

- [ ] **Step 1: Write failing tests:** właściciel przełącza aktywny → nieaktywny i z powrotem (`assertFalse`/`assertTrue` na `fresh()->is_active`); cudzy link: 403 (przez handler `AccessDeniedHttpException` kończy się redirectem na `links.index`, wystarczy sprawdzić, że `is_active` się nie zmienił); link z `disabledByAdmin()`: odrzucone, `is_active` i `disabled_by_admin_at` bez zmian; gość → login.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/LinkToggleTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** kontroler i trasę w grupie `auth`, `verified`, przed `Route::resource`.
- [ ] **Step 4: Frontend `links/Index.vue`:** w kolumnie Status zamiast samego `Badge` dodaj natywny `<button role="switch" :aria-checked="link.is_active" aria-label="Toggle link">` wołający `router.patch(toggle(link.id).url, {}, { preserveScroll: true })`; gdy `link.disabled_by_admin_at` ustawione, przycisk ma `disabled` i obok pojawia się `Badge variant="destructive"` z tekstem „Blocked by admin". Po `php artisan wayfinder:generate` zaimportuj `toggle` z `@/routes/links`.
- [ ] **Step 5: Run** test z kroku 2 i `npm run build` (Expected: bez błędów TS).
- [ ] **Step 6: Commit** `Let owners toggle their links from the list`.

---

### Task 5: `StatsSeries` i `AdminStatsReport`

**Files:**
- Create: `app/Support/StatsSeries.php`, `app/Support/AdminStatsReport.php`, `app/Http/Requests/Admin/AdminStatsRequest.php`
- Modify: `app/Support/LinkStatsReport.php` (prywatna `series()` zastąpiona wywołaniem `StatsSeries::build`)
- Test: `tests/Feature/AdminStatsReportTest.php`; `tests/Feature/LinkStatsTest.php` i `LinkStatsDataTest.php` muszą przejść bez edycji.

**Interfaces:**
- Produces: `StatsSeries::build(Collection $perDay, CarbonImmutable $from, CarbonImmutable $to, string $granularity): array` (`list<array{date: string, count: int}>`; ta sama logika co dotychczasowa prywatna `series()`, przeniesiona bez zmian).
- Produces: `AdminStatsRequest extends LinkStatsRequest` z `authorize(): bool { return true; }` (dostęp chroni middleware `admin`).
- Produces: `AdminStatsReport::build(?User $user, LinkStatsRequest $request): array` z kluczami: `period`, `granularity`, `total` (kliknięcia w okresie), `series`, `counters` (`array{users?: int, links: int, activeLinks: int, disabledLinks: int, clicks: int}`; `users` tylko dla widoku globalnego, `clicks` to suma kliknięć w okresie dla zakresu), `topLinks` (`list<array{id: string, title: ?string, code: string, clicks: int}>`, max 10), `topUsers` (`list<array{id: string, name: string, email: string, clicks: int}>`, max 10; pusty dla `$user !== null`). `disabledLinks` = linki nieaktywne wg `active()` (wszystkie − aktywne). Okres rozwiązuje `StatsPeriod::resolve` jak w `LinkStatsReport`; dla `period=all` pierwszy klik z zakresu.

- [ ] **Step 1: Write failing tests** (`AdminStatsReportTest`, `freezeTime()`): dwóch użytkowników z linkami i kliknięciami; globalnie `counters` zgadzają się (users, links, activeLinks, disabledLinks: link `inactive()` i `disabledByAdmin()` liczą się jako wyłączone), `series` ma zera dla pustych dni i `total` = suma `series`; `topLinks` posortowane malejąco po kliknięciach i ucięte do 10; `topUsers` jw.; dla `$user` wszystkie wartości dotyczą tylko jego linków (kliknięcia drugiego użytkownika nie przeciekają) i `topUsers === []`, brak klucza `users`; użytkownik bez linków oraz system bez kliknięć: zera, puste listy; okres `7d` odfiltrowuje starsze kliknięcia.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/AdminStatsReportTest.php`. Expected: FAIL.
- [ ] **Step 3: Refaktor.** Przenieś `series()` do `StatsSeries::build` (publiczna statyczna), `LinkStatsReport` woła ją. Run `php artisan test --compact tests/Feature/LinkStatsTest.php tests/Feature/LinkStatsDataTest.php`. Expected: PASS bez zmian w testach.
- [ ] **Step 4: Implement `AdminStatsReport`:** zapytania na `LinkClick::query()->toBase()` z `join links` i warunkiem `links.user_id` gdy `$user` podany; top listy przez `groupBy` + `orderByDesc` + `limit(10)`.
- [ ] **Step 5: Run** wszystkie trzy pliki testów. Expected: PASS. Pint.
- [ ] **Step 6: Commit** `Add admin stats report and share the series builder`.

---

### Task 6: Zarządzanie użytkownikami (backend)

**Files:**
- Create: `app/Http/Controllers/Admin/UserController.php`, `UserBlockController.php`, `UserRoleController.php`, `app/Http/Requests/Admin/UpdateUserRoleRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: `UserPolicy` (Task 2), `AdminStatsReport` (Task 5), `AdminStatsRequest`.
- Produces trasy (grupa `auth`, `verified`, `admin`, prefiks `admin`, nazwa `admin.`): `admin.users.index`, `admin.users.show`, `admin.users.destroy`, `admin.users.block` (`PATCH`), `admin.users.unblock` (`DELETE admin/users/{user}/block`), `admin.users.role` (`PATCH`).
- Props `admin/users/Index`: `users` (paginator 15: `id`, `name`, `email`, `role`, `blocked_at`, `created_at`, `links_count`, `can: {block: bool, delete: bool, changeRole: bool}`), `search: string`. Props `admin/users/Show`: `user` (jak wyżej, z `can`), `links` (paginator 15: model linku + `clicks_count`), plus klucze z `AdminStatsReport::build($user, $request)`.
- `UpdateUserRoleRequest`: `role` => `['required', Rule::in([User, Admin])]` (wartości enuma `user`/`admin`); `authorize()` = `can('changeRole', $this->route('user'))`.

- [ ] **Step 1: Write failing tests:** lista (admin widzi użytkowników, `User` 403, flagi `can` zależne od aktora, `links_count`); wyszukiwanie po nazwie i e-mailu (`search`), fraza z `%`/`_` dopasowuje literalnie (np. `a_b` nie łapie `axb`); paginacja; `show` zwraca statystyki ograniczone do użytkownika i `links`, nieistniejące id → 404; blokada: ustawia `blocked_at`, odblokowanie czyści; `Admin` blokujący `Admin`/siebie → 403, `SuperAdmin` blokujący `Admin` → ok; usunięcie: kasuje użytkownika, jego linki i `link_statistics` (`assertDatabaseMissing`), a rekordy innych zostają; 403 dla zabronionych par; zmiana roli: `SuperAdmin` `User`→`Admin`→`User`; `Admin` 403; wartość `super_admin` i śmieci → błąd walidacji; zmiana własnej roli → 403.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/Admin/UserManagementTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** kontrolery (każda mutacja: `Gate::authorize`, flash toast jak w `LinkController`, `back()`/redirect). Wyszukiwanie: escapuj `\`, `%`, `_` w frazie przed `LIKE ... ESCAPE`. `can` zbuduj w jednym miejscu (metoda prywatna kontrolera mapująca `User` → tablica).
- [ ] **Step 4: Run** test z kroku 2 oraz `AdminAccessTest` (zdejmij `todo` tylko jeśli trasa `/admin` już istnieje, inaczej w Task 7). Expected: PASS. Pint.
- [ ] **Step 5: Commit** `Add admin user management`.

---

### Task 7: Moderacja linków, statystyki linku i dashboard (backend)

**Files:**
- Create: `app/Http/Controllers/Admin/DashboardController.php`, `LinkDisableController.php`, `LinkStatsController.php`
- Modify: `routes/web.php`, `app/Http/Controllers/LinkStatsController.php` (nowe propsy)
- Test: `tests/Feature/Admin/LinkModerationTest.php`, `tests/Feature/Admin/AdminDashboardTest.php`; zdejmij `todo` z `AdminAccessTest`.

**Interfaces:**
- Consumes: `AdminStatsReport`, `LinkStatsReport`, `LinkStatsRequest`, `LinkPolicy::view` (admin).
- Produces trasy: `admin.dashboard` (`GET admin`), `admin.links.disable` (`PATCH`), `admin.links.enable` (`DELETE admin/links/{link}/disable`), `admin.links.stats` (`GET admin/links/{link}/stats`), `admin.links.stats.data`.
- `admin/Dashboard` props: wynik `AdminStatsReport::build(null, $request)`. `Admin\LinkStatsController` zwraca `Inertia::render('links/Stats', [...])` z tymi samymi propsami co `LinkStatsController` plus `dataUrl: string` (route `admin.links.stats.data`, bez query) i `backUrl: string` (route `admin.users.show` właściciela). Istniejący `LinkStatsController` dostaje `dataUrl` (route `links.stats.data`) i `backUrl` (route `links.index`). `data()` w obu zwraca JSON jak dotąd.
- `LinkDisableController`: `store(Link $link)` ustawia `disabled_by_admin_at = now()`, `destroy(Link $link)` zeruje; autoryzacja: middleware `admin` wystarcza (admin moderuje link dowolnego użytkownika, w tym `Admin`/`SuperAdmin`); toast + `back()`.

- [ ] **Step 1: Write failing tests:** disable/enable ustawia i czyści `disabled_by_admin_at`, nie dotyka `is_active`; `User` 403, gość login; nieistniejący link 404; zablokowany link nie przekierowuje, po `enable` znów tak; admin otwiera `admin.links.stats` cudzego linku (200, props zawierają `dataUrl`, `backUrl`, `total`) i `admin.links.stats.data` (JSON jak w `LinkStatsDataTest`); `User` na cudzym linku nadal 403 (route `links.stats`); `dashboard`: admin 200 z kluczami `counters`, `series`, `topLinks`, `topUsers`, `User` 403, parametr `period=7d` działa, niepoprawny `period` → błąd walidacji.
- [ ] **Step 2: Run** nowe testy. Expected: FAIL.
- [ ] **Step 3: Implement** kontrolery i trasy; w istniejących testach statystyk nic nie zmieniaj (nowe propsy są addytywne). Run `php artisan test --compact tests/Feature/LinkStatsTest.php tests/Feature/LinkStatsDataTest.php`. Expected: PASS.
- [ ] **Step 4: Run** `php artisan test --compact tests/Feature/Admin tests/Feature/AdminAccessTest.php`. Expected: PASS. Pint.
- [ ] **Step 5: Commit** `Add admin link moderation, link stats and dashboard`.

---

### Task 8: Frontend panelu

**Files:**
- Create: `resources/js/components/ClicksChart.vue`, `resources/js/pages/admin/Dashboard.vue`, `resources/js/pages/admin/users/Index.vue`, `resources/js/pages/admin/users/Show.vue`
- Modify: `resources/js/pages/links/Stats.vue`, `resources/js/components/AppSidebar.vue`, `resources/js/types/link.ts` (typy admina)

**Interfaces:**
- Consumes: propsy z Tasks 6-7 (kształty w ich blokach Interfaces); Wayfinder `@/routes/admin`, `@/routes/admin/users`, `@/routes/admin/links` po `php artisan wayfinder:generate`.
- Produces: `ClicksChart.vue` z propsami `{ series: DailyClicks[]; granularity: 'day' | 'month'; loading?: boolean }` (markup słupków wyciągnięty 1:1 ze `Stats.vue`, linie ok. 411-415 i 628-645, razem z `role="img"` i `aria-label`).

- [ ] **Step 1: `Stats.vue`:** dodaj propsy `dataUrl: string` i `backUrl: string`; zamień `statsData.url(link.value.id)` (linia ok. 172) i `statsData.url(link.value.id, {...})` (ok. 228) na `dataUrl` z tym samym query (nie importuj już `statsData`); breadcrumb „Links" wskazuje `backUrl`. Wykres zastąp `<ClicksChart>`. Zachowaj zachowanie istniejącej strony; `tests/e2e/stats.spec.ts` przechodzi bez zmian (jeśli środowisko pozwala je uruchomić).
- [ ] **Step 2: `admin/Dashboard.vue`:** cztery karty z `counters` (Users, Links, Active/Disabled links, Clicks), przełącznik 7/30/90 dni (`router.get(admin().url, { period })`), `ClicksChart`, dwie tabele: top linki (link do `admin.links.stats`) i top użytkownicy (link do `admin.users.show`), „No data" gdy lista pusta. Breadcrumbs „Admin".
- [ ] **Step 3: `admin/users/Index.vue`:** tabela (name, email, role, status Badge `Blocked`/`Active`, links_count, created_at), pole wyszukiwania (debounce, `router.get` z `preserveState`, `replace`), paginacja jak w `links/Index.vue`, link do `Show`.
- [ ] **Step 4: `admin/users/Show.vue`:** nagłówek z danymi, roli i statusu; akcje widoczne wg `user.can`: Block/Unblock (`router.patch`/`router.delete`), Delete w `Dialog` z potwierdzeniem (fokus na „Cancel"), Change role (`<select>` User/Admin + `router.patch`) tylko gdy `can.changeRole`; statystyki użytkownika (karty `counters`, `ClicksChart`, przełącznik okresu); tabela linków: title, code, clicks, status (`Active` / `Disabled by owner` / `Disabled by admin`), przyciski Disable/Enable (admin) i Stats (`admin.links.stats`). `aria-label` na przyciskach ikon.
- [ ] **Step 5: `AppSidebar.vue`:** pozycja „Admin" (ikona `Shield` z `@lucide/vue`, `href: adminDashboard()`) dodawana do `mainNavItems` tylko gdy `usePage().props.auth.user.role` jest `admin` lub `super_admin` (`computed`).
- [ ] **Step 6: Verify:** `php artisan wayfinder:generate`, `npm run build` (Expected: bez błędów TS/lint), `npx eslint resources/js --max-warnings=0` jeśli skonfigurowany.
- [ ] **Step 7: Commit** `Add admin panel pages and navigation`.

---

### Task 9: Przegląd i test ręczny

- [ ] **Step 1:** `php artisan test --compact` (pełny zestaw, poproś użytkownika o uruchomienie zgodnie z `AGENTS.md`) i `vendor/bin/pint --format agent`, `vendor/bin/phpstan analyse` (jest `phpstan.neon`). Expected: zielone.
- [ ] **Step 2: Test ręczny** w przeglądarce (skill `playwright-cli` lub użytkownik): `php artisan user:make-super-admin <email>`; zalogowanie; zablokowanie użytkownika (jego sesja wylogowana, `/{code}` jego linku 404); wyłączenie linku przez admina (właściciel widzi „Blocked by admin", przełącznik nieaktywny); statystyki linku z panelu; zmiana roli; usunięcie konta.
- [ ] **Step 3:** Przegląd całej gałęzi (`superpowers:requesting-code-review`), poprawki findings osobnym commitem.
