# Pakiety i limity linków – design (etap 1)

## Cel

Wprowadzić mechanizm pakietów, który ogranicza liczbę skróconych linków użytkownika. Pakietami zarządza wyłącznie super administrator. Etap 1 obejmuje ręczne przypisywanie pakietów; płatności (Laravel Cashier, Stripe w trybie sandbox) są poza zakresem i powstaną w etapie 2.

## Ustalenia

- Pakiety tworzy, edytuje, usuwa i przypisuje wyłącznie super administrator.
- Pakiet przypisuje się do konkretnego użytkownika lub administratora. Jeden pakiet na użytkownika.
- Konto super administratora nie wymaga pakietu i nie ma limitu.
- Pakiet ma nazwę, limit linków oraz osobną cenę dla każdego okresu rozliczeniowego.
- Okresy: jednorazowo (na stały okres z datą wygaśnięcia), miesięcznie, kwartalnie, rocznie. Okres wybiera super administrator przy przypisaniu.
- Użytkownik bez pakietu lub z wygasłym pakietem korzysta z pakietu domyślnego (flaga `is_default`). Jeśli brak pakietu domyślnego, limit wynosi 0.
- Po przekroczeniu limitu blokowane jest wyłącznie tworzenie nowych linków. Istniejące linki działają i można nimi zarządzać.
- Brak automatycznego odnowienia w etapie 1.

## Model danych

Wszystkie tabele z kluczami UUID.

### `packages`

| Kolumna | Typ | Uwagi |
|---|---|---|
| `id` | uuid | PK |
| `name` | string | |
| `link_limit` | unsigned integer | |
| `price_one_time` | unsigned integer, nullable | grosze |
| `price_monthly` | unsigned integer, nullable | grosze |
| `price_quarterly` | unsigned integer, nullable | grosze |
| `price_yearly` | unsigned integer, nullable | grosze |
| `is_default` | boolean | co najwyżej jeden pakiet |
| `created_at`, `updated_at` | timestamps | |

Pusta cena oznacza, że okres jest niedostępny dla pakietu. Pakiet domyślny może nie mieć żadnej ceny; wtedy nie da się go przypisać ręcznie i działa tylko jako fallback.

### `package_assignments`

| Kolumna | Typ | Uwagi |
|---|---|---|
| `id` | uuid | PK |
| `user_id` | uuid | FK, unikalny (jeden pakiet na użytkownika) |
| `package_id` | uuid | FK, restrict |
| `billing_period` | string | enum `BillingPeriod` |
| `price` | unsigned integer | migawka ceny wybranego okresu w chwili przypisania |
| `starts_at` | timestamp | |
| `expires_at` | timestamp | |
| `created_at`, `updated_at` | timestamps | |

Przypisanie nowego pakietu zastępuje istniejący wiersz użytkownika. Dla okresów cyklicznych `expires_at` = `starts_at` + okres (miesiąc, 3 miesiące, rok). Dla `one_time` super administrator podaje datę wygaśnięcia ręcznie.

### Enum `BillingPeriod`

`OneTime`, `Monthly`, `Quarterly`, `Yearly`, z metodą wyliczającą datę wygaśnięcia i mapowaniem na kolumnę ceny pakietu.

## Reguły domenowe

Pakiet efektywny użytkownika (`User::package()`):

1. super administrator → brak pakietu i brak limitu (`linkLimit()` zwraca `null`),
2. aktywne przypisanie (`expires_at` w przyszłości) → pakiet z przypisania,
3. w przeciwnym razie → pakiet z `is_default`,
4. brak pakietu domyślnego → limit 0.

`User::canCreateLink()`: liczba wszystkich linków użytkownika jest mniejsza od limitu (lub brak limitu). Wygaśnięcie jest liczone przy odczycie, bez crona.

## Backend

- Modele `Package`, `PackageAssignment` (UUID, fabryki, seedery tam, gdzie pasują do istniejących konwencji).
- `Admin\PackageController` (index, create, store, edit, update, destroy) oraz `Admin\UserPackageController` (`update` przypisuje, `destroy` zdejmuje przypisanie). Dostęp tylko dla super administratora, w stylu istniejących kontrolerów panelu admina (policy lub sprawdzenie `isSuperAdmin()`).
- Form Requests: wybrany okres musi mieć cenę w pakiecie; co najwyżej jeden pakiet z `is_default` (zmiana flagi w transakcji).
- Usunięcie pakietu używanego w przypisaniach lub domyślnego kończy się błędem walidacji.
- `LinkPolicy::create` korzysta z `canCreateLink()`. `LinkController::create` i `store` wywołują `Gate::authorize('create', Link::class)`. Przekroczenie limitu daje 403 z czytelnym komunikatem. Edycja, wyłączanie i usuwanie istniejących linków bez zmian.

## Frontend (Inertia + Vue)

- `admin/packages/Index|Create|Edit`: lista z limitem i cenami, formularz z czterema polami cen.
- Strona użytkownika w panelu: karta „Pakiet” widoczna tylko dla super administratora. Formularz: select pakietu, radio okresu (tylko okresy z ceną), data startu (domyślnie dziś), data wygaśnięcia ręczna wyłącznie dla jednorazowego. Dla super administratora jako użytkownika komunikat „bez limitu”.
- Lista linków użytkownika: licznik „X z Y linków”; przycisk „Nowy link” wyłączony z podpowiedzią po osiągnięciu limitu.
- Pozycja menu „Pakiety” tylko dla super administratora.
- Trasy przez Wayfinder.

## Testy (Pest)

- Pakiet efektywny: super administrator, aktywne przypisanie, wygasłe, domyślny, brak domyślnego.
- Limit: blokada tworzenia po osiągnięciu, istniejące linki nadal działają.
- Autoryzacja: administrator i użytkownik dostają 403 na zarządzaniu pakietami i przypisaniami.
- Przypisanie: wyliczanie `expires_at` dla każdego okresu, odrzucenie okresu bez ceny, zastąpienie poprzedniego przypisania, migawka ceny.
- Usuwanie pakietu: blokada dla używanego i domyślnego.

## Poza zakresem etapu 1

Bramka płatności, samoobsługowy zakup, automatyczne odnowienia, faktury.

## Etap 2: Laravel Cashier (uwagi na przyszłość)

- Bramka: Stripe w trybie sandbox (test mode) przez Laravel Cashier.
- `package_assignments` pozostaje źródłem prawdy o pakiecie użytkownika. Webhooki Cashiera (utworzenie, odnowienie, anulowanie subskrypcji) będą tworzyć i aktualizować przypisanie.
- Okresy cykliczne zmapują się na ceny Stripe; do `packages` dojdą identyfikatory cen Stripe (`stripe_price_id` per okres) dopiero w etapie 2.
- Ryzyko: migracje Cashiera domyślnie używają `foreignId('user_id')`, a klucze użytkowników są UUID. Po instalacji trzeba je opublikować i zmienić na `foreignUuid` oraz ustawić `Cashier::useCustomerModel`.
- Cashier i kolumny Stripe nie są instalowane w etapie 1; zmiana zależności wymaga zgody.
