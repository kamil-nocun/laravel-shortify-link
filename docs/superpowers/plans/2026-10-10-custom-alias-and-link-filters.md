# Własny alias linku i filtry listy linków — plan implementacji

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Użytkownik z pakietem dającym uprawnienie tworzy link z własnym aliasem, a lista linków ma wyszukiwanie i filtry.

**Architecture:** Uprawnienie to flaga `allows_custom_alias` na `packages`, kopiowana do `package_assignments` jak `domain_limit`. Alias zapisuje się w istniejącej kolumnie `links.code` (unikalność `(domain_key, code)` już jest). Filtry listy to scope'y na `Link` sterowane parametrami zapytania w `LinkController::index`.

**Tech Stack:** Laravel, Pest (MySQL w testach), Inertia v3 + Vue, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-10-10-custom-alias-and-link-filters-design.md`

## Global Constraints

- Alias: 3–50 znaków, wzorzec `[A-Za-z0-9_-]`, unikalny w obrębie domeny (`null` = domena główna), rozróżnia wielkość liter (kolacja binarna).
- Alias niezmienny po utworzeniu; `UpdateLinkRequest` nie przyjmuje `alias`.
- Utrata uprawnienia nie psuje istniejących aliasów.
- Kolizja aliasu w wyścigu → błąd walidacji `alias`, bez ponowienia z innym kodem.
- Regex trasy `{code}`: `[0-9A-Za-z_-]+`.
- Filtry listy: `search`, `status` (`active|inactive|expired|disabled`), `domain` (`main` albo id domeny użytkownika); tylko lista użytkownika, nie panel admina.
- Po zmianie PHP: `vendor/bin/pint --dirty --format agent`. Testy: `php artisan test --compact <plik>`.
- Stringi UI i komunikaty po angielsku (jak reszta aplikacji, `__()`), dokumenty po polsku.

## Review Focus

- Użytkownik bez uprawnienia wysyła `alias` bezpośrednim żądaniem (UI go ukrywa): oczekiwany błąd walidacji, link nie powstaje. (Task 2)
- Alias różniący się tylko wielkością liter od istniejącego (`Promo` vs `promo`): oba dozwolone. (Task 2)
- Alias z zarezerwowanym słowem w innej wielkości liter (`Admin`): odrzucony. (Task 2)
- Fraza wyszukiwania z `%` lub `_` nie działa jak wzorzec. (Task 4)
- Link na domenie wyłączonej przez admina nie pasuje do żadnego `status` (scope `active` go wyklucza, a `inactive/expired/disabled` nie), ale jest widoczny bez filtra statusu. (Task 4)

---

### Task 1: Uprawnienie do aliasu w pakiecie

**Files:**
- Create: migracja przez `php artisan make:migration add_allows_custom_alias_to_packages_and_assignments --no-interaction`
- Modify: `app/Models/Package.php`, `app/Models/PackageAssignment.php`, `app/Models/User.php` (obok `canAddDomain`), `app/Billing/ProjectStripeEvents.php:82`, `app/Http/Requests/Admin/StorePackageRequest.php`, `database/factories/PackageFactory.php`, `database/factories/PackageAssignmentFactory.php`, `resources/js/components/PackageForm.vue`, `resources/js/types/package.ts`
- Test: `tests/Feature/CustomAliasPermissionTest.php`

**Interfaces:**
- Produces: `packages.allows_custom_alias` i `package_assignments.allows_custom_alias` (boolean, domyślnie `false`); `User::allowsCustomAlias(): bool`; `PackageFactory::withCustomAlias(): static`; `PackageAssignmentFactory` kopiuje flagę z pakietu, tak jak `domain_limit`.

- [ ] **Step 1: Napisz testy `CustomAliasPermissionTest.php`** (Pest, `User::factory()`, `PackageFactory`, `PackageAssignmentFactory`):
  - `allowsCustomAlias()` zwraca `true` dla super admina; flagę aktywnego przypisania dla zwykłego użytkownika (snapshot: edycja pakietu po przypisaniu nie zmienia wyniku); flagę pakietu domyślnego bez przypisania; `false` bez przypisania i bez pakietu domyślnego.
  - przypisanie utworzone przez zdarzenie Stripe (`ProjectStripeEvents`) dostaje flagę pakietu — skopiuj układ istniejącego testu z `tests/Feature/PackageSnapshotTest.php`.
  - admin tworzy pakiet z `allows_custom_alias=true` przez `route('admin.packages.store')` i pakiet ma flagę; bez pola zapisuje `false`.

- [ ] **Step 2: Uruchom testy, upewnij się, że padają** (`php artisan test --compact tests/Feature/CustomAliasPermissionTest.php`).

- [ ] **Step 3: Migracja** dodająca obie kolumny (`boolean`, `default(false)`), z poprawnym `down()`.

- [ ] **Step 4: Modele i fabryki.** Dodaj `allows_custom_alias` do `#[Fillable]`, docblocków `@property bool` i `casts()` w `Package` i `PackageAssignment`; fabryki: `'allows_custom_alias' => false` w `PackageFactory`, kopia z pakietu w `PackageAssignmentFactory`, stan `withCustomAlias()`. W `ProjectStripeEvents:82` dopisz flagę obok `domain_limit`. Znajdź inne miejsca tworzące przypisanie: `grep -rn "'domain_limit' =>" app` — każde dostaje flagę.

