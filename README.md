# APIs für REDAXO

## Beschreibung

Dieses AddOn ermöglich es, APIs in REDAXO zu nutzen. Dabei geht es vor allem um die Nutzung von APIs aus anderen Systemen heraus, um z.B. Daten abzugleichen oder zu ergänzen. Weiterhin ist die API erweiterbar. Jedes andere AddOn kann eigene Endpunkte anlegen. 

Zunächst ist geplant die Basisfeatures von REDAXO abzubilden. 

## Geplante und umgesetzte Endpunkte

### Endpunkte

| Endpunkt                             | Method    | Beschreibung       | Status |
|--------------------------------------|-----------|--------------------|--------|
| /api/structure/articles              | GET       | Artikelliste       | ✅      |
| /api/structure/articles/{id}/slice   | POST      | Slices erstellen   | ✅      |
| /api/structure/articles              | POST      | Artikel anlegen    | ✅      |
| /api/structure/articles/{id}         | DELETE    | Artikel löschen    | ✅      |
| /api/structure/articles/{id}         | PUT/PATCH | Artikel ändern     | ❌      |
| /api/structure/categories            | POST      | Kategorie anlegen  | ✅      |
| /api/structure/categories/{id}       | DELETE    | Kategorie anzeigen | ✅      |
| /api/structure/categories/{id}       | PUT/PATCH | Kategorie ändern   | ❌      |
| /api/media                           | GET       | Medienliste        | ❌      |
| /api/media                           | POST      | Medium anlegen     | ❌      |
| /api/media/{id}                      | DELETE    | Medium löschen     | ❌      |
| /api/media/{id}                      | PUT/PATCH | Medium ändern      | ❌      |
| /api/modules                         | GET       | Modulliste         | ✅      |
| /api/modules                         | POST      | Modul anlegen      | ✅      |
| /api/module/{id}                     | DELETE    | Modul löschen      | ✅      |
| /api/module/{id}                     | PUT/PATCH | Modul ändern       | ✅      |
| /api/templates                       | GET       | Template Liste     | ✅      |
| /api/templates                       | POST      | Template anlegen   | ✅      |
| /api/templates/{id}                  | DELETE    | Template löschen   | ✅      |
| /api/templates/{id}                  | PUT/PATCH | Template ändern    | ✅      |
| /api/users                           | GET       | Userliste          | ❌      |
| /api/users                           | POST      | User anlegen       | ❌      |
| /api/users/{id}                      | DELETE    | User löschen       | ❌      |
| /api/users/{id}                      | PUT/PATCH | User ändern        | ❌      |
| /api/users/roles                     | GET       | Rollenliste        | ❌      |
| /api/users/roles                     | POST      | Rolle anlegen      | ❌      |
| /api/users/roles/{id}                | DELETE    | Rolle löschen      | ❌      |
| /api/users/roles/{id}                | PUT/PATCH | Rolle ändern       | ❌      |
| /api/clangs                          | GET       | Sprachenliste      | ❌      |
| /api/clangs                          | POST      | Sprache anlegen    | ❌      |
| /api/clangs/{id}                     | DELETE    | Sprache löschen    | ❌      |
| /api/clangs/{id}                     | PUT/PATCH | Sprache ändern     | ❌      |

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

