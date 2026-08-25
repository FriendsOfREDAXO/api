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
| /api/structure/articles/{id}/slices            | GET       | Slices eines Artikel anzeigen   | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles/{id}/slices            | POST      | ArticleSlice erstellen          | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles/{id}/slices/{slice_id} | GET       | Slice eines Artikel anzeigen    | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles/{id}/slices/{slice_id} | PUT/PATCH | Slice eines Artikel ändern      | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles/{id}/slices/{slice_id} | DELETE    | Slice eines Artikel löschen     | ✅      | ✅    | ✅       | ✅            |
| /api/structure/categories                      | POST      | Kategorie anlegen               | ✅      | ✅    | ✅       | ✅            |
| /api/structure/categories/{id}                 | PUT/PATCH | Kategorie ändern                | ✅      | ✅    | ✅       | ✅            |
| /api/structure/categories/{id}                 | DELETE    | Kategorie löschen               | ✅      | ✅    | ✅       | ✅            |
| /api/media                                     | GET       | Medienliste                     | ✅      | ✅    | ✅       | ✅            |
| /api/media                                     | POST      | Medium anlegen (multipart)      | ✅      | ✅    | ✅       | ✅            |
| /api/media/{filename}/info                     | GET       | Mediametadaten                  | ✅      | ✅    | ✅       | ✅            |
| /api/media/upload                              | POST      | Chunked Upload starten          | ✅      | ✅    | ✅       | ✅            |
| /api/media/upload/{upload_id}                  | GET       | Chunked Upload: Stand           | ✅      | ✅    | ✅       | ✅            |
| /api/media/upload/{upload_id}/chunk/{index}    | POST/PUT  | Chunked Upload: Chunk senden    | ✅      | ✅    | ✅       | ✅            |
| /api/media/upload/{upload_id}/finalize         | POST      | Chunked Upload abschliessen     | ✅      | ✅    | ✅       | ✅            |
| /api/media/upload/{upload_id}                  | DELETE    | Chunked Upload abbrechen        | ✅      | ✅    | ✅       | ✅            |
| /api/media/{filename}/update                   | PUT/PATCH | Medium ändern                   | ✅      | ✅    | ✅       | ✅            |
| /api/media/{filename}/delete                   | DELETE    | Medium löschen                  | ✅      | ✅    | ✅       | ✅            |
| /api/media/{filename}/file                     | GET       | Mediafile (raw)                 | ✅      | ✅    | ✅       | ✅            |
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
| /api/users/{id}/role                           | GET       | Userrollen eines Users auflisten | ✅      | ✅    | ✅       | ✅            |
| /api/users/{id}/role/{role_id}                 | POST      | Userrolle einem User zuweisen   | ✅      | ✅    | ✅       | ✅            |
| /api/users/{id}/role/{role_id}                 | DELETE    | Userrolle eines Users entfernen | ✅      | ✅    | ✅       | ✅            |
| /api/users/roles                               | GET       | Rollenliste                     | ✅      | ✅    | ✅       | ✅            |
| /api/users/roles                               | POST      | Rolle anlegen                   | ✅      | ✅    | ✅       | ✅            |
| /api/users/roles/{id}                          | GET       | Rolle holen                     | ✅      | ✅    | ✅       | ✅            |
| /api/users/roles/{id}                          | PUT/PATCH | Rolle ändern                    | ✅      | ✅    | ✅       | ✅            |
| /api/users/roles/{id}                          | DELETE    | Rolle löschen                   | ✅      | ✅    | ✅       | ✅            |
| /api/users/roles/{id}/duplicate                | POST      | Rolle duplizieren               | ✅      | ✅    | ✅       | ✅            |
| /api/system/clangs                             | GET       | Sprachenliste                   | ✅      | ✅    | ✅       | ✅            |
| /api/system/clangs                             | POST      | Sprache anlegen                 | ✅      | ✅    | ✅       | ✅            |
| /api/system/clangs/{id}                        | GET       | Sprache auslesen                | ✅      | ✅    | ✅       | ✅            |
| /api/system/clangs/{id}                        | PUT/PATCH | Sprache ändern                  | ✅      | ✅    | ✅       | ✅            |
| /api/system/clangs/{id}                        | DELETE    | Sprache löschen                 | ✅      | ✅    | ✅       | ✅            |
| /api/metainfo/types                            | GET       | Verfügbare Feldtypen            | ✅      | ✅    | ❌       | —            |
| /api/metainfo/fields                           | GET       | Felddefinitionen Liste          | ✅      | ✅    | ❌       | —            |
| /api/metainfo/fields                           | POST      | Felddefinition anlegen          | ✅      | ✅    | ❌       | —            |
| /api/metainfo/fields/{id}                      | GET       | Felddefinition holen            | ✅      | ✅    | ❌       | —            |
| /api/metainfo/fields/{id}                      | PUT/PATCH | Felddefinition ändern           | ✅      | ✅    | ❌       | —            |
| /api/metainfo/fields/{id}                      | DELETE    | Felddefinition löschen          | ✅      | ✅    | ❌       | —            |
| /api/structure/articles/{id}/metainfo          | GET       | Artikel-Metainfo lesen          | ✅      | ✅    | ✅       | ✅            |
| /api/structure/articles/{id}/metainfo          | PUT/PATCH | Artikel-Metainfo ändern         | ✅      | ✅    | ✅       | ✅            |
| /api/structure/categories/{id}/metainfo        | GET       | Kategorie-Metainfo lesen        | ✅      | ✅    | ✅       | ✅            |
| /api/structure/categories/{id}/metainfo        | PUT/PATCH | Kategorie-Metainfo ändern       | ✅      | ✅    | ✅       | ✅            |
| /api/media/{filename}/metainfo                 | GET       | Medien-Metainfo lesen           | ✅      | ✅    | ✅       | ✅            |
| /api/media/{filename}/metainfo                 | PUT/PATCH | Medien-Metainfo ändern          | ✅      | ✅    | ✅       | ✅            |
| /api/system/clangs/{id}/metainfo               | GET       | Sprach-Metainfo lesen           | ✅      | ✅    | ✅       | ✅            |
| /api/system/clangs/{id}/metainfo               | PUT/PATCH | Sprach-Metainfo ändern          | ✅      | ✅    | ✅       | ✅            |
| /api/me                                        | GET       | Selbstauskunft: erlaubte Endpunkte | ✅      | ✅    | ✅       | ✅            |