- [ ] **Step 5: `User::allowsCustomAlias(): bool`** w kolejności: `isSuperAdmin()` → `true`; w pozostałych przypadkach `(bool) ($this->activeAssignment()?->allows_custom_alias ?? Package::where('is_default', true)->value('allows_custom_alias') ?? false)`.

- [ ] **Step 6: Formularz pakietu.** `StorePackageRequest`: `'allows_custom_alias' => ['boolean']`. `PackageForm.vue`: pole `allows_custom_alias` (wartość startowa `props.package?.allows_custom_alias ?? false`) jako `Checkbox` z etykietą "Allow custom link aliases", wzorem checkboxa `is_default`. Dodaj pole do `types/package.ts`.

- [ ] **Step 7: Uruchom testy z kroku 1 oraz `tests/Feature/PackageSnapshotTest.php` i `tests/Feature/PackageModelTest.php` — mają przechodzić.**

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "Add custom alias permission to packages"
```

### Task 2: Tworzenie linku z aliasem i przekierowanie

**Files:**
- Create: `app/Support/ReservedAliases.php`
- Modify: `app/Http/Requests/Links/StoreLinkRequest.php`, `app/Http/Requests/Links/UpdateLinkRequest.php`, `app/Support/LinkCodeGenerator.php`, `routes/web.php` (regex trasy `{code}`)
- Test: `tests/Feature/CustomAliasTest.php`

**Interfaces:**
- Consumes: `User::allowsCustomAlias(): bool`, `PackageFactory::withCustomAlias()` (Task 1).
- Produces: `ReservedAliases::isReserved(string $alias): bool` (porównanie bez rozróżniania wielkości liter); `StoreLinkRequest` przyjmuje opcjonalne `alias`; `LinkCodeGenerator::createFor(User $user, array $attributes): Link` — gdy `$attributes['alias']` jest ustawiony, używa go jako `code`, a klucz `alias` nigdy nie trafia do modelu; przy kolizji rzuca `Illuminate\Validation\ValidationException` z błędem na `alias`.

- [ ] **Step 1: Napisz testy `CustomAliasTest.php`** (pomocnik: użytkownik z aktywnym przypisaniem pakietu `withCustomAlias()`):
  - poprawny alias (`promocja-lato`, `a_b-1`) zapisuje się jako `code`; `get('/promocja-lato')` przekierowuje na `original_url`.
  - odrzucenie z błędem na `alias` (dataset): 2 znaki, 51 znaków, znak spoza wzorca (`ab c`, `zażółć`), zarezerwowane (`admin`, `Admin`, `dashboard`), duplikat w tej samej domenie.
  - ten sam alias na dwóch różnych domenach (w tym głównej) jest dozwolony; `Promo` i `promo` na jednej domenie są dozwolone razem.
  - użytkownik bez uprawnienia wysyłający `alias` dostaje błąd na `alias`, `Link::count()` pozostaje 0.
  - brak `alias` generuje kod jak dotąd (`strlen(code) >= LinkCodeGenerator::MIN_LENGTH`).
  - kolizja w wyścigu: `Link::factory()` z tym kodem tworzony w momencie zapisu nie jest łatwy do wymuszenia, więc testuj `LinkCodeGenerator::createFor` bezpośrednio z zajętym aliasem przy pominiętej walidacją ścieżce — oczekuj `ValidationException` z kluczem `alias`.
  - admin (`superAdmin()`) tworzący link klientowi przez `route('admin.users.links.store', $user)`: klient bez uprawnienia dostaje błąd na `alias` mimo że admin ma uprawnienie; klient z uprawnieniem — link z aliasem.
  - `PATCH route('links.update', $link)` z `alias` nie zmienia `code`.
  - utrata uprawnienia: link z aliasem utworzony, pakiet użytkownika zmieniony na bez-flagi, `get('/alias')` nadal przekierowuje.
  - test pokrycia zarezerwowanych słów: dla każdej trasy z `Route::getRoutes()` pierwszy segment URI, jeśli nie zawiera `{`, spełnia `ReservedAliases::isReserved()`.

- [ ] **Step 2: Uruchom testy, upewnij się, że padają.**

- [ ] **Step 3: `ReservedAliases`** (`final`, stała z listą ze spec: `admin dashboard links domains billing settings login logout register forgot-password reset-password verify-email email two-factor-challenge user up internal api`; test z kroku 1 wskaże brakujące — dopisz je do listy aż przejdzie, np. `confirm-password`, `storage`).

- [ ] **Step 4: `StoreLinkRequest::rules()`** dodaje klucz `alias`: gdy `$this->linkOwner()->allowsCustomAlias()` → `['nullable', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', closure odrzucająca `ReservedAliases::isReserved`, `Rule::unique('links', 'code')->where('domain_id', $this->input('domain_id'))`]`, w przeciwnym razie `['prohibited']`. Komunikat dla `alias.prohibited`: "Your package does not include custom aliases." (przez `messages()`). Atrybut `alias` w `attributes()` jako `__('alias')`.

- [ ] **Step 5: `UpdateLinkRequest::rules()`** — obok `unset($rules['domain_id'])` dodaj `unset($rules['alias'])`, żeby `validated()` nie zawierało aliasu.

- [ ] **Step 6: `LinkCodeGenerator::createFor`** — wyciągnij `alias` z `$attributes`; z aliasem tworzy `$user->links()->create([...$attributes, 'code' => $alias])` i łapie `UniqueConstraintViolationException`, zamieniając ją na `ValidationException::withMessages(['alias' => __('This alias is already taken.')])`; bez aliasu zachowuje obecną ścieżkę z jednym ponowieniem.

- [ ] **Step 7: Trasa przekierowania** — w `routes/web.php` zmień `->where('code', '[0-9A-Za-z]+')` na `'[0-9A-Za-z_-]+'`.

- [ ] **Step 8: Uruchom `CustomAliasTest.php`, `LinkCrudTest.php`, `LinkRedirectTest.php`, `DomainRedirectTest.php`, `DomainLinkCreationTest.php`, `LinkCodeGeneratorTest.php` — wszystkie przechodzą.**

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "Allow custom link aliases for packages that include them"
```

### Task 3: Pole aliasu w formularzu

**Files:**
- Modify: `app/Http/Controllers/LinkController.php` (`create`), `app/Http/Controllers/Admin/UserLinkController.php` (`create`), `resources/js/components/LinkForm.vue`, `resources/js/pages/links/Create.vue`, `resources/js/pages/admin/users/links/Create.vue`
- Test: `tests/Feature/CustomAliasTest.php` (dopisz)

**Interfaces:**
- Consumes: `User::allowsCustomAlias()` (Task 1), pole `alias` w żądaniu (Task 2).
- Produces: prop Inertia `canSetAlias: bool` na stronach `links/Create` i `admin/users/links/Create` (dla admina: uprawnienie klienta, `$user->allowsCustomAlias()`).

- [ ] **Step 1: Test** — `get(route('links.create'))` zwraca prop `canSetAlias` równy `true`/`false` zależnie od pakietu (`assertInertia(fn ($page) => $page->where('canSetAlias', true))`); to samo dla `admin.users.links.create` z uprawnieniem klienta, nie admina.

- [ ] **Step 2: Uruchom test, upewnij się, że pada.**

- [ ] **Step 3: Kontrolery** przekazują `canSetAlias`: `LinkController::create` → `$request->user()->allowsCustomAlias()`, `UserLinkController::create` → `$user->allowsCustomAlias()`.

- [ ] **Step 4: `LinkForm.vue`** — nowy prop `canSetAlias?: boolean`; pole `alias` w `useForm` (`''`), w `transform` `alias: data.alias || null`. Pole wyświetla się tylko gdy `!link`: gdy `canSetAlias` — `Input` z etykietą "Custom alias", placeholderem "Optional" i podglądem `{host}/{alias}` (host wybranej domeny albo `window.location.host`), błąd `form.errors.alias`; gdy nie — `Input` `disabled` z tekstem "Custom aliases are included in selected packages" i linkiem do `route billing.package.show` (import z `@/routes/billing/package`). Obie strony `Create.vue` przekazują `:can-set-alias="canSetAlias"` i deklarują prop.

- [ ] **Step 5: Wayfinder/typy** — `npm run build` bez błędów; uruchom `php artisan test --compact tests/Feature/CustomAliasTest.php`.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "Show the custom alias field in the link form"
```

### Task 4: Wyszukiwanie i filtry — backend

**Files:**
- Create: `app/Enums/LinkStatus.php`, `app/Http/Requests/Links/IndexLinksRequest.php`
- Modify: `app/Models/Link.php` (scope'y), `app/Http/Controllers/LinkController.php` (`index`)
- Test: `tests/Feature/LinkListFilterTest.php`

**Interfaces:**
- Produces: `enum LinkStatus: string { Active = 'active'; Inactive = 'inactive'; Expired = 'expired'; Disabled = 'disabled'; }`; scope'y `Link::matching(string $term)` (tytuł, kod, `original_url`; `%` i `_` w frazie escapowane) i `Link::withStatus(LinkStatus $status)` (kolejność pierwszeństwa: disabled > expired > inactive > active; `Active` deleguje do istniejącego scope'a `active`); `IndexLinksRequest` z regułami `search` (`nullable|string|max:255`), `status` (`nullable`, `Rule::enum(LinkStatus::class)`), `domain` (`nullable`, `main` albo id domeny należącej do użytkownika); prop Inertia `filters: {search, status, domain}` (wartości `string|null`).

- [ ] **Step 1: Napisz testy `LinkListFilterTest.php`** (`get(route('links.index', [...]))`, `assertInertia`, porównuj listę `links.data.*.id`):
  - `search` po tytule, po kodzie i po fragmencie `original_url`; fraza `100%` nie trafia w link `100x` a trafia w `100%`; fraza `a_c` nie trafia w `abc`.
  - każdy `status` zwraca tylko właściwe linki: `inactive` (`is_active=false`), `expired` (`expires_at` w przeszłości), `disabled` (`disabled_by_admin_at` ustawione), `active`; link wyłączony przez admina i wygasły liczy się jako `disabled`.
  - `domain=main` zwraca linki bez domeny; `domain=<id>` linki tej domeny; `domain=<id obcej domeny>` → błąd walidacji.
  - `status=bogus` → `assertSessionHasErrors('status')`.
  - kombinacja `search` + `status` + `domain`.
  - filtry przeżywają paginację: `links.next_page_url` zawiera parametry zapytania (utwórz > 15 pasujących linków).
  - prop `filters` zwraca przekazane wartości.
  - użytkownik widzi tylko własne linki mimo pasującego `search`.
  - link na domenie wyłączonej przez admina jest na liście bez `status`, a z `status=active` go nie ma.

- [ ] **Step 2: Uruchom testy, upewnij się, że padają.**

- [ ] **Step 3: Enum, scope'y i request** zgodnie z Interfaces. Escapowanie frazy: `addcslashes($term, '%_\\')`.

- [ ] **Step 4: `LinkController::index(IndexLinksRequest $request)`** — zapytanie `$request->user()->links()->with('domain')->withCount('clicks')->latest()`, warunki dokładane tylko dla wypełnionych filtrów, `->paginate(15)->withQueryString()`, dodatkowo props `filters` i `domains` (`$request->user()->domains()->get(['id', 'host'])`, potrzebne selektorowi w Task 5). Pozostałe propsy (`packageNotice`, `quota`) bez zmian.

- [ ] **Step 5: Uruchom `LinkListFilterTest.php` oraz `LinkCrudTest.php`, `LinkLimitTest.php`, `LinkScopeTest.php` — przechodzą.**

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "Filter and search the link list"
```

### Task 5: Wyszukiwanie i filtry — interfejs

**Files:**
- Modify: `resources/js/pages/links/Index.vue`, `resources/js/types/link.ts`
- Test: `tests/Feature/LinkListFilterTest.php` (dopisz: kontrakt propsów); reszta to weryfikacja ręczna.

**Interfaces:**
- Consumes: propsy `filters` i `domains` (Task 4).

- [ ] **Step 1: Typy** — w `types/link.ts` dodaj `LinkFilters = { search: string | null; status: string | null; domain: string | null }`.

- [ ] **Step 2: `Index.vue`** — nad tabelą pasek: pole wyszukiwania (`Input`, debounce 300 ms), `select` statusu ("All statuses", Active, Inactive, Expired, Blocked by admin) i, gdy użytkownik ma domeny, `select` domeny ("All domains", "Main domain", hosty). Każda zmiana wywołuje `router.get(index().url, filtrySNiepustymiWartosciami, { preserveState: true, preserveScroll: true, replace: true })`; stan początkowy z `props.filters`. Gdy `links.data` jest puste i jakiś filtr jest ustawiony — komunikat "No links match your filters." oraz przycisk czyszczący filtry; bez filtrów zostaje obecne "You have no links yet.". Paginacja używa `prev_page_url`/`next_page_url`, które po `withQueryString()` niosą filtry — nie wymaga zmian.

- [ ] **Step 3: Weryfikacja** — `npm run build` bez błędów; `php artisan test --compact tests/Feature/LinkListFilterTest.php`; ręcznie w przeglądarce (`composer run dev`): wpisanie frazy zmienia URL i listę, odświeżenie zachowuje filtry, przejście na stronę 2 zachowuje filtry, czyszczenie filtrów wraca do pełnej listy.

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "Add search and filters to the link list page"
```

---

Po zakończeniu poproś użytkownika o uruchomienie pełnego zestawu: `php artisan test --compact`.
