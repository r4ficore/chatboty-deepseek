# Etap 1 – analiza istniejącego rozwiązania

## Architektura enigma_ebook.html
- **UI/UX**: ciemny layout (Inter), jednokolumnowy chat z nagłówkiem, menu (nowy/zakończ czat, historia), pole tekstowe, wskaźnik pisania, modal historii, panel zarządzania rozdziałami, widget załączników knowledge base.
- **Komponenty specjalne**: preview plików TXT (<FILE name="...">), akcje kopiuj/podgląd/pobierz, panel zaakceptowanych rozdziałów, obsługa reasoning plików, final download box.
- **Styl**: gradientowe tła, obramowania, czcionka Inter, responsywny chat (max-width 800px). Część stylu i struktury można użyć jako baza ciemnego motywu dla designera.

## Logika front-end
- **Stan**: `conversationContext.fullHistory` (zapisywany w localStorage pod `enigma_chats`), identyfikator bieżącego czatu `currentChatId`, stan ebooka (`ebookState` dla etapów toc/chapters/final) oraz `knowledgeBase` (pliki, limity, teksty).
- **Wiadomości**: dodawane przez `addChatMessage`; zapisywanie historii wraz z wyrenderowanym HTML (aby odtworzyć podglądy). Typing indicator (`showTypingIndicator/hideTypingIndicator`).
- **DeepSeek API**: `callDeepSeek` → POST do `deepseek_proxy.php` z parametrami `{ model: "deepseek-reasoner", messages, max_tokens: 4000 }`, obsługa retry przy 502, propagacja błędów.
- **Budowanie promptu**: `sendToDeepSeekAI(userContent, opts)` tworzy `messages` z system promptem (rola pisarza ebooków, instrukcje formatowania w <FILE>), ewentualnie wiedza z załączników (`buildKnowledgeMessage`, `buildAttachmentContextMessages`).
- **Parsowanie outputu**: `parseFileBlocks` wyciąga pliki <FILE name="...">, generuje podglądy i linki pobrania. Sterowanie etapami (spis treści → rozdziały → final) zależy od wykrytych plików (`spis_tresci.txt`, `rozdzial_N.txt`, `reasoning_N.txt`).
- **Historia i nawigacja**: modal z listą czatów (ostatnie 30 dni), `startNewChat` resetuje stan i wyświetla instrukcję startową; obsługa menu, skrót Enter dla wysyłki, auto-resize textarea.
- **Załączniki**: obsługa uploadu PDF/MD/TXT/DOCX (pdf.js, mammoth), limity (max 3 pliki, 60k znaków), normalizacja treści, render listy, możliwość usuwania; snippetowanie wiedzy przy zapytaniach.

## Elementy do ponownego użycia w enigma_designer
- Ogólna struktura chat + nagłówek + menu + modal historii + typing indicator (można odchudzić o panel rozdziałów/ebook).
- Mechanika zapisywania/odtwarzania historii i renderowania wiadomości HTML.
- Integracja z `deepseek_proxy.php` i funkcja `callDeepSeek` (wydłużyć max_tokens/timeout na ~2400 linii outputu; zachować retry).
- Wskaźnik pisania + komunikaty błędów/timeoutów.
- Parsowanie bloków kodu/plików można adaptować do obsługi jednego bloku ```html``` (zamiast <FILE>), z opcjami kopiuj/pobierz.
- Komponent uploadu wiedzy można pominąć lub uprościć (nie jest wymagany w designerze, chyba że chcemy dodatki referencyjne).

## Zmiany potrzebne dla designera
- **System prompt**: zastąpić treścią z `enigma_designer_prompt.md` (warunek startu, format outputu, zasady stylów 1–7, max ~2400 linii, jedna prośba o input jeśli brak pełnego outputu Landing Architect).
- **UI copy**: opis startowy musi informować o koniecznym pełnym outputcie LA i o blokowym zwracaniu kodu HTML; wyróżnić długie generowanie (baner/progress + wydłużony timeout).
- **Logika**: usunąć etapy ebook (toc/chapters, reasoning, panel zaakceptowanych), dodać obsługę pojedynczego pliku HTML w odpowiedzi (kopiuj/pobierz, ewentualnie podgląd kodu w monospaced bloku); historia czatu nadal przydatna.
- **Limit długości**: zwiększyć `max_tokens`/timeout, komunikat o generowaniu do 2400 linii, status „model pracuje/generuje na żywo”.
- **Przykładowe UI/animacje**: inspirować się landingami z `landings/` (np. gradienty, glassmorphism, mikroanimacje, interaktywne karty) i zadbać o mobile-first.

## Kontekst z enigma_designer_prompt.md
- Rola DESIGNER: konwersja outputu Landing Architect → pełny plik HTML; bez wymyślania treści; wierność treści i struktury.
- Warunek startu: weryfikacja obecności STYLE, mapa sekcji, treść sekcji, NOTATKI DLA DESIGNERA/DEVA; jeśli brak – jedna prośba o wklejenie pełnego outputu LA, bez generowania kodu.
- Output: pojedynczy blok ```html ... ``` bez zbędnych opisów; komentarz tylko na prośbę użytkownika.
- Kodowanie: semantyczny HTML5, mobile-first, jeden blok <style>, JS tylko gdy wynika z notatek; mapowanie stylów #1–#7; priorytet responsywności i czystości kodu.

## Przegląd landings/
- `landings/example1`: zaawansowany dark/tech landing (Tailwind + Alpine + AOS), glassmorphism, animacje (gradienty, floating, shimmer), bogata sekcja CTA/FAQ/pricing; inspiracja dla nowoczesnych layoutów.
- `landings/example2` (do przejrzenia podczas implementacji): spodziewane różne stylizacje/układy do naśladowania.

## Wnioski na dalsze etapy
- Możemy przenieść kluczowe elementy UI (nagłówek, chat, typing indicator, historia) i uprościć do jednego strumienia kodu HTML.
- Trzeba zdefiniować nowy copy startowy + baner długiego generowania + mechanizm kopii/pobierania kodu.
- Integracja API pozostaje ta sama, ale z nowym system promptem i większym limitem tokenów.
- Przy implementacji warto dodać scrollowalny blok kodu, przyciski „Kopiuj kod”/„Pobierz jako HTML”, oraz jasne komunikaty o wymaganym inputcie LA.
