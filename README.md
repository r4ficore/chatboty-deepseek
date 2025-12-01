# Enigma Designer (Gemini 3)

Repozytorium zawiera front-endy zasilane modelem Gemini (domyślnie **gemini-3.0-pro**) przez proxy PHP:

- **enigma_designer.html** – projektant landing page z obsługą długich odpowiedzi i narzędziami kopiowania/pobierania kodu.
- **enigma_ebook.html** – generator treści ebooka (pozostawiony dla kompatybilności; wymaga własnego proxy lub aktualizacji do Gemini).

## Wymagania serwera backend (gemini_proxy.php)
- PHP 8.0+ z rozszerzeniem cURL.
- Dostęp wychodzący HTTPS do `https://generativelanguage.googleapis.com`.
- Klucz API w zmiennej środowiskowej `GEMINI_API_KEY` (zalecane). Nie zapisuj tajnych kluczy w repozytorium.
  - Opcjonalnie (tylko do testów) możesz przekazać `api_key` w payloadzie POST do `gemini_proxy.php`.
  - Proxy posiada awaryjny klucz testowy zgodny z wytycznymi klienta; produkcyjnie nadpisz go własnym `GEMINI_API_KEY`.

## Uruchomienie na serwerze współdzielonym (np. home.pl)
1. Wgraj pliki frontendu (`enigma_designer.html`, opcjonalnie `enigma_ebook.html`) oraz `gemini_proxy.php` do tego samego katalogu.
2. Skonfiguruj klucz API, np. w `.htaccess` (w repo znajduje się plik `.htaccess` z placeholderem):
   - `SetEnv GEMINI_API_KEY your_api_key_here`
3. Upewnij się, że `gemini_proxy.php` jest dostępny publicznie przez HTTPS (np. `https://twojadomena.pl/gemini_proxy.php`). Jeśli serwer blokuje metody POST/OPTIONS, włącz je w konfiguracji lub skontaktuj się z obsługą hostingu.
4. Otwórz `enigma_designer.html` w przeglądarce i użyj parametru `?proxy=https://twojadomena.pl/gemini_proxy.php`, aby wymusić własny endpoint backendu (adres zostanie też zapamiętany w lokalnym storage).

## Wskazówki testowe
- Jeśli podczas hostingu statycznego (GitHub Pages itp.) widzisz błąd 404/405 dla PHP, ustaw `proxy` na działający serwer z PHP.
- Logi błędów API zapisują się w `gemini_errors.log` obok pliku proxy.

## Prompt systemowy dla Gemini 3
- Centralny prompt używany przez Enigma Designer jest zdefiniowany w pliku `designer_config.js` jako stała `DESIGNER_GEMINI_SYSTEM_PROMPT`.
- Frontend wstrzykuje go do zapytań jako wiadomość systemowa (patrz dolny skrypt w `enigma_designer.html`).
- Aby zmienić prompt, edytuj treść w `designer_config.js`; to jedyne źródło prawdy wykorzystywane przez aplikację i backend.

