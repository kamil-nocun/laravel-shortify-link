# Płatności: zakup pakietów przez Cashier (etap 2a) – plan implementacji

> **Dla agentów wykonawczych:** WYMAGANY SUB-SKILL: użyj superpowers:subagent-driven-development (zalecane) albo superpowers:executing-plans, żeby wykonać plan zadanie po zadaniu. Kroki używają checkboxów (`- [ ]`).

**Cel:** Użytkownik kupuje płatny pakiet przez Stripe Checkout, administrator przypisuje pakiet tworząc subskrypcję z fakturą, a webhooki Stripe aktualizują uprawnienia w `package_assignments`.

**Architektura:** `laravel/cashier` z UUID. Jedyny punkt styku ze Stripe to interfejs `PackageBilling` (implementacja `StripePackageBilling`, w testach atrapa). Webhooki (zdarzenia zapisane w `stripe_events` dla idempotencji) rzutują subskrypcję na `package_assignments`, które zostaje tabelą uprawnień. `packages:renew` jest usuwany.

**Tech Stack:** Laravel 13, PHP 8.5, MySQL 8.4, `laravel/cashier`, Inertia v3 + Vue, Wayfinder, Pest, Pint.

**Spec:** `docs/superpowers/specs/2026-10-09-cashier-purchase-design.md`

## Global Constraints

- Klucze UUID dla wszystkich nowych tabel i kluczy obcych (także tabel Cashiera).
- Stripe tylko w trybie testowym: poza produkcją klucz `STRIPE_SECRET` musi zaczynać się od `sk_test_`, inaczej operacje Stripe są odrzucane.
- Waluta `pln`, ceny w groszach (jak w `packages`).
- Cashier odnawia okresy cykliczne; usunąć `packages:renew` razem z harmonogramem i testami.
- Pakiet domyślny (Free) i wycofane pakiety nie są sprzedawane; super admin nie kupuje.
- Aplikacja rozmawia ze Stripe wyłącznie przez `PackageBilling`; testy nie wywołują prawdziwego API.
- Konwencje projektu: Form Requests, `Gate::authorize`, `Inertia::flash('toast', …)`, Wayfinder, typy zwrotne, Pest, Pint. Kod i komunikaty UI po angielsku.
- Po zmianach PHP: `vendor/bin/pint --dirty --format agent`; po zmianie tras: `php artisan wayfinder:generate --with-form --no-interaction`.
- Zależność `laravel/cashier` dodana za zgodą użytkownika; innych zależności nie zmieniać.

## Decyzje planu (poza specem – do potwierdzenia)

- **Luka w specu – wycofanie pakietu:** archiwizacja Price nie zatrzymuje odnowień istniejących subskrypcji. Przy wycofaniu pakietu `PackageBilling::cancelAtPeriodEnd` ustawia anulowanie na koniec okresu dla każdej aktywnej subskrypcji tego pakietu; webhook `customer.subscription.deleted` kończy wtedy przypisanie, a użytkownik wraca do Free (wybór płatnego pakietu zostaje po stronie użytkownika).
- **Luka w specu – zmiana ceny:** istniejące subskrypcje zostają na starym Price (grandfathering) do zmiany pakietu w etapie 2b. Przy `invoice.paid` `price` w migawce to kwota faktury, a `link_limit` to aktualny limit pakietu, więc edycja limitu obowiązuje od odnowienia, a edycja ceny nowych kupujących.
- Usuwamy trasę `DELETE admin/users/{user}/package` i przycisk „Remove": anulowanie subskrypcji to etap 2b.
- Ponowny zakup/przypisanie przy aktywnym przypisaniu z subskrypcją jest odrzucane (zmiana w 2b); wiersze z etapu 1 bez `subscription_id` można zastąpić zakupem.
- Odpowiedź webhooka dla zdarzenia nieznanego użytkownika/pakietu: 200 z ostrzeżeniem w logu.

## Review Focus

