# Historia płatności – design

Rozszerza `2026-10-09-cashier-purchase-design.md`. Tam, gdzie dokumenty się różnią, obowiązuje ten.

## Cel

Pokazać, kto, kiedy i ile zapłacił za pakiet oraz w jakim stanie jest faktura. Zakres widoczności jest taki sam jak przy linkach do płatności:

- użytkownik widzi wyłącznie własną historię,
- administrator widzi własną historię i historię swoich klientów,
- super administrator widzi historię wszystkich kont.

Poza zakresem: zwroty, windykacja, eksport PDF (etap 2b).

## Ustalenia

- Źródłem danych jest lokalna tabela zasilana webhookami Stripe oraz jednorazowy backfill starszych faktur ze Stripe.
- Do historii trafiają faktury we wszystkich stanach: wystawione, opłacone, nieudane, anulowane i nieściągalne. Szkice i faktury na 0 zł są pomijane.
- Jeśli płatność ma zapisany `invoice_url`, korzystamy z niego: link „Pay invoice” pochodzi z najnowszej otwartej płatności w tabeli i nie powoduje zapytania do API Stripe przy wejściu na stronę. `PackageBilling::pendingInvoiceUrl` jest usuwany.
- Stripe jest dalej dostępny wyłącznie przez `PackageBilling`; testy nie wywołują prawdziwego API.

## Model danych

Tabela `payments` (klucze UUID, w tym `user_id` i `package_id` jako `foreignUuid`):

| Kolumna | Opis |
|---|---|
| `user_id` | właściciel faktury, kasowanie kaskadowe |
| `stripe_invoice_id` | unikalny identyfikator faktury; klucz upsertu |
| `package_id` | pakiet rozpoznany po cenie faktury (nullable) |
| `billing_period` | okres rozpoznany po cenie faktury (nullable) |
| `status` | `open`, `paid`, `payment_failed`, `void`, `uncollectible` (enum `PaymentStatus`) |
| `amount` | kwota w groszach |
| `currency` | waluta |
| `invoice_url` | adres strony faktury hostowanej przez Stripe |
| `issued_at` | data wystawienia |
| `paid_at` | data zapłaty (nullable) |
| `stripe_event_at` | czas zdarzenia, które ostatnio zmieniło wiersz |

## Webhooki

Nowy listener na `WebhookReceived` obsługuje `invoice.finalized`, `invoice.payment_succeeded`, `invoice.payment_failed`, `invoice.voided` i `invoice.marked_uncollectible`.

- Każde zdarzenie robi upsert po `stripe_invoice_id` z pełnego obiektu faktury.
- Zdarzenie o czasie starszym niż `stripe_event_at` wiersza jest ignorowane, więc kolejność dostarczenia nie psuje stanu. To zastępuje tabelę `stripe_events` dla historii.
- Faktura nieznanego klienta daje odpowiedź 200 i ostrzeżenie w logu (jak w przypadku przypisań).
- Mapowanie ceny Stripe na `(Package, BillingPeriod)` jest wspólne z `ProjectStripeEvents`: wyszukiwanie po kolumnach `stripe_price_id_*` (także wycofanych pakietów) wyciągamy do jednego miejsca.

## Backfill

- `PackageBilling::invoicesFor(User): iterable` zwraca faktury klienta ze Stripe jako tablice w kształcie obiektu faktury.
- Komenda `payments:backfill` przechodzi po użytkownikach z `stripe_id` i zapisuje ich faktury tym samym mapperem co webhooki. Jest idempotentna.

## Widoki

Tabela „Payment history”: data, pakiet i okres, kwota, status, link do faktury (dla stanu `open` – „Pay”). Ostatnie 20 wpisów, bez paginacji.

- Strona „My package”: własna historia.
- Strona `admin/users/{id}`: historia konta według polityki `manages` (administrator widzi swoich klientów, super administrator wszystkich). Konto poza zakresem pozostaje niedostępne, jak dotychczas.

## Testy

- Mapowanie każdego statusu z webhooka na wiersz `payments`.
- Zdarzenie starsze niż zapisany stan nie nadpisuje wiersza.
- Pominięcie faktury na 0 zł i szkicu.
- Nieznany klient: 200, brak zapisu.
- Backfill: zapisuje faktury i jest idempotentny (atrapa `invoicesFor`).
- Widoczność: użytkownik tylko własna historia, administrator własna i klientów, super administrator wszystkie, brak dostępu do kont poza zakresem.
- Link „Pay invoice” pochodzi z tabeli, bez wywołania Stripe.
