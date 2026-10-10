# Role i uprawnienia (RBAC) – plan implementacji

> **Dla agentów wykonawczych:** WYMAGANY SUB-SKILL: użyj superpowers:subagent-driven-development (zalecane) albo superpowers:executing-plans, żeby wykonać plan zadanie po zadaniu. Kroki używają checkboxów (`- [ ]`).

**Cel:** Zastąpić sztywny enum `UserRole` rolami i uprawnieniami z bazy (jedna pula ról, jedna rola na użytkownika, nadpisania per użytkownik) wraz z ekranem ról, nadpisaniami na stronie użytkownika i ukrywaniem niedozwolonych akcji.

**Architektura:** Katalog uprawnień to enum `Permission` (klucze używane w kodzie) synchronizowany do tabeli `permissions` przez `PermissionCatalog::sync()`. `User::hasPermission()` liczy efektywne uprawnienia (rola ∪ przyznane − odebrane; rola `is_super` zawsze `true`). Policy zachowują swoje kontrole strukturalne (`outranks`, `manages`, „nie sobie”) i dokładają sprawdzenie uprawnienia. Przełączenie źródła roli (Zadanie 2) jest odrębne od przejścia policy na uprawnienia (Zadanie 3), żeby pakiet testów był zielony po każdym zadaniu.

**Tech Stack:** Laravel 13, PHP 8.5, MySQL 8.4, Inertia v3 + Vue, Wayfinder, Pest, Pint.

**Spec:** `docs/superpowers/specs/2026-10-10-roles-permissions-design.md`

## Global Constraints

- Klucze UUID dla wszystkich nowych tabel i kluczy obcych (`foreignUuid`, `HasUuids`).
- Nie zmieniamy zależności (żadnego `spatie/laravel-permission`, żadnego nowego komponentu UI z biblioteki). Stare migracje zostają bez zmian; zmiany schematu robimy nowymi migracjami.
- Konwencje projektu: Form Requests, `Gate::authorize`, Wayfinder, typy zwrotne, Pest, Pint. Kod i komunikaty UI po angielsku; spec i plany po polsku.
- Po zmianach PHP: `vendor/bin/pint --dirty --format agent`. Po zmianie tras: `php artisan wayfinder:generate --with-form --no-interaction`.
- Testy: `php artisan test --compact <plik>`; pełny zestaw uruchamia użytkownik na końcu.
- Zakres „nad kim” (`client_admin`, `outranks`, `manages`, `visibleTo`) zostaje bez zmian w zachowaniu.

## Decyzje planu (poza specem – do potwierdzenia)

- **Bez `Gate::before`.** Spec przewiduje `Gate::before` dla `is_super`, ale ominęłoby ono kontrole strukturalne policy: super admin mógłby blokować i usuwać samego siebie, edytować cudze linki przez trasy klienta. Zamiast tego `hasPermission()` zwraca `true` dla `is_super`, a policy dalej robią własne kontrole.
- **`admin.access` tylko z roli.** Nadpisania per użytkownik nie mogą dotyczyć `admin.access` (walidacja odrzuca ten klucz). Dzięki temu listy „klienci” i „administratorzy” oraz `visibleTo` da się zapytać po samej roli, bez złożonego SQL.
- **Rozróżnienie klient / administrator / super admin:** super admin = rola `is_super`; administrator = rola z `admin.access`, nie `is_super`; klient = pozostali. Metody `User::isSuperAdmin()`, `isAdmin()` (administrator lub super admin), `isClient()` i scope'y `User::clients()`, `User::administrators()`.
- **`roles.is_default`** (nie ma go w specu): dokładnie jedna rola jest domyślna dla nowych kont (rejestracja, tworzenie użytkownika). `User::creating` uzupełnia pusty `role_id` rolą domyślną. Roli `is_default` i `is_super` nie można usunąć.
- **Nazwy ról startowych** jako stałe w `PermissionCatalog`: `SUPER_ADMIN_ROLE = 'Super admin'`, `ADMINISTRATOR_ROLE = 'Administrator'`, `CLIENT_ROLE = 'Client'`. Fabryka użytkownika wyszukuje je po nazwie.
- Dane startowe (uprawnienia i role) tworzy migracja przez `PermissionCatalog::sync()`; testy (`RefreshDatabase`, transakcje) widzą je bez dodatkowego seedowania.
- Uprawnienia „administratorskie” nowej roli administratora zgodnie ze specem: wszystkie klucze grup `Admin panel`, `Users`, `Links (admin)`, `Domains (admin)` poza `users.change_role`; bez `administrators.*`, `packages.*`, `roles.manage`. Rola `Client` ma `links.manage`, `links.stats`, `domains.manage`, `billing.manage`.

