# Etap 2 – projekt funkcjonalny enigma_designer.html

## Cele i zakres
- Zaprojektować UI/UX czatu nastawionego na generowanie landing pages (mobile-first, estetyka nowoczesna, mikroanimacje/gradienty inspirowane folderem `landings/`).
- Ustalić copy startowe przypominające o wymaganym pełnym outputcie Landing Architect i o formacie odpowiedzi (blok ```html``` + komentarz poza blokiem).
- Zaplanować mechanikę długiego generowania (status „model pracuje/generuje kod na żywo” z progres/animacją) oraz wydłużone timeouty.
- Zdefiniować strukturę danych, integrację z API (`deepseek_proxy.php`) i ustawienia promptu z `enigma_designer_prompt.md`.

## Layout i UX
- **Nagłówek**: tytuł „Enigma Designer”, podtytuł „AI Landing Page Builder”; akcje w prawym górnym rogu: „Nowa sesja”, „Historia”, „Pomoc”.
- **Baner startowy**: komunikat o wymaganiu pełnego outputu LA (STYLE, mapa sekcji, treść sekcji, NOTATKI dla designera/deva). CTA „Wklej pełny output LA” + link do przykładu.
- **Chat area**: przewijany kontener max ~800–900px szerokości, wątki message bubbles (user/system/assistant) z ciemnym motywem i akcentami gradientowymi; blok kodu assistant w monospace (scroll, copy/download buttons nad blokiem).
- **Input dock**: textarea auto-resize + przycisk „Wyślij”; obok wskaźnik „model generuje” (pulsujący/animacja) oraz licznik długości (informuje o limicie ~2400 linii outputu).
- **Historia**: modal lista wcześniejszych czatów (tytuł + data); akcje „Otwórz”, „Usuń”; przy otwarciu ładuje zapisane wiadomości.
- **Stan długiego generowania**: widoczny baner „Generuję do 2400 linii kodu, proszę czekać…” + animacja shimmer i opcjonalny progres pseudo-animowany; blokuje ponowne wysłanie do czasu odpowiedzi.
- **Akcje kodu**: przy każdym bloku ```html``` przyciski „Kopiuj” (clipboard), „Pobierz .html”, „Powiększ” (toggle pełny szeroki widok). Przyciski pojawiają się na hover lub w stałym pasku narzędzi.
- **Responsywność**: mobile-first kolumnowy układ; header i input dock sticky; obsługa klawiatury (Enter=wyślij, Shift+Enter=new line); aria-label w przyciskach.

## Stany i logika
- **Start**: jeśli brak minimalnego kontekstu LA → pojedyncza prośba o wklejenie pełnego outputu (zachować w historii jako wiadomość systemowa). Nie wysyłać do API dopóki brak kompletnego wejścia.
- **Wysyłanie**: `sendToDeepSeekAI` buduje `messages = [systemPromptFromFile, ...history, {role:"user", content}]`; dodaje `context: { conversationId }` jeśli potrzebne; blokuje UI w trakcie fetch.
- **System prompt**: treść z `enigma_designer_prompt.md` wczytana jako string; zawiera zasady formatowania (blok ```html```, brak własnej treści, mapowanie stylów 1–7, max ~2400 linii).
- **Limity i timeout**: `max_tokens` podniesione (np. 8000–9000) + `timeoutMs` wydłużony (np. 180s) + `retry` przy 502/timeoutach.
- **Parsing odpowiedzi**: wykrywanie pierwszego bloku ```html``` w wiadomości; render w <pre><code> z klasą monospace; pozostały tekst (komentarz) renderowany poza blokiem. Brak oczyszczania/edytowania treści.
- **Historia**: `localStorage` (np. klucz `enigma_designer_chats`); zapisuje `messages`, datę, tytuł (pierwsze słowa usera), oraz pełny HTML bloku (do odtworzenia narzędzi kopiuj/pobierz).
- **Nowa sesja**: czyści stan, pokazuje baner startowy, resetuje ID czatu.
- **Błędy**: komunikat toast „Problem z API (kod X). Spróbuj ponownie.”; jeśli 502/timeout → pojedyncza próba ponowienia; w przypadku braku bloków kodu → informacja „Brak kodu HTML w odpowiedzi, poproś o ponowne wygenerowanie”.
- **Uploader wiedzy**: na tym etapie pomijamy; opcjonalnie placeholder na przyszłość (disabled, z opisem w pomocy).

## API i integracje
- Endpoint: `deepseek_proxy.php` POST JSON `{ model: "deepseek-reasoner", messages, max_tokens: 7800, temperature: 0.4 }` (limit API 8192).
- Header: `Content-Type: application/json`.
- Retry: max 1–2 przy 502 lub timeout; exponential backoff krótki.
- Wskaźnik pracy: zaczyna się przed `fetch`, kończy po `response` lub błędzie.
- Obsługa stream? Na razie brak (pozostawiamy fetch całości); UI ma informować o „live generation” przez animację w trakcie oczekiwania.

## Teksty i komunikaty
- Start: „Aby rozpocząć, wklej pełny output Landing Architect (STYLE, mapa sekcji, treści sekcji, NOTATKI dla designera/deva).”
- Brak kontekstu: „Potrzebuję pełnego outputu LA. Wklej go, abym mógł wygenerować kod.”
- Generowanie: „Generuję kod HTML (do ~2400 linii). Proszę czekać, model pracuje na żywo…”
- Sukces kopiowania/pobierania: toast „Skopiowano kod” / „Pobrano plik enigma-landing.html”.
- Błędy: zwięzłe, ciemne toast/alerty.

## Zadania implementacyjne (dla Etapu 3)
- Stworzyć strukturę HTML/CSS/JS na bazie `enigma_ebook.html`, usunąć elementy ebook i dodać komponenty kodu/banerów.
- Wczytać `enigma_designer_prompt.md` do stałej JS; podmienić konstrukcję `messages` i ustawienia requestu.
- Zaimplementować parsing bloku ```html``` i akcje kopiuj/pobierz/powiększ.
- Dodać długi timeout, wskaźnik pracy i blokadę UI podczas generowania.
- Uporządkować localStorage i modal historii z nowym kluczem i formatem rekordów.
