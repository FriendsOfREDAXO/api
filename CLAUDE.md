# CLAUDE.md

Diese Datei bietet Orientierung für Claude Code (claude.ai/code) bei der Arbeit mit diesem Repository.

## Projektübersicht

Dies ist das **API-AddOn** für REDAXO CMS. Es stellt eine REST-API-Schicht bereit, die REDAXO-Kernfunktionalität (Artikel, Kategorien, Medien, Benutzer, Templates, Module, Sprachen) als HTTP-Endpunkte verfügbar macht. Basiert auf Symfony Routing/HttpFoundation/HttpKernel mit OpenAPI-3.0-Dokumentation via Swagger UI im Backend.

**Namespace:** `FriendsOfRedaxo\Api`
**PHP:** >=8.2
**Abhängigkeiten:** REDAXO >=5.17.0, YForm >=4.1.1

## Entwicklungsbefehle

```bash
# Alle Tests ausführen (erfordert laufende REDAXO-Instanz + tests/.env)
./vendor/bin/phpunit

# Einzelne Testdatei ausführen
./vendor/bin/phpunit tests/ClangsApiTest.php

# Swagger-UI-Assets bauen
pnpm install && pnpm build
```

Tests sind **Integrationstests**, die echte HTTP-Requests via cURL an eine laufende REDAXO-Installation senden. Setup:

1. `cp tests/.env.example tests/.env` und Werte anpassen (Basis-URL, Bearer-Token, Backend-Credentials, existierende IDs).
2. Bearer-Token im REDAXO-Backend unter `API → Token` anlegen und alle benötigten Scopes vergeben — die Test-Suite erwartet u.a. `structure/articles/slices/{list,add,get,update,delete}`, `system/clangs/*`, `modules/*`, `templates/*`, `users/*`, `media/*`, `metainfo/*`.
3. Restricted-Backend-User mit eingeschränkten Permissions anlegen (Default-Login `apitest_restricted`):
   ```bash
   redaxo/bin/console user:create apitest_restricted <password> --name="API Test Restricted"
   ```
4. `tests/.env` ist gitignored — Geheimnisse bleiben lokal.

`tests/bootstrap.php` parst `.env` ohne externe Dependency; `tests/config.php` liest die Werte über `getenv()` mit Fallbacks.

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

| Datei              | Endpunkte                                              | Scope-Prefix     |
| ------------------ | ------------------------------------------------------ | ---------------- |
| `Structure.php`    | Artikel, Kategorien, Slices CRUD                       | `structure/`     |
| `Media.php`        | Mediendateien und Medienkategorien CRUD                | `media/`         |
| `Users.php`        | Benutzer und Rollen                                    | `users/`         |
| `Modules.php`      | Module CRUD                                            | `modules/`       |
| `Templates.php`    | Templates CRUD                                         | `templates/`     |
| `Clangs.php`       | Sprachen CRUD                                          | `system/clangs/` |
| `Metainfo.php`     | Metainfo-Felddefinitionen + Werte (Artikel/Kategorie/Medium/Sprache) | `metainfo/`      |

Die `lib/RoutePackage/Backend/`-Klassen erweitern jeweils ihre Bearer-Variante, klonen alle passenden Routen, hängen `backend/` an Pfad und Scope und ersetzen das Auth-Objekt durch `BackendUser`. Beim Anlegen eines neuen Bearer-Endpunkts entsteht der Backend-Spiegel automatisch — eigene `Backend/*.php`-Implementierungen sind nur nötig, wenn das Standardverhalten überschrieben werden soll (Beispiel: `Backend/Media.php`).

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

- **GET-Liste**: Query-Parameter parsen via `RouteCollection::getQuerySet($_REQUEST, $Parameter['query'])`. Listen einheitlich über `ListHelper::paginateArray()` aufbauen (Pagination + Sort + Meta-Block).
- **POST/PUT/PATCH**: Body parsen via `json_decode(rex::getRequest()->getContent(), true)`, dann validieren mit `RouteCollection::getQuerySet($Data, $Parameter['Body'])`
- **Erstellte IDs**: REDAXO Extension Points nutzen (z.B. `ART_ADDED`, `CLANG_ADDED`, `SLICE_ADDED`), um auto-generierte IDs abzufangen
- **Permissions im Backend-Scope**: Nutzer via `RouteCollection::getBackendUser($Route)` holen; ist er `null`, wird per Bearer-Token aufgerufen und es greifen Token-Scopes statt User-Permissions. Andernfalls `rex_user::isAdmin()` / `getComplexPerm('structure'|'modules'|'media'|'clang')` prüfen und 403 zurückgeben, wenn die Berechtigung fehlt.
- **PRE-Extension-Points & API-Kontext**: Manche Extension Points (z.B. `SLICE_UPDATE`, `SLICE_DELETE`) rufen `rex::requireUser()` auf — das schlägt im Bearer-Token-Kontext fehl. Im API-Kontext entweder den EP nur firen, wenn `rex::getUser() !== null`, oder die Service-Methode bewusst umgehen und nur den POST-EP firen (siehe `Structure::handleUpdateArticleSlice` / `handleDeleteArticleSlice`).
- **Service-Exceptions**: `rex_api_exception` trägt eine i18n-übersetzte Message. Status-Code daher nicht über `str_contains($e->getMessage(), 'not found')` ermitteln (locale-abhängig), sondern über einen Helper, der EN- und DE-Marker prüft (siehe `Users::statusFromApiException`).
- Rückgabe: `new Response(json_encode($data), $statusCode)`