## Review Focus

- Super admin nie może zablokować, usunąć ani zmienić roli samego siebie mimo pełnych uprawnień (Zadanie 3).
- Odebranie uprawnienia nadpisaniem (`granted=false`) wygrywa z rolą; przyznanie nie dodaje `admin.access` (Zadania 1, 5).
- Rola z przypisanymi użytkownikami, `is_super` i `is_default` nie da się usunąć; nie da się odebrać sobie `roles.manage` ani zmienić własnej roli (Zadania 4, 5).
- Zmiana roli, która dodaje lub zabiera `admin.access`, odpina `assignedAdmins` i `managedClients` jak dziś; zmiana między dwiema rolami klienta nie odpina (Zadanie 4).
- Rola klienta bez `links.manage` nie utworzy ani nie zmieni linku przez trasy klienta; limit z pakietu nadal obowiązuje (Zadanie 3).
- Migracja danych: każdy istniejący użytkownik dostaje właściwą rolę, a nowa rejestracja rolę domyślną (Zadania 1, 2).

---

### Zadanie 1: Katalog uprawnień, tabele i modele (zmiana addytywna)

**Files:**
- Create: `app/Enums/Permission.php`, `app/Support/PermissionCatalog.php`, `app/Models/Role.php`, `app/Models/PermissionRecord.php` (tabela `permissions`; nazwa unika kolizji z enumem), `database/factories/RoleFactory.php`
- Create: migracja `create_permissions_and_roles_tables` (tabele `permissions`, `roles`, `permission_role`, `permission_user`, kolumna `users.role_id` nullable, `foreignUuid` z `nullOnDelete`; wywołuje `PermissionCatalog::sync()` i uzupełnia `users.role_id` z kolumny `users.role`)
- Modify: `app/Models/User.php` (relacja `role()`, `permissionOverrides()`, `hasPermission()`; stary kod bez zmian)
- Test: `tests/Feature/PermissionCatalogTest.php`, `tests/Feature/UserPermissionTest.php`

**Interfaces:**
- Produces:
  - `enum Permission: string` z przypadkami z kluczami: `admin.access`; `users.view`, `users.create`, `users.update`, `users.delete`, `users.block`, `users.change_role`; `administrators.manage`, `administrators.assign`; `links.create_for_user`, `links.disable`; `domains.manage_for_user`, `domains.disable`; `packages.manage`, `packages.assign`; `roles.manage`; `links.manage`, `links.stats`, `domains.manage`, `billing.manage`. Metody `label(): string`, `group(): string` (grupy: `Admin panel`, `Users`, `Administrators`, `Links (admin)`, `Domains (admin)`, `Packages`, `Roles`, `Client features`) i `static forClients(): list<self>` (4 klucze klienta).
  - `PermissionCatalog::sync(): void` – idempotentny upsert `permissions` (po `key`, aktualizuje `label` i `group`) oraz tworzy brakujące role startowe z uprawnieniami wg „Decyzji planu”; istniejących ról nie nadpisuje.
  - `Role` (`HasUuids`): `$fillable = ['name', 'description']`, `is_super` i `is_default` bool; `permissions(): BelongsToMany<PermissionRecord>`, `users(): HasMany`; `permissionKeys(): Collection<int, string>`.
  - `User::role(): BelongsTo<Role>`; `User::hasPermission(Permission|string $key): bool`; `User::permissionKeys(): Collection<int, string>` (zapamiętane w obiekcie; `forgetPermissions(): void` czyści).
- Consumes: —

