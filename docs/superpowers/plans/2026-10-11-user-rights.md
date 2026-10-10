# Prawa użytkownika i zgody — plan implementacji

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rejestr zgód powiązany z wersjami dokumentów CMS (wymuszany po zalogowaniu i przy rejestracji), eksport danych konta jako ZIP z CSV oraz usuwanie konta razem z klientem Stripe.

**Architecture:** `ConsentDocuments` jest jedynym źródłem wiedzy o wymaganych dokumentach i zgodach użytkownika; middleware `EnsureConsentsAccepted` (globalnie w grupie `web`, z wyjątkami po nazwie trasy) kieruje na ekran akceptacji. Eksport składa `AccountExport` (zbiory wierszy) i `CsvWriter` (zabezpieczone komórki) w ZIP. Usuwanie przechodzi przez akcję `DeleteUserAccount`, która najpierw woła `PackageBilling::deleteCustomer()`.

**Tech Stack:** Laravel, Fortify, Inertia + Vue, Pest, PHP `ZipArchive`.

**Spec:** `docs/superpowers/specs/2026-10-11-user-rights-design.md`

## Global Constraints

- Wymagane dokumenty: slugi `terms` i `privacy-policy` (stała `ConsentDocuments::SLUGS`); pomijane, gdy strona nie jest opublikowana lub nie ma wersji.
- `user_consents`: UUID PK; `user_id` FK cascade; `page_version_id` FK restrict; `accepted_at`; unikalny `(user_id, page_version_id)`; bez IP i User-Agent.
- Wyjątki middleware (nazwy tras): `consent.show`, `consent.store`, `content.show`, `logout`, `privacy.export`, `profile.destroy`, `verification.*`. Trasy `links.redirect` i `domains.check` wyłączone z tego middleware (bez sesji).
- Eksport: `GET settings/privacy/export`, nazwa trasy `privacy.export`, plik `shortify-export-YYYY-MM-DD.zip` z `profile.csv`, `links.csv`, `domains.csv`, `payments.csv`, `consents.csv` (UTF-8, `,`, nagłówki angielskie); komórki zaczynające się od `=`, `+`, `-`, `@`, tabulatora lub CR poprzedzane `'`. Bez statystyk odwiedzających.
- Usuwanie: błąd Stripe zostawia konto i dane nietknięte; komunikat "Could not delete the account right now, try again later".
- Konwencje repo: UUID, cienkie kontrolery, logika w `app/Actions` i `app/Support`, Form Requests, teksty UI po angielsku, Pest feature tests, `vendor/bin/pint --dirty --format agent` po zmianach PHP, `npx vp fmt` tylko na własnych plikach Vue/TS.

## Review Focus

- Użytkownik bez zgody nadal może wylogować się, pobrać dane i usunąć konto (trasy z wyjątków); zwykłe żądania Inertia dostają przekierowanie 303, a żądania JSON 409.
- Niepełna lista: tylko `terms` opublikowane, `privacy-policy` w szkicu; nowa wersja jednego dokumentu wymusza ponowną akceptację tylko tego dokumentu; podwójne kliknięcie "Accept" nie tworzy duplikatów ani błędu.
- Klient w Stripe już usunięty (`resource_missing`) nie blokuje usunięcia konta; inny błąd Stripe zatrzymuje je przed kasowaniem czegokolwiek.
- Eksport zawiera wyłącznie dane zalogowanego użytkownika (drugi użytkownik z podobnymi danymi nie przecieka); tytuł linku zaczynający się od `=HYPERLINK(...)` jest zabezpieczony; konto bez linków, domen i płatności daje poprawne ZIP-y z samymi nagłówkami.
- Usunięcie konta kasuje także jego zgody (kaskada), ale nie wersje dokumentów.
- Rejestracja bez żadnego opublikowanego dokumentu działa bez checkboxa.

---

### Task 1: Rejestr zgód i ConsentDocuments

**Files:**
- Create: migracja `create_user_consents_table` (`php artisan make:migration`), `app/Models/UserConsent.php`, `database/factories/UserConsentFactory.php`, `app/Support/ConsentDocuments.php`
- Modify: `app/Models/User.php` (relacja `consents()`)
- Test: `tests/Feature/ConsentDocumentsTest.php`

