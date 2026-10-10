# Własny alias linku i filtry listy linków — design

## Kontekst i cel

Aplikacja generuje kod linku automatycznie (`LinkCodeGenerator`), a użytkownik nie ma na niego wpływu. Lista linków (`LinkController::index`) pokazuje tylko paginację, bez wyszukiwania i filtrów.

To pierwszy z pięciu podprojektów (alias + filtry, limit kliknięć + strona wygaśnięcia, kod QR, API, RODO). Projekt służy jako portfolio, ale ma pozostać możliwy do skomercjalizowania, dlatego alias jest funkcją płatnych planów, a nie wszystkich użytkowników.

Kryteria sukcesu:
- użytkownik z uprawnieniem tworzy link z własnym aliasem, np. `domena/promocja-lato`;
- użytkownik bez uprawnienia nie może tego zrobić ani przez UI, ani przez bezpośrednie żądanie;
- lista linków daje się przeszukać i przefiltrować, a stan filtrów przeżywa paginację i odświeżenie strony.

Poza zakresem: edycja aliasu po utworzeniu, filtry w panelu admina, wyszukiwanie full-text.

## Decyzje

- **Uprawnienie do aliasu to flaga pakietu** (`allows_custom_alias`), kopiowana do przypisania jako snapshot, tak jak `link_limit` i `domain_limit`. Odrzucone: wyliczanie z "pakiet inny niż domyślny" (ręcznie przypisane pakiety dawałyby aliasy bez możliwości wyłączenia) oraz z ceny (zależność od danych Stripe).
- **Alias to ta sama kolumna `links.code`.** Istniejąca unikalność `(domain_key, code)` z kolacją binarną pokrywa wymóg unikalności w obrębie domeny. Brak zmian schematu `links`.
- **Rozróżnianie wielkości liter zostaje** (kolacja binarna): `Promo` i `promo` to różne aliasy, tak jak dla kodów generowanych.
- **Alias jest niezmienny po utworzeniu**, zgodnie z zasadą, że kod linku nigdy się nie zmienia (`LinkController::update`).
- **Utrata uprawnienia nie usuwa aliasów.** Istniejące linki działają dalej, zablokowane jest tylko tworzenie nowych aliasów.

## Dane

Jedna migracja:
- `packages.allows_custom_alias` — boolean, domyślnie `false`;
- `package_assignments.allows_custom_alias` — boolean, domyślnie `false`.

Istniejące pakiety i przypisania nie dostają uprawnienia, dopóki admin go nie włączy. Przy tworzeniu przypisania flaga jest kopiowana z pakietu w tym samym miejscu, w którym kopiowane są limity. Formularz pakietu w panelu admina dostaje checkbox, a `Package` i `PackageAssignment` rozszerzenie `#[Fillable]` i casts.

`User::allowsCustomAlias(): bool`:
1. super admin → `true`;
2. aktywne przypisanie → jego flaga (snapshot wygrywa nad późniejszą edycją pakietu);
3. brak przypisania → flaga pakietu domyślnego;
4. brak pakietu domyślnego → `false`.

## Tworzenie linku

`StoreLinkRequest` dostaje pole `alias` (opcjonalne):
- 3–50 znaków, wzorzec `[A-Za-z0-9_-]`;
- nie może należeć do listy zarezerwowanych słów;
- unikalny w obrębie wybranej domeny (`domain_id` albo domena główna dla `null`);
- pole jest dozwolone tylko, gdy `linkOwner()->allowsCustomAlias()`. Użytkownik bez uprawnienia, który je wyśle, dostaje błąd walidacji. Samo ukrycie pola w UI nie wystarcza.

Właścicielem sprawdzanego uprawnienia jest `linkOwner()`. Dzięki temu admin tworzący link w imieniu klienta (`UserLinkController`) podlega uprawnieniom klienta, a nie własnym.

