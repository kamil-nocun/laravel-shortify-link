# Podstrony CMS z wersjonowaniem — projekt

Data: 2026-10-10

## Kontekst i cel

Wdrożenie RODO wymaga publicznych stron (Polityka prywatności, Cookies) edytowanych bez zmian w kodzie oraz dowodu, jaka wersja dokumentu obowiązywała w dniu udzielenia zgody. Ten cykl dostarcza mechanizm podstron CMS jako fundament dla dalszych kroków RODO:

1. **Podstrony CMS** (ten dokument)
2. Prywatność odwiedzających (minimalizacja danych w `link_statistics`, retencja)
3. Prawa użytkownika (eksport i usunięcie konta)
4. Zgody i dokumenty (treści stron w CMS, rejestr zgód wskazujący wersję strony)
5. Dokumentacja zgodności

Poza zakresem tego cyklu: rejestr zgód, treści prawne, nawigacja/stopka z linkami do stron, wielojęzyczność.

## Założenia

- Jeden język, jedna treść na slug.
- Edytor WYSIWYG (TipTap), treść zapisywana jako HTML.
- Każda zmiana treści tworzy niezmienną wersję.
- Publiczny adres: `/content/{slug}`. Trasa ma dwa segmenty, a `{code}` jeden, więc nie koliduje z kodami linków i nie wymaga rezerwacji slugów.
- Nowe tabele z kluczami UUID.

## Model danych

**`pages`**
- `id` (UUID), `slug` (unikalny), `status` (enum `PageStatus`: `Draft`, `Published`), `current_version_id` (UUID, nullable FK do `page_versions`), `timestamps`.

**`page_versions`**
- `id` (UUID), `page_id` (FK, cascade), `title`, `body` (zsanityzowany HTML), `created_by` (FK do `users`, nullOnDelete), `created_at`. Bez `updated_at`: wersje są niezmienne.

Relacje: `Page hasMany PageVersion`, `Page belongsTo currentVersion`. Fabryki dla obu modeli.

## Logika

- `App\Actions\SavePageVersion`: sanitizuje `body`, tworzy `PageVersion`, ustawia `current_version_id`. Jedyna droga zapisu treści (tworzenie, edycja, przywracanie).
- `App\Support\HtmlSanitizer` (cienki wrapper na `symfony/html-sanitizer`): allowlista tagów typowych dla edytora (nagłówki, akapity, listy, pogrubienie/kursywa, linki `http/https/mailto`, cytaty, `hr`). Usuwa skrypty, atrybuty zdarzeń i `javascript:` w URL.
- Przywracanie wersji = `SavePageVersion` z treścią starej wersji (nowa wersja, historia nie jest nadpisywana).
- Publikacja = zmiana `status`. Strona nigdy nie jest publiczna bez `current_version_id`.

## Panel admina

- Uprawnienie `Permission::PagesManage` (`pages.manage`), `PagePolicy`.
- Trasy `admin/pages`: lista, tworzenie, edycja (zapis treści tworzy wersję), zmiana statusu, historia wersji z podglądem i przywracaniem.
- Cienkie kontrolery, walidacja przez Form Requests (slug: unikalny, `[a-z0-9-]+`).
- Strony Inertia/Vue w `resources/js/pages/admin/pages`, edytor jako komponent opakowujący TipTap.

## Widok publiczny

`GET /content/{slug}` (nazwa trasy `content.show`): aktualna wersja strony ze statusem `Published`. Szkic, brak wersji lub nieistniejący slug dają 404. Treść renderowana jako zsanityzowany HTML.

## Zależności (zatwierdzone, licencja MIT)

- `symfony/html-sanitizer`
- `@tiptap/vue-3`, `@tiptap/starter-kit` (oraz rozszerzenie linków, jeśli nie ma go w starter-kit)

HTMLPurifier odrzucony (LGPL).

## Bezpieczeństwo

Sanitizacja po stronie serwera przy każdym zapisie wersji; po stronie klienta nie ufamy edytorowi. Dostęp do panelu chroniony `PagesManage`.

## Testy (Pest, feature)

- zapis tworzy nową wersję i ustawia `current_version_id`; stare wersje pozostają bez zmian,
- sanitizacja usuwa `<script>`, `onclick`, `javascript:` i zachowuje dozwolone tagi,
- przywrócenie wersji tworzy nową wersję z treścią starej,
- strona opublikowana jest widoczna pod `/content/{slug}`, szkic i nieistniejący slug zwracają 404,
- autoryzacja: użytkownik bez `PagesManage` dostaje 403 w panelu,
- walidacja unikalności i formatu sluga.
