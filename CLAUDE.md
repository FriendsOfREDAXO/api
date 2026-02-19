# CLAUDE.md

Diese Datei bietet Orientierung für Claude Code (claude.ai/code) bei der Arbeit mit diesem Repository.

## Projektübersicht

Dies ist das **API-AddOn** für REDAXO CMS. Es stellt eine REST-API-Schicht bereit, die REDAXO-Kernfunktionalität (Artikel, Kategorien, Medien, Benutzer, Templates, Module, Sprachen) als HTTP-Endpunkte verfügbar macht. Basiert auf Symfony Routing/HttpFoundation/HttpKernel mit OpenAPI-3.0-Dokumentation via Swagger UI im Backend.

**Namespace:** `FriendsOfRedaxo\Api`
**PHP:** >=8.2
**Abhängigkeiten:** REDAXO >=5.17.0, YForm >=4.1.1

## Entwicklungsbefehle

```bash
# Alle Tests ausführen (erfordert laufende REDAXO-Instanz + konfigurierte tests/config.php)
./vendor/bin/phpunit

# Einzelne Testdatei ausführen
./vendor/bin/phpunit tests/ClangsApiTest.php

# Swagger-UI-Assets bauen
pnpm install && pnpm build
```

Tests sind **Integrationstests**, die echte HTTP-Requests via cURL an eine laufende REDAXO-Installation senden. Vor dem Ausführen `tests/config.php` mit `base_url` und `api_token` konfigurieren.

## Architektur

### Request-Ablauf

1. `boot.php` registriert alle RoutePackages in `RouteCollection` und hängt sich in `YREWRITE_PREPARE` ein (early priority)
2. `RouteCollection::handle()` prüft, ob der Request-Pfad mit `/api/` beginnt, und nutzt dann Symfonys `UrlMatcher` zum Routen-Matching
3. Das `Auth`-Objekt der gematchten Route wird geprüft (`BearerAuth` oder `BackendUser`); bei fehlender Autorisierung wird 401 zurückgegeben
4. Der `_controller`-Callback der Route wird mit den gematchten Parametern aufgerufen

### Zentrale Klassen

- **`RouteCollection`** (`lib/RouteCollection.php`) — Zentrales Routen-Register. Registriert Routen, matcht Requests, dispatcht an Controller. Bietet auch `getQuerySet()` zur Validierung/Typumwandlung von Request-Parametern gegen Route-Definitionen.
- **`RoutePackage`** (`lib/RoutePackage.php`) — Abstrakte Basisklasse. Jede Ressourcengruppe erweitert diese und implementiert `loadRoutes()`.
- **`Auth`** (`lib/Auth/Auth.php`) — Abstrakte Auth-Basis. Zwei Implementierungen:
  - `BearerAuth` — Token-basierte Authentifizierung via `Authorization: Bearer <token>` Header, validiert gegen `rex_api_token`-Tabelle mit Scope-Prüfung
  - `BackendUser` — Session-Cookie-Authentifizierung für reine Backend-Endpunkte
- **`Token`** (`lib/Token.php`) — Verwaltet API-Tokens in der `rex_api_token`-Tabelle. Tokens haben Scopes (kommagetrennte Route-Scope-Namen).
- **`OpenAPIConfig`** (`lib/OpenAPIConfig.php`) — Generiert OpenAPI-3.0-Spezifikation aus registrierten Routen für Swagger UI.

### Route Packages (lib/RoutePackage/)

Jede Datei definiert Routen und Handler-Methoden für eine Ressourcengruppe:

| Datei              | Endpunkte                                    | Scope-Prefix     |
| ------------------ | -------------------------------------------- | ---------------- |
| `Structure.php`    | Artikel, Kategorien, Slices CRUD             | `structure/`     |
| `Media.php`        | Mediendateien und Medienkategorien CRUD       | `media/`         |
| `Users.php`        | Benutzer und Rollen                          | `users/`         |
| `Modules.php`      | Module CRUD                                  | `modules/`       |
| `Templates.php`    | Templates CRUD                               | `templates/`     |
| `Clangs.php`       | Sprachen CRUD                                | `system/clangs/` |
| `Backend/Media.php`| Backend-authentifizierte Medien-Endpunkte    | `backend/media/` |

