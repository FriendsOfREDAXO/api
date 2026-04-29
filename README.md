# APIs für REDAXO

## Beschreibung

Dieses AddOn ermöglich es, APIs in REDAXO zu nutzen. Dabei geht es vor allem um die Nutzung von APIs aus anderen Systemen heraus, um z.B. Daten abzugleichen oder zu ergänzen. Weiterhin ist die API erweiterbar. Jedes andere AddOn kann eigene Endpunkte anlegen. 

Zunächst ist geplant die Basisfeatures von REDAXO abzubilden. 

## Geplante und umgesetzte Endpunkte

Wenn getestet, dann wurde explicit nochmal geprüft, ob die Funktionalität exakt so umgesetzt sind, wie sie in REDAXO/Core verwendet wurde. 

* Passende Extension Points
* Vorhandene Klassen wurden genutzt
* Felder sind auf das Nötigste reduziert. Keine Felder von externen/anderen AddOns/PlugIns werden ausgegeben oder verarbeitet.
* OpenAPI Spezifikationen sind vorhanden und richtig verwendet

### Endpunkte

Spalten: **Status** = Endpoint implementiert · **Test** = Bearer-API-Test vorhanden · **Backend** = Backend-Variante (`/api/backend/...`) verfügbar · **Backend Test** = Admin-/Restricted-User-Test in `BackendApiTest`.

