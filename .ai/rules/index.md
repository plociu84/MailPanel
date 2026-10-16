# Mapa reguł projektowych

Poniższe reguły obowiązują w całym repozytorium `panel-poczta`. Przed edycją jakiegokolwiek pliku zapoznaj się z regułami, których globy obejmują dany plik.

| Glob | Plik reguły | Opis |
| :--- | :--- | :--- |
| `**/*` | `ssh-workflow.md` | Weryfikacja edycji przez `git diff` i `php -l`, praca przez terminal w środowisku SSH-remote |
| `**/*` | `operational.md` | Stop-lossy (po 2 próbach zmień metodę), oszczędność tokenów, reakcja na feedback |
| `**/*` | `escape-hatch.md` | Wzorzec „zmierzyć, nie zgadywać" dla typowych zadań edycyjnych |
| `config/**` | `escape-hatch.md` | Szczególny nacisk na pliki konfiguracyjne |
| `app/Services/**` | `escape-hatch.md` | Szczególny nacisk na serwisy |
| `resources/views/**` | `views.md` | Assety statyczne z `public/assets` (zero CDN), brak kroku build |


