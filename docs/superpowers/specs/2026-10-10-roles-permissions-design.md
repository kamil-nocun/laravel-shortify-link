# Role i uprawnienia (RBAC) – projekt

## Cel

Zastąpić sztywne role `UserRole` (user / admin / super_admin) elastycznym modelem: super admin tworzy własne role z dowolnym zestawem uprawnień, nadaje je klientom i administratorom oraz może nadpisać uprawnienia pojedynczemu użytkownikowi. Panel pokazuje tylko akcje dozwolone dla zalogowanego.

## Zakres

W zakresie:

- tabele `permissions`, `roles`, `permission_role`, `permission_user`, kolumna `users.role_id` (zamiast `users.role`),
- jedna pula ról bez podziału klient/admin, jedna rola na użytkownika,
- uprawnienia w bazie (katalog edytowalny bez zmian w UI), nadpisania per użytkownik,
- przepięcie policy, middleware i polecenia `MakeSuperAdmin` na uprawnienia,
- CRUD ról, nadpisania uprawnień użytkownika, selektor roli przy tworzeniu kont,
- rozbicie `admin/users/Show.vue` na komponenty-sekcje,
- ukrywanie niedozwolonych akcji w UI.

Poza zakresem:

- wiele ról na użytkownika, grupy klientów, konta zespołowe,
- zmiany w `client_admin` i zakresie „nad kim” (zostają bez zmian),
- zastąpienie pakietów rolami – limity ilościowe zostają w pakiecie,
- ujednolicenie list klientów i administratorów.

## Model danych

Wszystkie nowe tabele mają klucze główne i obce typu UUID.

- `permissions`: `id`, `key` (unikalny, np. `users.block`), `label`, `group`. Katalog uprawnień; UI listuje go dynamicznie, grupując po `group`.
- `roles`: `id`, `name`, `description`, `is_super` (bool), timestamps.
- `permission_role`: `permission_id`, `role_id`, unikalne razem.
- `permission_user`: `permission_id`, `user_id`, `granted` (bool), unikalne razem. `true` dodaje uprawnienie ponad rolę, `false` odbiera je mimo roli.
- `users.role_id`: FK do `roles`, zastępuje `users.role` i enum `UserRole` (enum do usunięcia).

### Efektywne uprawnienia

`(uprawnienia roli ∪ nadpisania granted=true) − nadpisania granted=false`. Wynik liczony raz na żądanie i zapamiętany w obiekcie `User`. Rola z `is_super` przechodzi każde sprawdzenie i omija nadpisania.

### Dostęp do panelu admina

Daje go uprawnienie `admin.access`. `User::isAdmin()` zostaje jako skrót do `hasPermission('admin.access')`, `isSuperAdmin()` to `role->is_super`.

### Klucze uprawnień

Kod odwołuje się do kluczy przez stałe (`Permission::UsersBlock` itp.). Nowa funkcja dodaje swój klucz do katalogu przez seeder lub migrację; zarządzanie rolami odbywa się w UI. Wstępna lista (do doprecyzowania w planie):

- panel: `admin.access`
- użytkownicy: `users.view`, `users.create`, `users.update`, `users.delete`, `users.block`, `users.change_role`
- administratorzy: `administrators.manage`, `administrators.assign`
- treści klientów (z poziomu admina): `links.create_for_user`, `links.disable`, `domains.manage_for_user`, `domains.disable`
- pakiety: `packages.manage`, `packages.assign`
- role: `roles.manage`
- funkcje klienta: `links.manage`, `links.stats`, `domains.manage`, `billing.manage`

### Role startowe (migracja)

- `Super admin` (`is_super`) – jedyna rola nieedytowalna i niekasowalna.
- `Klient` – domyślna dla nowych kont, uprawnienia klienta jak dziś.
- `Administrator` – dzisiejsze uprawnienia admina, bez `packages.manage`, `packages.assign`, `administrators.manage`, `administrators.assign`, `users.change_role` i `roles.manage` (dziś tylko super admin).

Migracja mapuje `users.role` na `role_id` i usuwa starą kolumnę. Migracja musi być odwracalna lub jawnie udokumentowana jako nieodwracalna.

## Autoryzacja