| Endpunkt                                       | Method    | Beschreibung                    | Status | Test | Backend | Backend Test |
|------------------------------------------------|-----------|---------------------------------|--------|------|---------| ------------ |
| /api/structure/articles                        | GET       | Artikelliste                    | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles                        | POST      | Artikel anlegen                 | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles/{id}                   | GET       | Artikel anzeigen                | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles/{id}                   | PUT/PATCH | Artikel ändern                  | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles/{id}                   | DELETE    | Artikel löschen                 | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles/{id}/slices            | GET       | Slices eines Artikel anzeigen   | ✅      | ✅    | ✅       | ❌            |
| /api/structure/articles/{id}/slices            | POST      | ArticleSlice erstellen          | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles/{id}/slices/{slice_id} | GET       | Slice eines Artikel anzeigen    | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles/{id}/slices/{slice_id} | PUT/PATCH | Slice eines Artikel ändern      | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles/{id}/slices/{slice_id} | DELETE    | Slice eines Artikel löschen     | ✅      | ✅    | ✅       | ✅            |
| /api/structure/categories                      | POST      | Kategorie anlegen               | ✅      | ✅    | ✅       | ✅            |
| /api/structure/categories/{id}                 | PUT/PATCH | Kategorie ändern                | ✅      | ✅    | ✅       | ✅            |
| /api/structure/categories/{id}                 | DELETE    | Kategorie löschen               | ✅      | ✅    | ✅       | ✅            |
| /api/media                                     | GET       | Medienliste                     | ✅      | ✅    | ✅       | ✅            |
| /api/media                                     | POST      | Medium anlegen                  | ✅      | ✅    | ✅       | ❌            |
| /api/media/{filename}                          | GET       | Mediametadaten                  | ✅      | ✅    | ✅       | ❌            |
| /api/media/{filename}                          | PUT/PATCH | Medium ändern                   | ✅      | ✅    | ✅       | ❌            |
| /api/media/{filename}                          | DELETE    | Medium löschen                  | ✅      | ✅    | ✅       | ❌            |
| /api/media/{filename}/file                     | GET       | Mediafile (raw)                 | ✅      | ✅    | ✅       | ❌            |
| /api/media/category                            | GET       | Mediakategorienliste            | ✅      | ✅    | ✅       | ✅            |
| /api/media/category                            | POST      | Mediakategorie anlegen          | ✅      | ✅    | ✅       | ✅            |
| /api/media/category/{id}                       | PUT/PATCH | Mediakategorie ändern           | ✅      | ✅    | ✅       | ✅            |
| /api/media/category/{id}                       | DELETE    | Mediakategorie löschen          | ✅      | ✅    | ✅       | ✅            |
| /api/modules                                   | GET       | Modulliste                      | ✅      | ✅    | ✅       | ✅            |
| /api/modules                                   | POST      | Modul anlegen                   | ✅      | ✅    | ✅       | ✅            |
| /api/modules/{id}                              | GET       | Modul auslesen                  | ✅      | ✅    | ✅       | ✅            |
| /api/modules/{id}                              | PUT/PATCH | Modul ändern                    | ✅      | ✅    | ✅       | ✅            |
| /api/modules/{id}                              | DELETE    | Modul löschen                   | ✅      | ✅    | ✅       | ✅            |
| /api/templates                                 | GET       | Template Liste                  | ✅      | ✅    | ✅       | ✅            |
| /api/templates                                 | POST      | Template anlegen                | ✅      | ✅    | ✅       | ✅            |
| /api/templates/{id}                            | GET       | Template auslesen               | ✅      | ✅    | ✅       | ✅            |
| /api/templates/{id}                            | PUT/PATCH | Template ändern                 | ✅      | ✅    | ✅       | ✅            |
| /api/templates/{id}                            | DELETE    | Template löschen                | ✅      | ✅    | ✅       | ✅            |
| /api/users                                     | GET       | Userliste                       | ✅      | ✅    | ✅       | ✅            |
| /api/users                                     | POST      | User anlegen                    | ✅      | ✅    | ✅       | ✅            |
| /api/users/{id}                                | GET       | User holen                      | ✅      | ✅    | ✅       | ✅            |
| /api/users/{id}                                | PUT/PATCH | User ändern                     | ✅      | ✅    | ✅       | ✅            |
| /api/users/{id}                                | DELETE    | User löschen                    | ✅      | ✅    | ✅       | ✅            |
| /api/users/{id}/role                           | GET       | Userrollen eines Users auflisten | ❌      |      |         |              |
| /api/users/{id}/role                           | POST      | Userrole einem User hinzufügen  | ❌      |      |         |              |
| /api/users/{id}/role                           | DELETE    | Userrole eines Users löschen    | ❌      |      |         |              |
| /api/users/roles                               | GET       | Rollenliste                     | ✅      | ✅    | ✅       | ✅            |
| /api/users/roles                               | POST      | Rolle anlegen                   | ✅      | ✅    | ✅       | ✅            |
| /api/users/roles/{id}                          | GET       | Rolle holen                     | ✅      | ✅    | ✅       | ✅            |
| /api/users/roles/{id}                          | PUT/PATCH | Rolle ändern                    | ✅      | ✅    | ✅       | ✅            |
| /api/users/roles/{id}                          | DELETE    | Rolle löschen                   | ✅      | ✅    | ✅       | ✅            |
| /api/users/roles/{id}/duplicate                | POST      | Rolle duplizieren               | ✅      | ✅    | ✅       | ❌            |
| /api/system/clangs                             | GET       | Sprachenliste                   | ✅      | ✅    | ✅       | ✅            |
| /api/system/clangs                             | POST      | Sprache anlegen                 | ✅      | ✅    | ✅       | ✅            |
| /api/system/clangs/{id}                        | GET       | Sprache auslesen                | ✅      | ✅    | ✅       | ✅            |
| /api/system/clangs/{id}                        | PUT/PATCH | Sprache ändern                  | ✅      | ✅    | ✅       | ✅            |
| /api/system/clangs/{id}                        | DELETE    | Sprache löschen                 | ✅      | ✅    | ✅       | ✅            |
| /api/metainfo/types                            | GET       | Verfügbare Feldtypen            | ✅      | ✅    | ✅       | ❌            |
| /api/metainfo/fields                           | GET       | Felddefinitionen Liste          | ✅      | ✅    | ✅       | ❌            |
| /api/metainfo/fields                           | POST      | Felddefinition anlegen          | ✅      | ✅    | ✅       | ❌            |
| /api/metainfo/fields/{id}                      | GET       | Felddefinition holen            | ✅      | ✅    | ✅       | ❌            |
| /api/metainfo/fields/{id}                      | PUT/PATCH | Felddefinition ändern           | ✅      | ✅    | ✅       | ❌            |
| /api/metainfo/fields/{id}                      | DELETE    | Felddefinition löschen          | ✅      | ✅    | ✅       | ❌            |
| /api/structure/articles/{id}/metainfo          | GET       | Artikel-Metainfo lesen          | ✅      | ✅    | ✅       | ❌            |
| /api/structure/articles/{id}/metainfo          | PUT/PATCH | Artikel-Metainfo ändern         | ✅      | ✅    | ✅       | ❌            |
| /api/structure/categories/{id}/metainfo        | GET       | Kategorie-Metainfo lesen        | ✅      | ✅    | ✅       | ❌            |
| /api/structure/categories/{id}/metainfo        | PUT/PATCH | Kategorie-Metainfo ändern       | ✅      | ✅    | ✅       | ❌            |
| /api/media/{filename}/metainfo                 | GET       | Medien-Metainfo lesen           | ✅      | ✅    | ✅       | ❌            |
| /api/media/{filename}/metainfo                 | PUT/PATCH | Medien-Metainfo ändern          | ✅      | ✅    | ✅       | ❌            |
| /api/system/clangs/{id}/metainfo               | GET       | Sprach-Metainfo lesen           | ✅      | ✅    | ✅       | ❌            |
| /api/system/clangs/{id}/metainfo               | PUT/PATCH | Sprach-Metainfo ändern          | ✅      | ✅    | ✅       | ❌            |

