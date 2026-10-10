# Historia płatności – plan implementacji

> **Dla agentów wykonawczych:** WYMAGANY SUB-SKILL: użyj superpowers:subagent-driven-development (zalecane) albo superpowers:executing-plans, żeby wykonać plan zadanie po zadaniu. Kroki używają checkboxów (`- [ ]`).

**Cel:** Zastąpić okres `one_time` okresem `monthly`, a potem zapisywać faktury Stripe w tabeli `payments` i pokazywać historię płatności zgodnie z zakresem widoczności ról.

**Architektura:** Listener na `WebhookReceived` robi upsert faktur do `payments` (klucz `stripe_invoice_id`, ochrona przed starszymi zdarzeniami przez `stripe_event_at`). Komenda `payments:backfill` zasila tabelę tym samym mapperem przez `PackageBilling::invoicesFor`. Link „Pay invoice” pochodzi z tabeli, a gdy go brak przy oczekującej płatności, z fallbacku `pendingInvoiceUrl`.

**Tech Stack:** Laravel 13, PHP 8.5, MySQL 8.4, Cashier (Stripe), Inertia v3 + Vue, Wayfinder, Pest, Pint.

**Spec:** `docs/superpowers/specs/2026-10-10-payment-history-design.md`

## Global Constraints

- Klucze UUID dla wszystkich nowych tabel i kluczy obcych (`foreignUuid`).
- Stripe tylko w trybie testowym; aplikacja rozmawia ze Stripe wyłącznie przez `PackageBilling`; testy nie wywołują prawdziwego API.
- Waluta `pln`, kwoty w groszach.
- Konwencje projektu: Form Requests, `Gate::authorize`, Wayfinder, typy zwrotne, Pest, Pint. Kod i komunikaty UI po angielsku.
- Po zmianach PHP: `vendor/bin/pint --dirty --format agent`; po zmianie tras: `php artisan wayfinder:generate --with-form --no-interaction`.
- Nie zmieniamy zależności. Stare migracje zostają bez zmian; zmiany schematu robimy nowymi migracjami.
- Testy odpalamy z pustym `STRIPE_WEBHOOK_SECRET` (już wymuszone w `phpunit.xml`).

## Decyzje planu (poza specem – do potwierdzenia)

- `payments.package_id` to `foreignUuid` nullable z `nullOnDelete` (pakiety są soft-delete, ale wiersz historii ma przetrwać twarde usunięcie).
- Do tabeli trafiają faktury o statusach Stripe `open`, `paid`, `uncollectible`, `void` oraz „nieudana płatność” (`payment_failed`, wyprowadzany ze zdarzenia `invoice.payment_failed` dla faktury `open`). Szkice i `amount_due = 0` i `total = 0` są pomijane.
- Wspólne rozpoznanie ceny Stripe jako `(Package, BillingPeriod)` ląduje w `Package::findByStripePrice(string): ?array{0: Package, 1: BillingPeriod}` (statyczna metoda, uwzględnia `withTrashed()`); `ProjectStripeEvents` z niej korzysta.
- Fallback linku wywołujemy tylko, gdy `awaitingPayment` jest prawdą, a tabela nie ma `invoice_url`.

## Review Focus

- Starsze zdarzenie (mniejszy `stripe_event_at`) nie cofa statusu faktury (Zadanie 3).
- Faktura na 0 zł i szkic nie trafiają do `payments` (Zadanie 3).
- Faktura nieznanego klienta lub ceny nie zwraca 500 i nic nie zapisuje (Zadanie 3).
- Istniejące przypisania `one_time` po migracji mają `monthly` i zachowują daty (Zadanie 1).
- Użytkownik nie widzi historii cudzego konta ani linków do cudzych płatności; administrator widzi tylko klientów z `manages` (Zadanie 5).
- Konto bez oczekującej płatności nie wywołuje Stripe przy wejściu na stronę (Zadanie 5).

---

