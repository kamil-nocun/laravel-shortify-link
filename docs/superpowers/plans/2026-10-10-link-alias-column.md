# Alias jako osobna kolumna — plan przeróbki

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Przerobić alias z "nadpisuje `code`" na osobną, opcjonalną kolumnę `links.alias`, tak by kod standardowy i alias działały jednocześnie, a alias dało się zmienić lub usunąć przy edycji.

**Architecture:** `links.code` zostaje wyłącznie kodem generowanym. Nowa kolumna `links.alias` ma własną unikalność `(domain_key, alias)`, a jedna przestrzeń nazw na domenę jest pilnowana w aplikacji przez `Link::isCodeOrAliasTaken()`. Przekierowanie szuka po `code` lub `alias`. Uprawnienie (`User::allowsCustomAlias()`, flaga pakietu) i pole formularza z Task 1–3 pierwszego planu zostają.

**Tech Stack:** Laravel, Pest (MySQL w testach), Inertia v3 + Vue, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-10-10-custom-alias-and-link-filters-design.md` (rewizja `6b0976e`). Poprzedni plan: `docs/superpowers/plans/2026-10-10-custom-alias-and-link-filters.md` (zadania 1–5 wykonane, tej przeróbki nie obejmują).

## Global Constraints

- Gałąź `feature/custom-alias-link-filters`, nowe commity na wierzchu (bez przepisywania historii). Każdy commit kończy się linią `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- Alias: 3–50 znaków, `[A-Za-z0-9_-]`, rozróżnia wielkość liter (kolacja `utf8mb4_bin`), nie jest słowem z `ReservedAliases` (bez rozróżniania wielkości liter).
- Jedna przestrzeń nazw na domenę (`null` = domena główna): wartość nie może być jednocześnie kodem jednego linku i aliasem innego w tej samej domenie. Ta sama wartość w różnych domenach jest dozwolona.
- `code` jest niezmienny. `short_url` oparty na kodzie; `alias_url` to pełny adres aliasu albo `null`.
- Uprawnienie sprawdzane dla `linkOwner()`, tylko gdy wartość aliasu faktycznie się zmienia (w tym usunięcie aliasu). Niezmieniona wartość nie blokuje edycji reszty linku. Utrata uprawnienia nie psuje istniejącego aliasu.
- Kolizja aliasu w wyścigu → błąd walidacji na polu `alias`, bez ponawiania z innym aliasem.
- Po zmianie PHP: `vendor/bin/pint --dirty --format agent`. Testy: `php artisan test --compact <plik>`. Frontend: `npm run build`; kod Vue w stylu istniejących plików (apostrofy, 4 spacje), bez zmian formatowania w niezmienianych liniach.
- Dev baza `shortify_link` wymaga `php artisan migrate` po nowej migracji (testy używają osobnej bazy i migrują same).

## Review Focus

- Kolizja w obu kierunkach: alias równy cudzemu kodowi w domenie, oraz wygenerowany kod równy istniejącemu aliasowi. (Task 1, Task 2)
- Użytkownik bez uprawnienia wysyła pusty alias przy edycji linku, który ma alias (ConvertEmptyStringsToNull zamienia `""` na `null`, a reguły `nullable` pomijają wtedy walidację): alias nie może zostać po cichu usunięty. (Task 2)
- Edycja linku z własnym, niezmienionym aliasem i własnym kodem nie koliduje sama ze sobą. (Task 2)
- Zapytanie przekierowania `code = X OR alias = X` jest zgrupowane, żeby nie wyciekło poza scope `active` ani poza domenę. (Task 1)
- Alias na domenie A równy kodowi na domenie B nie jest kolizją i przekierowuje tylko na A. (Task 1)

---

### Task 1: Kolumna, model, generator, przekierowanie

**Files:**
- Create: migracja przez `php artisan make:migration add_alias_to_links_table --no-interaction`
- Modify: `app/Models/Link.php`, `app/Support/LinkCodeGenerator.php`, `app/Http/Controllers/RedirectLinkController.php`, `resources/js/types/link.ts`
- Test: `tests/Feature/CustomAliasTest.php` (przerób testy ścieżki tworzenia), `tests/Feature/LinkCodeGeneratorTest.php`, `tests/Feature/LinkModelTest.php`

