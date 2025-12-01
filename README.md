# Chatboty DeepSeek

Repozytorium zawiera dwa chatboty front-endowe współpracujące z proxy PHP do DeepSeek API (domyślny model: **deepseek-reasoner**):

- **enigma_ebook.html** – generator treści ebooka.
- **enigma_designer.html** – projektant landing page z obsługą długich odpowiedzi (do ~2400 linii kodu) i narzędziami kopiowania/pobierania bloków kodu.

Skrót najważniejszych parametrów modelu Reasoner znajdziesz w pliku `deepseek_reasoner_notes.md`.

## Wymagania serwera backend (deepseek_proxy.php)
- PHP 8.0+ z rozszerzeniem cURL.
- Dostęp wychodzący HTTPS do `https://api.deepseek.com`.
- Klucz API ustawiony w zmiennej środowiskowej `DEEPSEEK_API_KEY` (zalecane) lub wpisany w pliku.

## Uruchomienie na serwerze współdzielonym (np. home.pl)
1. Wgraj na serwer statyczne pliki frontendu (`enigma_designer.html`, opcjonalnie `enigma_ebook.html`) oraz plik `deepseek_proxy.php` do tego samego katalogu.
2. Skonfiguruj klucz API:
   - Jeżeli hosting pozwala na zmienne środowiskowe (np. przez panel lub `.htaccess`), dodaj `SetEnv DEEPSEEK_API_KEY your_api_key_here`.
   - Alternatywnie wpisz własny klucz bezpośrednio w `deepseek_proxy.php` (zamień wartość zmiennej `$apiKey`), pamiętając o ryzyku ujawnienia klucza.
3. Upewnij się, że `deepseek_proxy.php` jest dostępny publicznie przez HTTPS (np. `https://twojadomena.pl/deepseek_proxy.php`). Jeśli serwer blokuje metody POST/OPTIONS, włącz je w konfiguracji lub skontaktuj się z obsługą hostingu.
4. Otwórz `enigma_designer.html` w przeglądarce i użyj parametru `?proxy=https://twojadomena.pl/deepseek_proxy.php`, aby wymusić własny endpoint backendu (adres zostanie też zapamiętany w lokalnym storage).

## Wskazówki testowe
- Jeśli podczas hostingu statycznego (GitHub Pages itp.) widzisz błąd 404/405 dla PHP, ustaw `proxy` na działający serwer z PHP.
- Logi błędów API zapisują się w `deepseek_errors.log` obok pliku proxy.

