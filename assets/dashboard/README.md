# Content Pilot — REDAXO API Dashboard

Ein **Standalone-Dashboard** das die [REDAXO API](../../README.md) konsumiert und alle verfügbaren Endpoints visuell darstellt. Ideal als Demo, Referenz und zum Testen der API.

## Features

| Feature | API-Endpoint |
|---|---|
| 📊 **Dashboard** mit Stats-Karten | Alle List-Endpoints parallel |
| 🌳 **Seitenstruktur** als interaktiver Baum | `GET /api/structure/articles` |
| 🖼 **Medienpool** als Galerie mit Filter | `GET /api/media` + `GET /api/media/{filename}/info` |
| 📄 **Templates** mit Code-Ansicht | `GET /api/templates` |
| ⚙️ **Module** mit Ein-/Ausgabe-Code | `GET /api/modules` |
| 👥 **Benutzer & Rollen** Übersicht | `GET /api/users` + `GET /api/users/roles` |
| 🌐 **Sprachen** Konfiguration | `GET /api/system/clangs` |
| 🔍 **Globale Suche** über alle Inhalte | Client-seitig |
| 📋 **API Request Log** mit Timing | Automatisch |
| 🌓 **Dark/Light Theme** | Client-seitig |

## Verwendung

### Option 1: Direkt im Browser öffnen

```bash
open assets/dashboard/index.html
```

### Option 2: Von einem Webserver ausliefern

Die Datei kann von überall ausgeliefert werden — sie benötigt keine Server-seitige Logik.

### Verbindung herstellen

1. **REDAXO URL** eingeben (z.B. `https://www.meine-seite.de`)
2. **API Token** eingeben (erstellt im Backend unter `API → Token`)
3. Sicherstellen, dass der Token **alle benötigten Scopes** hat:
   - `structure/articles/list`, `structure/articles/get`
   - `media/list`, `media/get`
   - `templates/list`
   - `modules/list`
   - `users/list`
   - `system/clangs/list`

### Apache .htaccess (falls nötig)

```apache
RewriteCond %{HTTP:Authorization} .
RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

### CORS (falls von externer Domain)

Falls das Dashboard von einer anderen Domain als REDAXO betrieben wird, muss CORS in der REDAXO-Installation konfiguriert werden.

## Screenshots

Das Dashboard bietet:
- **Stats-Karten**: Kompakte Übersicht aller Inhalte mit Klick-Navigation
- **Seitenstruktur-Baum**: Hierarchische Darstellung aller Artikel und Kategorien
- **Media-Galerie**: Thumbnail-Vorschau für Bilder, Icons für andere Dateitypen
- **Detail-Modals**: Klick auf ein Element zeigt alle Metadaten
- **API Log**: Jeder Request wird protokolliert mit Methode, Status und Antwortzeit

## Technologie

- **Zero Dependencies** — Kein Framework, keine Build-Tools, kein npm
- **Single HTML File** — Alles in einer Datei: HTML, CSS, JavaScript
- **Vanilla JS** — Fetch API, DOM Manipulation, ES6+
- **Session Storage** — Token wird nur für die Browser-Session gespeichert
- **Responsive** — Sidebar klappt auf Mobile als Overlay

## Für Entwickler

Dieses Dashboard zeigt:
1. Wie man die REDAXO API mit `fetch()` und Bearer Token konsumiert
2. Wie die API-Responses aufgebaut sind (flache JSON-Arrays)
3. Wie Pagination funktioniert (`page`, `per_page` Query-Parameter)
4. Wie man MediaPool-Thumbnails direkt über `/media/{filename}` einbindet
5. Wie man Fehlerbehandlung und Auth-Validierung implementiert
