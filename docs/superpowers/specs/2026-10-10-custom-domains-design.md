# Customowe domeny dla linków — projekt

## Cel

Użytkownik może podpiąć własną domenę i tworzyć na niej krótkie linki. Pakiet Free daje 0 domen, pakiety płatne mają limit domen ustawiany w pakiecie.

## Decyzje

- Link ma opcjonalną `domain_id`. Bez domeny działa na domenie głównej (`APP_URL`), z domeną na jej hoście. Ten sam `code` może istnieć na różnych domenach.
- Własność domeny potwierdza rekord DNS TXT z tokenem. Domena jest aktywna dopiero po weryfikacji.
- Limit domen blokuje tylko dodawanie nowych. Po spadku limitu (wygaśnięcie lub zmiana pakietu) istniejące domeny działają dalej.
- `domain_limit` w pakiecie ustawia wyłącznie super admin.
- Admin w swoim zakresie (`visibleTo`) może dodawać i usuwać domeny użytkowników oraz je blokować.

## Dane

### Tabela `domains`

UUID PK, `user_id` (FK), `host` (unikalny globalnie, małe litery), `verification_token`, `verified_at` (nullable), `disabled_by_admin_at`, `disabled_by` (jak w `links`), znaczniki czasu.

### Zmiany w istniejących tabelach

- `links.domain_id`: nullable FK, `nullOnDelete`. Unikalność kodu zmienia się z globalnej na parę (`domain_id`, `code`). Dla `domain_id = NULL` unikalność pilnuje walidacja, bo NULL-e nie kolidują w indeksie unikalnym.
- `packages.domain_limit` i `package_assignments.domain_limit`: domyślnie 0. Wartość z pakietu jest kopiowana do przypisania w momencie przypisania (tak jak `link_limit`).

### Limit

- `User::domainLimit()` działa jak `linkLimit()`: super admin ma `null` (bez limitu), w innych przypadkach limit z aktywnego przypisania, a gdy go brak, z pakietu domyślnego.
- `User::canAddDomain()` porównuje liczbę domen użytkownika z limitem.
- Usunięcie domeny jest blokowane, gdy ma linki. Użytkownik musi je najpierw usunąć lub przenieść, co zapobiega kolizji kodów po powrocie linków na domenę główną.

## Weryfikacja DNS

- Po dodaniu domeny użytkownik dostaje instrukcję: rekord TXT `_shortify-verify.<host>` z `verification_token` oraz CNAME (lub A) na host aplikacji. Wartość CNAME pochodzi z `config/shortify.php` (`cname_target`).
- `DomainVerifier` używa `dns_get_record()` i sprawdza wyłącznie TXT. CNAME jest tylko wskazówką dla użytkownika.
- Wywołanie: przycisk „Sprawdź ponownie" (`POST domains/{domain}/verify`) oraz zadanie kolejki `VerifyDomain`, które ponawia próbę co kilka minut przez 72 godziny od dodania.
- `DomainVerifier` jest wstrzykiwany, a w testach podmieniany na fałszywą implementację (jak `FakePackageBilling`).

## Routing

- Trasa `{code}` pozostaje jedna. `RedirectLinkController` czyta `$request->getHost()`:
  - host równy hostowi z `APP_URL`: link z `domain_id IS NULL`,
  - inny host: aktywna domena (`verified_at` ustawione, `disabled_by_admin_at` puste, właściciel niezablokowany), potem link z tą `domain_id`,
  - brak domeny: 404.
- Scope `Link::active()` uwzględnia aktywność domeny linku.
- `Link::short_url` używa hosta domeny (schemat z `APP_URL`), gdy `domain_id` jest ustawione.
- Endpoint dla proxy: `GET /internal/domain-check?domain=<host>` zwraca 200 dla aktywnej domeny, inaczej 404. Dostęp chroniony sekretem w nagłówku (wartość z `.env`).

## Interfejs

### Użytkownik

- Strona `domains` (Inertia/Vue): lista domen ze statusem (oczekuje / aktywna / zablokowana przez admina), instrukcja DNS, „Sprawdź ponownie", usuwanie, licznik `użyte/limit`. Przy limicie 0 zamiast formularza komunikat z linkiem do strony pakietu.
- Formularz linku ma wybór domeny: domena główna oraz aktywne domeny użytkownika.
- `DomainPolicy`: właściciel zarządza swoimi domenami. `StoreDomainRequest` waliduje format hosta, unikalność, zakaz hosta aplikacji i `canAddDomain()`.

### Admin

- Sekcja domen w `admin/users/Show.vue`: dodawanie i usuwanie domeny w imieniu użytkownika (z respektowaniem jego limitu) oraz blokowanie i odblokowywanie (`disabled_by_admin_at`).
- Trasy `admin/users/{user}/domains` i `admin/domains/{domain}/disable`, wzorowane na `UserLinkController` i `LinkDisableController`.
- Pole `domain_limit` w formularzu pakietu (`StorePackageRequest`) widoczne i edytowalne tylko dla super admina.
- Strona pakietu i `PackageOverview` pokazują linię „Domeny: x / y".

## Testy (Pest, feature)

- Limit: Free (0) nie doda domeny, płatny doda do limitu, spadek limitu nie wyłącza istniejących domen.
- Weryfikacja z fałszywym `DomainVerifier`: sukces, brak rekordu, koniec ponawiania po 72 godzinach.
- Redirect po hoście: domena główna, domena użytkownika, niezweryfikowana, zablokowana, nieznana, ten sam `code` na dwóch domenach.
- Blokada usunięcia domeny z linkami.
- Uprawnienia: cudza domena, admin spoza zakresu, `domain_limit` tylko dla super admina.
- Endpoint `domain-check` z poprawnym sekretem i bez niego.

## Poza zakresem

Certyfikaty TLS i proxy, domeny wildcard, przenoszenie linków między domenami.