- Powtórzone zdarzenie `invoice.paid` (ten sam `event.id`) zmienia stan dokładnie raz (Zadanie 3).
- Webhook z błędnym podpisem jest odrzucony i nic nie zapisuje (Zadanie 3).
- Zdarzenie dla nieznanego klienta Stripe lub ceny nie zwraca 500, żeby Stripe nie ponawiał w nieskończoność (Zadanie 3).
- Wycofanie pakietu z aktywnymi subskrypcjami planuje ich anulowanie na koniec okresu, a nie kończy ich natychmiast (Zadanie 2).
- Przypisanie przez admina nie daje limitu przed zapłatą, a zapłata przez Stripe (`invoice.paid`) go daje (Zadania 3, 5).
- Klucz produkcyjny `sk_live_…` w środowisku lokalnym jest odrzucony (Zadanie 1).

---

### Zadanie 1: Instalacja Cashiera z UUID i zabezpieczeniem trybu testowego

**Files:**
- Modify: `composer.json`/`composer.lock` (przez `composer require laravel/cashier`), `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `.env.example`
- Create: opublikowane i zmienione migracje Cashiera w `database/migrations/` (UUID), `app/Models/Subscription.php`, `app/Models/SubscriptionItem.php`
- Test: `tests/Feature/CashierSetupTest.php`

**Interfaces:**
- Produces:
  - `User` używa `Laravel\Cashier\Billable`.
  - `App\Models\Subscription extends Laravel\Cashier\Subscription` i `App\Models\SubscriptionItem extends Laravel\Cashier\SubscriptionItem`, oba z `HasUuids`; zarejestrowane przez `Cashier::useSubscriptionModel` / `useSubscriptionItemModel` w `AppServiceProvider::boot`.
  - Tabele `subscriptions`, `subscription_items` z UUID jako kluczem głównym, `user_id` i `subscription_id` jako `foreignUuid`; kolumny Billable (`stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`) na `users`.
  - `App\Support\StripeSandboxGuard::assertTestMode(): void` rzuca `RuntimeException`, gdy poza środowiskiem `production` `config('cashier.secret')` nie zaczyna się od `sk_test_`.
  - `.env.example`: `STRIPE_KEY=`, `STRIPE_SECRET=`, `STRIPE_WEBHOOK_SECRET=`, `CASHIER_CURRENCY=pln`.

- [ ] **Krok 1: Napisz testy** `CashierSetupTest.php`: `User::factory()->create()` ma metodę `createOrGetStripeCustomer` (trait `Billable`, `method_exists`); model `Subscription` tworzony przez fabrykę/ręcznie dostaje UUID jako `id` i zapisuje się dla użytkownika z UUID; `StripeSandboxGuard::assertTestMode()` przechodzi dla `sk_test_x`, rzuca dla `sk_live_x` w środowisku `testing`/`local`.
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Feature/CashierSetupTest.php`. Oczekiwane: FAIL.
- [ ] **Krok 3: Zainstaluj i opublikuj:** `composer require laravel/cashier`, `php artisan vendor:publish --tag=cashier-migrations`, dostosuj migracje do UUID (patrz Interfaces), zapisz konfigurację (`vendor:publish --tag=cashier-config` tylko jeśli potrzebna), dodaj trait i modele. Sprawdź w dokumentacji zainstalowanej wersji Cashiera nazwy metod `useSubscriptionModel`, `ignoreMigrations` i tras webhooka; jeśli się różnią, zapisz rulingiem w ledgerze.
- [ ] **Krok 4: Uruchom** `php artisan migrate --no-interaction` i testy. Oczekiwane: PASS.
- [ ] **Krok 5: Commit** `Install Cashier with UUID tables`.

### Zadanie 2: `PackageBilling` i synchronizacja pakietów ze Stripe

**Files:**
- Create: `app/Billing/PackageBilling.php` (interfejs), `app/Billing/StripePackageBilling.php`, `app/Billing/FakePackageBilling.php` (atrapa do testów, w `app/` lub `tests/Support`, zgodnie z konwencją projektu), migracja `add_stripe_columns_to_packages_table`
- Modify: `app/Models/Package.php`, `app/Providers/AppServiceProvider.php` (wiązanie interfejsu), `app/Http/Controllers/Admin/PackageController.php`
- Test: `tests/Feature/PackageStripeSyncTest.php`

