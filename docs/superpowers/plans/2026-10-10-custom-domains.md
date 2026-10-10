# Customowe domeny dla linków — plan implementacji

> **Dla wykonawców:** WYMAGANA UMIEJĘTNOŚĆ: superpowers:subagent-driven-development (zalecane) lub superpowers:executing-plans. Kroki używają checkboxów (`- [ ]`).

**Cel:** Użytkownik podpina własną domenę (po weryfikacji DNS) i tworzy na niej linki; limit domen wynika z pakietu (Free = 0).

**Architektura:** Nowy model `Domain` (UUID) z `verified_at` i blokadą admina. `links.domain_id` (nullable) wskazuje domenę. `RedirectLinkController` wybiera domenę po hoście żądania. Limit `domain_limit` leży w `packages` i jest kopiowany do `package_assignments`, jak `link_limit`. Weryfikację robi wstrzykiwany `DomainVerifier` (DNS TXT), podmieniany w testach.

**Stack:** Laravel (PHP 8.5), Inertia v3 + Vue, Wayfinder, Pest, MySQL.

**Spec:** `docs/superpowers/specs/2026-10-10-custom-domains-design.md`

## Ograniczenia globalne

- Nowe tabele i FK: UUID (`HasUuids`, `foreignUuid`).
- Teksty UI i komunikaty przez `__()`; flash przez `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])`.
- Trasy tylko przez nazwane trasy i Wayfinder (`@/actions/...`), bez twardych URL-i.
- Nie zmieniamy zależności (`composer.json`, `package.json`) i nie tworzymy nowych katalogów bazowych. Nowe klasy idą do `app/Support`, `app/Jobs`, `app/Policies`, `app/Http/...`.
- Po zmianach PHP: `vendor/bin/pint --dirty --format agent`. Testy: `php artisan test --compact <plik>`. Pełny zestaw uruchamia użytkownik.
- Katalog roboczy ma niezacommitowane zmiany niezwiązane z tym planem (billing, `ProjectStripeEvents.php`, `PackageOverview.vue`, `routes/web.php`, `Package.vue` i inne). Commituj tylko pliki tego zadania, a w plikach już zmodyfikowanych stage'uj wyłącznie własne hunki (`git add -p`).
- Treść wiadomości commitów: angielski, tryb rozkazujący, bez prefiksów; na końcu `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Review Focus

- Hosty w różnej wielkości liter i z portem (`Example.COM`, `example.com:8080`): host zapisany małymi literami, port odrzucony.
- Domena głównej aplikacji (host z `APP_URL`) nie może być dodana jako domena użytkownika.
- Ten sam `code` na domenie głównej i na domenie użytkownika działa niezależnie; dwa linki z tym samym kodem na domenie głównej są niemożliwe (także przy `domain_id = NULL`).
- Domena zablokowana lub niezweryfikowana nie przekierowuje i nie jest wybieralna w formularzu linku.
- Zablokowany użytkownik: jego domeny nie działają.
- Wygasły pakiet (limit spada): istniejące domeny działają, nowej nie da się dodać.
- Admin spoza zakresu (`manages`) nie dodaje, nie usuwa ani nie blokuje cudzych domen.
- Link nie może wskazać domeny innego użytkownika (także przy tworzeniu przez admina w imieniu użytkownika).

---

### Zadanie 1: Dane i limit domen

**Pliki:**
- Utwórz: migracje (`php artisan make:migration`): `create_domains_table`, `add_domain_id_to_links_table`, `add_domain_limit_to_packages_and_assignments`
- Utwórz: `app/Models/Domain.php`, `database/factories/DomainFactory.php`
- Zmień: `app/Models/User.php`, `app/Models/Link.php`, `app/Models/Package.php`, `app/Models/PackageAssignment.php`, `database/factories/PackageFactory.php`
- Test: `tests/Feature/DomainLimitTest.php`

**Interfejsy:**
- Produkuje: `Domain` z polami `id, user_id, host, verification_token, verified_at, disabled_by_admin_at, disabled_by`, relacje `user(): BelongsTo`, `links(): HasMany`, `disabledBy(): BelongsTo`, scope `active()` (zweryfikowana, niezablokowana przez admina, właściciel bez `blocked_at`), `isActive(): bool`, `isVerified(): bool`.
- Produkuje: `DomainFactory` ze stanami `verified()` i `disabledByAdmin()`; domyślnie niezweryfikowana, `host` małymi literami, `verification_token` = `Str::random(40)`.
- Produkuje: `User::domains(): HasMany`, `User::domainLimit(): ?int` (super admin `null`; w pozostałych przypadkach `activeAssignment()?->domain_limit`, a bez przypisania `domain_limit` pakietu domyślnego, a bez niego `0`), `User::canAddDomain(): bool`.
- Produkuje: `Link::domain(): BelongsTo`; `packages.domain_limit` i `package_assignments.domain_limit` (unsigned int, domyślnie 0); `domain_limit` w `$fillable`/dokumentacji `Package` i `PackageAssignment`; `PackageFactory` ma `domain_limit => 0`.

- [ ] **Krok 1: Test limitu** w `DomainLimitTest.php` (pomocnik: użytkownik z `PackageAssignment` o `domain_limit` N):
  - `Free user has a domain limit of zero` (`domainLimit()` = 0, `canAddDomain()` = false, gdy pakiet domyślny ma 0)
  - `assignment snapshot wins over later package edits` (zmiana `Package::domain_limit` nie zmienia `User::domainLimit()` z aktywnego przypisania)
  - `user can add domains up to the limit` (limit 2: `canAddDomain()` true, true po jednej, false po dwóch)
  - `super admin has no domain limit` (`null`, `canAddDomain()` true)
  - `lowering the limit keeps existing domains` (limit 1, dwie domeny w bazie: obie nadal istnieją, `canAddDomain()` false)
  - `a user's domain scope excludes unverified, disabled and blocked-owner domains` (`Domain::active()`)
