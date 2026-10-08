# Short Links CRUD Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Zalogowany użytkownik tworzy, edytuje, usuwa i przegląda własne skrócone linki w panelu klienta.

**Architecture:** Tabela `links` (UUID) + model `Link`, generator kodów `App\Support\LinkCodeGenerator`, resource controller z Form Requests i `LinkPolicy`, strony Inertia/Vue wołające trasy przez Wayfinder. Bez warstwy service/repository.

**Tech Stack:** Laravel (PHP 8.5), MySQL, Inertia v3 + Vue, Wayfinder, Pest, Tailwind, komponenty `@/components/ui/*`.

**Spec:** `docs/superpowers/specs/2026-10-08-links-crud-design.md`

## Mapa zadań

| # | Zadanie | Efekt |
|---|---|---|
| 1 | Tabela, model, factory, seeder | `Link` istnieje w bazie |
| 2 | `LinkCodeGenerator` | Unikalny kod o rosnącej długości |
| 3 | Backend CRUD | Trasy, controller, requests, policy |
| 4 | Frontend | Strony Vue + nawigacja |
| 5 | Weryfikacja końcowa | Wszystko zielone |

Kolejność ma znaczenie: każde zadanie korzysta z poprzedniego.

## Global Constraints

**Dane**
- Nowe tabele: UUID jako PK i FK (`HasUuids`, `foreignUuid`).
- `code`: generowany, niezmienny, nigdy z wejścia użytkownika.
- Kolumna `code`: kolacja `utf8mb4_bin` bezwarunkowo (baza to MySQL).
- Twarde usuwanie.
- Pełny adres skróconego linku (`short_url`) jest wyliczany: `config('app.url')` + `/` + `code`, zawsze `https://` (`http://` z `APP_URL` podmieniane). Nie zapisujemy go w bazie (brak kolumny).

**Kod linku (alfabet i długość)**
- Alfabet `0-9A-Za-z`, długość startowa 6.
- Skok na `L+1`, gdy zajętych kodów o długości `L` jest ≥ 99% z `62^L`.

**Architektura i trasy**
- Bez service/repository.
- Trasy `links.*` w grupie `auth` + `verified`.
- Nazwy, nawigacja i komunikaty po angielsku (jak reszta UI).

**Proces**
- Bez zmian w `phpunit.xml` (zmiana bazy testowej to osobny krok poza planem).
- Nie zmieniamy zależności. Dokumentacji nie dodajemy.
- Konwencje: zgodnie z `AGENTS.md` (typy zwrotne, PHPDoc, `vendor/bin/pint --dirty --format agent`, `--no-interaction` dla artisan).

## Review Focus

Pięć rzeczy, które łatwo zepsuć. Sprawdź każdą przed zakończeniem:

- [ ] **Schemat URL.** `url` przyjmuje dowolny schemat (`javascript:`, `ftp:`). Walidacja musi ograniczyć do samego `https`.
- [ ] **Edycja wygasłego linku.** Zmiana tytułu linku z już minioną `expires_at` nie może być blokowana regułą `after:now`, jeśli data się nie zmieniła.
- [ ] **Checkbox `is_active`.** Odznaczony musi zapisać `false` (brak pola w żądaniu ≠ `true`).
- [ ] **Cudzy link, `GET`.** Formularz edycji cudzego linku też przekierowuje na listę z komunikatem (nie tylko `PATCH`/`DELETE`). Brak 403.
- [ ] **Kolizje kodu.** Kolizja przy losowaniu i wyścig na unikalnym indeksie nie kończą się błędem 500.

---

### Task 1: Tabela `links`, model, factory, seeder

**Pliki**
- Create: `database/migrations/<timestamp>_create_links_table.php` (przez `php artisan make:model Link -mf --no-interaction`)
- Create: `app/Models/Link.php`, `database/factories/LinkFactory.php`
- Modify: `app/Models/User.php` (relacja `links()`), `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/LinkModelTest.php`

