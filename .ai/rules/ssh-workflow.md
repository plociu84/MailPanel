---
name: SSH Workflow Rules
alwaysApply: true
---

# Zasady edycji plików (SSH-remote)

## Weryfikacja edycji
- **Po każdej edycji pliku przez narzędzie ZAWSZE weryfikuj stan na dysku**: `git diff -- <plik>` oraz `php -l`.
- **Nigdy nie ufaj komunikatowi „Successfully edited".** Sukces edycji potwierdzaj odczytem pliku (`read_file`) lub — przy edycjach kodu — proś o `grep`/`cat`/`git diff` z terminala.
- **Nie commituj, dopóki `git diff` nie potwierdzi, że zmiana jest na dysku.**

## Sposób edycji
- **Edycje końcówek/dużych bloków rób przez terminal** (`head`, `python3`), nie przez narzędzia edycyjne.
- **Terminal nie zwraca wyjścia** — proś użytkownika o wklejenie wyników zamiast zgadywać.

## Minimalizacja kontekstu
- **Nie czytaj całych plików wielokrotnie.** Przeczytaj raz, potem operuj na fragmentach.
- **Zawężaj kontekst do niezbędnego minimum** — jeśli zadanie dotyczy jednej metody, nie ładuj całego pliku.