### Verbindlich: Exaktes Spiegeln des REDAXO-Core-Verhaltens

Die API ist **kein eigenständiges System**, sondern ein HTTP-Frontend für die existierenden Backend-Workflows. Sie muss sich exakt so verhalten wie der entsprechende Schritt in `pages/*.php` oder im jeweiligen `rex_*_service`. Das ist eine harte Anforderung, keine Empfehlung.

**Konkret heißt das:**

- **Erst Service, dann SQL.** Wenn REDAXO eine Service-Methode anbietet (`rex_article_service`, `rex_category_service`, `rex_clang_service`, `rex_media_service`, `rex_media_category_service`, `rex_content_service`, …), wird diese aufgerufen — Punkt. Direktes SQL ist nur erlaubt, wenn der entsprechende Backend-Pfad ebenfalls direktes SQL verwendet (Beispiele: Slice-Update/Delete, Templates, Module — die werden in `pages/*.php` über `rex_sql` gespeichert).
- **EPs feuern wie auf der Seite.** Wenn die Backend-Page nach einem Service-Call zusätzliche EPs feuert (z.B. `STRUCTURE_CONTENT_SLICE_ADDED` deprecated, `STRUCTURE_CONTENT_ARTICLE_UPDATED` nach Slice-Mutationen), feuert die API diese EPs ebenfalls — mit identischen Param-Schlüsseln und -Typen. Reihenfolge bewahren: PRE → Save → POST → deprecated POST → `art_content_updated`.
- **Keine erfundenen EPs.** Wenn REDAXO-Core für eine Operation **keinen** EP feuert (Rollen-CRUD via `pages/roles.php` ist so ein Fall), feuert die API auch keinen. EPs sind ein öffentliches API-Versprechen — wir erfinden hier keins.
- **Keine zusätzlichen Felder im EP-Payload.** Param-Liste 1:1 vom Core-Pfad übernehmen. Was der Core nicht setzt, setzen wir nicht.
- **Keine zusätzlichen Felder im Body-Schema.** Wenn der Core `editCategory` nur `name` ändert, akzeptiert auch unser PUT-Body nur `name`. Vermeintlich nützliche Erweiterungen (z.B. parent-Wechsel) werden ausgelassen, sonst weicht das Verhalten zwischen Backend und API ab.
- **Cache-Invalidierungen 1:1.** Was die Backend-Page nach dem Save ruft (`rex_article_cache::delete`, `rex_template_cache::delete`, `rex_media_cache::deleteCategory`, `rex_user::clearInstance`, …), ruft die API ebenfalls — und zwar an der gleichen Stelle.

**Bevor ein neuer/geänderter Endpoint committet wird, gegen das Core-Pendant verifizieren:**

1. Welche `pages/*.php` oder Service-Methode bildet das ab?
2. Welche EPs feuert dieser Pfad — in welcher Reihenfolge, mit welchen Params?
3. Welche Cache-/Instance-Invalidierungen passieren?
4. Was passiert bei Fehlern (Exception-Typ, gemappter Status)?

Erst wenn diese vier Punkte abgehakt sind, ist der Endpoint korrekt. Tests gehören dazu, um Regressionen abzufangen — aber Tests ersetzen nicht die direkte Code-Diffs zwischen API-Handler und Backend-Page.

### Service-Klassen

- `rex_user_service` (`lib/service_user.php`) — Benutzer-CRUD-Operationen mit Passwort-Richtlinien und Admin-Schutz
- `rex_user_role_service` (`lib/service_user_role.php`) — Benutzerrollen-Operationen
- `ListHelper` (`lib/ListHelper.php`) — Einheitliche Pagination/Sort/Meta-Hülle für alle Listen-Endpunkte

### Tests

Es gibt zwei Test-Basisklassen für die zwei Auth-Scopes:

- **`ApiTestCase`** — Bearer-Token-Tests. Stellt HTTP-Hilfsmethoden (`get()`, `post()`, `put()`, `patch()`, `delete()`, `postMultipart()`) und Assertions (`assertSuccess()`, `assertStatus()`, `assertError()`) bereit. Mit `trackResource()` werden erstellte Ressourcen in `tearDown()` automatisch aufgeräumt.
- **`BackendApiTest`** — Session-Cookie-Tests. Loggt sich beim Setup einmal als Admin und einmal als Restricted-User ins Backend ein (CSRF-Token + POST auf `/redaxo/index.php`), persistiert die Cookie-Jars und stellt `adminGet/Post/Put/Delete` und `restrictedGet/Post/Put/Delete` bereit. Damit lassen sich Permission-Pfade beider User-Rollen am selben Endpoint verifizieren.

Konfigurationswerte (Token, Backend-Credentials, existierende IDs) kommen aus `tests/.env` (gitignored, von `tests/bootstrap.php` geparsed). `tests/.env.example` zeigt die verfügbaren Keys.

### Datenbank

- `rex_api_token`-Tabelle — Speichert API-Tokens mit `name`, `token`, `status`, `scopes` (kommagetrennte Scope-Strings)

## Wichtige Hinweise

- Die API läuft im **Frontend-Kontext**, nicht im Backend. Extension Points, die nur im Backend-Kontext registriert werden (`rex::isBackend()`), werden nicht ausgelöst.
- Alle API-Routen haben das Prefix `/api/` (konfiguriert über `RouteCollection::$preRoute`).
- Apache kann den `Authorization`-Header entfernen — siehe README.md für den `.htaccess`-Fix.