- [ ] **Krok 1: Testy** `PermissionCatalogTest`: `sync` jest idempotentny (dwa wywołania, liczba wierszy równa liczbie przypadków `Permission`); tworzy dokładnie jedną rolę `is_super`, jedną `is_default` (`Client`) i rolę `Administrator` bez `users.change_role`, `packages.manage`, `roles.manage`; nie nadpisuje ręcznie zmienionych uprawnień istniejącej roli `Administrator`. `UserPermissionTest` (zbiór danych: rola, przyznanie, odebranie, super admin): `hasPermission` zwraca `true` dla uprawnienia roli; `granted=true` dodaje uprawnienie spoza roli; `granted=false` odbiera uprawnienie roli; rola `is_super` ma każde uprawnienie niezależnie od nadpisań; użytkownik bez roli (`role_id = null`) nie ma żadnego; zmiana nadpisania + `forgetPermissions()` odświeża wynik.
- [ ] **Krok 2: Uruchom testy** – oczekiwane FAIL (brak klas).
- [ ] **Krok 3: Zaimplementuj** enum, katalog, modele, migrację i `User::hasPermission` zgodnie z blokiem Interfaces. Backfill w migracji: `role='super_admin'` → rola `is_super`, `'admin'` → `Administrator`, `'user'` → `Client`. `down()` usuwa tabele i kolumnę `role_id`.
- [ ] **Krok 4: Dodaj test migracji danych** w `PermissionCatalogTest`: po `migrate:fresh` role startowe istnieją (`Role::count() === 3`). Pełny backfill sprawdza Zadanie 2 (po przełączeniu fabryk).
- [ ] **Krok 5: Uruchom** `php artisan test --compact tests/Feature/PermissionCatalogTest.php tests/Feature/UserPermissionTest.php` – oczekiwane PASS; pozostałe testy bez zmian (`php artisan test --compact tests/Feature/UserPolicyTest.php tests/Feature/UserRoleTest.php`).
- [ ] **Krok 6: Pint i commit** (`Add permission catalog, roles tables and effective permissions`).

---

### Zadanie 2: Przełączenie źródła roli na `role_id` (zachowanie bez zmian)

