---
name: Operational Rules
alwaysApply: true
---

# Reguły operacyjne (twarde, wykonalne)

## Stop-lossy
- **Po 2 nieudanych próbach tej samej metody — zmień metodę albo zapytaj.** Bez trzeciej, czwartej, piątej próby „może teraz zadziała".
- **Gdy narzędzie zwraca „string not found"** — to nie znaczy „spróbuj jeszcze raz". To znaczy, że założenie o pliku było błędne. Zmień założenie, nie próbę.
- **Jedno narzędzie, jedno użycie.** Bez strzelania po kilkanaście wariantów tego samego wywołania.

## Reakcja na feedback
- **Feedback użytkownika = natychmiastowa zmiana kursu.** Gdy użytkownik pisze „nie ta metoda" — to nie sygnał do przeprosin i powtórki, tylko do zatrzymania i innego podejścia.

## Oszczędność tokenów
- **Nie powtarzaj kontekstu**, który już został podany w rozmowie.
- **Nie generuj długich wyjaśnień**, jeśli użytkownik prosi o konkretną zmianę w kodzie.
- **Przed każdą operacją zastanów się, czy jest niezbędna** — jeśli możesz odpowiedzieć bez wywoływania narzędzia, zrób to.
- **Unikaj „rozmyślania na głos"** w odpowiedziach — skup się na konkretnym działaniu.

## Bezpieczeństwo
- **Nie modyfikuj plików poza katalogiem projektu** bez wyraźnego polecenia użytkownika.
- **Nie usuwaj plików** bez potwierdzenia.
- **Nie zmieniaj konfiguracji Git** (np. `git config`) bez pytania.