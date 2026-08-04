# REDAXO 6 API

REST API for REDAXO 6. Bearer-token secured endpoints for structure, media, users, languages and metainfo,
plus a mirror of every endpoint under `/api/backend/…` that authenticates with the REDAXO backend session.

The OpenAPI specification of everything that is registered is available in the backend under
**API → OpenAPI** and can be tried out right there.

This is the REDAXO 6 version of the addon (Composer package `friendsofredaxo/api`, namespace
`FriendsOfRedaxo\Api`, branch `2.x`). The REDAXO 5 version lives on `main` and is not upgraded in
place — see [Differences to the REDAXO 5 addon](#differences-to-the-redaxo-5-addon).

## Requirements

- REDAXO 6
- PHP 8.5 or newer

## Installation

```bash
composer require friendsofredaxo/api
php bin/console addon:install api
```

## Tokens and scopes

Tokens are managed in the backend under **API → Token** (admin only). A token grants access per *scope*,
and a scope is one route — `structure/articles/list`, `media/add`, `users/roles/delete` and so on. A token
without the matching scope gets a `401`, so tokens can be cut down to exactly what an integration needs.

```bash
curl -H 'Authorization: Bearer <token>' https://example.org/api/structure/articles
```

`/api/backend/<same path>` needs no token: it authenticates with the backend session cookie and then
applies the logged-in user's permissions (structure permissions, media categories, module permissions,
admin flag) the same way the corresponding backend page does.

## Endpoints

| Area | Endpoints |
|---|---|
| Structure | `structure/articles`, `structure/articles/{id}`, `structure/categories`, `structure/categories/{id}` |
| Slices | `structure/articles/{id}/slices`, `structure/articles/{id}/slices/{slice_id}` |
| Media | `media`, `media/{filename}/info`, `media/{filename}/file`, `media/{filename}/update`, `media/{filename}/delete` |
| Media categories | `media/category`, `media/category/{id}` |
| Languages | `system/clangs`, `system/clangs/{id}` |
| Users | `users`, `users/{id}`, `users/{id}/role/{role_id}` |
| User roles | `users/roles`, `users/roles/{id}`, `users/roles/{id}/duplicate` |
| Modules | `modules`, `modules/{key}` *(read-only)* |
| Templates | `templates`, `templates/{key}` *(read-only)* |
| Metainfo | `metainfo/fields`, `metainfo/fields/{name}` *(read-only)*, plus `…/metainfo` value endpoints on articles, categories, media and languages |

Lists share one envelope and support `page`, `per_page`, `sort` (`field:asc,other:desc`) and a `filter`
object:

```json
{
  "data": [],
  "meta": { "page": 1, "per_page": 100, "total": 0, "total_pages": 0 }
}
```

## Differences to the REDAXO 5 addon

REDAXO 6 changed parts of the content model, and the API follows those changes rather than emulating the
old shape.

**Templates and modules are PHP classes, not database rows.** They are declared with `#[AsTemplate]` /
`#[AsModule]` and identified by a string *key*. Accordingly:

- `template_id` became `template` (a template key) on articles and categories.
- A slice's `module_id` became `module` (a module key).
- The `templates/add|update|delete` and `modules/add|update|delete` endpoints are gone. Creating a template
  or module means writing a class, which is a deployment concern — those routes return `405`.
- `templates/{key}` reports the template's content sections (REDAXO 5's "ctypes") and the modules allowed
  in each of them, which is what you need before posting a slice.

**Metainfo fields are declared in code** via a `MetaSchema` marked with `#[AsMetaSchema]`; `console migrate`
syncs the columns. There is no field table and no management UI, so:

- `metainfo/fields` and `metainfo/fields/{name}` are read-only, and a field is addressed by its column name
  instead of a numeric id.
- Fields carry a `type` (the field class) instead of a `type_id`.
- `metainfo/types/list` and the field add/update/delete endpoints are gone.
- The **value** endpoints are unchanged. Values are converted by the field class itself, so custom field
  types defined by a project or addon store and read their values exactly as the backend editor does.

**`media/{filename}/update` additionally accepts POST.** PHP consumes a `multipart/form-data` body without
populating `$_FILES` for PUT and PATCH, so replacing the file itself only works reliably over POST.
PUT/PATCH remain available for JSON metadata updates.

## Extending

Register your own routes from an addon by subclassing `RoutePackage` and registering it in `boot()`:

```php
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Route;

class MyRoutes extends RoutePackage
{
    public function loadRoutes(): void
    {
        RouteCollection::registerRoute(
            'myaddon/things/list',
            new Route('myaddon/things', ['_controller' => self::class . '::handleList'], [], [], '', [], ['GET']),
            'List things',
            null,
            new BearerAuth(),
        );
    }

    public static function handleList(array $parameter, array $route = []): JsonResponse
    {
        return new JsonResponse(['data' => [], 'meta' => []]);
    }
}
```

```php
RouteCollection::registerRoutePackage(new MyRoutes());
```

The scope shows up in the token form automatically, and the handler receives the resolved route so it can
call `RouteCollection::getBackendUser($route)` to apply per-user permissions when reached through the
backend mirror.

Note that API requests are handled in the **frontend** context: `Core::isBackend()` is `false` and, under
bearer auth, `Core::getUser()` is `null`. Extension points that call `Core::requireUser()` will fail there.

## Tests

The suite drives the API over HTTP against a running installation.

```bash
composer install
cp tests/.env.example tests/.env   # fill in base URL, a token with all scopes, admin credentials
vendor/bin/phpunit
```

Tests that need something the installation does not have — a restricted token, backend credentials, a meta
schema — skip with a message instead of failing.

## License

MIT