**Files:**
- Create: migracja `drop_role_from_users_table` (ustawia `role_id` na `NOT NULL` po sprawdzeniu, że nie ma pustych; usuwa kolumnę `role`; `down()` przywraca kolumnę z mapowaniem zwrotnym)
- Delete: `app/Enums/UserRole.php`, `tests/Feature/UserRoleTest.php` (zastąpiony testem poniżej – usunięcie za zgodą użytkownika, patrz krok 9)
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Console/Commands/MakeSuperAdmin.php`, `app/Http/Controllers/Admin/UserController.php`, `app/Http/Controllers/Admin/AdministratorController.php`, `app/Http/Controllers/Admin/UserRoleController.php`, `app/Http/Requests/Admin/UpdateUserRoleRequest.php`, `app/Http/Requests/Admin/UpdateUserAdministratorsRequest.php`, `app/Http/Requests/Admin/StoreUserRequest.php`, `app/Http/Requests/Admin/StoreAdministratorRequest.php`
- Modify (testy): `tests/Feature/MakeSuperAdminCommandTest.php`, `tests/Feature/Admin/UserManagementTest.php`, `tests/Feature/Admin/ClientAssignmentTest.php`, `tests/Feature/Admin/AdministratorManagementTest.php`
- Create: `tests/Feature/UserRoleKindTest.php`

**Interfaces:**
- Consumes: `Role`, `PermissionCatalog` role-name constants, `User::hasPermission()` z Zadania 1.
- Produces:
  - `User::isSuperAdmin(): bool` (`role->is_super`), `isAdmin(): bool` (`isSuperAdmin() || hasPermission('admin.access')` – ale `hasPermission` dla super zwraca `true`, więc wystarcza `hasPermission(Permission::AdminAccess)`), `isClient(): bool` (`! isAdmin()`).
  - Scope'y `User::clients()` (rola nie `is_super` i bez `admin.access`) i `User::administrators()` (rola nie `is_super` z `admin.access`); `visibleTo` używa `clients()` zamiast `where('role', UserRole::User)`.
  - `User::creating`: pusty `role_id` → rola `is_default`.
  - `UserFactory::admin()`, `superAdmin()` ustawiają `role_id` ról startowych po nazwie; domyślnie klient (rola domyślna z `creating`).
  - `UpdateUserRoleRequest`: pole `role_id` (`uuid`, `exists:roles,id`, rola nie `is_super`); `UserRoleController` zapisuje `role_id`; odpina `assignedAdmins` i `managedClients` tylko gdy zmienia się to, czy rola ma `admin.access`.
  - `StoreAdministratorRequest`: `role_id` wymagane, rola z `admin.access` i nie `is_super`; `AdministratorController::store` zapisuje je zamiast `UserRole::Admin`. `StoreUserRequest`: `role_id` opcjonalne (domyślnie rola domyślna), rola bez `admin.access` i nie `is_super`, akceptowane tylko gdy autor ma `users.change_role` (inaczej ignorowane).
  - Pola `administrators.*` w `StoreUserRequest` i `UpdateUserAdministratorsRequest`: `Rule::exists('users', 'id')` ograniczone do administratorów (podzapytanie po roli: `whereIn('role_id', roles z admin.access i nie is_super)`).
  - `MakeSuperAdmin`: istniejący super admin = `User::whereHas('role', is_super)`; przypisuje rolę `is_super`.
  - Odpowiedź `UserController::present` zwraca `role` jako nazwę roli (`role->name`) i `role_id`.

- [ ] **Krok 1: Test `UserRoleKindTest`:** nowy użytkownik dostaje rolę domyślną i jest klientem (`isClient()`); `admin()` to administrator, nie super admin; `superAdmin()` to super admin i administrator; `User::clients()` nie zawiera adminów ani super admina; `User::administrators()` nie zawiera super admina.
- [ ] **Krok 2: Zaktualizuj istniejące testy** do `role_id` (te z listy „Modify (testy)”): zamiast `['role' => 'admin']` przekazuj `role_id` roli `Administrator`, zamiast `->role` porównuj `->isSuperAdmin()`/`->isAdmin()`/`->role->name`; zachowaj liczbę i sens asercji.
- [ ] **Krok 3: Uruchom** testy z kroków 1–2 – oczekiwane FAIL.
- [ ] **Krok 4: Zaimplementuj** zmiany z bloku Interfaces; usuń `UserRole` i `where('role', …)` w `UserController`, `AdministratorController`, `UpdateUserAdministratorsRequest`, `StoreUserRequest`, `MakeSuperAdmin`. Zastąp `$user->role === UserRole::User` przez `$user->isClient()`.
- [ ] **Krok 5: Migracja** `drop_role_from_users_table`.
- [ ] **Krok 6: Frontend minimalnie:** w `resources/js/types/auth.ts` dodaj `role_id: string` i usuń `role`; w `AppSidebar.vue` zastąp porównania ze stringami `role` polami `auth.isSuperAdmin` i `auth.isAdmin` udostępnionymi w `HandleInertiaRequests::share` (`'isAdmin' => $user?->isAdmin()`, `'isSuperAdmin' => $user?->isSuperAdmin()` obok `user`). Zaktualizuj typy w `types/link.ts` i użycia `user.role`.
- [ ] **Krok 7: Uruchom** `php artisan test --compact tests/Feature` (cały katalog `Feature`) – oczekiwane PASS poza ewentualnymi niezależnymi awariami; napraw regresje wynikające ze zmiany źródła roli.
- [ ] **Krok 8: Weryfikacja migracji na kopii danych:** `php artisan migrate:fresh --seed` lokalnie, potem utwórz konta trzech ról ręcznie przed migracją `drop_role…` (lub `migrate:rollback --step=2`, `migrate`) i sprawdź w `database-query`, że `role_id` wskazuje właściwe role.
- [ ] **Krok 9:** Usunięcie `UserRoleTest.php` wymaga zgody użytkownika (zasada projektu). Zapytaj; po zgodzie przenieś jego przypadki do `UserRoleKindTest`.
- [ ] **Krok 10: Pint, `npm run build`, commit** (`Switch user role source to roles table`).

---

### Zadanie 3: Policy i middleware na uprawnieniach

**Files:**
- Modify: `app/Policies/UserPolicy.php`, `LinkPolicy.php`, `DomainPolicy.php`, `PackagePolicy.php`, `app/Http/Middleware/EnsureUserIsAdmin.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/Admin/LinkDisableController.php`, `DomainDisableController.php`, `UserController.php` (index/show/`present`), `UserPackageController`/`UpdateUserPackageRequest` (bez zmian, korzystają z `assignPackage`)
- Test: `tests/Feature/PermissionPolicyTest.php`, aktualizacja `tests/Feature/UserPolicyTest.php`, `tests/Feature/AdminAccessTest.php`

**Interfaces:**
- Consumes: `User::hasPermission()`, `isAdmin()`, `isSuperAdmin()`, `isClient()`.
- Produces – metody `UserPolicy` (każda: strukturalna kontrola jak dziś **i** uprawnienie):
  - `viewAny(User $actor): bool` (`users.view`), `view(User $actor, User $target): bool` (`users.view` + `manages`), `create` (`users.create`), `update` (`users.update`), `delete` (`users.delete`), `block` (`users.block`), `changeRole` (`users.change_role`, nie sobie, cel nie super), `manageAdministrators` (`administrators.manage`), `assignAdministrators` (`administrators.assign`), `assignPackage` (`packages.assign`, cel nie super), `createLink` (`links.create_for_user`), `manageDomains` (`domains.manage_for_user`), `createDomain` (jak `manageDomains` + limit), nowe `disableLinks(User $actor, User $target): bool` (`links.disable` + `outranks` + `manages`), `disableDomains(User $actor, User $target): bool` (`domains.disable` + `outranks` + `manages`).
  - `LinkPolicy::create`, `update`, `delete`, `toggle` wymagają `links.manage`; `view` – `links.stats` dla właściciela albo `isAdmin()`.
  - `DomainPolicy::create`, `delete`, `verify` wymagają `domains.manage`.
  - `PackagePolicy`: wszystkie metody `packages.manage`.
  - `EnsureUserIsAdmin`: `abort_unless($request->user()?->hasPermission(Permission::AdminAccess), 403)` (przez `isAdmin()`).
  - `HandleInertiaRequests::share`: `auth.can` = `array<string, bool>` – klucz każdego `Permission` → `hasPermission`; `null`/pusta mapa dla gościa.
- `LinkDisableController` autoryzuje `disableLinks` (zamiast `block`), `DomainDisableController` – `disableDomains` (zamiast `manageDomains`). `UserController::present` dodaje do `can`: `disableLinks`, `disableDomains`. `UserController::index` autoryzuje `viewAny`, `show` – `view`.

- [ ] **Krok 1: Testy `PermissionPolicyTest`** (Pest, zbiory danych): (a) rola administratora bez `users.block` nie blokuje przypisanego klienta, z nim blokuje; (b) nadpisanie `granted=false` na `users.block` odbiera blokowanie mimo roli; (c) super admin nie może `block`/`delete`/`changeRole` samego siebie (`Gate::forUser($super)->allows(...)` = `false`) i nie może zmieniać cudzego linku trasą klienta (`PATCH links/{link}` → 403); (d) rola klienta bez `links.manage` dostaje 403 na `POST /links`, a z nią – tworzy link do limitu pakietu; (e) `disableLinks`/`disableDomains` wymagają osobnych uprawnień (administrator z `users.block`, ale bez `links.disable`, dostaje 403 na `PATCH admin/links/{link}/disable`); (f) `auth.can` w odpowiedzi Inertia zawiera mapę kluczy z `true` tylko dla uprawnień użytkownika (`assertInertia`).
- [ ] **Krok 2: Zaktualizuj** `UserPolicyTest` i `AdminAccessTest` (administrator bez uprawnień do pakietów nadal dostaje 403 na `/admin/packages`, `/admin/administrators`, `.../package`, `.../role`; ścieżki wykluczeń w teście zostają).
- [ ] **Krok 3: Uruchom** – oczekiwane FAIL.
- [ ] **Krok 4: Zaimplementuj** zmiany z bloku Interfaces; w każdej metodzie `UserPolicy` zastąp wywołania `isAdmin()`/`isSuperAdmin()` jako *kontroli uprawnienia* wywołaniem `hasPermission(...)` (super zwraca `true`), a zachowaj `outranks`/`manages`/„nie sobie”/„cel nie super”.
- [ ] **Krok 5: Uruchom** `php artisan test --compact tests/Feature` – oczekiwane PASS.
- [ ] **Krok 6: Pint i commit** (`Authorize through permissions instead of fixed roles`).

---

### Zadanie 4: CRUD ról (backend)

**Files:**
- Create: `app/Http/Controllers/Admin/RoleController.php`, `app/Http/Requests/Admin/StoreRoleRequest.php`, `UpdateRoleRequest.php`, `app/Policies/RolePolicy.php`
- Modify: `routes/web.php` (`Route::resource('roles', RoleController::class)->except('show')` w grupie `admin`), `app/Http/Controllers/Admin/UserRoleController.php` (autoryzacja i efekty uboczne – patrz niżej)
- Test: `tests/Feature/Admin/RoleManagementTest.php`; rozszerzenie `tests/Feature/AdminAccessTest.php` (trasy `admin.roles.*`, `{role}` w mapie parametrów)

**Interfaces:**
- Consumes: `Role`, `Permission`, `User::hasPermission`.
- Produces:
  - `RolePolicy`: `viewAny`, `create`, `update`, `delete` → `roles.manage`; `update` odrzuca rolę `is_super`; `delete` odrzuca `is_super`, `is_default` i rolę z użytkownikami (zwraca `Response::deny` z komunikatem).
  - `RoleController`: `index` (strona `admin/roles/Index`, props: `roles` – `{id, name, description, is_super, is_default, permissions_count, users_count}`), `create` (`admin/roles/Create`, props: `permissionGroups`), `store`, `edit` (`admin/roles/Edit`, props: `role` z `permissions: list<string>` oraz `permissionGroups`), `update`, `destroy`. `permissionGroups`: `list<array{group: string, permissions: list<array{key: string, label: string}>}>` z tabeli `permissions`.
  - `Store/UpdateRoleRequest`: `name` (wymagane, unikalne w `roles`, ignoruje bieżącą rolę przy `update`, max 255), `description` (nullable, max 1000), `permissions` (`array`), `permissions.*` (`Rule::exists('permissions', 'key')`).
  - Zabezpieczenie: aktualizacja własnej roli nie może odebrać `roles.manage` autorowi (walidacja: jeśli rola aktora, `permissions` musi zawierać `roles.manage`).
  - `UserRoleController`: po zmianie `role_id` czyści pamięć uprawnień celu; blokuje zmianę własnej roli (policy `changeRole` – już nie sobie).

- [ ] **Krok 1: Testy `RoleManagementTest`:** `roles.manage` tworzy rolę z zestawem uprawnień (zapis w `permission_role`); brak `roles.manage` → 403 na wszystkich trasach; duplikat nazwy → błąd walidacji; nieistniejący klucz uprawnienia → błąd; edycja roli `is_super` → 403; usunięcie roli z użytkownikami, `is_super` i `is_default` → odmowa i rola nadal istnieje; usunięcie pustej roli działa; aktor nie może zapisać swojej roli bez `roles.manage`; zmiana roli użytkownika między dwiema rolami klienta nie odpina `assignedAdmins`, a zmiana klient → rola z `admin.access` odpina (`Review Focus`).
- [ ] **Krok 2: Uruchom** – oczekiwane FAIL.
- [ ] **Krok 3: Zaimplementuj** kontroler, żądania, policy i trasy; zarejestruj `RolePolicy` zgodnie z auto-wykrywaniem Laravela (sprawdź `AppServiceProvider`, czy policy są rejestrowane ręcznie, i dopasuj).
- [ ] **Krok 4: Wygeneruj** Wayfinder (`php artisan wayfinder:generate --with-form --no-interaction`).
- [ ] **Krok 5: Uruchom** `php artisan test --compact tests/Feature/Admin tests/Feature/AdminAccessTest.php` – PASS.
- [ ] **Krok 6: Pint i commit** (`Add role management`).

---

### Zadanie 5: Nadpisania uprawnień użytkownika (backend)

**Files:**
- Create: `app/Http/Controllers/Admin/UserPermissionController.php`, `app/Http/Requests/Admin/UpdateUserPermissionsRequest.php`
- Modify: `routes/web.php` (`Route::put('users/{user}/permissions', [UserPermissionController::class, 'update'])->name('users.permissions.update')`), `app/Http/Controllers/Admin/UserController.php` (`show` dodaje `permissionOverrides`, `rolePermissions`, `permissionGroups`, `roles` dla `UserPermissionsCard`)
- Test: `tests/Feature/Admin/UserPermissionOverrideTest.php`; rozszerzenie `AdminAccessTest`

**Interfaces:**
- Consumes: `User::permissionOverrides(): BelongsToMany<PermissionRecord>` z pivotem `granted`, `UserPolicy::changeRole`.
- Produces:
  - `UpdateUserPermissionsRequest`: `authorize` → `can('changeRole', $this->route('user'))`; `granted` i `revoked` – tablice kluczy (`Rule::exists('permissions', 'key')`, bez `admin.access`, bez wspólnych elementów obu list).
  - `UserPermissionController::update(UpdateUserPermissionsRequest $request, User $user): RedirectResponse` – w transakcji zastępuje wszystkie nadpisania celu, czyści pamięć uprawnień, zwraca `back()` z toastem `Permissions updated.`.
  - Dane `show`: `permissionOverrides: {granted: list<string>, revoked: list<string>}`, `rolePermissions: list<string>`, `permissionGroups` (jak w Zadaniu 4), `roles: list<{id, name}>` (bez `is_super`) – tylko gdy aktor ma `users.change_role` i `can('changeRole', $user)`, inaczej puste.

- [ ] **Krok 1: Testy:** zapis nadpisań zapisuje wiersze `permission_user` z poprawnym `granted`; ponowny zapis zastępuje poprzednie; `admin.access` w którejkolwiek liście → błąd walidacji; ten sam klucz w obu listach → błąd; aktor bez `users.change_role` → 403; nie można zmienić nadpisań samemu sobie ani super adminowi (403 z `changeRole`); efektywne uprawnienia celu po zapisie zgadzają się z `hasPermission` (`granted=false` odbiera uprawnienie roli).
- [ ] **Krok 2: Uruchom** – FAIL; **Krok 3:** zaimplementuj; **Krok 4:** Wayfinder; **Krok 5:** `php artisan test --compact tests/Feature/Admin` – PASS.
- [ ] **Krok 6: Pint i commit** (`Add per-user permission overrides`).

---

### Zadanie 6: `usePermissions`, menu i typy

**Files:**
- Create: `resources/js/composables/usePermissions.ts`
- Modify: `resources/js/types/auth.ts` (`Auth` dostaje `can: Record<string, boolean>`, `isAdmin`, `isSuperAdmin`), `resources/js/components/AppSidebar.vue`

**Interfaces:**
- Produces: `usePermissions(): { can: (key: string) => boolean }` czytające `usePage().props.auth.can` (brak klucza = `false`).
- Menu w `AppSidebar.vue`: „Admin” (`administrators.manage`), „Packages” (`packages.manage`), „Roles” (`roles.manage`, ikona `ShieldCheck` z `@lucide/vue`, link do `admin/roles`), „Users” (`users.view`), „My links” (`links.manage`), „My domains” (`domains.manage`), „My package” (`billing.manage` i nie `isSuperAdmin`).

- [ ] **Krok 1:** zaimplementuj composable i menu; **Krok 2:** `npm run build` oraz `npm run lint` (jeśli skrypt istnieje; sprawdź `package.json`) – bez błędów; **Krok 3:** ręcznie w przeglądarce: konta super admina, administratora i klienta widzą odpowiednie pozycje menu; **Krok 4:** commit (`Show navigation by permission`).

---

### Zadanie 7: Ekran ról

**Files:**
- Create: `resources/js/pages/admin/roles/Index.vue`, `Create.vue`, `Edit.vue`, `resources/js/components/RoleForm.vue`, `resources/js/components/PermissionMatrix.vue`
- Modify: brak (tras i akcji Wayfinder dostarcza Zadanie 4)

**Interfaces:**
- Consumes: propsy ze strony `RoleController` (Zadanie 4); `@/routes/admin/roles`.
- Produces: `PermissionMatrix.vue` – `props: { groups: PermissionGroup[]; modelValue: string[]; disabled?: boolean }`, `emits: update:modelValue`; każda grupa w `Collapsible` z nagłówkiem „zaznacz wszystkie”, uprawnienie to `Checkbox` z etykietą. `RoleForm.vue` używa `useForm` / `<Form>` z polami `name`, `description`, `permissions`, wzorowany na `PackageForm.vue`. `Index.vue`: tabela z kolumnami nazwa, uprawnienia, użytkownicy; badge dla roli systemowej (`is_super`, `is_default`); przycisk usuwania ukryty dla ról, których nie można usunąć (`is_super`, `is_default`, `users_count > 0`); rola `is_super` tylko do odczytu.

- [ ] **Krok 1:** zaimplementuj strony i komponenty zgodnie ze wzorcem `admin/packages/*` i `PackageForm.vue` (skill `inertia-vue-development`, `wayfinder-development`); **Krok 2:** `npm run build` bez błędów; **Krok 3:** ręcznie: utwórz rolę, edytuj, spróbuj usunąć rolę z użytkownikami (przycisk ukryty), oglądaj rolę `Super admin` (tylko odczyt); **Krok 4:** commit (`Add roles screens`).

---

### Zadanie 8: Rozbicie `Show.vue` i karta uprawnień użytkownika

**Files:**
- Create: `resources/js/components/admin/user/UserHeader.vue`, `UserPackageCard.vue`, `UserLinksTable.vue`, `UserDomainsTable.vue`, `UserAdministratorsCard.vue`, `UserPermissionsCard.vue`
- Modify: `resources/js/pages/admin/users/Show.vue` (cienki układ składający sekcje; zachowuje dotychczasowe propsy i flagi `user.can.*`)

**Interfaces:**
- Consumes: propsy `show` (w tym nowe z Zadania 5: `permissionOverrides`, `rolePermissions`, `permissionGroups`, `roles`); trasy `admin.users.role`, `admin.users.permissions.update`.
- Produces: `UserPermissionsCard.vue` – wybór roli (formularz `PATCH admin/users/{user}/role` z `role_id`) i lista uprawnień z trzema stanami na pozycję (`inherited` / `granted` / `revoked`; „dziedziczone” pokazuje, czy uprawnienie daje rola); zapis `PUT admin/users/{user}/permissions` z `granted` i `revoked`. Klucz `admin.access` jest zablokowany do zmiany. Karta widoczna tylko gdy `roles.length > 0` (backend zwraca pustą listę bez prawa).

- [ ] **Krok 1:** wyodrębnij sekcje z `Show.vue` w kolejności `UserHeader` (nagłówek, status, dialogi blokady/roli/usunięcia), `UserPackageCard` (korzysta z `PackageOverview`, `PackageHistory`, `PaymentHistory`), `UserLinksTable`, `UserDomainsTable`, `UserAdministratorsCard` (korzysta z `AdministratorPicker`); zachowaj dotychczasowe zachowanie i warunki widoczności (`user.can.*`, `isSuperAdmin`); **Krok 2:** dodaj `UserPermissionsCard`; **Krok 3:** `npm run build`; **Krok 4:** ręcznie porównaj stronę przed i po rozbiciu dla klienta, administratora i super admina (te same sekcje i akcje), potem zmień rolę i nadpisanie uprawnienia; **Krok 5:** commit (`Split the admin user page and add permissions card`).

---

### Zadanie 9: Wybór roli przy tworzeniu kont i nazwa roli na listach

**Files:**
- Modify: `resources/js/pages/admin/users/Create.vue`, `resources/js/pages/admin/administrators/Create.vue`, `resources/js/pages/admin/users/Index.vue`, `resources/js/pages/admin/administrators/Index.vue`, `app/Http/Controllers/Admin/UserController.php` (create), `AdministratorController.php` (create/index)
- Test: rozszerzenie `tests/Feature/Admin/UserManagementTest.php` i `AdministratorManagementTest.php`

**Interfaces:**
- Produces: `UserController::create` przekazuje `roles: list<{id, name}>` (role bez `admin.access`, bez `is_super`) tylko autorowi z `users.change_role`, inaczej pustą listę; `AdministratorController::create` przekazuje `roles` (role z `admin.access`, bez `is_super`); listy `index` zwracają `role` (nazwa) dla każdego wiersza. Formularze wysyłają `role_id` (Zadanie 2 już go waliduje).

- [ ] **Krok 1: Testy:** utworzenie administratora z rolą bez `admin.access` → błąd walidacji; utworzenie klienta z rolą administratora → błąd; administrator bez `users.change_role` tworzący klienta nie ustawia roli (użyta rola domyślna mimo przesłanego `role_id`); `assertInertia` dla `roles` na stronach `create` i `role` na listach.
- [ ] **Krok 2:** uruchom – FAIL; **Krok 3:** zaimplementuj backend i formularze; **Krok 4:** Wayfinder, `npm run build`; **Krok 5:** `php artisan test --compact tests/Feature/Admin` – PASS; **Krok 6:** Pint i commit (`Choose a role when creating accounts`).

---

### Zadanie 10: Weryfikacja końcowa

- [ ] **Krok 1:** `vendor/bin/pint --dirty --format agent`, `npm run build`, `php artisan wayfinder:generate --with-form --no-interaction` – bez zmian po ponownym uruchomieniu.
- [ ] **Krok 2:** `grep -rn "UserRole\|'role'" app database/factories routes resources/js/pages resources/js/components` – brak odwołań do starego enumu i kolumny (poza starymi migracjami).
- [ ] **Krok 3:** ręczny przegląd w przeglądarce trzech kont (super admin, administrator, klient) po `php artisan migrate:fresh --seed`.
- [ ] **Krok 4:** poproś użytkownika o uruchomienie pełnego zestawu `php artisan test --compact` i przeglądu gałęzi.
