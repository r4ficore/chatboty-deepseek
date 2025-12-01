# DeepSeek Reasoner – skrót najważniejszych informacji

## Model i endpoint
- **Nazwa modelu:** `deepseek-reasoner`
- **Endpoint API:** `https://api.deepseek.com/chat/completions`
- **Typ API:** kompatybilny z formatem OpenAI Chat Completions (`model`, `messages[]`, opcjonalne: `max_tokens`, `temperature`, `top_p`, `stream`).
- **Główne zastosowanie:** odpowiedzi oparte na rozumowaniu (wydłużony czas generowania, dokładniejsze wnioskowanie niż `deepseek-chat`).

## Limity i parametry
- **Maks. długość odpowiedzi (`max_tokens`):** dokumentacja zwraca błąd, gdy wartość jest poza zakresem `1–8192`; ustawiaj niżej (np. 7800), aby mieć zapas na tokeny kontroli i uniknąć 400.
- **Kontekst wejściowy:** obowiązuje łączny limit tokenów promptu i odpowiedzi (API odrzuci przekroczenia komunikatem o zakresie `1–8192` dla `max_tokens`).
- **Streaming:** obsługiwany (`stream: true/false`).
- **Czas odpowiedzi:** model potrafi generować długo (np. pełne landing page), warto ustawić timeout 5 minut po stronie klienta/proxy.
- **Inne opcje:** `temperature` (0–2), `top_p`, `presence_penalty`, `frequency_penalty` działają jak w OpenAI Chat API.

## Błędy i diagnostyka
- **400 Bad Request:** najczęściej zbyt duże `max_tokens` (poza 1–8192) lub brakujące `messages[]`/`model`.
- **402/403:** brak środków lub nieważny klucz API.
- **502/504:** problemy sieciowe lub timeout – ponów z krótszym kontekstem lub zwiększ limit czasu po swojej stronie.

## Rozliczenia
- **Model jest płatny per token wejściowy i wyjściowy.** Stawki są publikowane w sekcji „Pricing” oficjalnej dokumentacji (`https://api-docs.deepseek.com/pricing`).
- **DeepSeek Reasoner jest droższy niż `deepseek-chat`** (model premium do zadań wymagających rozumowania). Sprawdzaj aktualne ceny w panelu lub w dokumentacji, bo mogą się zmieniać.

## Rekomendacje praktyczne dla Enigma Designer
- Ustaw `model: "deepseek-reasoner"`, `max_tokens: 7800` jako bezpieczny domyślny limit.
- Przy bardzo długich outputach LA skracaj historię rozmowy, żeby zmieścić się w limicie tokenów.
- Zostaw komunikaty timeoutu na 5 minut i pokazuj użytkownikowi, że model nadal pracuje, bo Reasoner potrafi liczyć dłużej.
- **Górny limit odpowiedzi to 8192 tokeny i nie da się go zwiększyć** – API zwraca błąd 400, gdy `max_tokens` wyjdzie poza zakres `1–8192`.
- Jeśli potrzebujesz dłuższego HTML-a:
  - Generuj w dwóch krokach (np. struktura + sekcje) i łącz po stronie klienta.
  - Wymuszaj krótsze prompty i skracaj historię, aby maksymalnie wykorzystać budżet wyjściowy.
  - Użyj strumieniowania i przycinaj tylko końcówkę zamiast przerwania w środku (np. kontroluj długość na podstawie `content-length`).
  - Dodaj „kontynuuj od…” jako automatyczny follow-up, jeśli odpowiedź została obcięta.
  - Rozważ bardziej zwięzły styl (np. komponenty reusable, zmienne CSS), aby zmieścić więcej treści w limicie tokenów.