**Interfejsy**
- Produces: `Link` (`HasUuids`, `HasFactory`).
  - Fillable: `title`, `original_url`, `code`, `is_active`, `expires_at`.
  - Casty: `is_active` bool, `expires_at` datetime.
  - `Link::user(): BelongsTo`, `User::links(): HasMany`.
  - Accessor `short_url` (`Attribute`, `config('app.url')` + `/` + `code`, `http://` podmieniane na `https://`, bez kolumny), dołączany do serializacji przez `$appends`.
  - `LinkFactory`: stany `inactive()` i `expired()`. Domyślnie `code` = losowe 6 znaków alfanumerycznych, `original_url` = `fake()->url()`, `is_active` = true, `expires_at` = null.

**Kroki**

- [ ] **Step 1: Napisz testy, które padają** w `tests/Feature/LinkModelTest.php` (Pest). Cztery testy:
  1. `a link belongs to a user and is listed in user links` (factory + `$user->links`).
  2. `factory states set is_active false and past expires_at`.
  3. `code is case sensitive unique`:
     - Tworzy `abc` i `ABC` dla różnych linków, oczekuje sukcesu.
     - Drugi `abc` rzuca `QueryException`.
     - Test ma `->skip(fn () => ! in_array(DB::getDriverName(), ['mysql', 'mariadb']), 'requires MySQL collation')`.
  4. `short_url combines app url and code and is not a database column`: przy `config(['app.url' => 'http://example.test/'])` `$link->short_url === 'https://example.test/'.$link->code`, `Schema::hasColumn('links', 'short_url')` jest false, `toArray()` zawiera `short_url`.
- [ ] **Step 2: Uruchom** `php artisan test --compact tests/Feature/LinkModelTest.php`. Oczekiwane: FAIL (brak klasy `Link`).
- [ ] **Step 3: Migracja `links`** (kolumny ze specu):
  - `uuid('id')->primary()`
  - `foreignUuid('user_id')->constrained()->cascadeOnDelete()`
  - `title`: string 255, nullable
  - `original_url`: string 2048
  - `code`: string, unique, `->collation('utf8mb4_bin')`
  - `is_active`: boolean, default true
  - `expires_at`: nullable timestamp
  - `timestamps()`
- [ ] **Step 4: Model, relacje, factory, seeder.**
  - Atrybuty (`#[Fillable([...])]`, PHPDoc `@property`) według wzorca z `app/Models/User.php`.
  - Accessor `short_url` + `$appends`.
  - Seeder tworzy kilka linków dla pierwszego użytkownika.
- [ ] **Step 5: Uruchom** `php artisan test --compact tests/Feature/LinkModelTest.php`. Oczekiwane: PASS (test kolacji pominięty na SQLite).
- [ ] **Step 6: Commit** `git add -A app database tests && git commit -m "Add links table, Link model and factory"`

---

### Task 2: `LinkCodeGenerator`

**Pliki**
- Create: `app/Support/LinkCodeGenerator.php`
- Test: `tests/Feature/LinkCodeGeneratorTest.php`

**Interfejsy**
- Consumes: `Link` (Task 1).
- Produces: `final class LinkCodeGenerator`
  - `__construct(private float $fillThreshold = 0.99)`
  - `generate(): string` zwraca unikalny kod.
  - `currentLength(): int`:
    - liczy `Link::whereRaw('LENGTH(code) = ?', [$L])->count()`,
    - porównuje `count >= 62**$L * $fillThreshold`,
    - start `L = 6`, `MIN_LENGTH` jako stała klasy.

**Kroki**

- [ ] **Step 1: Napisz testy, które padają.** Cztery testy:
  1. `generates a six character code over the alphabet`: pasuje do `/^[0-9A-Za-z]{6}$/`.
  2. `generated codes are unique across calls`.
  3. `grows to seven characters once the threshold is reached`:
     - `new LinkCodeGenerator(1 / 62 ** 6)` + jeden `Link::factory()` z 6-znakowym kodem,
     - oczekiwane: `strlen(generate()) === 7` i `currentLength() === 7`.
  4. `retries on collision`:
     - anonimowa podklasa nadpisuje `randomCode(int $length): string`,
     - najpierw zwraca kod już istniejący w bazie, potem nowy,
     - oczekiwany wynik: nowy kod.