**Interfaces:**
- Consumes: `Package`, `BillingPeriod`, `StripeSandboxGuard` (Zadanie 1).
- Produces:
  - `packages`: `stripe_product_id` i `stripe_price_id_one_time|monthly|quarterly|yearly` (string, nullable); `Package` fillable i metoda `stripePriceId(BillingPeriod): ?string`.
  - `interface PackageBilling`:
    - `syncPackage(Package $package): void` – tworzy/aktualizuje Product i Price, archiwizuje zmienione/usunięte Price, zapisuje identyfikatory;
    - `retirePackage(Package $package): void` – archiwizuje Product i Price oraz ustawia `cancelAtPeriodEnd` dla aktywnych subskrypcji tego pakietu;
    - `checkout(User $user, Package $package, BillingPeriod $period, string $successUrl, string $cancelUrl): string` – adres Stripe Checkout;
    - `invoice(User $user, Package $package, BillingPeriod $period): void` – subskrypcja lub faktura jednorazowa z wysłaniem faktury.
  - `PackageController::store`/`update` wołają `syncPackage` po zapisie; `destroy` (wycofanie i usunięcie) woła `retirePackage`; pakiet domyślny nigdy nie jest synchronizowany.

- [ ] **Krok 1: Napisz testy** `PackageStripeSyncTest.php` z `FakePackageBilling` (rejestruje wywołania): zapis nowego pakietu wywołuje `syncPackage` raz; pakiet domyślny nie jest synchronizowany; edycja ceny wywołuje `syncPackage`; wycofanie pakietu z przypisaniem i usunięcie bez przypisań wywołują `retirePackage`; błąd `syncPackage` (atrapa rzuca) cofa zapis pakietu (transakcja) i zwraca błąd walidacji zamiast 500.
- [ ] **Krok 2: Uruchom** testy. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj** interfejs, `StripePackageBilling` (przez Cashiera/Stripe SDK; zawsze wołaj `StripeSandboxGuard::assertTestMode()` na początku), migrację i zmiany kontrolera. W testach wiąż `PackageBilling` z atrapą (`$this->app->instance`). Implementacja produkcyjna jest weryfikowana ręcznie w sandboxie (Zadanie 6).
- [ ] **Krok 4: Uruchom** `php artisan test --compact tests/Feature/PackageStripeSyncTest.php tests/Feature/Admin/PackageManagementTest.php`. Oczekiwane: PASS (istniejące testy zarządzania pakietami z atrapą w `beforeEach` lub w `TestCase`).
- [ ] **Krok 5: Commit** `Sync packages with Stripe products and prices`.

### Zadanie 3: Webhooki Stripe i rzutowanie na uprawnienia

**Files:**
- Create: migracja `create_stripe_events_table` (`id` UUID, `stripe_event_id` unikalny, `type`, `processed_at`), migracja `add_subscription_id_to_package_assignments_table`, `app/Http/Controllers/StripeWebhookController.php`, `app/Billing/ProjectSubscriptionToAssignment.php` (akcja)
- Modify: `routes/web.php` (trasa `POST stripe/webhook`, wyłączenie domyślnej trasy Cashiera przez `Cashier::ignoreRoutes()` jeśli koliduje), `bootstrap/app.php` (wyjątek CSRF, jeśli potrzebny), `app/Models/PackageAssignment.php`
- Test: `tests/Feature/StripeWebhookTest.php`

**Interfaces:**
- Consumes: `Subscription`, `Package::stripePriceId`, `BillingPeriod`.
- Produces:
  - `package_assignments.subscription_id` (UUID, nullable, FK do `subscriptions`, `nullOnDelete`); `PackageAssignment::subscription(): BelongsTo`.
  - `StripeWebhookController extends Laravel\Cashier\Http\Controllers\WebhookController` z `handleInvoicePaid(array $payload)`, `handleCustomerSubscriptionUpdated(array $payload)`, `handleCustomerSubscriptionDeleted(array $payload)`, każdy przetwarzany przez wspólne sprawdzenie idempotencji po `stripe_events.stripe_event_id`.
  - `ProjectSubscriptionToAssignment::handle(User $user, Package $package, BillingPeriod $period, int $paidAmount, CarbonInterface $periodStart, CarbonInterface $periodEnd, Subscription $subscription): PackageAssignment` – `updateOrCreate` wiersza użytkownika z migawką (`link_limit` aktualnego pakietu, `price` = kwota faktury, `subscription_id`); dla `one_time` `periodEnd` = start + 1 miesiąc kalendarzowy.