**Interfaces:**
- Produces: `UserConsent` (`user_id`, `page_version_id`, `accepted_at`; relacje `user()`, `pageVersion()`); `User::consents(): HasMany`; `ConsentDocuments::SLUGS` (`['terms', 'privacy-policy']`); `ConsentDocuments::current(): Collection<int, PageVersion>`; `ConsentDocuments::pendingFor(User $user): Collection<int, PageVersion>`; `ConsentDocuments::accept(User $user): void`.

- [ ] **Step 1: Padające testy** w `ConsentDocumentsTest.php` (helper tworzy opublikowaną stronę ze slugiem i wersją przez `SavePageVersion`):
  - `current returns the current versions of published required pages only` (strona w szkicu, strona bez wersji i strona spoza listy są pominięte),
  - `pending lists current versions the user has not accepted`,
  - `accept stores consents for every current version and is idempotent` (dwa wywołania, dwa wpisy),
  - `a new version of a document makes only that document pending again`,
  - `consents are removed with the user but the page versions stay`,
  - `a version with consents cannot be deleted` (`UserConsent` + próba usunięcia `PageVersion` rzuca wyjątek klucza obcego).
- [ ] **Step 2:** `php artisan test --compact tests/Feature/ConsentDocumentsTest.php`; oczekiwane: FAIL.
- [ ] **Step 3:** Zaimplementuj migrację, model, fabrykę, relację i `ConsentDocuments` (`accept` przez `insertOrIgnore` z generowanym UUID).
- [ ] **Step 4:** `php artisan migrate`, uruchom test; oczekiwane: PASS.
- [ ] **Step 5: Commit**: `Add user consents tied to document versions`.

### Task 2: Wymuszenie zgód po zalogowaniu

**Files:**
- Create: `app/Http/Middleware/EnsureConsentsAccepted.php`, `app/Http/Controllers/ConsentController.php`, `resources/js/pages/consent/Show.vue`
- Modify: `bootstrap/app.php` (dopisanie do grupy `web`), `routes/web.php` (trasy `consent.*`, wyłączenie middleware z `links.redirect` i `domains.check`)
- Test: `tests/Feature/ConsentEnforcementTest.php`

**Interfaces:**
- Consumes: `ConsentDocuments` (Task 1).
- Produces: trasy `consent.show` (`GET consent`) i `consent.store` (`POST consent`, tylko `auth`); komponent `consent/Show` z propsem `documents: list<{id: string, title: string, slug: string}>`.

- [ ] **Step 1: Padające testy** w `ConsentEnforcementTest.php`:
  - `a user with pending documents is redirected to the consent page` (GET `dashboard` → `consent.show`),
  - `a json request gets 409` (`getJson('/dashboard')`),
  - `after accepting the user reaches the dashboard` (POST `consent.store` → wpisy zgód, potem `dashboard` 200),
  - `a new version of a document asks again`,
  - `a user without published documents is not blocked`,
  - `a draft document does not block`,
  - `exempt routes work without consent` (dataset: wylogowanie, `privacy.export` (tutaj tylko sprawdza brak przekierowania na zgodę, może zwrócić 404 do Task 5), `profile.destroy`),
  - `guests and the short link redirect are unaffected` (`links.redirect` z użytkownikiem z recaller cookie bez zgody nadal 302),
  - `the consent page lists pending documents` (props `documents`).
- [ ] **Step 2:** Uruchom; oczekiwane: FAIL.
- [ ] **Step 3:** Zaimplementuj middleware (`$request->user()` null → dalej; `routeIs` wyjątków → dalej; `pendingFor` puste → dalej; inaczej `expectsJson()` ? `abort(409)` : `redirect()->route('consent.show', status: 303)`), kontroler (`show` renderuje `consent/Show`, `store` woła `accept` i `redirect()->intended(route('dashboard'))`; gdy nic nie czeka, `show` przekierowuje na dashboard), stronę Vue (lista dokumentów z linkami `content.show` w nowej karcie, przycisk "Accept and continue", `Form` do `consent.store`; layout jak strony `auth/` przez wyjątek w `resources/js/app.ts` jeśli potrzebny) i dopisz middleware do `web` po `EnsureAccountIsNotBlocked`. Dodaj `EnsureConsentsAccepted::class` do `withoutMiddleware` tras `links.redirect` i `domains.check`.
- [ ] **Step 4:** Uruchom `ConsentEnforcementTest`, `AuthRedirectTest`, `AccountBlockingTest`, `VisitorPrivacyTest`, `DomainCheckTest`, `DashboardTest`; oczekiwane: PASS (pozostałe testy działają, bo bez opublikowanych dokumentów nic nie blokuje).
- [ ] **Step 5: Commit**: `Require consent to the current documents after login`.

