# Prawa użytkownika i zgody — projekt

Data: 2026-10-11

## Kontekst i cel

Krok 3 wdrożenia RODO (po podstronach CMS i prywatności odwiedzających). Użytkownik konta może: zobaczyć i pobrać swoje dane (art. 15 i 20), usunąć konto razem z danymi (art. 17) oraz świadomie zaakceptować regulamin i politykę prywatności, a aplikacja przechowuje dowód tej zgody wraz z wersją dokumentu.

Stan obecny (z kodu):
- Usunięcie konta istnieje (`DELETE settings/profile`, wymaga hasła): `$user->delete()` kaskadowo usuwa linki, statystyki, domeny, płatności, subskrypcje, przypisania pakietów i uprawnienia. Klient i subskrypcja w Stripe zostają, więc karta może być nadal obciążana, a dane osobowe zostają w Stripe.
- Usunięcie konta przez admina (`admin.users.destroy`) robi tylko `$user->delete()`.
- Eksportu danych nie ma.
- `CreateNewUser` nie zbiera zgody. Dokumenty (`terms`, `privacy-policy`) istnieją jako podstrony CMS z niezmiennymi wersjami (`page_versions`).

Poza zakresem: strona "Prywatność" w ustawieniach poza przyciskiem eksportu, anonimizacja płatności (płatności są kasowane lokalnie, archiwum księgowe zostaje w Stripe), opis przetwarzania w Polityce (krok 5).

## Założenia

- Wymagane dokumenty: strony `terms` i `privacy-policy`.
- Edycja dokumentu w CMS tworzy nową wersję i wymusza ponowną akceptację przez wszystkich użytkowników.
- Eksport jest generowany na żądanie i pobierany natychmiast (dane jednego konta są małe).
- Format eksportu: ZIP z osobnym plikiem CSV na zbiór danych. Bez nowych zależności (rozszerzenie PHP `zip` jest dostępne).
- Usunięcie klienta w Stripe anuluje jego subskrypcje, a faktury zostają w Stripe jako archiwum księgowe.

## Zgody

**Model danych** — `user_consents` (UUID): `id`, `user_id` (FK cascade), `page_version_id` (FK restrict; wersje nie są nigdy usuwane), `accepted_at`; unikalny `(user_id, page_version_id)`. IP i User-Agent nie są zapisywane.

**`App\Support\ConsentDocuments`:**
- `SLUGS = ['terms', 'privacy-policy']`.
- `current(): Collection<PageVersion>` zwraca aktualne wersje opublikowanych stron z listy, które mają wersję; pozostałe są pomijane (nic do zaakceptowania, nic nie blokuje).
- `pendingFor(User $user): Collection<PageVersion>` zwraca aktualne wersje, których użytkownik nie zaakceptował.
- `accept(User $user): void` zapisuje zgody na wszystkie aktualne wersje (`insertOrIgnore` po kluczu unikalnym).

**Rejestracja:** pole `accept_terms` (wymagane, `accepted`) z linkami do dokumentów w formularzu rejestracji; `CreateNewUser` po utworzeniu konta wywołuje `ConsentDocuments::accept()`. Jeśli żaden dokument nie jest opublikowany, checkbox i jego wymóg nie są wyświetlane ani walidowane.

**Wymuszenie po zalogowaniu:** middleware `App\Http\Middleware\EnsureConsentsAccepted` w grupie tras zalogowanych. Gdy `pendingFor($user)` jest niepuste, przekierowuje na `consent.show` (JSON: 409). Wyjątki, aby prawa nie zależały od zgody: `consent.show`, `consent.store`, wylogowanie, `privacy.export`, `profile.destroy`.

**Ekran zgody:** `GET consent` (`consent.show`, strona Inertia `consent/Show`) pokazuje oczekujące dokumenty (tytuł, link `content.show` w nowej karcie) i przycisk akceptacji; `POST consent` (`consent.store`) wywołuje `accept()` i przekierowuje na zamierzony adres lub dashboard. Użytkownik bez zgody może się wylogować lub usunąć konto.

## Eksport danych

`GET settings/privacy/export` (`privacy.export`, tylko zalogowany, z potwierdzeniem hasła jak przy usuwaniu konta) zwraca `shortify-export-YYYY-MM-DD.zip` z plikami CSV (UTF-8, separator `,`, nagłówki angielskie):
- `profile.csv`: nazwa, e-mail, rola, data utworzenia, data weryfikacji e-maila, aktualny pakiet,
- `links.csv`: tytuł, kod, alias, adres docelowy, domena, aktywność, wygaśnięcie, data utworzenia, liczba kliknięć,
- `domains.csv`: host, status weryfikacji, data utworzenia,
- `payments.csv`: faktura, okres, status, kwota, waluta, daty,
- `consents.csv`: tytuł dokumentu, slug, id wersji, data akceptacji.

Statystyki odwiedzających nie należą do konta i nie są eksportowane. Komórki zaczynające się od `=`, `+`, `-`, `@`, tabulatora lub CR są poprzedzane apostrofem (zabezpieczenie przed wstrzykiwaniem formuł).

Kod: `App\Support\AccountExport` (buduje zbiory wierszy), `App\Support\CsvWriter` (jedna funkcja z zabezpieczeniem komórek) i `App\Http\Controllers\Settings\PrivacyExportController`. Przycisk "Download my data" na stronie profilu obok usuwania.

## Usuwanie konta

- `PackageBilling::deleteCustomer(User $user): void` (nowa metoda interfejsu): przy braku `stripe_id` nic nie robi; w przeciwnym razie usuwa klienta w Stripe (co anuluje jego subskrypcje). Implementacja w `StripePackageBilling` i w `FakePackageBilling` (testy).
- `App\Actions\DeleteUserAccount::__invoke(User $user): void`: najpierw `deleteCustomer`, dopiero potem `$user->delete()`. Błąd Stripe przerywa operację przed jakąkolwiek zmianą w bazie (konto zostaje nietknięte) i jest zgłaszany użytkownikowi komunikatem "Could not delete the account right now, try again later".
- `ProfileController::destroy` i `Admin\UserController::destroy` używają tej akcji (admin zachowuje swoją autoryzację `delete`).

## Testy (Pest, feature)

- zgody: rejestracja bez checkboxa odrzucona, z checkboxem zapisuje zgody na aktualne wersje; brak opublikowanych dokumentów nie blokuje rejestracji; użytkownik z niezaakceptowaną wersją jest kierowany na `consent.show`, a po akceptacji ma dostęp; nowa wersja dokumentu wymusza ponowną akceptację; strona w szkicu lub bez wersji nie blokuje; wyjątki (wylogowanie, eksport, usunięcie konta) działają bez zgody; akceptacja jest idempotentna,
- eksport: ZIP zawiera pięć plików CSV z oczekiwanymi nagłówkami i wierszami tylko tego użytkownika, zabezpieczenie komórek z formułami, wymaganie zalogowania i hasła,
- usuwanie: konto z klientem Stripe wywołuje `deleteCustomer` przed usunięciem; błąd Stripe zostawia konto i dane; konto bez Stripe jest usuwane; usunięcie przez admina korzysta z tej samej akcji; zgody są usuwane razem z kontem.