Lista zarezerwowanych słów obejmuje co najmniej: `admin`, `dashboard`, `links`, `domains`, `billing`, `settings`, `login`, `logout`, `register`, `forgot-password`, `reset-password`, `verify-email`, `email`, `two-factor-challenge`, `user`, `up`, `internal`, `api`. Test porównuje listę z pierwszymi segmentami tras z routera, więc dodanie trasy bez aktualizacji listy przerywa build.

`LinkCodeGenerator::createFor`:
- gdy podano alias, używa go jako `code` zamiast generować kod;
- kolizja w wyścigu (`UniqueConstraintViolationException`) dla aliasu nie jest ponawiana z innym kodem, tylko zwraca błąd walidacji "alias zajęty";
- ścieżka bez aliasu działa jak dotąd (ponowienie raz).

Formularz tworzenia (`links/Create.vue`): użytkownik z uprawnieniem widzi pole aliasu z podglądem pełnego adresu. Pozostali widzą zablokowane pole z odnośnikiem do pakietów. Dotyczy to też formularza tworzenia linku przez admina dla klienta.

## Przekierowanie

Regex trasy `{code}` zmienia się z `[0-9A-Za-z]+` na `[0-9A-Za-z_-]+`. Zmiana jest bezpieczna, bo trasa jest zarejestrowana jako ostatnia, a słowa kolidujące z trasami aplikacji są zarezerwowane. `RedirectLinkController` nie wymaga zmian.

## Wyszukiwanie i filtry listy linków

Obsługa w istniejącym `LinkController::index`, bez nowej trasy. Parametry zapytania:
- `search` — `LIKE` po tytule, kodzie i `original_url` (znaki `%` i `_` w frazie są escapowane);
- `status` — `active`, `inactive`, `expired` albo `disabled` (wyłączony przez admina);
- `domain` — id domeny użytkownika albo wartość oznaczająca domenę główną.

Filtry statusu są scope'ami na modelu `Link`. Scope `active` już istnieje i zostaje użyty, nowe scope'y dla pozostałych wartości powstają obok niego. Wartość spoza listy w `status` jest odrzucana walidacją (`Rule::enum` lub `in`), a nie ignorowana po cichu.

Wynik używa `withQueryString()`, więc filtry przeżywają paginację. W `links/Index.vue` wyszukiwarka ma debounce i wysyła `router.get` z `preserveState` i `replace`. Stan filtrów odczytywany jest z URL, więc odświeżenie strony go zachowuje. Pusty wynik pokazuje komunikat o braku dopasowań, odrębny od pustej listy bez linków.

Zakres filtrów obejmuje wyłącznie listę użytkownika. Panel admina zostaje bez zmian.

## Testy (Pest, feature)

Alias:
- poprawny alias zapisuje się jako `code` i działa w przekierowaniu, także z `-` i `_`;
- odrzucenie: zły format, za krótki, za długi, zarezerwowany, duplikat w tej samej domenie;
- ten sam alias w dwóch różnych domenach (w tym głównej) jest dozwolony;
- użytkownik bez uprawnienia dostaje błąd walidacji przy wysłaniu `alias`, a link nie powstaje;
- admin tworzący link klientowi podlega uprawnieniu klienta;
- `allowsCustomAlias()` dla wszystkich czterech przypadków;
- flaga kopiuje się do przypisania, a późniejsza edycja pakietu nie zmienia snapshotu;
- utrata uprawnienia nie psuje przekierowania istniejącego aliasu;
- lista zarezerwowanych słów pokrywa pierwsze segmenty tras.

Filtry:
- `search` po tytule, kodzie i URL, w tym fraza ze znakami `%` i `_`;
- każdy status osobno i połączony z `search` oraz `domain`;
- nieprawidłowy `status` zwraca błąd walidacji;
- filtry zachowują się na kolejnej stronie paginacji;
- użytkownik widzi tylko własne linki.

Zmiany czysto wizualne (układ formularza, wygląd paska filtrów) nie wymagają osobnych testów.