### Task 3: Zgoda przy rejestracji

**Files:**
- Modify: `app/Actions/Fortify/CreateNewUser.php`, `app/Providers/FortifyServiceProvider.php` (prop `consentDocuments` dla `auth/Register`), `resources/js/pages/auth/Register.vue`
- Test: `tests/Feature/Auth/RegistrationConsentTest.php`

**Interfaces:**
- Consumes: `ConsentDocuments::current()`, `ConsentDocuments::accept()`.
- Produces: pole formularza `accept_terms`; prop `consentDocuments: list<{title: string, slug: string}>`.

- [ ] **Step 1: Padające testy:** `registration requires accepting the documents when some are published` (bez `accept_terms` → błąd walidacji `accept_terms`, użytkownik nie powstaje); `registration with acceptance stores consents for the current versions`; `registration works without the checkbox when nothing is published`; `the register page exposes the documents to accept`.
- [ ] **Step 2:** Uruchom; oczekiwane: FAIL.
- [ ] **Step 3:** W `CreateNewUser` dodaj regułę `accept_terms` (`accepted`, wymagana tylko gdy `ConsentDocuments::current()` jest niepuste) i po utworzeniu użytkownika wywołaj `accept()`. Widok rejestracji dostaje listę dokumentów; `Register.vue` pokazuje wymagany checkbox z linkami do `content.show` (w nowej karcie) tylko gdy lista jest niepusta.
- [ ] **Step 4:** Uruchom nowy test oraz istniejące testy rejestracji w `tests/Feature/Auth`; oczekiwane: PASS.
- [ ] **Step 5: Commit**: `Record consent at registration`.

### Task 4: Usuwanie konta ze Stripe

**Files:**
- Create: `app/Actions/DeleteUserAccount.php`
- Modify: `app/Billing/PackageBilling.php`, `app/Billing/StripePackageBilling.php`, `tests/Support/FakePackageBilling.php`, `app/Http/Controllers/Settings/ProfileController.php` (`destroy`), `app/Http/Controllers/Admin/UserController.php` (`destroy`)
- Test: `tests/Feature/DeleteAccountTest.php`

**Interfaces:**
- Produces: `PackageBilling::deleteCustomer(User $user): void` (brak `stripe_id` → nic; usuwa klienta w Stripe, co anuluje subskrypcje; `resource_missing` traktowane jak sukces); `FakePackageBilling` zapisuje wywołanie `['deleteCustomer', [$user->id]]` i ma flagę `public bool $failDeleteCustomer = false` rzucającą `RuntimeException`; `DeleteUserAccount::__invoke(User $user): void` (wstrzykuje `PackageBilling`).

- [ ] **Step 1: Padające testy** w `DeleteAccountTest.php` (z `FakePackageBilling` w kontenerze, jak w istniejących testach billing, np. `PackagePurchaseTest`):
  - `deleting the account removes the stripe customer first and then the data` (konto z `stripe_id`, linkiem, domeną, płatnością, zgodą: wszystko znika, `deleteCustomer` wywołane z id użytkownika),
  - `a stripe failure keeps the account and its data` (`failDeleteCustomer = true`: konto, link i płatność istnieją, użytkownik dostaje błąd z komunikatem z Global Constraints),
  - `an account without stripe is deleted without calling stripe`,
  - `profile deletion still requires the password`,
  - `an admin deleting a user goes through the same action` (Stripe wywołany; błąd Stripe zatrzymuje usunięcie i pokazuje komunikat),
  - dla implementacji Stripe: test w stylu `PackageStripeSyncTest` (sprawdź jak mockuje klienta) potwierdzający wywołanie usunięcia klienta oraz brak błędu przy `resource_missing`.