### Zadanie 1: Usunięcie okresu `one_time`

**Files:**
- Create: migracja `remove_one_time_billing_period`
- Modify: `app/Enums/BillingPeriod.php`, `app/Models/Package.php`, `app/Http/Requests/Admin/StorePackageRequest.php`, `app/Billing/PackageBilling.php` (opis), `app/Billing/StripePackageBilling.php`, `app/Billing/ProjectStripeEvents.php`, `database/factories/PackageFactory.php`, `resources/js/types/package.ts`, `resources/js/components/PackageForm.vue`, `resources/js/pages/admin/packages/Index.vue`
- Test: `tests/Feature/PackageModelTest.php`, `tests/Feature/StripeWebhookTest.php`, `tests/Feature/Admin/UserPackageAssignmentTest.php`, `tests/Feature/PackageStripeSyncTest.php`, nowy `tests/Feature/RemoveOneTimePeriodMigrationTest.php`

**Interfaces:**
- Produces:
  - `enum BillingPeriod` bez `OneTime`; zostają `Monthly`, `Quarterly`, `Yearly`; `expiresAt` bez gałęzi jednorazowej.
  - `packages` bez kolumn `price_one_time` i `stripe_price_id_one_time`; `Package::$fillable` i `@property` bez nich.
  - `PackageBilling::invoice` i `StripePackageBilling::checkout` tworzą wyłącznie subskrypcje (bez gałęzi `invoiceItems`/`invoice_creation`); `priceParameters` zawsze zwraca `recurring`.
  - `ProjectStripeEvents::applyPaidInvoice` ustawia `expires_at` zawsze z `period.end` linii faktury.

- [ ] **Krok 1: Napisz testy.** `RemoveOneTimePeriodMigrationTest`: przed uruchomieniem migracji (test wywołuje jej `up()` na danych wstawionych ręcznie przez DB) pakiet z `price_one_time = 4900` i `price_monthly = null` ma po niej `price_monthly = 4900`; pakiet z obiema cenami zachowuje starą `price_monthly`; przypisanie `one_time` ma `monthly` i niezmienione `starts_at`/`expires_at`. Zaktualizuj istniejące testy: `PackageModelTest` bez `OneTime`, `StripeWebhookTest` bez przypadku jednorazowego (zamiast niego test, że `expires_at` pochodzi z `period.end`), `UserPackageAssignmentTest` z dataset `['monthly']` i `available_periods === ['monthly']`.
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Feature/RemoveOneTimePeriodMigrationTest.php tests/Feature/PackageModelTest.php`. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj** migrację (`up`: kopiuje `price_one_time` do `price_monthly` gdzie ta jest pusta, ustawia `billing_period = 'monthly'` w `package_assignments` dla `one_time`, usuwa obie kolumny; `down` przywraca puste kolumny) oraz zmiany z bloku Interfaces. W UI usuń kolumnę i pole „One-time” oraz wpis w `billingPeriodLabels`.
- [ ] **Krok 4: Uruchom** `php artisan migrate --no-interaction`, `php artisan test --compact`, `npm run types:check`, `npm run build`. Oczekiwane: PASS.
- [ ] **Krok 5: Ponowna synchronizacja ze Stripe** (jednorazowo, ręcznie, wymaga kluczy testowych): zapisz każdy płatny pakiet w panelu albo wywołaj `syncPackage`, żeby przeniesione ceny miesięczne dostały Price. Oczekiwane: `stripe_price_id_monthly` ustawione dla pakietów z ceną miesięczną.
- [ ] **Krok 6: Commit** `Replace one-time billing period with monthly`.

### Zadanie 2: Tabela i model `payments`

**Files:**
- Create: `app/Enums/PaymentStatus.php`, `app/Models/Payment.php`, `database/factories/PaymentFactory.php`, migracja `create_payments_table`
- Modify: `app/Models/User.php` (relacja `payments`)
- Test: `tests/Feature/PaymentModelTest.php`

**Interfaces:**
- Produces:
  - `enum PaymentStatus: string` — `Open = 'open'`, `Paid = 'paid'`, `PaymentFailed = 'payment_failed'`, `Void = 'void'`, `Uncollectible = 'uncollectible'`.
  - `payments`: kolumny jak w specu (`id` UUID, `user_id` `foreignUuid` cascade, `stripe_invoice_id` unikalny, `package_id` nullable `nullOnDelete`, `billing_period` nullable, `status`, `amount`, `currency`, `invoice_url` nullable, `issued_at`, `paid_at` nullable, `stripe_event_at`, timestamps).
  - `Payment` (`HasUuids`, `HasFactory`) z castami (`status` → `PaymentStatus`, `billing_period` → `BillingPeriod`, daty); relacje `user()`, `package()` (z `withTrashed()`); `User::payments(): HasMany`.
  - `PaymentFactory` ze stanami `paid()`, `open()`.

- [ ] **Krok 1: Napisz testy** `PaymentModelTest`: fabryka tworzy wiersz z UUID; `stripe_invoice_id` jest unikalny (drugi wiersz z tym samym id rzuca `UniqueConstraintViolationException`); usunięcie użytkownika usuwa jego płatności; twarde usunięcie pakietu zeruje `package_id`; `status` i `billing_period` są rzutowane na enumy.
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Feature/PaymentModelTest.php`. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj** enum, migrację, model, fabrykę i relację według bloku Interfaces (`php artisan make:model Payment -mf --no-interaction` i dopasuj do UUID).
- [ ] **Krok 4: Uruchom** `php artisan migrate --no-interaction` i testy. Oczekiwane: PASS.
- [ ] **Krok 5: Commit** `Add payments table`.