- [ ] **Krok 2: Uruchom — oczekiwane FAIL** (brak klas/kolumn): `php artisan test --compact tests/Feature/DomainLimitTest.php`
- [ ] **Krok 3: Migracje.** `domains` jak w specu (`host` unique, `foreignUuid('user_id')->cascadeOnDelete()`, `foreignUuid('disabled_by')->nullable()` jak w migracji `add_disabled_by_to_links_table`). `links.domain_id`: `foreignUuid` nullable z `cascadeOnDelete` (usunięcie domeny z linkami blokuje polityka, kaskada działa tylko przy usunięciu użytkownika). Unikalność kodu: usuń `links_code_unique`, dodaj wirtualną kolumnę `domain_key` = `IFNULL(domain_id, '')` (`virtualAs`) i unikalny indeks (`domain_key`, `code`), zachowując kolację `utf8mb4_bin` kodu. Uruchom migracje. Jeśli MySQL odrzuci kolumnę wirtualną razem z FK, użyj `storedAs` z `restrictOnDelete` i usuwaj linki domeny przed jej usunięciem w kodzie użytkownika, a jeśli i to zawiedzie, zgłoś problem zamiast zmieniać projekt.
- [ ] **Krok 4: Modele i fabryki** jak w Interfejsach. `Domain` ma `#[Fillable(['host', 'verification_token'])]`, bez fillable dla `verified_at` i pól blokady (ustawiane przez `forceFill`).
- [ ] **Krok 5: Uruchom — oczekiwane PASS**; uruchom też `tests/Feature/LinkModelTest.php tests/Feature/LinkCodeGeneratorTest.php tests/Feature/LinkRedirectTest.php` (unikalność kodu nadal działa).
- [ ] **Krok 6: Commit** `Add domains table and package domain limit`

### Zadanie 2: Limit w pakietach (super admin) i migawka przypisania

**Pliki:**
- Zmień: `app/Http/Requests/Admin/StorePackageRequest.php`, `app/Http/Requests/Admin/UpdatePackageRequest.php` (jeśli ma własne reguły), `app/Billing/ProjectStripeEvents.php:82`, miejsca tworzące `PackageAssignment` (`grep -rn "'link_limit' =>" app`), `resources/js/components/PackageForm.vue`, `resources/js/pages/admin/packages/Index.vue`, `resources/js/types/package.ts`, `app/Billing/PackagePageData.php`, `resources/js/components/PackageOverview.vue`
- Test: `tests/Feature/Admin/PackageDomainLimitTest.php`