- `User::hasPermission(Permission|string $key): bool` – jedyne miejsce liczenia uprawnień.
- `Gate::before` w `AppServiceProvider`: dla `is_super` zwraca `true`. Ręczne `isSuperAdmin()` w policy znika.
- `UserPolicy`: `isAdmin()` zastąpione `hasPermission(...)` właściwej akcji. `outranks()` i `manages()` bez zmian (cel z `admin.access` ma poziom admina, więc zwykły admin nie ma nad nim władzy). Metody tylko dla super admina dostają własne klucze (`users.change_role`, `administrators.assign`, `packages.assign`, `administrators.manage`).
- `LinkPolicy`, `DomainPolicy`: `create`, `update`, `delete`, `toggle`, `verify` dodatkowo wymagają uprawnienia klienta (`links.manage`, `domains.manage`). Sprawdzanie własności i limit z pakietu zostają.
- `PackagePolicy`: `packages.manage`.
- `EnsureUserIsAdmin`: sprawdza `admin.access`.
- `HandleInertiaRequests` udostępnia `auth.can` – płaską mapę `{klucz: bool}` efektywnych uprawnień.

## Backend

- `RoleController` (`index`, `create`, `store`, `edit`, `update`, `destroy`) pod `roles.manage`; `RolePolicy` blokuje edycję i usuwanie roli `is_super` oraz usuwanie roli z przypisanymi użytkownikami. Walidacja `permissions.*` przez `exists:permissions,key`.
- `UserRoleController`: zmienia `role_id`. Zachowuje obecny efekt uboczny: gdy zmienia się obecność `admin.access`, odpina `assignedAdmins` i `managedClients`.
- `UserPermissionController@update`: zapis nadpisań w `permission_user`; wymaga `users.change_role`.
- `AdministratorController`, `StoreUserRequest`: wybór roli z listy ról zamiast enumu.
- `MakeSuperAdmin`: przypisuje rolę `is_super`.
- Zabezpieczenia: nie można usunąć roli z użytkownikami ani `is_super`, odebrać sobie `roles.manage`, ani zmienić własnej roli.

## Frontend

- Ukrywanie akcji: wzorzec `user.can.*` liczony w backendzie w `Show.vue` zostaje (policy zna kontekst „nad kim”). `auth.can` służy do menu (`AppSidebar`) i przycisków na listach, dostępny przez composable `usePermissions()` (`can(key)`).
- `pages/admin/roles/`: `Index.vue`, `Create.vue`, `Edit.vue`, wspólny `RoleForm.vue`. Macierz uprawnień pogrupowana po `group` w `Collapsible`, `Checkbox` per uprawnienie, „zaznacz wszystkie” w grupie, rola `is_super` tylko do odczytu. Pozycja „Role” w sidebarze przy `roles.manage`.
- Rozbicie `Show.vue` (bez nowych zależności) na komponenty w `resources/js/components/admin/user/`: `UserHeader`, `UserPackageCard`, `UserLinksTable`, `UserDomainsTable`, `UserAdministratorsCard`, `UserPermissionsCard` (rola + nadpisania w trzech stanach: dziedziczone / dodane / odebrane).
- `admin/users/Create.vue`, `admin/administrators/Create.vue`: selektor roli z backendu. Listy pokazują nazwę roli.
- Po dodaniu tras: generowanie Wayfinder.

## Testy (Pest)

- `hasPermission`: rola, przyznanie, odebranie, super admin.
- Policy użytkowników, linków i domen z nowymi uprawnieniami.
- CRUD ról z zabezpieczeniami; nadpisania uprawnień; zmiana roli odpina przypisania.
- Dostęp do panelu admina (istniejący test przepisany na nowy model).
- `assertInertia` dla propsów `can` i listy ról.
- Migracja danych: istniejący użytkownicy dostają właściwe role.

## Ryzyka

- Migracja `users.role` → `role_id` dotyka wszystkich kont; wymaga testu na danych i kopii przed wdrożeniem.
- Szerokie użycie `isAdmin()` / `isSuperAdmin()` / `UserRole` (m.in. `AdminStatsReport`, `BillingController`, `CheckoutRequest`, `Link`, `Domain`, `UserFactory`) – każde miejsce trzeba przejrzeć w planie.
- Nadpisania per użytkownik zwiększają liczbę stanów do przetestowania; efektywne uprawnienia liczy tylko `hasPermission`.
