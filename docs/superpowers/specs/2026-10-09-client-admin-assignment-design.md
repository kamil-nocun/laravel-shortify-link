# Przypisywanie klientów do administratorów – projekt

## Cel

Ograniczyć dostęp administratorów do danych osobowych klientów (RODO). Administrator widzi i obsługuje wyłącznie klientów, którzy są mu przypisani. Super administrator widzi wszystko i zarządza przypisaniami.

## Zasady widoczności

- Administrator widzi klientów, których sam utworzył, oraz tych, których przypisał mu super administrator.
- Super administrator widzi wszystkich klientów i wszystkich administratorów.
- Administrator nigdy nie widzi innych administratorów.
- Klient może mieć wielu administratorów (relacja wiele-do-wielu).
- Klient bez żadnego administratora jest widoczny tylko dla super administratora.
- Dane zbiorcze administratora (dashboard, liczniki, wykresy, linki do moderacji) obejmują wyłącznie jego klientów.
- Wejście na nieprzypisanego klienta lub jego link zwraca 404, nie 403 (nie ujawniamy istnienia konta).

## Dane i model

- Tabela `client_admin`: `id` (UUID PK), `client_id` i `admin_id` (UUID FK do `users`, `cascadeOnDelete`), `timestamps`, unikalny indeks `(client_id, admin_id)`.
- Usunięcie administratora kasuje jego przypisania, klienci zostają.
- `User::assignedAdmins()` (po stronie klienta) i `User::managedClients()` (po stronie administratora), obie `belongsToMany` z modelem pośrednim opartym o UUID.
- `User::scopeVisibleTo($query, User $actor)`: super administrator bez ograniczeń, administrator tylko klienci z przypisaniem do niego.
- `Link::scopeVisibleTo`: linki, których właściciel jest widoczny dla aktora.
- Fabryka `UserFactory`: stan `assignedTo(User $admin)`.

## Polityki i routing

- `UserPolicy::manages(User $actor, User $target)`: super administrator zawsze, administrator tylko gdy jest w `assignedAdmins` celu, pozostali nigdy.
- `update`, `delete`, `block`, `createLink` wymagają `outranks` oraz `manages`. `changeRole` bez zmian (tylko super administrator).
- Nowa metoda `assignAdministrators` (tylko super administrator).
- Route model binding `{user}` i `{link}` w grupie `admin.*` korzysta z `visibleTo`, czyli niewidoczny rekord daje 404.
- Nowy endpoint `PUT admin/users/{user}/administrators` (`UserAdministratorController`) wykonuje `sync` przypisań.

## Przepływy

- `UserController::store`: administrator automatycznie przypisuje siebie. Super administrator może przekazać `administrators[]` (opcjonalnie, może być puste) i wykonuje `sync`.
- `StoreUserRequest` przyjmuje `administrators` (tablica UUID, `exists` wśród administratorów) tylko od super administratora, od zwykłego administratora pole jest ignorowane.
- `UserController::index` i `AdminStatsReport` filtrują przez `visibleTo`. Licznik `users` dla administratora liczy tylko jego klientów.
- `UserRoleController`: zmiana roli klienta na administratora czyści jego wpisy jako klienta, zmiana administratora na klienta czyści jego wpisy jako administratora. Zdegradowany administrator zostaje klientem bez administratorów.
- `UserLinkController` sprawdza `manages`.

## UI (Vue)

- `admin/users/Create.vue`, `Edit.vue`: dla super administratora wielokrotny wybór administratorów (prop `administrators`). Edycja zapisuje przez `UserAdministratorController`.
- `admin/users/Show.vue`: sekcja „Przypisani administratorzy”, widoczna tylko dla super administratora (administrator nigdy nie widzi innych administratorów; sekcja jest ukryta także dla celów niebędących klientami) i edytowalna.
- `Index.vue` bez zmian, lista jest już przefiltrowana po stronie serwera.
- `present()` w `UserController` zwraca dodatkowo `can.assignAdministrators`.

## Testy (Pest)

- Administrator: lista, show, edit, block, delete, createLink i statystyki cudzego klienta dają 404, własny klient działa.
- Przypisanie przez super administratora otwiera dostęp, odpięcie go zamyka.
- Utworzenie klienta przez administratora tworzy przypisanie. Super administrator może wskazać administratorów przy tworzeniu.
- Zwykły administrator dostaje 403 na `assignAdministrators` i nie ustawi `administrators` przy tworzeniu.
- Cascade po usunięciu administratora, klient zostaje.
- Dashboard administratora liczy tylko jego zakres.
- Zmiana roli czyści przypisania.

## Poza zakresem

- Historia zmian przypisań (audyt).
- Masowe przepinanie klientów między administratorami.
- Powiadomienia o zmianie przypisania.
