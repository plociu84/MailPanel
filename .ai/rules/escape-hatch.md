---
name: Escape Hatch — wzorce dla typowych zadań
alwaysApply: true
---

# Wzorzec „escape hatch" dla zadań edycyjnych

## Zasada nadrzędna
**„Zmierzyć, nie zgadywać."** Zamiast zakładać stan pliku — zmierz go jedną komendą (`git diff`, `grep`, `php -l`). 90% marnowanych tokenów to zgadywanie stanu pliku zamiast jego weryfikacji.

## Wzorzec dla zadania „przenieś hasło do configu"
Dla tego typu zadań optymalny przebieg to **jeden komunikat z komendami terminala**, bez narzędzi edycyjnych:

```bash
# 1. edycja przez terminal
python3 -c "..."          # dopisanie bloku do config/services.php
sed -i 's/.../.../' app/Services/MailServerService.php

# 2. weryfikacja
php -l config/services.php && php -l app/Services/MailServerService.php
grep cyrus config/services.php

# 3. commit
git add -p && git diff --cached && git commit