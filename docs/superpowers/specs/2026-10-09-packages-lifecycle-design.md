# Cykl życia pakietów: edycja, wycofanie i odnowienie – design (rozszerzenie etapu 1)

Rozszerza `2026-10-09-packages-design.md`. Tam, gdzie oba dokumenty się różnią, obowiązuje ten.

## Cel

Zmiany pakietu (edycja, usunięcie) nie wpływają na użytkowników w trakcie opłaconego okresu. Obowiązują od ich następnego okresu rozliczeniowego. Okresy cykliczne odnawiają się automatycznie (bez płatności; płatności dochodzą w etapie 2).

## Ustalenia

- Pakiet domyślny (Free) nie może zostać usunięty ani wycofany.
- Tylko jeden pakiet może być domyślnym, gwarantowane także przez bazę danych.
- Edycja pakietu (limit, ceny) obowiązuje przypisania od ich następnego okresu. Pakiet domyślny nie ma okresów, więc jego edycja działa od razu.
- Usunięcie pakietu z przypisaniami wycofuje go: obecne okresy dobiegają końca na starych warunkach, potem użytkownik przechodzi na Free. Administrator nie wybiera pakietu zastępczego.
- Wybór między Free a pakietem płatnym należy do użytkownika końcowego i powstanie w etapie 2 razem z płatnościami (Cashier, Stripe sandbox). Do tego czasu płatny pakiet przypisuje ręcznie super administrator.
- Okresy cykliczne (miesięczny, kwartalny, roczny) odnawiają się automatycznie w dniu wygaśnięcia. Przypisania jednorazowe (stały okres) nie odnawiają się.

## Model danych (zmiany)

### `package_assignments`

Nowa kolumna `link_limit` (unsigned integer): migawka limitu pakietu z początku bieżącego okresu. Razem z istniejącą migawką `price` opisuje warunki obowiązujące w tym okresie. Migracja uzupełnia kolumnę dla istniejących wierszy wartością z ich pakietu.

### `packages`

- `SoftDeletes` (`deleted_at`). Wycofany pakiet znika z list i nie można go przypisać, ale wiersz zostaje, dopóki przypisania do niego wskazują (klucz obcy `restrictOnDelete` pozostaje).
- Gwarancja jednego domyślnego w bazie: kolumna generowana `default_marker` (1 gdy `is_default` jest prawdą i pakiet nie jest wycofany, w przeciwnym razie `NULL`) z indeksem unikalnym. Druga próba ustawienia domyślnego kończy się błędem bazy. Zgodność z bazą testową i mysql do sprawdzenia przy implementacji; jeśli któraś nie obsługuje kolumny generowanej z indeksem, zastępnik: `lockForUpdate` w transakcji samego przełączenia.

## Reguły domenowe (zmiany)

- Limit pakietu efektywnego dla aktywnego przypisania pochodzi z `package_assignments.link_limit`, nie z `packages.link_limit`. Aktywne przypisanie ma też nazwę swojego (być może wycofanego) pakietu.
- Przypisanie jest aktywne, gdy `starts_at` ≤ teraz < `expires_at` (jak w poprawce po przeglądzie etapu 1).
- Brak aktywnego przypisania → pakiet domyślny (bez zmian).

## Zachowania

### Edycja

`PackageController::update` zmienia tylko tabelę `packages`. Przypisania zachowują migawkę do odnowienia. Edycja pakietu domyślnego jest natychmiastowa, bo użytkownicy bez przypisania czytają jego limit bezpośrednio.

### Usunięcie

- Pakiet domyślny: odmowa (błąd walidacji), jak teraz.
- Pakiet bez przypisań: usuwany od razu.
- Pakiet z przypisaniami: miękkie usunięcie (wycofanie). Formularz potwierdzenia pokazuje liczbę dotkniętych użytkowników i wyjaśnia, że przejdą na Free po zakończeniu bieżącego okresu. Wycofanego pakietu nie da się przypisać ani edytować.

### Odnowienie

Nowe polecenie `packages:renew`, uruchamiane co godzinę przez scheduler Laravela. Dla każdego przypisania cyklicznego z `expires_at` ≤ teraz:

1. Pakiet wycofany, albo cena okresu w pakiecie zniknęła → przypisanie jest usuwane, użytkownik wraca do Free.
2. W przeciwnym razie nowy okres startuje od poprzedniego `expires_at`, z aktualnym `link_limit` pakietu i ceną okresu zapisanymi jako nowa migawka. Jeśli polecenie nie działało przez kilka okresów, przesuwa się okres po okresie, aż `expires_at` będzie w przyszłości. Nie nalicza opłat (etap 2).

Przypisania jednorazowe po wygaśnięciu przestają być aktywne (bez usuwania wiersza), jak dotąd. Polecenie jest idempotentne i wykonuje każdy wiersz w osobnej transakcji.

### Komunikat dla użytkownika

Gdy aktywne przypisanie wskazuje wycofany pakiet, lista linków pokazuje baner: „Pakiet <nazwa> zostaje wycofany. Od <data wygaśnięcia> obowiązuje pakiet Free.” Dane banera dostarcza `LinkController::index` (`packageNotice`).

## Frontend

- Strona pakietów: dialog usunięcia z liczbą dotkniętych użytkowników; edycja z podpowiedzią „Zmiany obowiązują od następnego okresu rozliczeniowego użytkowników” (dla pakietu domyślnego „obowiązują od razu”).
- Lista linków: baner o wycofaniu pakietu.
- Karta pakietu na stronie użytkownika pokazuje limit z migawki oraz oznaczenie „wycofany”, gdy dotyczy.

## Testy (Pest)

- Edycja pakietu nie zmienia limitu aktywnego przypisania; po `packages:renew` w dniu wygaśnięcia migawka jest odświeżona.
- Edycja pakietu domyślnego zmienia limit użytkowników bez przypisania od razu.
- Usunięcie pakietu z przypisaniami nie zmienia ich limitu; wycofany pakiet nie pojawia się na liście ani w opcjach przypisania; po odnowieniu użytkownik wraca na Free.
- Usunięcie i wycofanie pakietu domyślnego jest odrzucone.
- Odnowienie: nowy okres startuje od poprzedniego `expires_at`; pominięte okresy są nadrabiane; brak ceny okresu kończy przypisanie; przypisanie jednorazowe się nie odnawia; ponowne uruchomienie nic nie zmienia.
- Dwa pakiety domyślne naraz kończą się błędem bazy; przełączenie domyślnego pozostawia dokładnie jeden.
- Baner pojawia się tylko, gdy aktywne przypisanie wskazuje wycofany pakiet.

## Poza zakresem

Ekran wyboru Free lub płatnego pakietu przez użytkownika, naliczanie opłat i faktury (etap 2, Cashier).
