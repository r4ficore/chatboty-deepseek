# Plan wdrożenia obsługi załączników jako bazy wiedzy

## Cel
Zastąpić sztywne ładowanie pliku `Analiza_rynku_Ebook_Copywriting.pdf` mechanizmem pozwalającym użytkownikowi dodać do pięciu własnych materiałów referencyjnych (`pdf`, `md`, `doc`, `txt`), które zostaną włączone do kontekstu pracy modelu przy generowaniu ebooka. System ma bronić się przed przepełnieniem kontekstu dzięki ograniczeniu łącznej liczby znaków oraz czytelnemu komunikowaniu limitów.

## Założenia
- Obsługa załączników dostępna bez przeładowania strony, przy pomocy FileReader API w przeglądarce.
- Każdy plik po wczytaniu trafia do historii rozmowy jako wiadomość systemowa (niewidoczna treść, informacja o załączniku w UI).
- Limit: maksymalnie 5 plików, łączna suma rozmiaru tekstu w kontekście nie przekracza ustalonego bufora (np. 60 000 znaków).
- W przypadku przekroczenia limitu użytkownik otrzymuje komunikat i plik nie jest dodawany.
- Mechanizm można łatwo rozszerzyć na dodatkowe formaty w przyszłości.

## Taski (status)
1. **Refaktoryzacja inicjalizacji bazy wiedzy** ✅
   - usunięto logikę ładowania stałego pliku PDF z `knowledge_base/Analiza_rynku_Ebook_Copywriting.pdf`, w tym wywołania `loadKnowledgeBase()` oraz komunikaty o błędzie.
   - stan `knowledgeBase` startuje teraz z pustą listą załączników i metadanymi liczników.

2. **Dodanie interfejsu wgrywania plików** ✅
   - dodano ukryty `<input type="file" multiple>` przyjmujący `.pdf,.md,.doc,.docx,.txt` oraz przycisk aktywujący wybór plików.
   - wprowadzono listę załączonych materiałów z możliwością usunięcia.
   - zapewniono responsywne style dla małych szerokości.

3. **Odczyt i konwersja treści** ✅
   - wykorzystano `FileReader` wraz z `pdf.js` i `mammoth.js` do pobierania treści PDF/DOCX, a pliki `md/txt` przetwarzane są jako zwykły tekst.
   - po konwersji każdy plik jest walidowany względem limitów znaków i aktualizuje globalny budżet.

4. **Integracja z przebiegiem rozmowy** ✅
   - teksty z załączników są agregowane do wiadomości systemowej wysyłanej przed promptem użytkownika.
   - w czacie pojawiają się wpisy informacyjne „Załączono plik …” bez prezentowania pełnej treści.

5. **Obsługa limitów i błędów** ✅
   - zdefiniowano stałe limitów liczby plików, maksymalnego tekstu oraz rozmiaru pojedynczych plików.
   - dodano komunikaty o przekroczeniach i obsługę błędów parsowania.

6. **Czyszczenie stanu przy restarcie sesji** ✅
   - funkcje resetu i przywracania sesji czyszczą listę załączników oraz powiązaną wiadomość systemową.

## Ryzyka i mitigacje
- **Duże pliki PDF/Doc** – konwersja może być ciężka i przekraczać limit; wymagane przycinanie do wycinków lub ostrzeżenie.  
  _Mitigacja_: wprowadzenie limitu znaków z wyraźnym komunikatem oraz opcjonalne skracanie treści.
- **Brak wsparcia dla `doc/docx` bez biblioteki** – konieczna dodatkowa zależność lub fallback.  
  _Mitigacja_: jeśli w ramach implementacji nie uda się dodać biblioteki, należy przygotować ścieżkę komunikatu „format nieobsługiwany” i zasugerować konwersję do PDF/MD/TXT.
- **Złożoność UI** – integracja z istniejącym panelem może wprowadzić konflikty.  
  _Mitigacja_: zaplanowanie niezależnej sekcji UI z modułową logiką JS.

## Kryteria akceptacji
- Brak automatycznych prób wczytywania `Analiza_rynku_Ebook_Copywriting.pdf` po uruchomieniu aplikacji.
- Użytkownik może dodać maksymalnie 5 załączników, a UI pokazuje ich status i pozwala usuwać.
- Treść zaakceptowanych plików trafia do kontekstu rozmowy (widoczna w logu wiadomości systemowych lub w narzędziu debugującym).
- Przy przekroczeniu limitów aplikacja odmawia dodania pliku i informuje użytkownika o przyczynie.
- Reset sesji usuwa wszystkie dotychczasowe załączniki z kontekstu oraz UI.

Proszę o akceptację planu przed rozpoczęciem implementacji.
