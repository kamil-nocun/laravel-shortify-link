# Przypisywanie klientów do administratorów – plan implementacji

> **Dla agentów wykonawczych:** WYMAGANY SUB-SKILL: użyj superpowers:subagent-driven-development (zalecane) lub superpowers:executing-plans, aby wykonać plan zadanie po zadaniu. Kroki używają checkboxów (`- [ ]`).

**Cel:** Administrator widzi i obsługuje tylko przypisanych klientów (wiele-do-wielu), super administrator widzi wszystko i zarządza przypisaniami.

**Architektura:** Tabela pivot `client_admin` (UUID) + scope `visibleTo($actor)` na `User` i `Link`. Route model binding w grupie `admin.*` używa scope'u (niewidoczny rekord → 404). `UserPolicy::manages` jako druga warstwa. `AdminStatsReport` filtruje agregaty przez ten sam scope.

**Stos:** Laravel (PHP 8.5), Inertia v3 + Vue, Pest, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-10-09-client-admin-assignment-design.md`

## Ograniczenia globalne

- Nowe tabele: UUID jako PK i FK (`foreignUuid`).
- Komentarze PHPDoc zamiast inline; jawne typy zwracane; nawiasy klamrowe zawsze.
- Teksty UI i komunikaty przez `__()` jak w istniejącym kodzie; spec i plany po polsku.
- Brak nowych zależności. Nie ruszać niezacommitowanych zmian użytkownika: w commitach dodawać wyłącznie pliki z danego zadania (`git add <ścieżki>`, `git commit -- <ścieżki>`).
- Po zmianach w PHP: `vendor/bin/pint --dirty --format agent`. Testy: `php artisan test --compact <plik>`. Pełny zestaw uruchamia użytkownik.
- Nieprzypisany klient / jego link → 404 (nie 403).
- Stopka commitów: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Review Focus

- Administrator wchodzi na `users/{id}` super administratora lub innego administratora → 404.
- `POST admin/users/{nieprzypisany}/links` i `PATCH .../block` → 404 (binding, nie tylko polityka).
- `GET admin/links/{link-nieprzypisanego}/stats/data` (JSON) → 404.
- Dashboard administratora: `topLinks`, `topUsers`, `counters` nie zawierają danych cudzych klientów.
- Zwykły administrator wysyła `administrators[]` przy tworzeniu klienta → pole ignorowane, przypisany jest tylko on.
- Super administrator wysyła w `administrators[]` UUID klienta (nie administratora) lub nieistniejący → błąd walidacji.
- Zwykły użytkownik na trasach `admin.*` nadal dostaje 403 (binding nie może zamienić tego na 404).

## Struktura plików

- Create: `database/migrations/2026_10_09_180000_create_client_admin_table.php`
- Create: `app/Models/ClientAdmin.php` (model pośredni z UUID)
- Modify: `app/Models/User.php` (relacje, `scopeVisibleTo`), `app/Models/Link.php` (`scopeVisibleTo`)
- Modify: `database/factories/UserFactory.php` (stan `assignedTo`)
- Modify: `app/Policies/UserPolicy.php` (`manages`, `assignAdministrators`)
- Modify: `app/Providers/AppServiceProvider.php` (binding `user` i `link` dla `admin.*`)
- Modify: `app/Support/AdminStatsReport.php`, `app/Http/Controllers/Admin/UserController.php`, `UserRoleController.php`, `app/Http/Requests/Admin/StoreUserRequest.php`
- Create: `app/Http/Controllers/Admin/UserAdministratorController.php`, `app/Http/Requests/Admin/UpdateUserAdministratorsRequest.php`
- Modify: `routes/web.php`
- Modify (UI): `resources/js/pages/admin/users/{Create,Edit,Show}.vue`, `resources/js/types/*` (typ użytkownika admina, jeśli istnieje)
- Create: `tests/Feature/Admin/ClientAssignmentTest.php`
- Modify: istniejące testy admina (`UserManagementTest.php`, `UserPolicyTest.php`, `AdministratorManagementTest.php`, ewentualnie `tests/e2e/*.spec.ts`), które działają jako administrator na nieprzypisanych klientach.

---

### Zadanie 1: Dane, model i scope'y

**Files:**
- Create: migracja, `app/Models/ClientAdmin.php`
- Modify: `app/Models/User.php`, `app/Models/Link.php`, `database/factories/UserFactory.php`
- Test: `tests/Feature/Admin/ClientAssignmentTest.php`

**Interfaces:**
- Produces:
  - `User::assignedAdmins(): BelongsToMany` (administratorzy klienta), `User::managedClients(): BelongsToMany` (klienci administratora); obie `->using(ClientAdmin::class)->withTimestamps()`, klucze `client_id` / `admin_id`.
  - `User::visibleTo(User $actor)` – scope (`#[Scope]`, jak `Link::active`): super admin → wszyscy; administrator → `role = user` i `whereHas('assignedAdmins', id = $actor->id)`; pozostali → `whereRaw('1 = 0')`.
  - `Link::visibleTo(User $actor)` – scope: super admin → wszystkie; administrator → `whereHas('user', fn ($q) => $q->visibleTo($actor))`; pozostali → pusty.
  - `UserFactory::assignedTo(User $admin): static` (`afterCreating` → `$user->assignedAdmins()->attach($admin)`).

- [ ] **Krok 1: Napisz testy (padają)** w `ClientAssignmentTest.php`:
  - `attach tworzy wiersz pivot z niepustym id (UUID)` – po `$client->assignedAdmins()->attach($admin)` `DB::table('client_admin')->first()->id` jest niepuste.
  - `visibleTo: super admin widzi wszystkich, administrator tylko przypisanych klientów, nie widzi administratorów ani super admina, zwykły użytkownik nic`.
  - `Link::visibleTo` analogicznie dla linków klientów.
  - `usunięcie administratora kasuje przypisania, klient zostaje` (`assertDatabaseMissing('client_admin', ...)`, `assertModelExists($client)`).
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Feature/Admin/ClientAssignmentTest.php` – oczekiwane: FAIL (brak tabeli/relacji).
- [ ] **Krok 3: Migracja** `client_admin`: `uuid('id')->primary()`, `foreignUuid('client_id')` i `foreignUuid('admin_id')` → `constrained('users')->cascadeOnDelete()`, `timestamps()`, `unique(['client_id','admin_id'])`. Użyj `php artisan make:migration ... --no-interaction`.
- [ ] **Krok 4: `ClientAdmin extends Pivot`** z `HasUuids`, `$incrementing = false`. Relacje i scope'y wg Interfaces. Jeśli `attach` nie wygeneruje `id` (test z kroku 1 pada na `id`), zamiast tego w migracji ustaw `default` na UUID bazy lub zapisuj `id` przez `->attach($id, ['id' => (string) Str::uuid()])` we wszystkich miejscach – wybierz wariant, który przechodzi test, i trzymaj go spójnie.
- [ ] **Krok 5: Factory** – stan `assignedTo`.
- [ ] **Krok 6: Uruchom testy** – oczekiwane: PASS. Pint.
- [ ] **Krok 7: Commit** – `git add database/migrations/*client_admin* app/Models database/factories/UserFactory.php tests/Feature/Admin/ClientAssignmentTest.php` (uwaga: `app/Models` zawiera tylko pliki z tego zadania; sprawdź `git status`), komunikat `Add client_admin pivot, relations and visibleTo scopes`.

---

### Zadanie 2: Polityka, binding i 404 w panelu

**Files:**
- Modify: `app/Policies/UserPolicy.php`, `app/Providers/AppServiceProvider.php`
- Modify (poprawa istniejących testów): `tests/Feature/Admin/UserManagementTest.php`, `tests/Feature/UserPolicyTest.php`, inne testy admina działające na nieprzypisanych klientach
- Test: `tests/Feature/Admin/ClientAssignmentTest.php`

**Interfaces:**
- Consumes: `User::visibleTo`, `Link::visibleTo`, `UserFactory::assignedTo` (Zadanie 1).
- Produces:
  - `UserPolicy::manages(User $actor, User $target): bool` – super admin: true; administrator: `$target->assignedAdmins()->whereKey($actor->id)->exists()`; reszta false.
  - `update`, `delete`, `block`, `createLink` = `outranks && manages`.
  - `UserPolicy::assignAdministrators(User $actor): bool` = `$actor->isSuperAdmin()`.
  - Binding: `Route::bind('user', ...)` i `Route::bind('link', ...)` w `AppServiceProvider::boot()`: gdy `request()->routeIs('admin.*')` i `request()->user()?->isAdmin()` → `Model::visibleTo($actor)->findOrFail($value)`; w pozostałych przypadkach zwykły `findOrFail`. Komentarz PHPDoc: zwykłych użytkowników odrzuca middleware `admin` (403), binding nie może tego zamienić w 404.

- [ ] **Krok 1: Testy (padają)** w `ClientAssignmentTest.php`:
  - administrator → 404 dla: `GET admin.users.show`, `GET admin.users.edit`, `PATCH admin.users.block`, `DELETE admin.users.destroy`, `GET/POST admin.users.links.create/store` nieprzypisanego klienta; ten sam klient po `assignedTo($admin)` → 200/302.
  - administrator → 404 na `users/{id}` innego administratora i super administratora.
  - administrator → 404 na `admin.links.stats`, `admin.links.stats.data`, `PATCH admin.links.disable` linku nieprzypisanego klienta.
  - super administrator → 200 dla nieprzypisanego klienta.
  - zwykły użytkownik → 403 na `admin.users.show` dowolnego klienta (nie 404).
  - `UserPolicy::manages`: administrator bez przypisania false, z przypisaniem true.
- [ ] **Krok 2: Uruchom** – FAIL.
- [ ] **Krok 3: Zaimplementuj** politykę i bindingi wg Interfaces.
- [ ] **Krok 4: Napraw istniejące testy**: wszędzie, gdzie `User::factory()->admin()` działa na kliencie, utwórz klienta przez `User::factory()->assignedTo($admin)->create()`. Uruchom `php artisan test --compact tests/Feature/Admin tests/Feature/UserPolicyTest.php` – PASS.
- [ ] **Krok 5: Pint, commit** – tylko zmienione pliki; `Enforce client assignment in admin policy and route binding`.

---

### Zadanie 3: Zakres danych zbiorczych

**Files:**
- Modify: `app/Support/AdminStatsReport.php`, `app/Http/Controllers/Admin/UserController.php` (`index`)
- Test: `tests/Feature/Admin/ClientAssignmentTest.php`

**Interfaces:**
- Consumes: `User::visibleTo`, `Link::visibleTo`.
- Produces: `AdminStatsReport::build(?User $user, LinkStatsRequest $request)` – bez zmiany sygnatury; aktora bierze z `$request->user()`. Gdy `$user === null` i aktor nie jest super adminem: kliknięcia filtrowane `whereIn('links.user_id', User::visibleTo($actor)->select('id'))`, `$links = Link::visibleTo($actor)`, a `counters.users` = `User::visibleTo($actor)->count()`. Dla super admina zachowanie bez zmian.

- [ ] **Krok 1: Testy (padają)**: dwóch administratorów, każdy z własnym klientem, linkami i kliknięciami (`LinkClick` fabryka). Dashboard administratora A: `counters.users` = 1, `counters.links`/`clicks` tylko od klienta A, `topLinks` i `topUsers` bez klienta B. Dashboard super admina widzi oba. `admin.users.index` administratora A listuje tylko klienta A (po przypisaniu klienta B do A – obu).
- [ ] **Krok 2: Uruchom** – FAIL.
- [ ] **Krok 3: Zaimplementuj** wg Interfaces (`UserController::index` korzysta już z binding-owego scope'u? Nie – dodaj `->visibleTo($request->user())` do zapytania listy).
- [ ] **Krok 4: Testy PASS, pint, commit** – `Scope admin dashboard and user list to assigned clients`.

---

### Zadanie 4: Tworzenie klienta i zarządzanie przypisaniami

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php` (`store`), `app/Http/Requests/Admin/StoreUserRequest.php`, `routes/web.php`
- Create: `app/Http/Controllers/Admin/UserAdministratorController.php`, `app/Http/Requests/Admin/UpdateUserAdministratorsRequest.php`
- Test: `tests/Feature/Admin/ClientAssignmentTest.php`

**Interfaces:**
- Consumes: `UserPolicy::assignAdministrators`, relacje z Zadania 1.
- Produces:
  - `StoreUserRequest` – reguła `administrators` (`nullable|array`) i `administrators.*` (`uuid`, `Rule::exists('users','id')->where('role', UserRole::Admin)`) tylko gdy `$this->user()->isSuperAdmin()`; dla zwykłego administratora reguły pola brak, a `validated()` go nie zwraca.
  - `UserController::store`: po zapisie administrator → `$user->assignedAdmins()->attach($request->user())`; super admin → `$user->assignedAdmins()->sync($request->validated('administrators', []))`. Uwaga: `new User($request->validated())` – wyfiltruj `administrators` przed konstruktorem (`$request->safe()->except('administrators')`).
  - `UserAdministratorController::update(UpdateUserAdministratorsRequest $request, User $user): RedirectResponse` – `sync`, toast `__('Assigned administrators updated.')`, `back()`. Request: `authorize` → `can('assignAdministrators', User::class)`; reguły jak wyżej, pole `administrators` wymagane jako tablica (może być pusta).
  - Trasa: `Route::put('users/{user}/administrators', [UserAdministratorController::class, 'update'])->name('users.administrators.update')` w grupie `admin`.

- [ ] **Krok 1: Testy (padają)**:
  - administrator tworzy klienta → `assignedAdmins` zawiera tylko jego.
  - administrator przesyła `administrators[]` z cudzym UUID → ignorowane, przypisany tylko on.
  - super admin tworzy klienta z dwoma administratorami → oba przypisania; bez pola → brak przypisań.
  - super admin z UUID klienta lub losowym UUID w `administrators[]` → `assertSessionHasErrors('administrators.0')`.
  - `PUT users.administrators.update`: super admin zmienia przypisania (dodaje, odpina; po odpięciu administrator dostaje 404 na `show`); administrator → 403; klient pozostaje po odpięciu wszystkich i widoczny dla super admina.
- [ ] **Krok 2: Uruchom** – FAIL.
- [ ] **Krok 3: Zaimplementuj** wg Interfaces. Wygeneruj klasy przez `php artisan make:controller` / `make:request --no-interaction`.
- [ ] **Krok 4: Testy PASS, pint, commit** – `Assign administrators on client creation and via admin endpoint`.

---

### Zadanie 5: Zmiana roli czyści przypisania

**Files:**
- Modify: `app/Http/Controllers/Admin/UserRoleController.php`
- Test: `tests/Feature/Admin/ClientAssignmentTest.php`

**Interfaces:**
- Consumes: relacje z Zadania 1.
- Produces: `UserRoleController::__invoke` po zmianie roli wywołuje `$user->assignedAdmins()->detach()` oraz `$user->managedClients()->detach()` (w transakcji razem z zapisem roli – `DB::transaction`).

- [ ] **Krok 1: Testy (padają)**: klient z przypisanym administratorem awansowany na administratora → brak wierszy `client_admin` z `client_id` tego użytkownika; administrator z klientami zdegradowany do klienta → brak wierszy z jego `admin_id`, jego dawni klienci zostają i nie są już widoczni dla niego.
- [ ] **Krok 2: Uruchom** – FAIL. **Krok 3: Zaimplementuj.** **Krok 4: PASS, pint, commit** – `Clear client assignments when a user's role changes`.

---

### Zadanie 6: Interfejs (Vue)

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php` (`create`, `edit`, `show`, `present`), `resources/js/pages/admin/users/{Create,Edit,Show}.vue`, typy w `resources/js/types/`
- Test: `tests/Feature/Admin/ClientAssignmentTest.php`; Playwright `tests/e2e/` (opcjonalnie, tylko gdy istnieje spec panelu admina do rozszerzenia)

**Interfaces:**
- Consumes: trasa `admin.users.administrators.update`, `UserPolicy::assignAdministrators`.
- Produces (propsy Inertia):
  - `admin/users/Create`: `administrators: list<{id, name, email}>` (tylko dla super admina; dla administratora pusta tablica).
  - `admin/users/Edit`: `administrators` jak wyżej + `assignedAdministratorIds: list<string>`.
  - `admin/users/Show`: `assignedAdministrators: list<{id, name, email}>` (tylko dla super admina; dla administratora pusta tablica, sekcja ukryta).
  - `present()` dodaje `can.assignAdministrators`.

- [ ] **Krok 1: Testy (padają)** (Inertia `assertInertia`): `Create` dla super admina ma `administrators` z samymi administratorami, dla administratora pustą; `Edit` zwraca `assignedAdministratorIds`; `Show` zwraca `assignedAdministrators`; lista `users.data.*.can.assignAdministrators` true tylko dla super admina. Zaktualizuj istniejące asercje `can` w `UserManagementTest.php` (nowy klucz).
- [ ] **Krok 2: Uruchom** – FAIL.
- [ ] **Krok 3: Backend** – dodaj propsy wg Interfaces.
- [ ] **Krok 4: Vue** – aktywuj skill `inertia-vue-development` i `wayfinder-development`. `Create.vue`: wielokrotny wybór administratorów (natywny `<select multiple>` lub istniejący komponent z `resources/js/components`, sprawdź przed dodaniem nowego), widoczny tylko gdy `administrators.length > 0`, wysyła `administrators[]` razem z formularzem. `Edit.vue`: osobny formularz „Przypisani administratorzy” → `PUT` przez Wayfinder (`@/actions/...UserAdministratorController`), tylko gdy `can.assignAdministrators`. `Show.vue`: sekcja „Przypisani administratorzy” (lista tylko do odczytu), tylko dla super admina. Teksty przez istniejący mechanizm tłumaczeń projektu.
- [ ] **Krok 5: `npm run build`** (lub wygeneruj Wayfinder: `php artisan wayfinder:generate`), sprawdź brak błędów TS (`npm run types` jeśli skrypt istnieje – sprawdź `package.json`).
- [ ] **Krok 6: Testy PASS, pint, commit** – `Add administrator assignment UI`.

---

### Zadanie 7: Weryfikacja końcowa

- [ ] **Krok 1:** `php artisan test --compact tests/Feature/Admin tests/Feature/UserPolicyTest.php` – PASS.
- [ ] **Krok 2:** `vendor/bin/pint --dirty --format agent` – bez zmian.
- [ ] **Krok 3:** `git status` – brak niezamierzonych plików w commitach tego planu.
- [ ] **Krok 4:** Poproś użytkownika o uruchomienie pełnego zestawu `php artisan test --compact` oraz e2e (`tests/e2e`), jeśli dotyczą panelu admina.

## Samoocena planu

- Pokrycie spec: widoczność i 404 (Z2), dane (Z1), agregaty (Z3), przepływy tworzenia i przypisywania (Z4), zmiana roli (Z5), UI (Z6), testy (we wszystkich zadaniach).
- Spójność nazw: `visibleTo`, `manages`, `assignAdministrators`, `assignedTo`, `assignedAdmins`, `managedClients`, `admin.users.administrators.update` używane identycznie w zadaniach.