### Zadanie 3: Zapis faktur z webhooków

**Files:**
- Create: `app/Billing/RecordInvoicePayment.php`
- Modify: `app/Models/Package.php` (`findByStripePrice`), `app/Billing/ProjectStripeEvents.php` (użycie `findByStripePrice`), `app/Providers/AppServiceProvider.php` (rejestracja listenera)
- Test: `tests/Feature/PaymentWebhookTest.php`

**Interfaces:**
- Consumes: `Payment`, `PaymentStatus`, `User` (`stripe_id`).
- Produces:
  - `Package::findByStripePrice(string $priceId): ?array` — `[Package, BillingPeriod]` albo `null`.
  - `RecordInvoicePayment::fromInvoice(array $invoice, int $eventAt): ?Payment` — mapper faktury Stripe na wiersz `payments` (upsert po `stripe_invoice_id`; pomija szkice i faktury z `total = 0`; ignoruje, gdy `$eventAt` < `stripe_event_at` wiersza; nieznanego klienta loguje ostrzeżeniem i zwraca `null`).
  - `RecordInvoicePayment::onReceived(WebhookReceived $event): void` — obsługuje `invoice.finalized`, `invoice.payment_succeeded`, `invoice.payment_failed`, `invoice.voided`, `invoice.marked_uncollectible` (`$eventAt` = `created` zdarzenia). Status: `paid` → `Paid`, `void` → `Void`, `uncollectible` → `Uncollectible`, `open` po `invoice.payment_failed` → `PaymentFailed`, w innym przypadku `Open`. `invoice_url` = `hosted_invoice_url`.

