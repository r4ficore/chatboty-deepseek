# Plan pracy: enigma_designer.html

## Etap 1: Analiza istniejącego rozwiązania
- ✅ Przejrzyj `enigma_ebook.html`, aby zrozumieć strukturę UI (nagłówek, logika czatu, wskaźnik pisania, obsługa historii, zarządzanie plikami) oraz sposób komunikacji z `deepseek_proxy.php` i zarządzanie kontekstem.
- ✅ Zanotuj, które fragmenty logiki można ponownie użyć dla designera (wysyłanie promptów systemowych, zarządzanie wiadomościami, obsługa limitów/timeoutów, zapisywanie historii lokalnej).
- ✅ Zapoznaj się z `enigma_designer_prompt.md`, by upewnić się, jaką rolę i zasady ma mieć nowy chatbot (warunek startu, format outputu, limity długości ~2400 linii, sygnalizacja pracy modelu).
- ✅ Sprawdź przykładowe strony w `landings/`, aby dopasować oczekiwany poziom UI/UX oraz elementy interaktywne dla landing pages.
- Status: ✅ zakończony. Notatki i wnioski zapisane w `stage1_analysis.md` (architektura, logika, elementy do re-use, wymagania promptu designera, inspiracje landingów).

## Etap 2: Projekt funkcjonalny enigma_designer.html
- ✅ Zaprojektuj layout czatu na bazie stylistyki Enigma (ciemny motyw, komponenty jak w ebook writerze) z dostosowaniem do roli designera.
- ✅ Zdefiniuj sekcję opisu/promptu startowego, która wyjaśni użytkownikowi wymagany input (pełny output Landing Architect) i zasady generowania HTML.
- ✅ Określ mechanizm informowania o długim generowaniu kodu (np. rozbudowany typing indicator/baner „model generuje kod na żywo”).
- ✅ Zaplanuj sposób prezentacji wygenerowanego kodu (blok ```html``` w odpowiedzi, klarowny podział między kodem a komentarzem dla użytkownika) oraz ewentualne akcje pobrania/kopiowania.
- ✅ Ustal ustawienia API: ten sam endpoint `deepseek_proxy.php`, model/system prompt ustawiony zgodnie z `enigma_designer_prompt.md`, opcjonalne parametry (temperature, max_tokens) dostosowane do dużych odpowiedzi.
- Status: ✅ zakończony. Dokument „Etap 2 – projekt funkcjonalny” zapisany w `stage2_design.md`.

## Etap 3: Implementacja UI/UX
- ✅ Utwórz nowy plik `enigma_designer.html` na bazie struktury `enigma_ebook.html` (header, chat area, input, historia), usuwając funkcje specyficzne dla ebooków (TOC, rozdziały, pliki txt) i dodając elementy przydatne dla projektowania landingów (np. sekcja preview/CTA kopiuj kod).
- ✅ Dodaj komponenty wizualne zgodne z wymaganiami (modern, dynamiczne elementy, mobile-first), w tym responsywne siatki, gradienty/akcenty, animowane wskaźniki stanu generowania.
- ✅ Zaimplementuj logikę rozmowy: budowa wiadomości z system promptem z `enigma_designer_prompt.md`, wysyłanie user promptów do API, renderowanie odpowiedzi w blokach kodu bez mieszania z komentarzami.
- ✅ Wprowadź mechanizm limitowania długości (obsługa dużych odpowiedzi do ~2400 linii), np. konfiguracja `max_tokens`, wydłużony timeout fetch, widoczne komunikaty o oczekiwaniu.
- ✅ Zapewnij zapisywanie i przywracanie historii czatu (localStorage) oraz możliwość rozpoczęcia nowej sesji.
- Status: ✅ zakończony – podstawowy UI/UX i logika czatu gotowe w `enigma_designer.html`.

## Etap 4: Integracje i ergonomia pracy z kodem
- ✅ Dodaj akcje UI: kopiuj kod, zapis do pliku (np. `.html`), ekspandowanie/zamykanie bloków kodu, podświetlanie składni (jeśli lekkie), przyciski „Poproś o poprawki / Nowa wersja”.
- ✅ Upewnij się, że chatbot komunikuje wymaganie pełnego outputu Landing Architect, jeśli użytkownik go nie poda (jednorazowa prośba startowa).
- ✅ Zaimplementuj obsługę błędów i stanów edge-case (brak odpowiedzi, 502 -> ewentualne powtórzenie, przekroczenie limitów, brak połączenia), wyświetlając jasne komunikaty.
- ✅ Dodaj ułatwienia ergonomiczne: szybkie wklejenie szablonu LA, skróty klawiaturowe (Ctrl/Cmd+Enter, Esc), odporność zapisu historii na błędy localStorage.
- Status: ✅ zakończony.

## Etap 5: Testy i weryfikacja
- Test ręczny otwarcia `enigma_designer.html` w przeglądarce i przejścia podstawowego flow: instrukcja startowa → wklejenie przykładowego outputu LA → wygenerowanie kodu → kopiowanie/pobranie.
- Sprawdzenie responsywności (mobile/desktop) oraz czytelności bloków kodu (monospace, scroll, zachowanie formatowania).
- Weryfikacja, że odpowiedzi nie mieszają kodu z komentarzem i że wskaźnik pracy pojawia się przy długim generowaniu.
- Krótki smoke test lokalnego API: czy request do `deepseek_proxy.php` jest wykonywany z prawidłowym payloadem (system prompt + user), obsługa błędów HTTP.
- Status: w toku. Checklista i wyniki testów będą rejestrowane w `stage5_testing.md`.

## Etap 6: Porządki
- Uaktualnij ewentualne teksty w README lub sekcje pomocnicze (jeśli wymagane) o nowym chatbocie.
- Przejrzyj kod pod kątem spójności stylistycznej, dostępności (kolory, aria-label w przyciskach), oraz usuń martwy kod/komentarze.