- [ ] **Krok 1: Napisz testy** `StripeWebhookTest.php` (zdarzenia JSON podpisane testowym `STRIPE_WEBHOOK_SECRET` nagłówkiem `Stripe-Signature` albo wywołanie handlerów bezpośrednio, plus jeden test podpisu): `invoice.paid` dla znanego klienta i ceny tworzy przypisanie z limitem i ceną faktury; kolejny `invoice.paid` tej subskrypcji przesuwa daty i odświeża `link_limit` z bieżącego pakietu; `customer.subscription.deleted` usuwa przypisanie (użytkownik wraca do Free); to samo `event.id` dwa razy zmienia stan raz; błędny podpis → 400/403 i brak zmian; nieznany klient Stripe → 200 bez zmian; płatność jednorazowa daje start + 1 miesiąc kalendarzowy i brak odnowienia.
- [ ] **Krok 2: Uruchom** testy. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj** tabelę zdarzeń, kontroler i akcję. Mapowanie ceny Stripe na `(Package, BillingPeriod)` przez kolumny `stripe_price_id_*`; wycofany pakiet jest wyszukiwany `withTrashed()`.
- [ ] **Krok 4: Uruchom** testy webhooków i `tests/Feature/EffectivePackageTest.php`. Oczekiwane: PASS.
- [ ] **Krok 5: Commit** `Project Stripe webhooks onto package assignments`.

### Zadanie 4: Zakup przez użytkownika („Mój pakiet")

**Files:**
- Create: `app/Http/Controllers/BillingController.php`, `app/Http/Requests/Billing/CheckoutRequest.php`, `resources/js/pages/billing/Package.vue`
- Modify: `routes/web.php`, `resources/js/components/AppSidebar.vue`, `resources/js/types/package.ts`
- Test: `tests/Feature/PackagePurchaseTest.php`

**Interfaces:**
- Consumes: `PackageBilling::checkout`, `User::activeAssignment()`.
- Produces: trasy `billing.package.show` (`GET billing/package`) i `billing.package.checkout` (`POST billing/package/checkout`, pola `package_id`, `billing_period`); strona Inertia `billing/Package` z propami `assignment` (obecny pakiet, limit, `expires_at`, `awaiting_payment`) i `packages` (sprzedawane pakiety z `prices` per okres).

- [ ] **Krok 1: Napisz testy** `PackagePurchaseTest.php` (atrapa `PackageBilling`): zwykły użytkownik widzi pakiety bez domyślnego i wycofanych oraz swój obecny pakiet; `checkout` zwraca przekierowanie na adres z atrapy; okres bez ceny → błąd walidacji; wycofany/domyślny pakiet → błąd walidacji; super admin → 403; użytkownik z aktywnym przypisaniem z subskrypcją nie może kupić kolejnego (błąd, wskazówka o zmianie w etapie 2b); gość → login.
- [ ] **Krok 2: Uruchom** testy. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj** kontroler, request i trasy; `Inertia::location($url)` dla przekierowania na zewnętrzny adres. Strona Vue: karty pakietów (nazwa, limit, ceny okresów), wybór okresu, przycisk „Buy"; po powrocie komunikat o oczekiwaniu na potwierdzenie. Pozycja „My package" w sidebarze dla nie-super-adminów.
- [ ] **Krok 4: Uruchom** testy, `php artisan wayfinder:generate --with-form --no-interaction`, `npm run types:check`, `npm run build`. Oczekiwane: PASS (poza błędami typów w wygenerowanym `RedirectController.ts`).
- [ ] **Krok 5: Commit** `Add package purchase page`.