**Interfejsy:**
- Konsumuje: `Package::domain_limit`, `PackageAssignment::domain_limit` (Zadanie 1).
- Produkuje: reguła `domain_limit` `required|integer|min:0|max:4294967295` w obu requestach pakietu; migawka `domain_limit` przy każdym tworzeniu `PackageAssignment`; w propsach strony pakietu `domain_limit` i `domains_used`, a w `PackageOverview` linia „Domeny: x / y".

- [ ] **Krok 1: Test** `PackageDomainLimitTest.php`:
  - `super admin stores a package with a domain limit` (`admin.packages.store` zapisuje `domain_limit`)
  - `super admin updates the domain limit`
  - `a regular admin cannot create or update packages` (403, limit bez zmian)
  - `domain_limit is required and must be a non-negative integer` (błąd walidacji)
  - `a purchased package snapshots its domain limit` (po przetworzeniu zdarzenia Stripe/przypisaniu `PackageAssignment::domain_limit` = limit pakietu; wzoruj się na `tests/Feature/PackageSnapshotTest.php`)
- [ ] **Krok 2: Uruchom — oczekiwane FAIL.**
- [ ] **Krok 3: Implementacja** reguł, migawki w każdym miejscu tworzącym przypisanie, pola „Domain limit" w `PackageForm.vue` (domyślnie 0), kolumny w `Index.vue`, typów TS i linii w `PackageOverview.vue`/`PackagePageData` (wzoruj na `link_limit`).
- [ ] **Krok 4: Uruchom** nowy test oraz `tests/Feature/PackageSnapshotTest.php tests/Feature/PackageModelTest.php tests/Feature/StripeWebhookTest.php tests/Feature/PackagePurchaseTest.php` — PASS.
- [ ] **Krok 5: Commit** `Let super admins set a domain limit on packages`

### Zadanie 3: Weryfikacja DNS

**Pliki:**
- Utwórz: `app/Support/DomainVerifier.php` (interfejs), `app/Support/DnsDomainVerifier.php`, `app/Jobs/VerifyDomain.php`, `config/shortify.php`, `tests/Support/FakeDomainVerifier.php`
- Zmień: `app/Providers/AppServiceProvider.php` (bind), `tests/TestCase.php`, `.env.example`
- Test: `tests/Feature/DomainVerificationTest.php`

**Interfejsy:**
- Produkuje: `interface DomainVerifier { public function hasToken(Domain $domain): bool; }` (rekord TXT `_shortify-verify.<host>` o wartości `shortify-verify=<verification_token>`); `DnsDomainVerifier` używa `dns_get_record(..., DNS_TXT)` i zwraca `false` przy błędzie/braku rekordu; w `config/shortify.php`: `cname_target` (`env('SHORTIFY_CNAME_TARGET')`, domyślnie host z `app.url`), `domain_check_secret` (`env('SHORTIFY_DOMAIN_CHECK_SECRET')`).
- Produkuje: `Domain::markVerified(): void`; `VerifyDomain::__construct(public string $domainId)`; `handle(DomainVerifier $verifier): void` — pomija usuniętą, zweryfikowaną lub starszą niż 72 godziny od `created_at` domenę; po sukcesie ustawia `verified_at`; po porażce dispatchuje siebie z opóźnieniem 5 minut.
- Produkuje: `FakeDomainVerifier` (`public array $verified = []` — id domen uznanych za zweryfikowane; `hasToken` sprawdza wpis w tablicy), dostępny w testach jako `$this->verifier`, zbindowany w `TestCase::setUp` jak `PackageBilling`.

- [ ] **Krok 1: Test** `DomainVerificationTest.php`:
  - `job verifies a domain whose token is present` (`verified_at` ustawione)
  - `job requeues itself with a five minute delay while the token is missing` (`Queue::fake()`, `Queue::assertPushed(VerifyDomain::class)`)
  - `job gives up on domains older than 72 hours` (`created_at` 73 h temu: brak weryfikacji i brak nowego zadania)
  - `job ignores verified and deleted domains`
  - `DnsDomainVerifier matches only the exact TXT value` (test jednostkowy na wydzielonej metodzie przyjmującej listę rekordów, np. `matches(array $records, Domain $domain): bool`, bez realnego DNS)
- [ ] **Krok 2: Uruchom — FAIL.**
- [ ] **Krok 3: Implementacja** jak w Interfejsach; `AppServiceProvider::register` wiąże `DomainVerifier` z `DnsDomainVerifier`.
- [ ] **Krok 4: Uruchom — PASS.**
- [ ] **Krok 5: Commit** `Verify domain ownership through a DNS TXT record`

