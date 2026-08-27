Changelog
=========

Version 1.3.1 – 27.08.2026
---------------------------

### Neu

* `handleMediaList()` (Route `media`, gespiegelt als `backend/media`) unterstützt jetzt `filter[permitted_only]=1`: schaltet die Ergebnisliste (und einen expliziten `filter[category_id]`) auf die Medienkategorie-Rechte des anfragenden Backend-Users um (`getComplexPerm('media')`, inkl. Kategorie `0`, "kein Ordner"), statt wie bisher jedem User mit Basis-Medienrecht Leserecht auf alle Kategorien zu geben. Ein `filter[category_id]` auf eine nicht erlaubte Kategorie liefert dann `403`, wie es `checkMediaPerm()` bei den übrigen Routen dieser Datei bereits durchsetzt.
* **Bewusst ein Opt-in, kein neues Default-Verhalten:** Der klassische Medienpool (`mediapool/pages/media.list.php`) reicht die gewählte Kategorie ungeprüft an `rex_media_service::getList()` durch — jeder Backend-User mit Basis-Medienrecht hat dort traditionell Leserecht auf alle Kategorien, nur Schreibaktionen (verschieben/löschen) prüfen die Kategorie-Rechte. Diese Route spiegelt das per Default weiterhin exakt, um bestehende Aufrufer nicht zu brechen; die strengere Filterung ist für Aufrufer gedacht, die das bewusst enger handhaben wollen als der Core (z. B. das MediaPlace-Addon). Bearer-Token-Aufrufe sind unverändert: dort gelten Scopes statt User-Rechten.

Version 1.3 – 25.08.2026
------------------------

### Neu

* Selbstauskunft `/api/me`: listet die Endpunkte, die das aufrufende Token bzw. der Backend-User nutzen darf, auf Wunsch als gefilterte OpenAPI-Spec (`?format=openapi`). Der Endpunkt braucht keinen eigenen Scope — sonst würde er genau bei den Tokens fehlen, bei denen ein Scope vergessen wurde.
* Chunked Upload für große Mediendateien: `media/upload` mit init, chunk, status, finalize und abort. Die zusammengesetzte Datei geht an `rex_media_service::addMedia()`, damit Endungs-Blockliste, MIME-Allowlist und die Extension Points `MEDIA_ADD_FILE`/`MEDIA_ADDED` unverändert greifen. Alle fünf Routen teilen sich einen Scope — einzeln vergeben wären sie unbrauchbar. (#39)
* Medienliste: Freitextsuche über Dateiname und Titel (`filter[term]`, inklusive `type:jpg,png` und Gruppierung mit Anführungszeichen), Filter auf Dateiendungen (`filter[types]`) und rekursive Kategoriesuche über den Kategoriepfad (`filter[category_id_path]`) — dieselbe Semantik wie `rex_media_service::getList()` (#64)
* Tokens können ablaufen: Das Feld **Läuft ab** bietet Zeitspannen von 3 Stunden bis 1 Jahr an und nennt zu jeder den Zeitpunkt, auf den sie hinausläuft; **Benutzerdefiniert** schaltet ein Datumsfeld frei, **Nie** lässt das Token unbegrenzt gültig. Der Zeitpunkt wird aus der Datenbankzeit berechnet, weil der Ablauf gegen `now()` geprüft wird. Ein abgelaufenes Token wird wie ein unbekanntes behandelt: `401`. (#48)
* OpenAPI: Der geforderte Scope steht als `x-required-scope` und in der Beschreibung jeder Operation; die Endpunkte sind nach Ressource gruppiert statt nach Registrierungsreihenfolge sortiert.
* Slice-Endpunkte kennen `value20` (#52)

### Geändert

* Slice-Revisionen: Die Arbeitsversion des Plugins `structure/version` ist beim Anlegen über das Body-Feld `revision` erreichbar; Priorität und Extension-Point-Parameter zählen pro Revision. Im Backend-Scope wird `version[live_version]` geprüft, bevor in die Live-Version geschrieben wird — bei Bearer-Tokens ersetzen weiterhin die Scopes die User-Permissions. (#42)
* `filter[revision]` bei den Artikel-Endpunkten entfernt: `rex_article.revision` wird vom Core nie befüllt und in keiner Artikel-Query ausgewertet, der Filter hat nichts bewirkt außer Erwartungen zu wecken. Revisionen leben ausschließlich in `rex_article_slice`. Das Feld bleibt in der Response enthalten, damit sich das Antwortschema nicht ändert.
* Unique-Index auf `rex_api_token.token`: Ein doppelter Wert hätte den zweiten Eintrag samt Scopes unwirksam gemacht. Enthält eine bestehende Installation Duplikate, wird der Index übersprungen und eine Warnung ins System-Log geschrieben, statt das Update abzubrechen. (#26)
* Toten Kategorie-Code aus dem Media-RoutePackage entfernt — der auskommentierte Entwurf hätte über `rex_category_service` Artikelkategorien statt Medienkategorien gelöscht.
* Update auf symfony/routing 7.4.17

### Bugfixes

* Überschrittene PHP-Uploadgrenzen werden als `413` mit den geltenden Limits gemeldet statt als generisches `400`. Ein Body über `post_max_size` erreicht PHP gar nicht mehr vollständig — dieser Fall wird eigens erkannt.
* Metainfo-Werte: Arrays und Objekte auf einfachen Feldern werden mit `400` zurückgewiesen statt still verworfen (#66)
* Metainfo-Felder: Schlägt das `ALTER TABLE` fehl — etwa an der maximalen Zeilengröße —, wird das mit `500` gemeldet und die bereits geschriebene Feldzeile zurückgenommen. Verwaiste Zeilen aus früheren Fehlschlägen lassen sich löschen, statt dauerhaft `404` zu liefern. (#67)
* Sprachen: Der Versuch, die Startsprache zu löschen, antwortet mit `409` statt `500` — `rex_functional_exception` steht für einen fachlichen Verstoß, nicht für einen Serverfehler.
* Status-Übersetzung in der Token-Liste korrigiert

### Doku

* README: Chunked Upload, Uploadgrenzen, Medienfilter, Ablaufdatum und die Endpunkt-Tabellen ergänzt
* CLAUDE.md: Konventionen für den OpenAPI-Generator, Slice-Revisionen, Chunked Upload und die Token-Seite festgehalten

Die Notizen zu Version 1.2 und älter stehen bei den [GitHub-Releases](https://github.com/FriendsOfREDAXO/api/releases).