**Interfaces:**
- Consumes: `User::allowsCustomAlias()`, `PackageFactory::withCustomAlias()` (poprzedni plan, Task 1).
- Produces: kolumna `links.alias`; `Link::$alias` (`string|null`, w `#[Fillable]`); atrybut `Link::alias_url` (`string|null`, w `$appends`); `Link::isCodeOrAliasTaken(?string $domainId, string $value, ?string $ignoreLinkId = null): bool` (statyczna; sprawdza `code` i `alias` w domenie, pomija link o podanym id); `LinkCodeGenerator::createFor(User $user, array $attributes): Link` — zawsze generuje `code`, `alias` z `$attributes` zapisuje w kolumnie `alias`.

- [ ] **Step 1: Przerób testy w `CustomAliasTest.php`** i dopisz nowe (helper tworzenia linku z uprawnieniem zostaje):
  - link z aliasem: `get('/'.$code)` i `get('/'.$alias)` przekierowują na `original_url`; `code` ma ≥ `LinkCodeGenerator::MIN_LENGTH` znaków i `alias` jest w kolumnie `alias`; `alias_url` kończy się `/<alias>`, a dla linku bez aliasu jest `null`.
  - alias równy kodowi cudzego linku w tej samej domenie → `Link::isCodeOrAliasTaken()` zwraca `true`; ta sama wartość w innej domenie → `false`; `$ignoreLinkId` pomija własny link.
  - `LinkCodeGenerator::generate()` nie zwraca wartości zajętej jako alias w tej domenie (wymuś: fabryka linku z `alias` równym wynikowi zamockowanego `randomCode`, albo `fillThreshold` i seed — wybierz najprostszy deterministyczny sposób, np. podklasa generatora nadpisująca `randomCode` z kolejką wartości).
  - wyścig: `createFor` z aliasem zajętym przez inny link (pominięta walidacja) rzuca `ValidationException` z kluczem `alias`.
  - przekierowanie: alias na domenie A równy kodowi linku na domenie B przekierowuje tylko na link z A (żądanie z hosta domeny A).
  - `currentLength()` ignoruje aliasy: link z 6-znakowym aliasem nie zwiększa licznika (`tests/Feature/LinkCodeGeneratorTest.php`).
  Usuń/przerób testy zakładające, że alias trafia do `code` (np. "a valid alias becomes the code").

- [ ] **Step 2: Uruchom testy, upewnij się, że padają.**

- [ ] **Step 3: Migracja** — `links.alias` `string(50)` nullable, kolacja `utf8mb4_bin`, po kolumnie `code`; `DB::statement('ALTER TABLE links ADD UNIQUE links_domain_key_alias_unique (domain_key, alias)')`; `down()` usuwa indeks i kolumnę. Uruchom `php artisan migrate --no-interaction` na dev bazie.

- [ ] **Step 4: `Link`** — `alias` w `#[Fillable]` i docblocku `@property`; `'alias_url'` w `$appends`; atrybut `aliasUrl()` zwracający `null` bez aliasu, a w przeciwnym razie ten sam schemat adresu co `shortUrl` (wyodrębnij wspólną bazę adresu, bez kopiowania logiki domeny); `isCodeOrAliasTaken()` jak w Interfaces; scope `matching` obejmuje też `alias` (`orWhere('alias', 'like', $like)`).

- [ ] **Step 5: `LinkCodeGenerator`** — `generate()` pomija także wartości zajęte jako alias w domenie (użyj `Link::isCodeOrAliasTaken`); `createFor` zawsze generuje `code`, `alias` zapisuje w kolumnie; gdy zapis rzuca `UniqueConstraintViolationException` i `$attributes['alias']` jest ustawiony oraz zajęty (`isCodeOrAliasTaken` po stronie innego linku) → `ValidationException::withMessages(['alias' => __('This alias is already taken.')])`; w pozostałych przypadkach jedno ponowienie jak dotąd. Usuń logikę "alias zamiast kodu".