- [ ] **Step 2: Uruchom** `php artisan test --compact tests/Feature/LinkCodeGeneratorTest.php`. Oczekiwane: FAIL.
- [ ] **Step 3: Zaimplementuj klasę.**
  - Losowanie w `protected function randomCode(int $length): string` przez `random_int` po stałej `ALPHABET`.
  - Pętla `generate()` powtarza do braku kolizji (`Link::where('code', $code)->exists()`).
  - Kolacja binarna w MySQL gwarantuje rozróżnianie wielkości liter.
- [ ] **Step 4: Uruchom** testy generatora. Oczekiwane: PASS.
- [ ] **Step 5: Commit** `git commit -m "Add LinkCodeGenerator with growing code length"`

---

### Task 3: Backend CRUD (trasy, controller, requests, policy)

**Pliki**
- Create (przez `php artisan make:*`):
  - `app/Http/Controllers/LinkController.php`
  - `app/Http/Requests/Links/StoreLinkRequest.php`
  - `app/Http/Requests/Links/UpdateLinkRequest.php`
  - `app/Policies/LinkPolicy.php`
- Modify: `routes/web.php`, `bootstrap/app.php` (obsługa odmowy dostępu)
- Test: `tests/Feature/LinkCrudTest.php`

**Interfejsy**
- Consumes: `Link`, `LinkCodeGenerator` (wstrzykiwany do `store`).
- Produces:
  - Trasy `links.index|create|store|edit|update|destroy`: `Route::resource('links', LinkController::class)->except('show')`, w grupie `auth` + `verified`.
  - Strony Inertia:
    - `links/Index`: prop `links` (paginowany `LengthAwarePaginator` obiektów z `id,title,original_url,code,short_url,is_active,expires_at`),
    - `links/Create`,
    - `links/Edit`: prop `link` (z `short_url`).
  - Sukces `store/update/destroy`: redirect do `links.index` z `Inertia::flash('toast', ['type' => 'success', 'message' => ...])`, jak w `ProfileController`.
  - `LinkPolicy`: `update(User, Link): bool` i `delete(User, Link): bool` ↔ `$user->id === $link->user_id`. `edit` autoryzowane przez `update`.
  - Odmowa dostępu na trasach `links.*` (nie-JSON): `$exceptions->render()` w `bootstrap/app.php` łapie `AccessDeniedHttpException` (Laravel zamienia `AuthorizationException` przed callbackami), robi `Inertia::flash('toast', ['type' => 'error', 'message' => __('You are not allowed to do that.')])` i `to_route('links.index')`. Poza tym zakresem zostaje domyślne 403.

**Kroki**

- [ ] **Step 1: Napisz testy, które padają** w `LinkCrudTest.php`. Pogrupowane:
  - **Lista:** pokazuje tylko własne linki, najnowsze pierwsze (`assertInertia`).
  - **Pełny adres:** prop `links` na liście i prop `link` na `edit` zawierają `short_url` równy `config('app.url').'/'.$code` z `https://`.
  - **Gość:** przekierowany na `login` dla każdej trasy `links.*`.
  - **Tworzenie:**
    - `store` tworzy link z 6-znakowym kodem i `user_id` zalogowanego,
    - `store` ignoruje przysłany `code`.
  - **Edycja i usuwanie:**
    - `update` zmienia pola, ale nie `code`,
    - `destroy` usuwa.
  - **Cudzy link:** `edit`, `update`, `destroy` → przekierowanie na `links.index` z toastem błędu, link bez zmian.
  - **Walidacja:**
    - brak URL,
    - `javascript:alert(1)`,
    - `ftp://x.y`,
    - `http://example.com` (sam `https` dozwolony),
    - URL > 2048,
    - tytuł > 255,
    - `expires_at` w przeszłości przy `store`.
  - **Przypadki brzegowe:**
    - `update` tytułu linku z minioną `expires_at` bez zmiany daty → sukces,
    - `is_active => false` zapisuje się jako false.
- [ ] **Step 2: Uruchom** `php artisan test --compact tests/Feature/LinkCrudTest.php`. Oczekiwane: FAIL.
- [ ] **Step 3: Requests.**
  - Reguły:
    - `original_url`: `required|url:https|max:2048`
    - `title`: `nullable|string|max:255`
    - `is_active`: `boolean`
    - `expires_at`: `nullable|date|after:now`
  - W `UpdateLinkRequest` reguła `after:now` jest pomijana, gdy przesłana data równa się obecnej `expires_at` linku.
  - `code` poza regułami (`validated()` go nie zwraca).
