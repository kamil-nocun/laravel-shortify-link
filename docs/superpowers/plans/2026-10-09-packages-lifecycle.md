# Cykl życia pakietów – plan implementacji (rozszerzenie etapu 1)

> **Dla agentów wykonawczych:** WYMAGANY SUB-SKILL: użyj superpowers:subagent-driven-development (zalecane) albo superpowers:executing-plans, żeby wykonać plan zadanie po zadaniu. Kroki używają checkboxów (`- [ ]`).

**Cel:** Edycja i usunięcie pakietu obowiązują użytkownika od jego następnego okresu rozliczeniowego; okresy cykliczne odnawiają się automatycznie.

**Architektura:** Przypisanie zapisuje migawkę `link_limit` (obok istniejącej `price`), a limit użytkownika czyta z migawki. Pakiet z przypisaniami jest wycofywany (`SoftDeletes`). Polecenie `packages:renew` (scheduler co godzinę) odnawia przypisania cykliczne z aktualnymi warunkami albo kończy je, gdy pakiet wycofano lub zniknęła cena okresu.

**Tech Stack:** Laravel (PHP 8.5), MySQL 8.4, Inertia v3 + Vue, Wayfinder, Pest, Pint.

**Spec:** `docs/superpowers/specs/2026-10-09-packages-lifecycle-design.md` (rozszerza `2026-10-09-packages-design.md`)

## Global Constraints

- Klucze UUID dla nowych tabel; ta zmiana nie dodaje tabel.
- Free (pakiet domyślny) nie może zostać usunięty ani wycofany; tylko jeden pakiet domyślny (blokada wiersza `lockForUpdate` plus unikalny indeks `default_marker`, już wdrożone).
- Edycja pakietu domyślnego działa od razu; edycja pozostałych od następnego okresu przypisania.
- Wycofanie to `SoftDeletes`; klucz obcy `package_assignments.package_id` zostaje `restrictOnDelete`.
- Odnowienie dotyczy tylko okresów cyklicznych (`monthly`, `quarterly`, `yearly`); `one_time` się nie odnawia.
- Bez Cashiera, naliczania opłat i ekranu wyboru pakietu przez użytkownika (etap 2).
- Konwencje projektu: Form Requests, `Gate::authorize`, `Inertia::flash('toast', …)`, Wayfinder, typy zwrotne, Pest, Pint. Kod i komunikaty UI po angielsku.
- Po zmianach PHP: `vendor/bin/pint --dirty --format agent`. Po dodaniu tras/ akcji: `php artisan wayfinder:generate --with-form --no-interaction`.

## Decyzje planu (poza specem – do potwierdzenia)

- Przesuwanie okresów przy zaległym odnowieniu używa `addMonthsNoOverflow` na każdym kroku; początek 31. dnia może więc „dryfować" (31 stycznia → 28 lutego → 28 marca). Znany pułap, oznaczony komentarzem `ponytail:`.
- Harmonogram: `Schedule::command('packages:renew')->hourly()` w `routes/console.php`; uruchamianie `schedule:run` w produkcji jest poza zakresem planu.
- Przypisanie wskazujące pakiet z usuniętym wierszem (twardo) nie występuje: klucz obcy to uniemożliwia.

## Review Focus

- Edycja limitu pakietu nie zmienia limitu użytkownika w trakcie okresu, ale zmienia go po odnowieniu (Zadania 1, 3).
- Edycja pakietu domyślnego działa od razu dla użytkowników bez przypisania (Zadanie 1).
- Wycofany pakiet nie jest widoczny na listach ani w opcjach przypisania, a próba jego przypisania/edycji jest odrzucona (Zadanie 2).
- `packages:renew` uruchomione dwa razy z rzędu nie zmienia stanu drugi raz (idempotencja) i nadrabia pominięte okresy (Zadanie 3).
- Cena okresu usunięta z pakietu przed odnowieniem kończy przypisanie zamiast zostawić cenę `NULL` w migawce (Zadanie 3).