### Zadanie 4: Domeny użytkownika (backend i strona)

**Pliki:**
- Utwórz: `app/Policies/DomainPolicy.php`, `app/Http/Requests/Domains/StoreDomainRequest.php`, `app/Http/Controllers/DomainController.php`, `app/Http/Controllers/DomainVerifyController.php`, `resources/js/pages/domains/Index.vue`
- Zmień: `routes/web.php`, `resources/js/components/AppSidebar.vue` (pozycja menu, wzoruj na „Links")
- Test: `tests/Feature/DomainManagementTest.php`

**Interfejsy:**
- Konsumuje: `User::canAddDomain()`, `Domain`, `VerifyDomain`, `config('shortify.cname_target')`.
- Produkuje: trasy w grupie `auth, verified`: `domains.index` (GET), `domains.store` (POST), `domains.destroy` (DELETE `domains/{domain}`), `domains.verify` (POST `domains/{domain}/verify`).
- Produkuje: `DomainPolicy::create(User): Response|bool` (`canAddDomain()` lub `Response::deny(__('Domain limit reached for your package.'))`), `delete(User, Domain): bool` (właściciel i brak linków domeny), `verify(User, Domain): bool` (właściciel).
- Produkuje: `StoreDomainRequest` — `host`: `required|string|max:253`, normalizacja do małych liter i bez końcowej kropki w `prepareForValidation`, format nazwy domeny (regex FQDN bez portu, schematu i ścieżki), `unique:domains,host`, różny od hosta z `config('app.url')`; `authorize()` przez `Gate` `create` na `Domain::class`.
- Produkuje: `DomainController::store` tworzy domenę z `verification_token = Str::random(40)` i dispatchuje `VerifyDomain`; `index` przekazuje `domains` (id, host, status: `pending|active|disabled`, `verification_token`), `quota` (`used`, `limit`) i `cnameTarget`.

- [ ] **Krok 1: Test** `DomainManagementTest.php`:
  - `user adds a domain within the limit and the verification job is queued`
  - `host is lower-cased and a trailing dot is removed`
  - `invalid hosts are rejected` (dataset: port, schemat, ścieżka, spacja, `localhost`, pusty)
  - `the application host cannot be added`
  - `duplicate host is rejected, even for another user`
  - `Free user cannot add a domain` (403 z komunikatem o limicie)
  - `user at the limit cannot add another domain`
  - `owner verifies a domain manually` (z `$this->verifier->verified = [$domain->id]` → `verified_at` ustawione; bez tokenu → pozostaje niezweryfikowana, sesja z błędem)
  - `cannot verify or delete another user's domain` (403)
  - `a domain with links cannot be deleted; one without can`
  - `index lists only own domains with status and quota`
- [ ] **Krok 2: Uruchom — FAIL.**
- [ ] **Krok 3: Implementacja** backendu; policy rejestrowana jak pozostałe (auto-discovery lub `Gate::policy`, sprawdź konwencję). Strona `domains/Index.vue`: tabela ze statusem, instrukcja DNS (TXT i CNAME z `cnameTarget`), przyciski „Sprawdź ponownie" i „Usuń", formularz dodawania; przy limicie 0 komunikat z linkiem do `billing.package.show` zamiast formularza; wzoruj na `links/Index.vue` i `LinkForm.vue`. Wygeneruj Wayfinder (`php artisan wayfinder:generate` lub przez Vite).
- [ ] **Krok 4: Uruchom — PASS**; `npm run build` bez błędów typów.
- [ ] **Krok 5: Commit** `Let users manage their custom domains`

### Zadanie 5: Routing po hoście i linki na domenach

**Pliki:**
- Zmień: `app/Http/Controllers/RedirectLinkController.php`, `app/Models/Link.php` (scope `active()`, `shortUrl`), `app/Support/LinkCodeGenerator.php`, `app/Http/Requests/Links/StoreLinkRequest.php`, `app/Http/Requests/Admin/StoreUserLinkRequest.php`, `app/Http/Controllers/LinkController.php`, `app/Http/Controllers/Admin/UserLinkController.php`, `resources/js/components/LinkForm.vue`, `resources/js/pages/links/Create.vue`, `resources/js/pages/admin/users/links/Create.vue`, `resources/js/pages/links/Index.vue` (kolumna/pokazanie pełnego `short_url` jest już w danych)
- Test: `tests/Feature/DomainRedirectTest.php`, `tests/Feature/DomainLinkCreationTest.php`

**Interfejsy:**
- Konsumuje: `Domain::active()`, `Link::domain()`, `Link.domain_id`.
- Produkuje: `LinkCodeGenerator::generate(?string $domainId = null): string` (sprawdza istnienie kodu tylko w obrębie `domain_id`; `null` = domena główna) i `createFor(User $user, array $attributes)` używa `$attributes['domain_id'] ?? null`; wynik tej metody nadal ponawia próbę po `UniqueConstraintViolationException`.
- Produkuje: `Link::short_url` = schemat z `APP_URL` + host domeny (gdy `domain_id` ustawione, inaczej jak dotąd); `Link::active()` dodatkowo wymaga `domain_id IS NULL` albo aktywnej domeny.
- Produkuje: `RedirectLinkController::__invoke(Request $request, string $code)`: host równy hostowi z `config('app.url')` → `Link::active()->whereNull('domain_id')`; inny host → `Domain::active()->where('host', $request->getHost())->firstOrFail()`, potem `Link::active()->where('domain_id', $domain->id)`; brak → 404.
- Produkuje: pole `domain_id` w `StoreLinkRequest`: `nullable|uuid`, wartość musi być aktywną domeną właściciela linku (`Rule::exists('domains', 'id')` z warunkami `user_id`, `verified_at` niepuste, `disabled_by_admin_at` puste); właściciel to `$this->user()` w `StoreLinkRequest` i `$this->route('user')` w `StoreUserLinkRequest` (wydziel metodę `linkOwner(): User` nadpisywaną w potomku). `UpdateLinkRequest` nie zmienia `domain_id` (kod i domena linku są niezmienne).
- Produkuje: propsy `domains` (id, host — tylko aktywne domeny właściciela) w `links/Create` i `admin/users/links/Create`; `LinkForm.vue` wybór domeny (domyślnie „domena główna").

- [ ] **Krok 1: Testy** `DomainRedirectTest.php` (host symulowany `$this->get('http://'.$host.'/'.$code)`):
  - `main host serves only links without a domain`
  - `a verified domain serves its own links`
  - `the same code works independently on the main host and on a user domain`
  - `unverified, admin-disabled and blocked-owner domains return 404`
  - `unknown host returns 404`
  - `a link on a domain stops redirecting when its domain is disabled`
  - `short_url uses the domain host`
  - `host matching ignores case` (`Example.COM`)
  i `DomainLinkCreationTest.php`:
  - `user creates a link on their active domain` (generator zapisuje `domain_id`, `short_url` ma host domeny)
  - `user cannot use someone else's, unverified or disabled domain` (błąd walidacji `domain_id`)
  - `admin creating a link for a user can only pick that user's active domains`
  - `generator allows the same code on two domains but not twice on one` (w tym domena główna; naruszenie unikalności DB przy `domain_id = NULL`)
  - `domain link creation still respects the link limit`
- [ ] **Krok 2: Uruchom — FAIL.**
- [ ] **Krok 3: Implementacja** jak w Interfejsach; `LinkController::create` i `UserLinkController::create` przekazują `domains`.
- [ ] **Krok 4: Uruchom** nowe testy oraz `tests/Feature/LinkRedirectTest.php tests/Feature/LinkCrudTest.php tests/Feature/LinkLimitTest.php tests/Feature/LinkScopeTest.php tests/Feature/LinkCodeGeneratorTest.php tests/Feature/RecordLinkClickTest.php` — PASS; `npm run build`.
- [ ] **Krok 5: Commit** `Serve short links on verified custom domains`

### Zadanie 6: Endpoint dla proxy

**Pliki:**
- Utwórz: `app/Http/Controllers/DomainCheckController.php`
- Zmień: `routes/web.php` (przed trasą `{code}`; bez grupy `auth`)
- Test: `tests/Feature/DomainCheckTest.php`

**Interfejsy:**
- Konsumuje: `Domain::active()`, `config('shortify.domain_check_secret')`.
- Produkuje: trasa `domains.check` — `GET internal/domain-check?domain=<host>`; nagłówek `X-Domain-Check-Secret` porównywany przez `hash_equals`; brak/niepoprawny sekret lub nieustawiony sekret w konfiguracji → 403; aktywna domena → 200; inna → 404.

- [ ] **Krok 1: Test** `DomainCheckTest.php`: `active domain returns 200`, `unknown, unverified and disabled domains return 404`, `missing or wrong secret returns 403`, `an unset secret rejects every request` (także pusty nagłówek), `host is matched case-insensitively`.
- [ ] **Krok 2: Uruchom — FAIL.**
- [ ] **Krok 3: Implementacja** kontrolera i trasy.
- [ ] **Krok 4: Uruchom — PASS.**
- [ ] **Krok 5: Commit** `Add domain check endpoint for the TLS proxy`

### Zadanie 7: Panel admina

**Pliki:**
- Utwórz: `app/Http/Controllers/Admin/UserDomainController.php`, `app/Http/Controllers/Admin/DomainDisableController.php`, `app/Http/Requests/Admin/StoreUserDomainRequest.php`
- Zmień: `app/Policies/UserPolicy.php`, `app/Providers/AppServiceProvider.php` (`scopeAdminRouteBindings`: dodaj `'domain'`), `app/Models/Domain.php` (scope `visibleTo(User $actor)` jak w `Link`), `app/Http/Controllers/Admin/UserController.php` (`show`), `resources/js/pages/admin/users/Show.vue`, `routes/web.php`
- Test: `tests/Feature/Admin/UserDomainTest.php`

**Interfejsy:**
- Konsumuje: `StoreDomainRequest` reguły hosta (Zadanie 4), `DomainVerifier`/`VerifyDomain`.
- Produkuje: `UserPolicy::manageDomains(User $actor, User $target): bool` (`outranks` i `manages`, jak `block`) oraz `UserPolicy::createDomain(User $actor, User $target): Response|bool` (`manageDomains` i `canAddDomain()` celu, komunikat o limicie jak w `createLink`).
- Produkuje: trasy w grupie `admin`: `admin.users.domains.store` (POST `users/{user}/domains`), `admin.domains.destroy` (DELETE `domains/{domain}`), `admin.domains.disable` (PATCH `domains/{domain}/disable`), `admin.domains.enable` (DELETE `domains/{domain}/disable`); odpowiedzi jak `LinkDisableController` (`back()` z toastem); usunięcie wymaga braku linków domeny.
- Produkuje: prop `domains` w `admin/users/Show` (id, host, status, `disabled_by_name`) tylko gdy `can('manages', $user)`, oraz `domainLimit`; sekcja w `Show.vue` z formularzem dodawania, usuwaniem i blokowaniem.

- [ ] **Krok 1: Test** `UserDomainTest.php`:
  - `admin adds a domain for a managed user within the limit` (zadanie `VerifyDomain` w kolejce)
  - `admin cannot add a domain beyond the user's limit`
  - `admin outside the user's scope gets 404 or 403 for add, delete, disable` (zgodnie z istniejącą konwencją `scopeAdminRouteBindings`)
  - `super admin can manage any user's domains`
  - `admin disables and enables a domain, setting disabled_by` (domena przestaje przekierowywać)
  - `admin cannot delete a domain that has links`
  - `a regular user cannot reach the admin domain routes` (403)
  - `show lists the user's domains only for admins who manage them`
- [ ] **Krok 2: Uruchom — FAIL.**
- [ ] **Krok 3: Implementacja** kontrolerów, polityk, tras i UI (wzoruj na `UserLinkController`, `LinkDisableController` i sekcji linków w `Show.vue`).
- [ ] **Krok 4: Uruchom** nowy test oraz `tests/Feature/Admin` — PASS; `npm run build`.
- [ ] **Krok 5: Commit** `Let admins manage and disable user domains`

### Zadanie 8: Weryfikacja końcowa

- [ ] **Krok 1:** `vendor/bin/pint --dirty --format agent`, `npm run build` (typy TS), `php artisan route:list --name=domain` — brak kolizji z trasą `{code}`.
- [ ] **Krok 2:** `php artisan test --compact` dla wszystkich plików z zadań 1–7 i dla `tests/Feature/Admin`. Pełny zestaw uruchamia użytkownik.
- [ ] **Krok 3:** Zacommituj formatowanie, jeśli Pint coś zmienił (`Format custom domains code`).