- [ ] **Step 2:** Uruchom; oczekiwane: FAIL.
- [ ] **Step 3:** Dodaj metodę do interfejsu, implementacje (Stripe: `$this->stripe()->customers->delete($user->stripe_id)` z przechwyceniem `InvalidRequestException` o kodzie `resource_missing`) i fake; zaimplementuj akcję (billing, potem `$user->delete()`); w obu kontrolerach zamień `$user->delete()` na akcję i obsłuż wyjątek: wyloguj dopiero po sukcesie (w `ProfileController::destroy` kolejność: akcja, `Auth::logout()`, unieważnienie sesji), przy błędzie wróć z błędem walidacji/komunikatem bez zmian w bazie.
- [ ] **Step 4:** Uruchom `DeleteAccountTest` oraz istniejące testy usuwania (`Settings` w `tests/Feature`, `Admin/UserManagementTest`); oczekiwane: PASS.
- [ ] **Step 5: Commit**: `Delete the Stripe customer when deleting an account`.

### Task 5: Eksport danych

**Files:**
- Create: `app/Support/CsvWriter.php`, `app/Support/AccountExport.php`, `app/Http/Controllers/Settings/PrivacyExportController.php`
- Modify: `routes/settings.php` (trasa `privacy.export` w grupie `auth`, `verified` nie jest wymagane), `resources/js/pages/settings/Profile.vue` (sekcja "Download my data" z linkiem do pobrania)
- Test: `tests/Unit/CsvWriterTest.php`, `tests/Feature/AccountExportTest.php`

**Interfaces:**
- Produces: `CsvWriter::render(list<string> $header, iterable<list<scalar|null>> $rows): string` (zabezpiecza komórki wg Global Constraints, cytuje wg RFC 4180); `AccountExport::files(User $user): array<string, string>` (nazwa pliku → treść CSV, dokładnie pięć kluczy z Global Constraints); `PrivacyExportController::__invoke(Request $request): Symfony\Component\HttpFoundation\BinaryFileResponse` (ZIP w katalogu tymczasowym, `deleteFileAfterSend`).

- [ ] **Step 1: Padające testy:**
  - `CsvWriterTest`: `prefixes formula cells with an apostrophe` (dataset: `=1+1`, `+1`, `-1`, `@SUM(A1)`, tabulator, CR), `quotes commas, quotes and newlines`, `renders null as an empty cell`, `renders the header even without rows`.
  - `AccountExportTest`: `exports only the signed-in user's data` (drugi użytkownik z linkiem, domeną i płatnością nie pojawia się), `contains the five csv files with the expected headers`, `link titles that look like formulas are escaped`, `an empty account still produces headers only`, `payments and consents appear`, `guests are redirected to login`, `the response is a zip download named shortify-export-YYYY-MM-DD.zip` (otwórz ZIP przez `ZipArchive` w teście).
- [ ] **Step 2:** Uruchom; oczekiwane: FAIL.
- [ ] **Step 3:** Zaimplementuj `CsvWriter` (strumień `php://temp` + `fputcsv`, ręczne poprzedzanie komórek), `AccountExport` (kolumny z Spec "Eksport danych"; liczba kliknięć linku przez `withClickTotal()`), kontroler (`ZipArchive`, plik w `sys_get_temp_dir()`), trasę oraz sekcję w `Profile.vue` (zwykły link `<a :href="privacyExport().url" download>` z Wayfinder `@/routes`; po `php artisan wayfinder:generate`).
- [ ] **Step 4:** Uruchom oba pliki testów oraz `ConsentEnforcementTest` (wyjątek `privacy.export` działa bez zgody); oczekiwane: PASS. `npm run build`; oczekiwane: bez błędów.
- [ ] **Step 5: Commit**: `Add account data export as zipped CSV`.

### Task 6: Weryfikacja końcowa

- [ ] **Step 1:** `vendor/bin/pint --format agent`; `npx vp check` ograniczony do własnych plików Vue; `npm run build`.
- [ ] **Step 2:** `php artisan test --compact` (pełny zestaw, ok. 45 s); oczekiwane: PASS.
- [ ] **Step 3:** Ręcznie (za zgodą użytkownika, bo zmienia dane lokalne): zalogować się na lokalnym koncie, sprawdzić ekran zgody po opublikowaniu nowej wersji dokumentu oraz pobranie eksportu.