---

### Zadanie 1: Migawka limitu w przypisaniu i wycofywanie w modelu

**Files:**
- Create: `database/migrations/2026_10_09_210000_add_link_limit_to_package_assignments_table.php`, `database/migrations/2026_10_09_210001_add_soft_deletes_to_packages_table.php`
- Modify: `app/Models/PackageAssignment.php`, `app/Models/Package.php`, `app/Models/User.php`, `app/Http/Controllers/Admin/UserPackageController.php`, `database/factories/PackageAssignmentFactory.php`
- Test: `tests/Feature/PackageSnapshotTest.php`

**Interfaces:**
- Consumes: istniejące `Package`, `PackageAssignment`, `User::package()` / `linkLimit()`.
- Produces:
  - `package_assignments.link_limit` (unsigned integer, migawka; migracja uzupełnia istniejące wiersze z ich pakietów). `PackageAssignment` fillable zawiera `link_limit`; fabryka domyślnie ustawia `link_limit` na limit swojego pakietu (z `package_id`).
  - `packages.deleted_at`; `Package` używa `SoftDeletes`; `PackageAssignment::package()` zwraca również wycofane (`withTrashed()`).
  - `User::activeAssignment(): ?PackageAssignment` (przypisanie z `isActive()`), `User::package()` korzysta z niego; `User::linkLimit()`: `null` dla super admina, inaczej limit z migawki aktywnego przypisania, inaczej limit pakietu domyślnego, inaczej 0.
  - `UserPackageController::update` zapisuje `link_limit` pakietu jako migawkę.

- [ ] **Krok 1: Napisz testy** `PackageSnapshotTest.php`: edycja `link_limit` pakietu (`$package->update(['link_limit' => 5])`) nie zmienia `linkLimit()` użytkownika z aktywnym przypisaniem (nadal wartość z migawki); przypisanie przez `admin.users.package.update` zapisuje `link_limit` pakietu; edycja pakietu domyślnego od razu zmienia `linkLimit()` użytkownika bez przypisania; wycofany (soft-deleted) pakiet nadal daje użytkownikowi limit z migawki do wygaśnięcia, a `$user->packageAssignment->package` zwraca wycofany pakiet.
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Feature/PackageSnapshotTest.php`. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj** dwie migracje (backfill `link_limit` przez `DB::statement`/`update` z joinem na `packages`; `softDeletes()` na `packages`), zmiany w modelach i fabryce oraz w `UserPackageController::update`. Zaktualizuj `EffectivePackageTest` i `LinkLimitTest`, jeśli ich dane używają `Package::link_limit` zamiast migawki (fabryka przypisania pobiera limit z pakietu, więc zwykle bez zmian).
- [ ] **Krok 4: Uruchom** `php artisan migrate --no-interaction`, następnie `php artisan test --compact tests/Feature/PackageSnapshotTest.php tests/Feature/EffectivePackageTest.php tests/Feature/LinkLimitTest.php tests/Feature/Admin/UserPackageAssignmentTest.php`. Oczekiwane: PASS.
- [ ] **Krok 5: Commit** `Snapshot the link limit on package assignments`.

### Zadanie 2: Wycofanie zamiast usunięcia pakietu

**Files:**
- Modify: `app/Http/Controllers/Admin/PackageController.php`, `app/Http/Requests/Admin/UpdateUserPackageRequest.php`, `app/Http/Controllers/Admin/UserController.php` (opcje pakietów i `presentAssignment`)
- Test: `tests/Feature/Admin/PackageManagementTest.php`, `tests/Feature/Admin/UserPackageAssignmentTest.php`

**Interfaces:**
- Consumes: `SoftDeletes` na `Package` i `activeAssignment` (Zadanie 1).
- Produces: `PackageController::destroy` — pakiet domyślny: błąd walidacji `package`; pakiet z przypisaniami: miękkie usunięcie (`delete()`); pakiet bez przypisań: `forceDelete()`. `UpdateUserPackageRequest` odrzuca wycofany pakiet (`Rule::exists('packages', 'id')->whereNull('deleted_at')`). `UserController::presentAssignment` dodaje klucz `package_withdrawn: bool`.

- [ ] **Krok 1: Zaktualizuj i dopisz testy.** W `PackageManagementTest`: test „default and assigned packages cannot be deleted" zmień na dwa — domyślny nadal odrzucony (`assertSessionHasErrors('package')`); pakiet z przypisaniem zostaje po `destroy` w bazie jako `onlyTrashed()`, znika z `admin.packages.index` i z `edit` (404), a przypisanie użytkownika nadal działa i zachowuje limit; pakiet bez przypisań jest usuwany trwale (`withTrashed()->find` zwraca `null`). W `UserPackageAssignmentTest`: przypisanie wycofanego pakietu daje błąd walidacji `package_id`; `show` zwraca `packageAssignment.package_withdrawn` równe `true` dla wycofanego i `false` dla zwykłego, a lista `packages` nie zawiera wycofanych.
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Feature/Admin/PackageManagementTest.php tests/Feature/Admin/UserPackageAssignmentTest.php`. Oczekiwane: FAIL (nowe/zmienione testy).
- [ ] **Krok 3: Zaimplementuj** zmiany w kontrolerze, request i `UserController` (`presentAssignment` czyta pakiet przez `withTrashed`, `package_withdrawn` = `$assignment->package->trashed()`).
- [ ] **Krok 4: Uruchom** testy z kroku 2 oraz `tests/Feature/AdminAccessTest.php`. Oczekiwane: PASS.
- [ ] **Krok 5: Commit** `Withdraw packages that still have assignments`.

