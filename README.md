# APIs für REDAXO

## Beschreibung

Dieses AddOn ermöglich es, APIs in REDAXO zu nutzen. Dabei geht es vor allem um die Nutzung von APIs aus anderen Systemen heraus, um z.B. Daten abzugleichen oder zu ergänzen. Weiterhin ist die API erweiterbar. Jedes andere AddOn kann eigene Endpunkte anlegen. 

Zunächst ist geplant die Basisfeatures von REDAXO abzubilden. 

## Geplante und umgesetzte Endpunkte

### Endpunkte

| Endpunkt                             | Method    | Beschreibung       | Status                                     |
|--------------------------------------|-----------|--------------------|--------------------------------------------|
| /api/structure/articles              | GET       | Artikelliste       | <span style="color:green">umgesetzt</span> |
| /api/structure/articles/{id}/slice   | POST      | Slices erstellen   | <span style="color:green">umgesetzt</span> |
| /api/structure/articles              | POST      | Artikel anlegen    | <span style="color:green">umgesetzt</span> |
| /api/structure/articles/{id}         | DELETE    | Artikel löschen    | <span style="color:green">umgesetzt</span> |
| /api/structure/articles/{id}         | PUT/PATCH | Artikel ändern     | <span style="color:red">noch offen</span>  |
| /api/structure/categories            | POST      | Kategorie anlegen  | <span style="color:green">umgesetzt</span> |
| /api/structure/categories/{id}       | DELETE    | Kategorie anzeigen | <span style="color:green">umgesetzt</span> |
| /api/structure/categories/{id}       | PUT/PATCH | Kategorie ändern   | <span style="color:red">noch offen</span>                                 |
| /api/media                           | GET       | Medienliste        | <span style="color:red">noch offen</span>                                 |
| /api/media                           | POST      | Medium anlegen     | <span style="color:red">noch offen</span>                                 |
| /api/media/{id}                      | DELETE    | Medium löschen     | <span style="color:red">noch offen</span>                                 |
| /api/media/{id}                      | PUT/PATCH | Medium ändern      | <span style="color:red">noch offen</span>                                 |
| /api/modules                         | GET       | Modulliste         | <span style="color:red">noch offen</span>                                 |
| /api/modules                         | POST      | Modul anlegen      | <span style="color:red">noch offen</span>                                 |
| /api/module/{id}                     | DELETE    | Modul löschen      | <span style="color:red">noch offen</span>                                 |
| /api/module/{id}                     | PUT/PATCH | Modul ändern       | <span style="color:red">noch offen</span>                                 |
| /api/templates                       | GET       | Template Liste     | <span style="color:red">noch offen</span>                                 |
| /api/templates                       | POST      | Template anlegen   | <span style="color:red">noch offen</span>                                 |
| /api/templates/{id}                  | DELETE    | Template löschen   | <span style="color:red">noch offen</span>                                 |
| /api/templates/{id}                  | PUT/PATCH | Template ändern    | <span style="color:red">noch offen</span>                                 |
| /api/users                           | GET       | Userliste          | <span style="color:red">noch offen</span>                                 |
| /api/users                           | POST      | User anlegen       | <span style="color:red">noch offen</span>                                 |
| /api/users/{id}                      | DELETE    | User löschen       | <span style="color:red">noch offen</span>                                 |
| /api/users/{id}                      | PUT/PATCH | User ändern        | <span style="color:red">noch offen</span>                                 |
| /api/clangs                          | GET       | Sprachenliste      | <span style="color:red">noch offen</span>                                 |
| /api/clangs                          | POST      | Sprache anlegen    | <span style="color:red">noch offen</span>                                 |
| /api/clangs/{id}                     | DELETE    | Sprache löschen    | <span style="color:red">noch offen</span>                                 |
| /api/clangs/{id}                     | PUT/PATCH | Sprache ändern     | <span style="color:red">noch offen</span>                                 |

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

