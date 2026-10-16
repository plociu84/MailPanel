---
name: View Assets Rules
paths:
  - 'resources/views/**'
---

# Widoki (Blade)

## Assety front-endu: statyczne pliki w public/assets, zero CDN, brak builda
Biblioteki JS/CSS to wersjonowane pliki w `public/assets/` (Bootstrap 5.3.2, FontAwesome 6.4.0, Tom Select 2.3.1, jQuery 3.7.1, jquery.tablesorter 2.31.3, self-hosted Inter woff2) wstawiane wyłącznie przez `asset('assets/...')`. Nie dodawaj tagów `<link>`/`<script>` z CDN — panel musi działać bez internetu. Nie przywracaj `package.json`/`vite`/Tailwind: nie ma kroku build, a `public/assets/` jest jedynym źródłem assetów. Bump biblioteki = pobranie przypiętej wersji do `public/assets/` + edycja widoku, która ją referuje. Po zmianie w Blade uruchom `php artisan view:clear` (brak `npm run build`).