- [ ] **Krok 1: Napisz testy** `PaymentWebhookTest` (zdarzenia JSON przez `route('cashier.webhook')` jak w `StripeWebhookTest`): `invoice.finalized` tworzy wiersz `open` z `invoice_url` i pakietem/okresem z ceny; kolejne `invoice.payment_succeeded` tej faktury zmienia status na `paid` i ustawia `paid_at`; `invoice.payment_failed` daje `payment_failed`; `invoice.voided` daje `void`; zdarzenie z mniejszym `created` niż zapisane `stripe_event_at` nie zmienia wiersza; faktura z `total = 0` i szkic (`status = draft`) nie tworzą wiersza; nieznany klient → 200 i brak wiersza; ten sam `event.id` dwa razy daje jeden wiersz.
- [ ] **Krok 2: Uruchom** `php artisan test --compact tests/Feature/PaymentWebhookTest.php`. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj** `Package::findByStripePrice`, `RecordInvoicePayment` (cena z linii faktury: `price.id` albo `pricing.price_details.price`, jak w `ProjectStripeEvents`) i zarejestruj `Event::listen(WebhookReceived::class, [RecordInvoicePayment::class, 'onReceived'])`. Przepnij `ProjectStripeEvents::resolvePrice` na `findByStripePrice`.
- [ ] **Krok 4: Uruchom** testy webhooków: `php artisan test --compact tests/Feature/PaymentWebhookTest.php tests/Feature/StripeWebhookTest.php`. Oczekiwane: PASS.
- [ ] **Krok 5: Commit** `Record Stripe invoices as payments`.

### Zadanie 4: Backfill starszych faktur

**Files:**
- Create: `app/Console/Commands/BackfillPayments.php`
- Modify: `app/Billing/PackageBilling.php`, `app/Billing/StripePackageBilling.php`, `tests/Support/FakePackageBilling.php`
- Test: `tests/Feature/BackfillPaymentsCommandTest.php`

**Interfaces:**
- Consumes: `RecordInvoicePayment::fromInvoice(array $invoice, int $eventAt): ?Payment`.
- Produces:
  - `PackageBilling::invoicesFor(User $user): iterable` — faktury klienta ze Stripe jako tablice w kształcie obiektu faktury (`StripePackageBilling`: `Cashier::stripe()->invoices->all(['customer' => $user->stripe_id, 'limit' => 100])->autoPagingIterator()`, mapowane przez `toArray()`; użytkownik bez `stripe_id` → pusta lista; zawsze `StripeSandboxGuard::assertTestMode()`).
  - `FakePackageBilling::$invoices` (`array<string, list<array>>` według `user_id`).
  - Komenda `payments:backfill` wypisuje liczbę zapisanych faktur; jako `$eventAt` używa `created` faktury.

- [ ] **Krok 1: Napisz testy** `BackfillPaymentsCommandTest` (atrapa z fakturami): komenda zapisuje opłaconą i otwartą fakturę użytkownika; drugie uruchomienie nie duplikuje wierszy; użytkownik bez `stripe_id` jest pomijany; faktura na 0 zł jest pomijana; wyjście zawiera liczbę zapisanych faktur.
- [ ] **Krok 2: Uruchom** testy. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj** `invoicesFor`, atrapę i komendę (`php artisan make:command BackfillPayments --no-interaction`, sygnatura `payments:backfill`).
- [ ] **Krok 4: Uruchom** `php artisan test --compact tests/Feature/BackfillPaymentsCommandTest.php tests/Feature/PackageStripeSyncTest.php`. Oczekiwane: PASS.
- [ ] **Krok 5: Commit** `Add payments backfill command`.

### Zadanie 5: Historia i link do płatności w widokach

**Files:**
- Modify: `app/Billing/PackagePageData.php`, `app/Http/Controllers/BillingController.php`, `app/Http/Controllers/Admin/UserController.php`, `resources/js/pages/billing/Package.vue`, `resources/js/pages/admin/users/Show.vue`, `resources/js/types/package.ts`
- Create: `resources/js/components/PaymentHistory.vue`
- Test: `tests/Feature/PaymentHistoryTest.php`, aktualizacja `tests/Feature/PackagePurchaseTest.php` i `tests/Feature/Admin/UserPackageAssignmentTest.php`