### Zadanie 5: Przypisanie przez administratora jako faktura Stripe

**Files:**
- Modify: `app/Http/Controllers/Admin/UserPackageController.php`, `app/Http/Controllers/Admin/UserController.php` (stan płatności w `presentAssignment`), `routes/web.php` (usunięcie trasy `destroy`), `resources/js/components/UserPackageCard.vue`, `resources/js/types/package.ts`, `routes/console.php`
- Delete: `app/Console/Commands/RenewPackageAssignments.php`, `tests/Feature/RenewPackageAssignmentsTest.php`
- Test: `tests/Feature/Admin/UserPackageAssignmentTest.php` (przepisanie)

**Interfaces:**
- Consumes: `PackageBilling::invoice` (Zadanie 2), `PackageAssignment::subscription` (Zadanie 3).
- Produces: `UserPackageController::update` nie zapisuje uprawnienia, tylko wywołuje `invoice` i zwraca toast „Invoice sent"; prop `packageAssignment` dostaje `payment_state` (`awaiting_payment` | `active` | `expired`) wyliczany ze stanu subskrypcji/przypisania. Usunięte: polecenie `packages:renew`, jego wpis w `routes/console.php`, trasa `admin.users.package.destroy`.

- [ ] **Krok 1: Przepisz testy** `UserPackageAssignmentTest.php` (atrapa `PackageBilling`): admin przypisujący pakiet woła `invoice` z właściwymi argumentami i nie tworzy wiersza `package_assignments`; okres bez ceny/pakiet domyślny/wycofany → błąd walidacji; tylko super admin, cel nie może być super adminem; przypisanie przy aktywnej subskrypcji odrzucone; `show` zwraca `payment_state`. Usuń testy dat i migawki z etapu 1, które nie mają już sensu; test „removing the assignment" usuń razem z trasą.
- [ ] **Krok 2: Uruchom** testy. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj** zmiany kontrolera i UI (przycisk „Assign and send invoice", wskaźnik stanu, brak „Remove"). Usuń polecenie, harmonogram i test odnowień.
- [ ] **Krok 4: Uruchom** `php artisan test --compact tests/Feature/Admin tests/Feature/AdminAccessTest.php`, `npm run types:check`, `npm run build`. Oczekiwane: PASS.
- [ ] **Krok 5: Commit** `Assign packages through Stripe invoices`.

### Zadanie 6: Weryfikacja końcowa i test sandbox

- [ ] **Krok 1:** `vendor/bin/pint --dirty --format agent` i pełny zestaw `php artisan test --compact`. Oczekiwane: PASS.
- [ ] **Krok 2:** `npx playwright test tests/e2e/links.spec.ts tests/e2e/stats.spec.ts`. Oczekiwane: PASS.
- [ ] **Krok 3 (z użytkownikiem):** poproś o testowe klucze Stripe w `.env`, uruchomienie `stripe listen --forward-to http://localhost:8000/stripe/webhook` i `php artisan migrate`; razem przejdź zakup kartą `4242 4242 4242 4242`, wysłanie faktury przez admina, odnowienie (zegar testowy Stripe) i wycofanie pakietu.

## Samoocena planu

- Pokrycie speca: Cashier z UUID i strażnik trybu testowego (Z1), synchronizacja Product/Price i `PackageBilling` (Z2), webhooki, idempotencja, rzutowanie (Z3), zakup przez użytkownika (Z4), przypisanie przez fakturę i usunięcie `packages:renew` (Z5), weryfikacja i sandbox (Z6). Poza zakresem pozostają rezygnacja, swap, Billing Portal i windykacja (2b).
- Spójność nazw: `PackageBilling::{syncPackage,retirePackage,checkout,invoice}`, `ProjectSubscriptionToAssignment::handle`, `StripeSandboxGuard::assertTestMode`, `subscription_id`, `payment_state` użyte jednolicie.
- Dwie luki w specu (wycofanie i zmiana ceny przy działających subskrypcjach) są jawnie wypisane w „Decyzjach planu".