- [ ] **Step 6: `RedirectLinkController`** — warunek `where(fn ($query) => $query->where('code', $code)->orWhere('alias', $code))` (zgrupowany, przed filtrem domeny); reszta bez zmian. Dodaj `alias: string | null; alias_url: string | null;` do `Link` w `resources/js/types/link.ts`.

- [ ] **Step 7: Uruchom `CustomAliasTest`, `LinkCodeGeneratorTest`, `LinkModelTest`, `LinkRedirectTest`, `DomainRedirectTest`, `DomainLinkCreationTest`, `LinkCrudTest`, `LinkScopeTest`, `LinkListFilterTest` — przechodzą.** Pint.

- [ ] **Step 8: Commit** — `Store the alias in its own column next to the generated code`.

### Task 2: Walidacja tworzenia i edycji, wyszukiwanie po aliasie

**Files:**
- Modify: `app/Http/Requests/Links/StoreLinkRequest.php`, `app/Http/Requests/Links/UpdateLinkRequest.php`, `app/Http/Controllers/LinkController.php` (`edit`, `update`)
- Test: `tests/Feature/CustomAliasTest.php`, `tests/Feature/LinkListFilterTest.php`

**Interfaces:**
- Consumes: `Link::isCodeOrAliasTaken(?string $domainId, string $value, ?string $ignoreLinkId = null): bool`, `LinkCodeGenerator::createFor`, `Link::$alias` (Task 1); `User::allowsCustomAlias()`.
- Produces: `StoreLinkRequest` i `UpdateLinkRequest` przyjmują `alias`; `links/Edit` dostaje propsy `canSetAlias: bool` i `mainHost: string` (ta sama wartość i wyprowadzenie co w `LinkController::create`).

- [ ] **Step 1: Testy w `CustomAliasTest.php`:**
  - tworzenie: odrzucenie z błędem na `alias` (dataset): za krótki, za długi, znak spoza wzorca, zarezerwowany (`Admin`), duplikat aliasu w tej samej domenie, równy kodowi innego linku w tej samej domenie; ten sam alias w innej domenie i `Promo`/`promo` obok siebie są dozwolone; brak uprawnienia + alias → błąd, link nie powstaje.
  - edycja przez właściciela z uprawnieniem (`patch route('links.update')`): ustawia alias; zmienia alias (stary przestaje przekierowywać, kod działa nadal); pusty alias usuwa alias (kod działa); wysłanie własnego, niezmienionego aliasu i dowolna zmiana tytułu przechodzi (brak kolizji ze sobą); alias równy cudzemu kodowi/aliasowi w domenie linku → błąd; zarezerwowany → błąd.
  - edycja bez uprawnienia: zmiana aliasu → błąd na `alias`; **pusty alias przy linku, który ma alias → błąd na `alias`, alias nadal ustawiony**; niezmieniony alias albo brak pola + zmiana tytułu przechodzi; link nie ma aliasu, a użytkownik wysyła pusty alias → przechodzi.
  - utrata uprawnienia: alias nadal przekierowuje, a edycja tytułu działa.
  - admin tworzący link klientowi (`admin.users.links.store`) podlega uprawnieniu klienta (przerób istniejący test pod nową kolumnę).
  - kolizja w wyścigu przy `update` (wymuś `UniqueConstraintViolationException` przez zapis aliasu zajętego po walidacji, np. test `LinkController` z podmienioną regułą, albo test jednostkowy pomocniczej metody — wybierz najprostszy) zwraca błąd walidacji na `alias`.
  - `links/Edit` zwraca propsy `canSetAlias` i `mainHost`.
  - W `LinkListFilterTest.php`: `search` trafia w alias linku.

- [ ] **Step 2: Uruchom testy, upewnij się, że padają.**

- [ ] **Step 3: `StoreLinkRequest::rules()`** — reguły aliasu (tylko gdy `linkOwner()->allowsCustomAlias()`, inaczej `prohibited`): `nullable|string|min:3|max:50|regex`, zarezerwowane (obecny closure), oraz closure odrzucające wartość, dla której `Link::isCodeOrAliasTaken($this->input('domain_id'), $value)` (zastępuje obecne `Rule::unique('links', 'code')`).