### Zadanie 3: Polecenie `packages:renew`

**Files:**
- Create: `app/Console/Commands/RenewPackageAssignments.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/RenewPackageAssignmentsTest.php`

**Interfaces:**
- Consumes: `PackageAssignment` z migawką (`link_limit`, `price`), `BillingPeriod::expiresAt`, `Package::priceFor`.
- Produces: komenda `packages:renew` (klasa `RenewPackageAssignments`, `handle(): int` zwraca `Command::SUCCESS`) i wpis w `routes/console.php`: `Schedule::command('packages:renew')->hourly();`.

Reguły dla każdego przypisania cyklicznego z `expires_at` ≤ teraz, w osobnej transakcji z `lockForUpdate` na wierszu przypisania:
1. Pakiet wycofany albo `priceFor(billing_period)` równe `null` → usuń przypisanie.
2. W przeciwnym razie ustaw `starts_at` = poprzednie `expires_at`, `expires_at` = `billing_period->expiresAt(starts_at)`, i powtarzaj przesunięcie, aż `expires_at` będzie w przyszłości; `link_limit` i `price` ustaw z aktualnego pakietu. Dodaj komentarz `// ponytail: month-end drift, see plan decisions`.

- [ ] **Krok 1: Napisz testy** `RenewPackageAssignmentsTest.php` (używaj `$this->travelTo`/ `artisan('packages:renew')`): przypisanie miesięczne wygasłe wczoraj po edycji pakietu (limit 5 → 8, cena 1000 → 1200) dostaje po komendzie `starts_at` = dawne `expires_at`, `expires_at` w przyszłości, `link_limit` 8, `price` 1200; przypisanie niewygasłe się nie zmienia; wygasłe jednorazowe się nie zmienia; wycofany pakiet → przypisanie usunięte i `linkLimit()` użytkownika równe limitowi Free; cena okresu ustawiona na `NULL` → przypisanie usunięte; zaległe trzy miesiące są nadrobione (jedna komenda, wynik w przyszłości, kolejne uruchomienie niczego nie zmienia – porównaj `fresh()` przed/po); ponowne uruchomienie tuż po pierwszym nic nie zmienia.
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Feature/RenewPackageAssignmentsTest.php`. Oczekiwane: FAIL (brak komendy).
- [ ] **Krok 3: Zaimplementuj** komendę (`php artisan make:command RenewPackageAssignments --no-interaction`, `$signature = 'packages:renew'`) i harmonogram. Zapytanie: przypisania z `billing_period != one_time` i `expires_at <= now()`, iteracja `chunkById`.
- [ ] **Krok 4: Uruchom** testy komendy oraz `php artisan schedule:list` (Oczekiwane: `packages:renew` co godzinę). PASS.
- [ ] **Krok 5: Commit** `Renew recurring package assignments automatically`.

### Zadanie 4: Frontend i baner o wycofaniu

**Files:**
- Modify: `app/Http/Controllers/LinkController.php`, `resources/js/pages/links/Index.vue`, `resources/js/pages/admin/packages/Index.vue`, `resources/js/pages/admin/packages/Edit.vue`, `resources/js/components/UserPackageCard.vue`, `resources/js/types/package.ts`
- Test: `tests/Feature/LinkLimitTest.php` (rozszerzenie)

**Interfaces:**
- Consumes: `package_withdrawn` (Zadanie 2), `activeAssignment` (Zadanie 1).
- Produces: prop `packageNotice: {package_name: string, ends_at: string} | null` w `LinkController::index` — niepusty, gdy aktywne przypisanie użytkownika wskazuje wycofany pakiet.

- [ ] **Krok 1: Napisz testy** (rozszerz `LinkLimitTest`): `links.index` zwraca `packageNotice` z nazwą i `ends_at` dla aktywnego przypisania do wycofanego pakietu oraz `null` dla zwykłego pakietu i dla użytkownika bez przypisania.
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Feature/LinkLimitTest.php`. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj** prop w `LinkController::index`. Frontend: baner (komponent `Alert`) w `links/Index.vue` – „Package {name} is being withdrawn. From {date} the Free package applies."; w `admin/packages/Index.vue` dialog usunięcia pokazuje „{n} users will move to Free after their current period." gdy `assignments_count > 0` (pakiet domyślny bez przycisku usuwania); w `Edit.vue` podpowiedź „Changes apply from each user's next billing period" (dla `is_default` „Changes apply immediately"); `UserPackageCard.vue` dodaje `Badge` „Withdrawn" przy `assignment.package_withdrawn` (`PackageAssignment` w `package.ts` dostaje `package_withdrawn: boolean`).
- [ ] **Krok 4: Uruchom** testy z kroku 2, `npm run types:check` (tylko błędy w wygenerowanym `RedirectController.ts` są dopuszczalne) i `npm run build`. Oczekiwane: PASS.
- [ ] **Krok 5: Commit** `Show package withdrawal notices in the UI`.

### Zadanie 5: Weryfikacja końcowa

- [ ] **Krok 1:** `vendor/bin/pint --dirty --format agent`, następnie pełny zestaw `php artisan test --compact`. Oczekiwane: wszystko PASS.
- [ ] **Krok 2:** `npx playwright test tests/e2e/links.spec.ts tests/e2e/stats.spec.ts` przy działającym serwerze i zmigrowanej bazie dev (`php artisan migrate`). Oczekiwane: PASS.
- [ ] **Krok 3:** Poproś użytkownika o ręczne sprawdzenie w przeglądarce: edycja pakietu, wycofanie pakietu z przypisaniami (baner), `php artisan packages:renew`.

## Samoocena planu

- Pokrycie speca: migawka limitu i wycofywanie (Z1), usuwanie/wycofanie i niemożność przypisania wycofanego (Z2), odnowienie, nadrabianie, kończenie przy braku ceny i harmonogram (Z3), baner, dialog i podpowiedzi UI (Z4). Free niedeletowalny i jeden domyślny już wdrożone (blokada + indeks unikalny).
- Spójność typów: `link_limit` (migawka), `activeAssignment()`, `package_withdrawn`, `packageNotice` użyte identycznie w zadaniach.