## Bei Problemen mit Authorization

Es kann sein, dass Apache nicht alle Header weitergibt. In diesem Fall kann es helfen, die folgenden Zeilen in die .htaccess zu schreiben:

```
# Sets the HTTP_AUTHORIZATION header removed by Apache
RewriteCond %{HTTP:Authorization} .
RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

## Authentifizierung beachten

Die meisten APIs haben Authentifizierung. Das heisst, es muss ein API-Token im Backend angelegt werden, um die Endpunkte nutzen zu können, wie auch der entsprechende Scope gesetzt werden.
Andere APIs haben eine Backend-Authentifizierung, die dann über den Backend-User läuft, d.h. es kann der Session Cookie verwendet werden, um die Endpunkte zu nutzen.

## API Struktur

Am besten direkt im AddOn unter OpenAPI nachsehen. Dort werden alle verfügbaren Endpunkte aufgelistet.

### Response-Format für Listen-Endpunkte

Alle Listen-Endpunkte liefern ein einheitliches Response-Format mit Daten und Meta-Informationen:

```json
{
  "data": [
    { "id": 1, "name": "..." },
    { "id": 2, "name": "..." }
  ],
  "meta": {
    "page": 1,
    "per_page": 100,
    "total": 42,
    "total_pages": 1
  }
}
```

### Paginierung

Alle Listen-Endpunkte unterstützen Paginierung über Query-Parameter:

| Parameter  | Typ | Default | Beschreibung                |
|-----------|-----|---------|----------------------------|
| `page`     | int | 1       | Seitennummer (1-basiert)   |
| `per_page` | int | 100     | Einträge pro Seite         |

Beispiel: `GET /api/media?page=2&per_page=10`

### Sortierung

Alle Listen-Endpunkte unterstützen Sortierung über den `sort` Query-Parameter. Mehrere Sortierfelder können kommagetrennt angegeben werden:

```
?sort=field1:asc,field2:desc
```

| Richtung | Beschreibung |
|---------|-------------|
| `asc`   | Aufsteigend (Standard) |
| `desc`  | Absteigend  |

Beispiele:
- `GET /api/media?sort=filesize:desc` - Medien nach Dateigröße absteigend
- `GET /api/structure/articles?sort=name:asc,createdate:desc` - Artikel nach Name aufsteigend, dann nach Erstelldatum absteigend
- `GET /api/system/clangs?sort=priority:asc` - Sprachen nach Priorität

Bei ungültigem Sortierfeld wird ein `400 Bad Request` zurückgegeben.

Jeder Endpunkt hat eine eigene Whitelist erlaubter Sortierfelder (siehe OpenAPI-Dokumentation).

## Was funktioniert vielleicht nicht, und müssen AddOn Entwickler beachten

Das API AddON funktioniert aus dem Frontend-User-Kontext heraus. Das heisst, sollte es registrierte Methoden an bestimmten
ExtensionPoints geben, welche nur im Backend-User-Kontext gesetzt wurden, z.B. (rex::isBackend) -> registerEP, dann werden diese nicht in der dieser API ausgeführt.
D.h. diese AddOns müssen entsprechend angepasst werden.

## Weitere noch nicht beachtete Usecases

### FE API (Wird hier noch nicht behandelt)
    - GET API 
        - für Content frei und abhängig vom Frontenduserrechten YCom/YGroup
    - POST/UPDATE/GET/DELETE API
        - YCOm Profile, Password etc.
        - für YForm
        - Für Sonsiges

### Backend API

Authentifizierung läuft über den PHP Session Cookie, d.h. es muss ein Backend-User angemeldet sein, um die Endpunkte nutzen zu können. Diese Endpunkte beachten die Rechte des einzelnen Users und ist dafür gedacht, dass man diese nur aus dem Backend heraus aufrufen kann. Z.B. wenn man eine alternative Anzeige oder Verwaltung nutzen oder aufbauen möchte.

## Credits: 
checked by: https://www.coderabbit.ai
