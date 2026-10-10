# Własny alias linku i filtry listy linków — design

> **Rewizja 2026-10-10.** Pierwsza wersja zapisywała alias w kolumnie `links.code` (alias zastępował kod, niezmienny po utworzeniu). Po decyzji, że **kod standardowy i alias mają działać jednocześnie**, alias jest osobnym, opcjonalnym polem, które można zmieniać i usuwać. Sekcje "Decyzje", "Dane", "Tworzenie i edycja linku", "Przekierowanie" i "Testy" opisują stan docelowy. Implementacja pierwszej wersji (commity do `14bfa51`) jest przerabiana kolejnymi commitami.

## Kontekst i cel

Aplikacja generuje kod linku automatycznie (`LinkCodeGenerator`), a użytkownik nie ma na niego wpływu. Lista linków (`LinkController::index`) pokazuje tylko paginację, bez wyszukiwania i filtrów.

To pierwszy z pięciu podprojektów (alias + filtry, limit kliknięć + strona wygaśnięcia, kod QR, API, RODO). Projekt służy jako portfolio, ale ma pozostać możliwy do skomercjalizowania, dlatego alias jest funkcją płatnych planów, a nie wszystkich użytkowników.

Kryteria sukcesu:
- każdy link ma standardowy, generowany kod, który działa zawsze;
- użytkownik z uprawnieniem może dodatkowo ustawić, zmienić lub usunąć alias (np. `domena/promocja-lato`), a oba adresy przekierowują na ten sam cel;
- użytkownik bez uprawnienia nie może ustawić ani zmienić aliasu, ani przez UI, ani przez bezpośrednie żądanie;
- lista linków daje się przeszukać (także po aliasie) i przefiltrować, a stan filtrów przeżywa paginację i odświeżenie strony.

Poza zakresem: przekierowanie ze starych wartości aliasu po jego zmianie, filtry w panelu admina, wyszukiwanie full-text.

## Decyzje

- **Uprawnienie do aliasu to flaga pakietu** (`allows_custom_alias`), kopiowana do przypisania jako snapshot, tak jak `link_limit` i `domain_limit`. Odrzucone: wyliczanie z "pakiet inny niż domyślny" (ręcznie przypisane pakiety dawałyby aliasy bez możliwości wyłączenia) oraz z ceny (zależność od danych Stripe).
- **Alias to osobna kolumna `links.alias`** (nullable), a `links.code` pozostaje wyłącznie kodem generowanym i niezmiennym. Dzięki temu rozesłany standardowy adres nigdy nie przestaje działać, a zmiana aliasu unieważnia tylko poprzedni alias.
- **Jedna przestrzeń nazw na domenę.** Wartość nie może jednocześnie być kodem jednego linku i aliasem innego w tej samej domenie (`null` = domena główna). Dwa indeksy unikalne nie chronią się wzajemnie, więc kontrola krzyżowa jest w aplikacji: walidacja aliasu sprawdza kody i aliasy, a generator kodów pomija wartości zajęte jako alias. Wyścig między losowym kodem a ręcznie wpisanym aliasem jest teoretyczny (kod ma co najmniej 6 losowych znaków).
- **Rozróżnianie wielkości liter zostaje** (kolacja binarna dla obu kolumn): `Promo` i `promo` to różne aliasy.
- **Utrata uprawnienia nie usuwa aliasu.** Istniejący alias działa dalej; zablokowana jest tylko jego zmiana i ustawianie nowych.
- **`short_url` zostaje oparty na kodzie** (stabilny, zawsze obecny). Nowy atrybut `alias_url` zwraca pełny adres aliasu albo `null`.

## Dane

Migracje:
- `packages.allows_custom_alias` i `package_assignments.allows_custom_alias` — boolean, domyślnie `false` (zrobione w Task 1). Istniejące pakiety i przypisania nie dostają uprawnienia, dopóki admin go nie włączy. Przy tworzeniu przypisania flaga jest kopiowana z pakietu w tym samym miejscu, w którym kopiowane są limity.
- `links.alias` — `string`, nullable, kolacja `utf8mb4_bin`, z unikalnością `(domain_key, alias)` (kolumna wirtualna `domain_key` już istnieje).
- Rewizja przerabia kod z pierwszej wersji (alias zapisywany jako `code`), więc migracja nie przenosi danych: gałąź nie była mergowana i nie ma produkcyjnych aliasów.

`User::allowsCustomAlias(): bool` (zrobione w Task 1):
1. super admin → `true`;
2. aktywne przypisanie → jego flaga (snapshot wygrywa nad późniejszą edycją pakietu);
3. brak przypisania → flaga pakietu domyślnego;
4. brak pakietu domyślnego → `false`.

## Tworzenie i edycja linku

`StoreLinkRequest` i `UpdateLinkRequest` przyjmują opcjonalne `alias`:
- 3–50 znaków, wzorzec `[A-Za-z0-9_-]`;
- nie może należeć do listy zarezerwowanych słów (porównanie bez rozróżniania wielkości liter);
- unikalny w obrębie domeny linku względem **kodów i aliasów** innych linków (przy edycji edytowany link jest pomijany, więc jego własny kod i alias nie kolidują);
- uprawnienie sprawdzane dla `linkOwner()` i tylko wtedy, gdy wartość aliasu faktycznie się zmienia. Użytkownik bez uprawnienia, który wyśle inną wartość niż obecna, dostaje błąd walidacji. Samo ukrycie pola w UI nie wystarcza. Wysłanie niezmienionej wartości (lub niewysłanie pola) nie blokuje edycji pozostałych danych.
- pusty alias przy edycji usuwa alias (kod nadal działa); przy tworzeniu oznacza brak aliasu.