### Neuen Endpunkt hinzufügen

1. Eine `RoutePackage`-Subklasse in `lib/RoutePackage/` erstellen oder erweitern
2. In `loadRoutes()` `RouteCollection::registerRoute()` aufrufen mit:
   - Einem eindeutigen **Scope**-String (z.B. `'resource/action'`) — wird auch für Token-Berechtigungen verwendet
   - Einem Symfony `Route`-Objekt mit `_controller`, optionalen `Body`- (POST/PUT-Felder) und `query`-Definitionen (GET-Parameter)
   - Einem `Auth`-Objekt (`new BearerAuth()` oder `new BackendUser()`)
3. Das RoutePackage in `boot.php` via `RouteCollection::registerRoutePackage()` registrieren
4. Handler-Methoden sind statisch, erhalten ein `$Parameter`-Array (Route-Parameter + Definitionen) und geben `Symfony\Component\HttpFoundation\Response` zurück

### Routen-Registrierungsmuster

```php
RouteCollection::registerRoute(
    'scope/name',              // Scope-String (für Auth + OpenAPI)
    new Route(
        'path/{id}',           // URL-Pfad (ohne /api-Prefix)
        [
            '_controller' => 'Class::method',
            'Body' => [...],   // POST/PUT-Feld-Definitionen
            'query' => [...],  // GET-Parameter-Definitionen
        ],
        ['id' => '\d+'],       // Requirements (Regex)
        [], [], '', [],
        ['GET']                // HTTP-Methoden
    ),
    'Beschreibung für OpenAPI',
    null,                      // Eigene Response-Definitionen (oder null)
    new BearerAuth()           // Auth-Handler
);
```

### Handler-Muster

- **GET-Liste**: Query-Parameter parsen via `RouteCollection::getQuerySet($_REQUEST, $Parameter['query'])`
- **POST/PUT**: Body parsen via `json_decode(rex::getRequest()->getContent(), true)`, dann validieren mit `RouteCollection::getQuerySet($Data, $Parameter['Body'])`
- **Erstellte IDs**: REDAXO Extension Points nutzen (z.B. `ART_ADDED`, `CLANG_ADDED`, `SLICE_ADDED`), um auto-generierte IDs abzufangen
- Rückgabe: `new Response(json_encode($data), $statusCode)`

### Service-Klassen

- `rex_user_service` (`lib/service_user.php`) — Benutzer-CRUD-Operationen mit Passwort-Richtlinien und Admin-Schutz
- `rex_user_role_service` (`lib/service_user_role.php`) — Benutzerrollen-Operationen

### Tests

Tests erweitern `ApiTestCase`, welche HTTP-Hilfsmethoden (`get()`, `post()`, `put()`, `patch()`, `delete()`, `postMultipart()`) und Assertions (`assertSuccess()`, `assertStatus()`, `assertError()`) bereitstellt. Mit `trackResource()` werden erstellte Ressourcen in `tearDown()` automatisch aufgeräumt.

### Datenbank

- `rex_api_token`-Tabelle — Speichert API-Tokens mit `name`, `token`, `status`, `scopes` (kommagetrennte Scope-Strings)

## Wichtige Hinweise

- Die API läuft im **Frontend-Kontext**, nicht im Backend. Extension Points, die nur im Backend-Kontext registriert werden (`rex::isBackend()`), werden nicht ausgelöst.
- Alle API-Routen haben das Prefix `/api/` (konfiguriert über `RouteCollection::$preRoute`).
- Apache kann den `Authorization`-Header entfernen — siehe README.md für den `.htaccess`-Fix.