- [ ] **Step 4: Policy, controller, trasy.**
  - `index`: `$request->user()->links()->latest()->paginate(15)`.
  - `store`:
    - `$request->user()->links()->create([...$request->validated(), 'code' => $generator->generate()])`,
    - przy `UniqueConstraintViolationException` jedno ponowienie z nowym kodem.
  - `edit/update/destroy`: autoryzacja przez `$this->authorize`, zgodnie z konwencją projektu. Sprawdź bazowy `Controller`; bez traita użyj `Gate::authorize`.
- [ ] **Step 5: Uruchom** testy CRUD. Oczekiwane: PASS.
- [ ] **Step 6: Pint i commit.**
  - `vendor/bin/pint --dirty --format agent`
  - `git commit -m "Add links CRUD controller, requests and policy"`

---

### Task 4: Frontend (strony Vue, nawigacja)

**Pliki**
- Create:
  - `resources/js/pages/links/Index.vue`
  - `resources/js/pages/links/Create.vue`
  - `resources/js/pages/links/Edit.vue`
  - `resources/js/components/LinkForm.vue`
- Modify: `resources/js/components/AppSidebar.vue` (pozycja "Links" z ikoną lucide, `href` z Wayfinder)
- Test: `tests/Feature/LinkCrudTest.php` (asercje nazw komponentów Inertia: `links/Index|Create|Edit`)

**Interfejsy**
- Consumes: propsy ze Task 3. Trasy z `@/routes/links` i `@/actions/App/Http/Controllers/LinkController` (po `php artisan wayfinder:generate`).
- Produces: `LinkForm.vue`
  - propsy: `link?: Link`, `submit` obsługiwany przez `useForm`,
  - pola: `original_url`, `title`, `is_active` (boolean, zawsze wysyłany), `expires_at`,
  - kod tylko do odczytu na stronie edycji i w tabeli,
  - `short_url` tylko do odczytu w tabeli i w formularzu edycji (przy tworzeniu ukryty, bo kod jeszcze nie istnieje).

**Kroki**

- [ ] **Step 1: Test nazw komponentów.**
  - W `LinkCrudTest.php` upewnij się, że istnieją asercje `assertInertia(fn ($page) => $page->component('links/Index'))` (analogicznie `links/Create`, `links/Edit`).
  - Uruchom. Oczekiwane: FAIL przy włączonym `ensure_pages_exist`, inaczej PASS (wtedy to kontrola nazw komponentów).
- [ ] **Step 2: Strony i formularz.**
  - Wzorzec: `resources/js/pages/settings/Profile.vue` (układ, `Heading`, `Input`, `Label`, `InputError`, `Button`, breadcrumbs, `defineOptions({ layout })`).
  - Tabela z paginacją (`Link` Inertia), kolumna z pełnym adresem `short_url`.
  - Formularz edycji pokazuje `short_url` tylko do odczytu.
  - Przyciski Edit/Delete.
  - Potwierdzenie usunięcia dialogiem z `@/components/ui/dialog`.
  - Badge statusu z `@/components/ui/badge`.
  - Pusty stan listy.
- [ ] **Step 3: Nawigacja.** Dodaj pozycję w `AppSidebar.vue` obok Dashboard.
- [ ] **Step 4: Build i sprawdzenie.**
  1. `php artisan wayfinder:generate`
  2. `npm run build`. Oczekiwane: bez błędów TypeScript.
  3. `php artisan test --compact tests/Feature/LinkCrudTest.php`. Oczekiwane: PASS.
  4. Ręcznie: `composer run dev`, w przeglądarce przejdź Create → Edit → Delete.
- [ ] **Step 5: Commit** `git commit -m "Add links pages and sidebar navigation"`

---

### Task 5: Weryfikacja końcowa

- [ ] **Step 1: Uruchom komplet.**
  - `vendor/bin/pint --dirty --format agent`
  - `npm run build`
  - `php artisan test --compact tests/Feature`
  - Oczekiwane: wszystko zielone. Test kolacji pominięty do czasu osobnego kroku z bazą testową MySQL.
- [ ] **Step 2: Poproś użytkownika** o pełny `php artisan test --compact` (wymóg `AGENTS.md`).