Właścicielem sprawdzanego uprawnienia jest `linkOwner()`. Dzięki temu admin tworzący link w imieniu klienta podlega uprawnieniom klienta, a nie własnym.

Lista zarezerwowanych słów obejmuje co najmniej: `admin dashboard links domains billing settings login logout register forgot-password reset-password verify-email email two-factor-challenge user up internal api build storage stripe`. Test porównuje listę z pierwszymi segmentami tras z routera oraz z katalogami najwyższego poziomu w `public/`.

`LinkCodeGenerator`:
- `createFor` zawsze generuje kod (`generate()` pomija wartości zajęte jako alias w tej domenie) i zapisuje `alias` obok, gdy podano;
- kolizja aliasu w wyścigu (`UniqueConstraintViolationException`) daje błąd walidacji na `alias`, bez ponawiania z innym aliasem;
- `currentLength()` liczy tylko kody, więc aliasy nie wpływają na długość generowanych kodów.

`LinkController::update` zapisuje nowy alias (albo `null`), a kod nigdy się nie zmienia. Kolizja w wyścigu daje błąd na polu `alias`.

Formularz linku (tworzenie i edycja, `LinkForm.vue`): użytkownik z uprawnieniem widzi pole aliasu z podglądem pełnego adresu (host z propa serwerowego `mainHost`, bez `window` ze względu na SSR) oraz przy edycji krótką informację, że zmiana aliasu wyłącza poprzedni alias, a standardowy kod nadal działa. Pozostali widzą zablokowane pole z odnośnikiem do pakietów. Dotyczy to też formularza admina tworzącego link dla klienta. Strona edycji dostaje prop `canSetAlias` i `mainHost`.

## Przekierowanie

Regex trasy `{code}`: `[0-9A-Za-z_-]+` (zrobione w Task 2). `RedirectLinkController` wyszukuje link po `code = X` **lub** `alias = X` w obrębie domeny (`null` = domena główna), z zachowaniem scope'a `active`. Dzięki jednej przestrzeni nazw wynik jest jednoznaczny.

## Wyszukiwanie i filtry listy linków

Obsługa w istniejącym `LinkController::index`, bez nowej trasy. Parametry zapytania:
- `search` — `LIKE` po tytule, kodzie, aliasie i `original_url` (znaki `%` i `_` w frazie są escapowane; warunek `filled()`, żeby fraza `0` działała);
- `status` — `active`, `inactive`, `expired` albo `disabled` (wyłączony przez admina);
- `domain` — id domeny użytkownika albo wartość `main`.

Filtry statusu są scope'ami na modelu `Link`; scope `active` już istnieje i zostaje użyty. Wartość spoza listy w `status` jest odrzucana walidacją, a nie ignorowana po cichu. Wynik używa `withQueryString()`, więc filtry przeżywają paginację. W `links/Index.vue` wyszukiwarka ma debounce i wysyła `router.get` z `preserveState` i `replace`. Stan filtrów odczytywany jest z URL. Pusty wynik pokazuje komunikat o braku dopasowań, odrębny od pustej listy bez linków.

Lista pokazuje kod, a pod nim alias, jeśli jest ustawiony. Zakres filtrów obejmuje wyłącznie listę użytkownika. Panel admina zostaje bez zmian.

## Testy (Pest, feature)

Alias:
- link z aliasem jest dostępny pod kodem **i** pod aliasem, oba przekierowują na `original_url` (także alias z `-` i `_`); link bez aliasu działa pod kodem;
- odrzucenie z błędem na `alias`: za krótki, za długi, znak spoza wzorca, zarezerwowany (w tym `Admin`), duplikat innego aliasu w domenie, równy kodowi innego linku w domenie;
- generator nie zwraca kodu równego istniejącemu aliasowi w tej samej domenie;
- ten sam alias w dwóch różnych domenach (w tym głównej) jest dozwolony; `Promo` i `promo` na jednej domenie są dozwolone razem;
- użytkownik bez uprawnienia wysyłający alias przy tworzeniu dostaje błąd, link nie powstaje; przy edycji zmiana aliasu jest odrzucona, a edycja z niezmienionym aliasem przechodzi;
- admin tworzący link klientowi podlega uprawnieniu klienta;
- edycja zmienia alias, stary alias przestaje działać, kod działa nadal; pusty alias usuwa alias; edytowany link nie koliduje z własnym kodem ani aliasem;
- kolizja w wyścigu (`createFor` i `update`) zwraca błąd walidacji z kluczem `alias`;
- utrata uprawnienia nie psuje przekierowania istniejącego aliasu;
- `allowsCustomAlias()` dla wszystkich czterech przypadków; flaga kopiuje się do przypisania, a późniejsza edycja pakietu nie zmienia snapshotu;
- pokrycie zarezerwowanych słów: trasy z routera i katalogi z `public/`;
- `currentLength()` ignoruje aliasy.

Filtry:
- `search` po tytule, kodzie, aliasie i fragmencie `original_url`, w tym fraza ze znakami `%` i `_` oraz fraza `0`;
- każdy status osobno i połączony z `search` oraz `domain`; błędny `status` zwraca błąd walidacji;
- filtry zachowują się na kolejnej stronie paginacji; prop `filters` zwraca przekazane wartości;
- użytkownik widzi tylko własne linki.

Zmiany czysto wizualne (układ formularza, wygląd paska filtrów) nie wymagają osobnych testów.