- [ ] **Step 4: `UpdateLinkRequest`** — reguła `alias` nie jest już usuwana. Domena = `$link->domain_id` (nie z żądania), `isCodeOrAliasTaken(..., $link->id)`. Dla właściciela z uprawnieniem te same reguły co przy tworzeniu. Dla właściciela bez uprawnienia: reguła `prohibited` nie wystarcza (puste wartości przechodzą, a `""` → `null` omija reguły `nullable`), dlatego dodaj `after()` hook: jeśli żądanie zawiera klucz `alias` i `($this->input('alias') ?: null) !== $link->alias`, dodaj błąd na `alias` ("Your package does not include custom aliases."). `validated()` nie może wtedy zmienić aliasu. `expiresAtIsUnchanged()` i reszta bez zmian.

- [ ] **Step 5: `LinkController`** — `edit` przekazuje `canSetAlias` (`$link->user->allowsCustomAlias()`, właściciel linku) i `mainHost` (to samo wyrażenie co w `create`; gdyby miało pojawić się po raz trzeci, wyodrębnij prywatną metodę w kontrolerze); `update` łapie `UniqueConstraintViolationException` przy zapisie i rzuca `ValidationException::withMessages(['alias' => __('This alias is already taken.')])`. Komentarz PHPDoc `update` ("its code never changes") zostaje prawdziwy.

- [ ] **Step 6: Uruchom `CustomAliasTest`, `LinkListFilterTest`, `LinkCrudTest`, `DomainLinkCreationTest`, `LinkLimitTest` — przechodzą.** Pint.

- [ ] **Step 7: Commit** — `Validate and edit aliases independently of the generated code`.

### Task 3: Interfejs — alias w edycji i na liście

**Files:**
- Modify: `resources/js/components/LinkForm.vue`, `resources/js/pages/links/Edit.vue`, `resources/js/pages/links/Index.vue`
- Test: weryfikacja `npm run build` i `npm run types:check` (błędy w ignorowanym `resources/js/actions/.../RedirectController.ts` pomijamy); kontrakt propsów pokrywa Task 2.

**Interfaces:**
- Consumes: propsy `canSetAlias`, `mainHost` (strona edycji, Task 2), pola `alias`, `alias_url` w `Link` (Task 1).

- [ ] **Step 1: `LinkForm.vue`** — pole `alias` w `useForm` startuje od `props.link?.alias ?? ''`; pole wyświetla się także przy edycji (usuń warunek `!link`). Użytkownik z uprawnieniem: `Input` z podglądem `{mainHost lub host wybranej domeny}/{alias}` (przy edycji host domeny linku, bez `window`/SSR), przy edycji dodatkowo krótka informacja "Changing or clearing the alias disables the old alias. The standard short URL keeps working." Bez uprawnienia: zablokowane pole z bieżącym aliasem (lub dotychczasowym tekstem, gdy brak) i odnośnikiem do pakietów, jak przy tworzeniu. `transform` wysyła `alias: data.alias || null`. `Edit.vue` deklaruje i przekazuje `canSetAlias` oraz `mainHost`.

- [ ] **Step 2: `Index.vue`** — kolumna "Short URL" pokazuje `link.short_url`, a pod nim (gdy jest) `link.alias_url` mniejszą, mutowaną czcionką; bez zmian formatowania pozostałych linii.

- [ ] **Step 3: Weryfikacja** — `npm run build` bez błędów; `npm run types:check` bez nowych błędów w zmienionych plikach; `php artisan test --compact tests/Feature/CustomAliasTest.php tests/Feature/LinkListFilterTest.php`. Ręczna kontrola w przeglądarce jest po stronie użytkownika (implementer nie ma przeglądarki): tworzenie i edycja linku z aliasem i bez, oba adresy działają, lista pokazuje alias.

- [ ] **Step 4: Commit** — `Show and edit the alias in the link form and list`.

---

Po zakończeniu: końcowy przegląd całej przeróbki, a następnie prośba do użytkownika o pełny zestaw: `php artisan test --compact` (niestabilny `AdminAccessTest` — patrz notatka o losowym `link_limit` w `PackageFactory`).
