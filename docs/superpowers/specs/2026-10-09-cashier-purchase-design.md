# Płatności: zakup pakietów przez Laravel Cashier (etap 2a) – design

Rozszerza `2026-10-09-packages-design.md` i `2026-10-09-packages-lifecycle-design.md`. Tam, gdzie dokumenty się różnią, obowiązuje ten. Obejmuje zakup i webhooki; rezygnacja, zmiana pakietu w trakcie i Billing Portal to etap 2b (osobny spec).

## Cel

Użytkownik kupuje płatny pakiet przez Stripe (tryb sandbox, bez prawdziwych pieniędzy). Administrator przypisuje pakiet, tworząc w Stripe prawdziwą subskrypcję i płatność w imieniu użytkownika. Stripe odnawia okresy cykliczne, a webhooki aktualizują uprawnienia użytkownika.

## Ustalenia

- Płatności przez Laravel Cashier (Stripe), wyłącznie w trybie testowym (klucze `sk_test_…`).
- Stripe/Cashier jest źródłem prawdy o odnowieniach. Polecenie `packages:renew` i jego harmonogram są usuwane.
- Przypisanie pakietu przez administratora nie jest już natychmiastowe ani bezpłatne: tworzy subskrypcję Stripe z fakturą wysyłaną użytkownikowi. Pakiet aktywuje się po opłaceniu. Do tego czasu użytkownik ma pakiet domyślny. Przyznanie pakietu gratis nie jest częścią tego etapu.
- `package_assignments` zostaje tabelą uprawnień (projekcją subskrypcji): limit, cena, okres, daty. Pakiet efektywny i limit liczone są jak dotąd z aktywnego przypisania.
- Pakiet jednorazowy (`one_time`) to płatność jednorazowa ważna 1 miesiąc kalendarzowy, bez odnowienia; daty nie są edytowalne ręcznie.
- Super administrator nie kupuje i nie dostaje pakietu.
- Zachowują się reguły wcześniejszych specyfikacji: jeden pakiet na użytkownika, edycja i wycofanie pakietu obowiązują od następnego okresu, tylko jeden pakiet domyślny.

## Zależności i konfiguracja

- Dodanie pakietu `laravel/cashier` (zgoda użytkownika na nową zależność udzielona przy akceptacji designu).
- Konfiguracja w `.env`: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `CASHIER_CURRENCY=pln`. Klucze tylko testowe; aplikacja odmawia startu operacji Stripe, gdy klucz nie zaczyna się od `sk_test_` poza środowiskiem produkcyjnym.
- Klucze UUID: opublikowane migracje Cashiera zmieniamy z `foreignId('user_id')` na `foreignUuid`, a `subscriptions` i `subscription_items` dostają UUID jako klucz główny. `User` używa traitu `Billable`; kolumny `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at` dochodzą do `users`.
- Lokalnie webhooki przekazuje Stripe CLI (`stripe listen --forward-to …/stripe/webhook`).

## Model danych (zmiany)

### `packages`

Nowe kolumny (nullable): `stripe_product_id` oraz `stripe_price_id_one_time`, `stripe_price_id_monthly`, `stripe_price_id_quarterly`, `stripe_price_id_yearly`. Zapis pakietu synchronizuje obiekty Stripe:

