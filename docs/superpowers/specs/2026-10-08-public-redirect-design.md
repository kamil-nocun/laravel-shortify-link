# Publiczny redirect po kodzie: `GET /{code}`

Data: 2026-10-08

## W skrócie

- Wejście na `short_url` przekierowuje (302) na `original_url`.
- Link nieaktywny, wygasły lub nieistniejący daje jedną stronę 404 (Inertia/Vue).
- Redirect to czysty 302 z backendu. Vue odpowiada tylko za stronę 404.

## Zakres

| W zakresie | Poza zakresem |
|---|---|
| Publiczna trasa `GET /{code}` | Liczniki i statystyki kliknięć |
| Egzekwowanie `is_active` i `expires_at` | Osobne komunikaty dla wygasłego i nieaktywnego |
| Strona 404 w Vue | Cache |
| Testy Pest + jeden test E2E | |

Spec CRUD linków wyłączył egzekwowanie `is_active` i `expires_at`. Ten spec je wprowadza.

## Założenia

- 302, nie 301. 301 jest cache'owany w przeglądarkach i ignorowałby późniejszą dezaktywację lub wygaśnięcie linku.
- Wszystkie przypadki odmowy dają ten sam 404, więc odpowiedź nie zdradza, czy kod istnieje.
- `code` rozróżnia wielkość liter (kolumna `utf8mb4_bin`).

## Backend

- **Trasa:** `Route::get('{code}', RedirectLinkController::class)->name('links.redirect')`, publiczna (bez `auth`). Rejestrowana **na końcu** `routes/web.php`, z `where('code', '[0-9A-Za-z]+')` (alfabet `LinkCodeGenerator`). Nie koliduje z `login`, `dashboard`, `links`.
- **Kontroler:** `RedirectLinkController`, jedna akcja `__invoke(string $code)`. `Link::active()->where('code', $code)->firstOrFail()`, potem `redirect()->away($link->original_url)`.
- **Scope:** `Link::scopeActive`: `is_active = true` i (`expires_at` null lub `expires_at > now()`).
- **Odpowiedź 404:** `ModelNotFoundException` z tej trasy jest renderowany w `bootstrap/app.php` jako strona Inertia z kodem 404, tylko dla `routeIs('links.redirect')`. Pozostałe 404 bez zmian.

## Frontend

- Nowa strona `resources/js/pages/errors/NotFound.vue`, w stylu istniejących stron, z linkiem do `/`.
- Teksty przez istniejący mechanizm tłumaczeń.

## Testy

Pest (`LinkRedirectTest`):
- aktywny link: 302 na `original_url`;
- nieaktywny, wygasły i nieistniejący: 404 ze stroną Inertia `errors/NotFound`;
- kod z inną wielkością liter: 404;
- link z `expires_at` w przyszłości działa;
- `/login` i `/dashboard` nadal działają (brak kolizji trasy).

Playwright (`tests/e2e`): jeden przepływ: aktywny link przekierowuje, nieaktywny pokazuje stronę 404.
