# APIs für REDAXO

## Beschreibung

Dieses AddOn ermöglich es, APIs in REDAXO zu nutzen. Dabei geht es vor allem um die Nutzung von APIs aus anderen Systemen heraus, um z.B. Daten abzugleichen oder zu ergänzen. Weiterhin ist die API erweiterbar. Jedes andere AddOn kann eigene Endpunkte anlegen. 

Zunächst ist geplant die Basisfeatures von REDAXO abzubilden. 

## Allgemeine Verwendung der APIs

### API-Token erstellen

Bevor die API verwendet werden kann, muss zuerst ein API-Token im REDAXO-Backend erstellt werden:

1. Im Backend zu "API" > "Token" navigieren
2. Auf das "+" Symbol klicken, um einen neuen Token zu erstellen
3. Einen Namen für den Token eingeben
4. Den generierten Token kopieren oder einen eigenen eingeben
5. Die benötigten Scopes (Zugriffsrechte) für den Token auswählen
6. Den Token speichern

### API verwenden

Alle API-Anfragen müssen den API-Token im `Authorization`-Header mit dem Prefix `Bearer` enthalten:

```
Authorization: Bearer TokenHier
```

### Beispiele

#### Beispiel 1: Artikelliste abfragen

```bash
curl -X GET "https://example.com/api/structure/articles" \
     -H "Authorization: Bearer TokenHier"
```

#### Beispiel 2: Einen neuen Artikel anlegen

```bash
curl -X POST "https://example.com/api/structure/articles/" \
     -H "Authorization: Bearer TokenHier" \
     -H "Content-Type: application/json" \
     -d '{
           "name": "Mein neuer Artikel",
           "category_id": 1,
           "status": 1,
           "template_id": 1
         }'
```

### Swagger UI - API-Dokumentation und Test-Tool

Das AddOn bietet eine integrierte Swagger UI-Oberfläche, die eine vollständige Dokumentation aller verfügbaren API-Endpunkte bereitstellt. Damit kann man:

- Alle verfügbaren Endpunkte einsehen
- Die benötigten Parameter für jeden Endpunkt anzeigen
- Die API direkt im Browser testen

Um die Swagger UI zu öffnen:
1. Im REDAXO-Backend zu "API" > "OpenAPI" navigieren
2. Mit dem Token autorisieren (durch Klick auf den "Authorize"-Button)
3. Den gewünschten Endpunkt auswählen und Anfragen direkt aus der Oberfläche ausführen

## Eigene API-Endpunkte entwickeln

Entwickler können eigene API-Endpunkte erstellen und in das vorhandene API-System integrieren. Hier ist ein grundlegendes Beispiel:

### 1. Erstellen Sie ein RoutePackage

```php
<?php
// in einem eigenen AddOn: lib/MeinRoutePackage.php

namespace MeinAddOn\Api;

use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

class MeinRoutePackage extends RoutePackage
{
    public function loadRoutes(): void
    {
        // Registrieren eines neuen Endpunkts
        RouteCollection::registerRoute(
            'mein_addon/beispiel',  // Scope-Name für Berechtigungen
            new Route(
                'mein_addon/beispiel',  // URL-Pfad wird zu: /api/mein_addon/beispiel
                [
                    '_controller' => 'MeinAddOn\Api\MeinRoutePackage::handleBeispiel',
                ],
                [],  // Requirements
                [],  // Options
                '',  // Host
                [],  // Schemes
                ['GET']  // Methods
            ),
            'Beschreibung dieses Endpunkts'  // Beschreibung für OpenAPI
        );
    }

    /** @api */
    public static function handleBeispiel($Parameter): Response
    {
        // Implementierung des Endpunkts
        return new Response(json_encode(['erfolg' => true, 'nachricht' => 'Es funktioniert!']), 200);
    }
}
```

### 2. Registrieren des RoutePackage