- pierwszy zapis tworzy Product,
- okres z ceną dostaje Price (jednorazowy dla `one_time`, cykliczny dla pozostałych),
- zmiana ceny tworzy nowy Price i archiwizuje stary, więc trwające subskrypcje zostają na starym Price (zgodnie z „edycja od nowego okresu"),
- usunięcie ceny archiwizuje Price,
- wycofanie pakietu archiwizuje Product i jego ceny.

Pakiet domyślny (Free) nie ma obiektów Stripe.

### `package_assignments`

Nowa kolumna `subscription_id` (UUID, nullable, klucz obcy do `subscriptions`, `nullOnDelete`) łącząca uprawnienie z subskrypcją Stripe. Wiersze bez subskrypcji (z etapu 1) pozostają ważne do wygaśnięcia i nie są migrowane.

### Cashier

Tabele `subscriptions`, `subscription_items` zgodnie z Cashierem, w wersji z UUID. Typ subskrypcji: jeden, `default` (jeden pakiet na użytkownika).

## Granica ze Stripe: `PackageBilling`

Interfejs `PackageBilling` jest jedynym miejscem styku aplikacji ze Stripe; implementacja produkcyjna `StripePackageBilling` używa Cashiera, a testy podstawiają atrapę. Metody (sygnatury do doprecyzowania w planie):

- `syncPackage(Package $package): void` – tworzy lub aktualizuje Product i Price, zapisuje identyfikatory.
- `checkout(User $user, Package $package, BillingPeriod $period, string $successUrl, string $cancelUrl): string` – zwraca adres Stripe Checkout (subskrypcja dla okresów cyklicznych, płatność jednorazowa dla `one_time`).
- `invoice(User $user, Package $package, BillingPeriod $period): void` – tworzy subskrypcję (lub fakturę jednorazową dla `one_time`) z wysyłaniem faktury użytkownikowi (`collection_method = send_invoice`).

## Zachowania

### Zakup przez użytkownika

Nowa strona „Mój pakiet" (`/billing/package`, dla zalogowanego użytkownika, nie dla super administratora) pokazuje obecny pakiet, limit, datę wygaśnięcia i listę dostępnych pakietów (bez wycofanych, bez domyślnego) z cenami okresów. Wybór pakietu i okresu przekierowuje na Stripe Checkout. Po powrocie strona informuje, że aktywacja następuje po potwierdzeniu płatności przez Stripe. Użytkownik z aktywną subskrypcją nie kupuje drugiego pakietu w 2a (zmiana to etap 2b); strona pokazuje wtedy swój obecny pakiet.

### Przypisanie przez administratora

Karta pakietu na stronie użytkownika wywołuje `PackageBilling::invoice`. Admin widzi stan: „oczekuje na płatność" (subskrypcja `incomplete`/faktura otwarta), „aktywny", „wygasły". Ponowne przypisanie, gdy trwa aktywna subskrypcja, jest odrzucone (zmiana w 2b). Przypisywać może wyłącznie super administrator; pakiet domyślny i wycofane nie są oferowane.

### Webhooki

Trasa `POST /stripe/webhook` (wyłączona z CSRF) z weryfikacją podpisu Stripe, obsługa przez dziedziczący kontroler Cashiera. Obsługiwane zdarzenia i skutek:

- `invoice.paid` – tworzy lub odświeża `package_assignments` użytkownika: pakiet i okres wyznaczone z ceny Stripe, `link_limit` i `price` jako migawka bieżącego pakietu, `starts_at`/`expires_at` z okresu faktury (dla `one_time` start + 1 miesiąc kalendarzowy), `subscription_id`.
- `customer.subscription.updated` – aktualizuje daty okresu.
- `customer.subscription.deleted` – kończy przypisanie (użytkownik wraca do pakietu domyślnego).
- `invoice.payment_failed` – nie zmienia uprawnień w 2a; stan widoczny dla admina (obsługa windykacji to 2b).

Każde zdarzenie jest przetwarzane idempotentnie (zapis `event.id` w tabeli `stripe_events`, powtórka ignorowana). Nieznany pakiet lub użytkownik w zdarzeniu: logowanie ostrzeżenia, odpowiedź 200 (Stripe nie ponawia).

## Frontend

- Strona „Mój pakiet" z kartami pakietów i wyborem okresu; przycisk „Kup" prowadzi do Checkout.
- Karta pakietu użytkownika w panelu admina pokazuje stan płatności i przycisk „Przypisz i wyślij fakturę".
- Pozycja menu „Mój pakiet" dla zwykłych użytkowników i administratorów.
- Trasy przez Wayfinder.

## Testy (Pest)

- Synchronizacja: zapis pakietu wywołuje `syncPackage`; zmiana ceny tworzy nowy Price (na atrapie sprawdzamy wywołania); wycofanie archiwizuje.
- Zakup: strona „Mój pakiet" zwraca dostępne pakiety, `checkout` zwraca adres, super admin i użytkownik z aktywną subskrypcją są odrzucani.
- Przypisanie przez admina: wywołuje `invoice`, nie tworzy uprawnień przed zapłatą; ponowne przypisanie przy aktywnej subskrypcji odrzucone; tylko super admin.
- Webhooki (zdarzenia podpisane testowym sekretem): `invoice.paid` tworzy uprawnienie z migawką; odnowienie przesuwa daty; `subscription.deleted` kończy przypisanie i wraca pakiet domyślny; to samo zdarzenie dwa razy zmienia stan raz; błędny podpis → odrzucone; nieznany użytkownik → 200 bez zmian.
- Migracje: UUID w tabelach Cashiera.
- Ręczny test sandbox na końcu: zakup kartą testową `4242…`, webhook przez Stripe CLI, odnowienie symulowane zegarem testowym Stripe.

## Poza zakresem (etap 2b)

Rezygnacja, przejście na Free, zmiana pakietu lub okresu w trakcie (swap z proracją), Billing Portal (karta, faktury), windykacja nieudanych płatności, przyznawanie pakietu gratis.