**Interfaces:**
- Consumes: `Payment`, `User::payments()`, `PackageBilling::pendingInvoiceUrl`, polityka `manages`.
- Produces:
  - `PackagePageData::paymentsFor(User $user): list<array{id, issued_at, package_name, billing_period, amount, status, invoice_url}>` — ostatnie 20 wpisów, najnowsze pierwsze.
  - `PackagePageData::invoiceUrlFor(User $user): ?string` — `invoice_url` najnowszej płatności `open` z tabeli; gdy go brak, a konto czeka na płatność (otwarta płatność bez linku albo niedokończona subskrypcja), `pendingInvoiceUrl` w `try/catch` z `report()`; w pozostałych przypadkach `null` bez wywołania Stripe.
  - Prop `payments` na `billing/Package` (własna historia) i na `admin/users/Show` tylko gdy `can('manages', $user)`, inaczej `[]`; `invoiceUrl` korzysta z `invoiceUrlFor`.
  - `PaymentHistory.vue` (props `payments`) — tabela: data, pakiet i okres, kwota, status (badge), link „Pay” dla `open` / „View” dla pozostałych, gdy jest `invoice_url`.

- [ ] **Krok 1: Napisz testy** `PaymentHistoryTest`: użytkownik widzi tylko swoje płatności na `billing.package.show` (cudze nie wchodzą do propsa); administrator widzi na `admin.users.show` płatności klienta z `manages`, a klient poza zakresem daje 404; super administrator widzi płatności każdego konta; użytkownik bez uprawnień do panelu dostaje 403 na `admin.users.show`; wpisy są posortowane malejąco i ograniczone do 20; `invoiceUrl` pochodzi z tabeli bez wywołania atrapy `pendingInvoiceUrl` (atrapa notuje wywołania); przy otwartej płatności bez `invoice_url` wywoływany jest fallback; konto bez oczekującej płatności nie wywołuje Stripe; wyjątek z fallbacku daje `invoiceUrl = null` i status 200.
- [ ] **Krok 2: Uruchom** testy. Oczekiwane: FAIL.
- [ ] **Krok 3: Zaimplementuj** metody `PackagePageData`, propsy w kontrolerach oraz komponent `PaymentHistory.vue` osadzony w `billing/Package.vue` i `admin/users/Show.vue`. Uwaga: `FakePackageBilling` musi notować wywołania `pendingInvoiceUrl` (dodaj wpis do `$calls`).
- [ ] **Krok 4: Uruchom** `php artisan test --compact`, `php artisan wayfinder:generate --with-form --no-interaction`, `npm run types:check`, `npm run build`. Oczekiwane: PASS (poza błędami typów w wygenerowanym `RedirectController.ts`).
- [ ] **Krok 5: Commit** `Show payment history`.

### Zadanie 6: Weryfikacja końcowa

- [ ] **Krok 1:** `vendor/bin/pint --dirty --format agent` i pełny zestaw `php artisan test --compact`. Oczekiwane: PASS.
- [ ] **Krok 2:** `npx playwright test tests/e2e/links.spec.ts tests/e2e/stats.spec.ts`. Oczekiwane: PASS.
- [ ] **Krok 3 (z użytkownikiem):** z włączonym `stripe listen` uruchom `php artisan payments:backfill`, przypisz pakiet kontu, opłać fakturę i sprawdź historię jako użytkownik, administrator i super administrator.

## Samoocena planu

- Pokrycie speca: zmiana wstępna (Z1), model danych (Z2), webhooki i idempotencja po czasie zdarzenia (Z3), backfill (Z4), widoki, zakres ról i fallback linku (Z5), weryfikacja (Z6). Poza zakresem: zwroty, windykacja, PDF.
- Spójność nazw: `RecordInvoicePayment::fromInvoice`, `Package::findByStripePrice`, `PackageBilling::invoicesFor`, `PackagePageData::{paymentsFor,invoiceUrlFor}`, `PaymentStatus`, `stripe_event_at` użyte jednolicie.
- Zadanie 1 zmienia dane produkcyjne (przypisania i ceny); jest odwracalne tylko częściowo (`down` przywraca puste kolumny, nie wartości).
