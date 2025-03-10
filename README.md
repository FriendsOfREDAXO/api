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

| Endpunkt                                       | Method    | Beschreibung                    | Status | Test |
|------------------------------------------------|-----------|---------------------------------|--------|------|
| /api/structure/articles                        | GET       | Artikelliste                    | ✅      | ✅    |
| /api/structure/articles/{id}/slices            | GET       | Slices eines Artikel anzeigen   | ❌      |
| /api/structure/articles/{id}/slices            | POST      | ArticleSlice erstellen          | ✅      | ✅    |
| /api/structure/articles/{id}/slices/{slice_id} | GET       | Slice eines Artikel anzeigen    | ❌      |
| /api/structure/articles/{id}/slices/{slice_id} | PUT/PATCH | Slice eines Artikel ändern      | ❌      |
| /api/structure/articles                        | POST      | Artikel anlegen                 | ✅      | ✅    |
| /api/structure/articles/{id}                   | GET       | Artikel anzeigen                | ❌      |
| /api/structure/articles/{id}                   | DELETE    | Artikel löschen                 | ✅      | ✅    |
| /api/structure/articles/{id}                   | PUT/PATCH | Artikel ändern                  | ❌      |
| /api/structure/categories                      | POST      | Kategorie anlegen               | ✅      | ✅    |
| /api/structure/categories/{id}                 | DELETE    | Kategorie löschen               | ✅      | ✅    |
| /api/structure/categories/{id}                 | PUT/PATCH | Kategorie ändern                | ❌      |
| /api/media                                     | GET       | Medienliste                     | ✅      | ✅    |
| /api/media/{filename}                          | GET       | Mediametadaten                  | ✅      | ✅    |
| /api/media/{filename}/file                     | GET       | Mediafile (raw)                 | ✅      | ✅    |
| /api/media                                     | POST      | Medium anlegen                  | ❌      |
| /api/media/{id}                                | DELETE    | Medium löschen                  | ✅      | ✅    |
| /api/media/{id}                                | PUT/PATCH | Medium ändern                   | ❌      |
| /api/media/categories                          | GET       | Mediakategorienliste            | ❌      |
| /api/media/categories                          | POST      | Mediakategorie anlegen          | ❌      |
| /api/media/categories/{id}                     | DELETE    | Mediakategorie löschen          | ❌      |
| /api/media/categories/{id}                     | PUT/PATCH | Mediakategorie ändern           | ❌      |
| /api/modules                                   | GET       | Modulliste                      | ✅      | ✅    |
| /api/modules                                   | POST      | Modul anlegen                   | ✅      | ✅    | 
| /api/module/{id}                               | GET       | Modul auslesen                  | ✅      | ✅    |
| /api/module/{id}                               | DELETE    | Modul löschen                   | ✅      | ✅    |
| /api/module/{id}                               | PUT/PATCH | Modul ändern                    | ✅      | ✅    |
| /api/templates                                 | GET       | Template Liste                  | ✅      | ✅    |
| /api/templates                                 | POST      | Template anlegen                | ✅      | ✅    |
| /api/templates/{id}                            | DELETE    | Template löschen                | ✅      | ✅    | 
| /api/templates/{id}                            | PUT/PATCH | Template ändern                 | ✅      | ✅    | 
| /api/users                                     | GET       | Userliste                       | ✅      | ✅    |
| /api/users                                     | POST      | User anlegen                    | ❌      |
| /api/users/{id}                                | GET       | User holen                      | ✅      | ✅    |
| /api/users/{id}/role                           | GET       | Userrolen eines Users auflisten | ❌      |
| /api/users/{id}/role                           | POST      | Userrole einem Users hinzufügen | ❌      |
| /api/users/{id}/role                           | DELETE    | Userrole eines Users löschen    | ❌      |
| /api/users/{id}                                | DELETE    | User löschen                    | ✅      | ✅    |
| /api/users/{id}                                | PUT/PATCH | User ändern                     | ❌      |
| /api/users/roles                               | GET       | Rollenliste                     | ✅      | ✅    |
| /api/users/roles                               | POST      | Rolle anlegen                   | ❌      |
| /api/users/roles/{id}                          | DELETE    | Rolle löschen                   | ❌      |
| /api/users/roles/{id}                          | PUT/PATCH | Rolle ändern                    | ❌      |
| /api/system/clangs                             | GET       | Sprachenliste                   | ✅      | ✅    |
| /api/system/clangs                             | POST      | Sprache anlegen                 | ✅      | ✅    |
| /api/system/clangs/{id}                        | GET       | Sprache auslesen                | ✅      | ✅    |
| /api/system/clangs/{id}                        | DELETE    | Sprache löschen                 | ✅      | ✅    |
| /api/system/clangs/{id}                        | PUT/PATCH | Sprache ändern                  | ✅      | ✅    |

## Bei Problemen mit Authorization

Es kann sein, dass der Apache nicht alle Header weitergibt. In diesem Fall kann es helfen, die folgenden Zeilen in die .htaccess zu schreiben:

```
# Sets the HTTP_AUTHORIZATION header removed by Apache
RewriteCond %{HTTP:Authorization} .
RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

## Ohne API-Token geht nichts

Bitte zuerst einen API-Token im Backend anlegen und die ensprechenden Scopes (Endpunkt) freigeben.

## API Struktur

Am besten direkt im AddOn unter OpenAPI nachsehen. Dort werden alle verfügbaren Endpunkte aufgelistet.

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

### BE API (Wird hier noch nicht behandelt)
    - für alles mit BE-User-Rechten

## Credits: 
checked by: https://www.coderabbit.ai