```php
<?php
// in Ihrem boot.php des AddOns

use MeinAddOn\Api\MeinRoutePackage;
use FriendsOfRedaxo\Api\RouteCollection;

// Registrieren Sie Ihr RoutePackage
RouteCollection::registerRoutePackage(new MeinRoutePackage());
```

### 3. Parameter validieren und verarbeiten

Bei komplexeren Endpunkten können Sie Query-Parameter oder Body-Daten definieren und validieren:

```php
RouteCollection::registerRoute(
    'mein_addon/beispiel_mit_parametern',
    new Route(
        'mein_addon/beispiel_mit_parametern',
        [
            '_controller' => 'MeinAddOn\Api\MeinRoutePackage::handleBeispielMitParametern',
            'query' => [
                'filter' => [
                    'fields' => [
                        'name' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                    ],
                    'type' => 'array',
                    'required' => false,
                    'default' => [],
                ],
            ],
        ],
        [],
        [],
        '',
        [],
        ['GET']
    ),
    'Beispiel mit Parametern'
);

/** @api */
public static function handleBeispielMitParametern($Parameter): Response
{
    try {
        $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        // Verarbeiten der validierten Parameter...
    } catch (Exception $e) {
        return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
    }
    
    // Weitere Verarbeitung...
}
```

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

## Best Practices für API-Entwicklung

Bei der Entwicklung eigener API-Endpunkte sollten folgende Best Practices beachtet werden:

1. **Wiederverwendung bestehender REDAXO-Funktionen**:
   - Nutzen Sie vorhandene REDAXO-Klassen und Methoden (z.B. `rex_article_service`, `rex_media_service`)
   - Lösen Sie die passenden Extension Points aus, um Kompatibilität mit anderen AddOns zu gewährleisten

2. **Sicherheit**:
   - Validieren Sie alle Eingaben und Parameter gründlich
   - Verwenden Sie `rex_escape()` für die Ausgabe von Variablen in Fehlermeldungen
   - Prüfen Sie Berechtigungen vor Ausführung kritischer Operationen

3. **Struktur und Standards**:
   - Folgen Sie dem bestehenden API-Design-Muster für Konsistenz
   - Liefern Sie immer passende HTTP-Statuscodes zurück (200, 201, 400, 401, 404, etc.)
   - Verwenden Sie die JSON-Struktur konsistent (z.B. `{"error": "Fehlermeldung"}` bei Fehlern)

4. **Dokumentation**:
   - Dokumentieren Sie die API-Endpunkte mit aussagekräftigen Beschreibungen
   - Nutzen Sie den OpenAPI-Standard für Parameterdeklarationen

5. **Performance**:
   - Nutzen Sie QuerySets für Datenbankabfragen
   - Implementieren Sie Paginierung bei Listen-Endpunkten

## Weiterführende Informationen und Ressourcen

### Nützliche Referenzen im Quellcode

Um eigene API-Endpunkte zu entwickeln, lohnt sich ein Blick in die bestehenden Implementierungen:

- [Structure.php](https://github.com/FriendsOfREDAXO/api/blob/main/lib/RoutePackage/Structure.php) - Artikelstruktur API
- [Media.php](https://github.com/FriendsOfREDAXO/api/blob/main/lib/RoutePackage/Media.php) - Medien API
- [Templates.php](https://github.com/FriendsOfREDAXO/api/blob/main/lib/RoutePackage/Templates.php) - Templates API

### Externe Ressourcen

- [REDAXO API-Dokumentation](https://redaxo.org/doku/main) - Offizielle REDAXO-Dokumentation
- [Symfony Routing Komponente](https://symfony.com/doc/current/routing.html) - Dokumentation der verwendeten Routing-Komponente
- [OpenAPI-Spezifikation](https://swagger.io/specification/) - Dokumentation der OpenAPI-Spezifikation
- [RESTful API Design Best Practices](https://restfulapi.net/) - Allgemeine Best Practices für RESTful APIs

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