**Metainfo & Backend:** Wert-Endpunkte (Article/Category/Media/Clang) sind via Backend-Session erreichbar und prüfen die jeweiligen User-Rechte: `structure`-Perm für Article/Category, `media`-Perm für Media, **admin-only für Clang** (REDAXO-Core's Sprachen-Page `pages/system.clangs.php` ist via `setRequiredPermissions('isAdmin')` ebenfalls admin-only — wir spiegeln das exakt). Field-Management (`/metainfo/types`, `/metainfo/fields`, `/metainfo/fields/{id}`) bleibt bewusst Bearer-only — Schema-Änderungen sind kein typischer Backend-User-Job.

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

Der Token-Wert muss eindeutig sein — die Spalte trägt einen Unique-Index, und die Token-Seite lehnt einen bereits vergebenen Wert mit einer Meldung ab. Enthält eine bestehende Installation doppelte Werte, wird der Index beim Update übersprungen und eine Warnung ins System-Log geschrieben; nach dem Auflösen der Duplikate greift er beim nächsten Update.

### Ablaufdatum für Tokens

Ein Token kann optional ablaufen. Die Auswahl **Läuft ab** auf der Token-Seite bietet dafür Zeitspannen an (3 Stunden bis 1 Jahr) und nennt zu jeder den Zeitpunkt, auf den sie hinausläuft. **Benutzerdefiniert** schaltet das Feld **Ablaufdatum** frei, **Nie** lässt das Token unbegrenzt gültig — so verhalten sich auch alle Tokens, die vor dem Update angelegt wurden.

Der Zeitpunkt wird beim Speichern aus der Datenbankzeit berechnet, nicht im Browser: eine Auswahl von „7 Tage“ liegt damit auch dann sieben Tage in der Zukunft, wenn Server und Arbeitsplatz in verschiedenen Zeitzonen stehen.

Ist der Ablauf gesetzt und erreicht, wird das Token nicht mehr autorisiert: Anfragen bekommen `401` mit `{"error": "Authorization failed"}`, genau wie bei einem unbekannten Token. Der Vergleich läuft über die Datenbankzeit (`now()`), also über dieselbe Zeit, in der das Datum im Backend eingegeben wurde.

## Slices: Live-Version und Arbeitsversion

Slices tragen eine Revision: `0` ist die Live-Version, `1` die Arbeitsversion des Plugins `structure/version`. Die API behandelt sie durchgehend:

* `GET .../slices` liefert per Default die Live-Version. `?revision=1` liefert die Arbeitsversion.
* `POST .../slices` legt per Default in der Live-Version an. `"revision": 1` im Body legt in der Arbeitsversion an — der Wert geht an `rex_content_service::addSlice()`, die Priorität wird innerhalb der Revision gezählt.
* `GET`, `PUT/PATCH` und `DELETE` auf `.../slices/{slice_id}` arbeiten auf genau dem adressierten Slice, unabhängig von seiner Revision. Die Revision selbst ändern sie nicht — genau wie `rex_content_service::editSlice()`, das sie aus dem Datensatz liest. Ein `revision` im Update-Body wird darum mit `400` abgelehnt.

```bash
# in der Arbeitsversion anlegen
curl -X POST -H "Authorization: Bearer DEIN_TOKEN" -H "Content-Type: application/json" \
  -d '{"module_id":2,"clang_id":1,"ctype_id":1,"revision":1,"value1":"Entwurf"}' \
  https://example.org/api/structure/articles/42/slices
```

Wer per API in die Live-Version schreibt, während im Backend eine Arbeitsversion gepflegt wird, verliert diese Slices beim nächsten „Arbeitsversion → Live": `rex_article_revision::copyContent()` leert das Ziel, bevor es kopiert. Die Revision gehört deshalb bewusst gesetzt.

Bei den Backend-Endpunkten (`/api/backend/...`) gilt dieselbe Rechteprüfung wie im Backend: wer das Recht `version[live_version]` nicht hat, wird dort auf die Arbeitsversion festgelegt und bekommt für die Live-Revision `403` mit `required_permission: version[live_version]` — beim Anlegen wie beim Ändern und Löschen. Ohne aktives Plugin `structure/version` greift die Prüfung nicht. Für Bearer-Tokens greift sie ebenfalls nicht: dort ersetzen Scopes die User-Permissions, wie bei den Kategorie- und Modulrechten auch.

**Unbekannte Felder im Slice-Body werden abgelehnt** (`400 Unknown field(s): …`). Ein Tippfehler wie `revison` galt vorher als Erfolg, und der Slice landete in der Live-Version.

## Selbstauskunft: /api/me

`GET /api/me` beantwortet für den *aufrufenden* Zugang die Frage, was er darf. Gedacht für Clients und Agenten, die die API ohne externe Doku bedienen sollen:

* Gelistet werden **nur Endpunkte, für die der Scope tatsächlich vorhanden ist** — nicht die komplette Routentabelle.
* Der Endpunkt braucht **keinen eigenen Scope**. Jedes gültige Token bekommt eine Antwort, auch ein neu angelegtes.
* Das Token selbst wird nicht ausgegeben, nur sein Name und seine Scopes.

```bash
curl -H "Authorization: Bearer DEIN_TOKEN" https://example.org/api/me
```

```json
{
  "meta": {
    "api_base": "/api",
    "auth": { "type": "bearer", "token_name": "Sync", "scopes": ["structure/articles/list", "..."] },
    "endpoint_count": 26,
    "openapi_url": "/api/me?format=openapi"
  },
  "endpoints": [
    {
      "scope": "structure/articles/get",
      "methods": ["GET"],
      "path": "/api/structure/articles/{id}",
      "description": "Get article details",
      "tags": ["default"],
      "path_parameters": { "id": { "required": true, "type": "string", "pattern": "\\d+" } }
    }
  ]
}
```

Pro Endpunkt werden `path_parameters`, `query` und `body` mit Typ, `required`, Default und Beschreibung ausgegeben — leere Blöcke werden weggelassen. `required` folgt der Validierung: ein Feld ohne explizites `required` **ist** erforderlich.

`GET /api/me?format=openapi` liefert dieselbe Menge als vollständige OpenAPI-3.0-Spezifikation — gleicher Generator wie die Swagger-UI im Backend, nur auf die erlaubten Routen gefiltert. Das kompakte Format ist der Default, weil es bei vielen Routen deutlich weniger Kontext kostet. Parameter und Body-Felder tragen dort ihren Typ und Default im `schema`, sind also für Client-Generatoren verwendbar. Was die Spec nicht enthält, sind Response-Schemas pro Route: Listen liefern `{data, meta}` (siehe unten), Detail-Routen das Objekt flach.

Für Backend-Session-Zugriffe gibt es `GET /api/backend/me`. Dort wird nicht vorab gefiltert: Backend-Permissions werden pro Request geprüft, ein gelisteter Endpunkt kann also weiterhin mit 403 antworten. Der Hinweis steht in `meta.note`.

### Fehlender Scope ist unterscheidbar

Bei einem gültigen Token ohne den benötigten Scope nennt die 401-Antwort den Scope, der fehlt. Bei ungültigem oder fehlendem Token fehlt das Feld:

```json
{ "error": "Authorization failed", "required_scope": "users/list" }
```

## API Struktur

Am besten direkt im AddOn unter OpenAPI nachsehen. Dort werden alle verfügbaren Endpunkte aufgelistet. Programmatisch übernimmt das `/api/me` (siehe oben).

In der Endpunktliste der Swagger-UI wird die Beschreibung auf 50 Zeichen gekürzt, damit jeder Endpunkt eine Zeile bleibt — der vollständige Text erscheint beim Hover über der Beschreibung. Gekürzt wird nur die Anzeige: die ausgelieferte Spezifikation enthält die Beschreibung unverändert.

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

### Upload-Grenzen

`POST /api/media` unterliegt den PHP-Limits der Installation. Die Antwort unterscheidet die Fälle, damit ein Client erkennt, woran es liegt:

| Situation | Status | Antwort |
|---|---|---|
| Datei größer als `upload_max_filesize` | `413` | `error` plus `limits` mit beiden Werten in Bytes |
| Request größer als `post_max_size` (PHP verwirft den ganzen Body) | `413` | zusätzlich `content_length` |
| Kein `file`-Feld im Request | `400` | `No file uploaded` |
| Übertragung abgebrochen | `400` | `Upload incomplete` |

Beispiel:

```json
{
  "error": "File too large: exceeds upload_max_filesize",
  "limits": { "upload_max_filesize": 2097152, "post_max_size": 8388608 }
}
```

Wer regelmäßig größere Dateien überträgt, erhöht die Limits in der `php.ini` — oder wartet auf die Chunked-Upload-Endpunkte (Issue #39).

### Große Dateien: Chunked Upload

`POST /api/media` ist an `upload_max_filesize` und `post_max_size` gebunden. Größere Dateien gehen stückweise über vier Endpunkte — das Anlegen selbst erledigt danach derselbe Core-Service wie beim normalen Upload, es entsteht also kein zweiter Weg ins Medienverzeichnis.

```
1. POST   /api/media/upload
          { "filename": "video.mp4", "size": 524288000, "category_id": 3, "title": "…" }
          → 201 { "upload_id": "…", "chunk_size_max": 2031616, "expires_at": "…" }

2. POST   /api/media/upload/{upload_id}/chunk/{index}
          Body: die Chunk-Bytes (application/octet-stream)
          → 200 { "bytes_received": …, "bytes_missing": … }

3. GET    /api/media/upload/{upload_id}
          → 200 { "chunks": [0,1,2], "complete": false, "bytes_missing": … }

4. POST   /api/media/upload/{upload_id}/finalize
          → 201 { "filename": "video.mp4" }
```

Wissenswertes:

- **Chunkgröße**: `chunk_size_max` aus der Init-Antwort ist die kleinere der beiden PHP-Grenzen minus Reserve. Kleinere Chunks sind immer erlaubt.
- **Index** ist nullbasiert und muss lückenlos bei `0` beginnen. Derselbe Index erneut gesendet **ersetzt** den Chunk — damit ist ein Wiederholen nach Verbindungsabbruch möglich.
- **Fortsetzen**: `GET` liefert die bereits angekommenen Indizes; der Client sendet nur den Rest.
- **Abbrechen**: `DELETE /api/media/upload/{upload_id}` verwirft die Teile.
- **Grenzen**: höchstens 2 GiB pro Datei und 20 000 Chunks. Die bei `init` angekündigte Größe ist verbindlich — mehr Bytes werden abgewiesen, weniger verhindern das Abschließen.
- **Verfall**: ein begonnener Upload wird nach 24 Stunden verworfen; aufgeräumt wird beim nächsten `init`.
- **Ein Scope**: alle fünf Endpunkte laufen über den Scope `media/upload` (Backend: `backend/media/upload`). Einzeln sind sie unbrauchbar, deshalb gibt es dafür nur eine Checkbox auf der Token-Seite.
- **Bindung an den Aufrufer**: ein Upload ist nur für das Token beziehungsweise den Backend-User sichtbar, der ihn begonnen hat. Fremde Zugriffe erhalten `404`.
- Die Endung wird bereits bei `init` geprüft, damit ein unerlaubter Dateityp nicht erst nach der Übertragung auffällt.

Chunks liegen bis zum Abschluss unter `redaxo/data/addons/api/upload/` und sind über den Webserver nicht erreichbar.

### Medien suchen und filtern

`GET /api/media` kennt neben den Bereichsfiltern (`filesize_min/max`, `width_min/max`, `height_min/max`) drei Filter, die dasselbe leisten wie die Suche der Backend-Medienliste (`rex_media_service::getList()`):

| Filter                     | Beschreibung |
|----------------------------|--------------|
| `filter[term]`             | Freitext über **Dateiname oder Titel**. Mehrere durch Leerzeichen getrennte Begriffe werden UND-verknüpft, `"in Anführungszeichen"` bleibt ein Begriff, und `type:jpg,png` filtert stattdessen die Dateiendung. |
| `filter[category_id_path]` | Kategorie **inklusive aller Unterkategorien**. Unbekannte Kategorie ergibt `404`. |
| `filter[types]`            | Kommagetrennte Liste von Dateiendungen, z. B. `jpg,png`. |

Beispiele:

```
GET /api/media?filter[term]=logo
GET /api/media?filter[term]=logo type:svg,png
GET /api/media?filter[category_id_path]=4
```

Zur Abgrenzung: `filter[filename]` und `filter[filetype]` vergleichen **exakt**, `filter[title]` sucht als Teilstring. Wer einen Dateinamen nur teilweise kennt, nimmt `filter[term]`.

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

Eigene Endpunkte anderer AddOns erscheinen automatisch in `/api/me` und in der OpenAPI-Spezifikation — es ist nichts zusätzlich zu registrieren. Ausgegeben wird dabei genau das, was die Route deklariert: gepflegte `query`- und `Body`-Definitionen samt `description` machen den Endpunkt für einen aufrufenden Client oder Agenten benutzbar, fehlende Definitionen lassen ihn ohne Parameter erscheinen. Datei-Uploads sollten `'type' => 'file'` verwenden, dann wird in der Spezifikation `multipart/form-data` mit `format: binary` erzeugt.

Wer eigene Tags vergibt, sollte auch den Sprachschlüssel `api_openapi_tag_<tag>_description` mitliefern — sonst bleibt die Tag-Beschreibung in Swagger UI und in der Spezifikation leer.

`new BearerAuth(false)` autorisiert jedes gültige Token ohne Scope-Prüfung. Das ist für Selbstauskunft-artige Endpunkte gedacht; alles, was Daten liest oder schreibt, gehört hinter `new BearerAuth()` mit eigenem Scope.

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
