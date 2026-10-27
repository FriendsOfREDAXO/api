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

A token can expire: the token page offers periods from 3 hours to 1 year (each labelled with the point in time it
leads to), a custom date, or never. An expired token is treated like an unknown one: `401`.

`GET /api/me` needs no scope: it lists the endpoints the calling token (or, under `/api/backend/me`, the backend
user) may use, with their parameters; `?format=openapi` returns them as an OpenAPI document. In the OpenAPI
specification every operation names the scope it needs (`x-required-scope`).

`/api/backend/<same path>` needs no token: it authenticates with the backend session cookie and then
applies the logged-in user's permissions (structure permissions, media categories, module permissions,
admin flag) the same way the corresponding backend page does.

## Endpoints

| Area | Endpoints |
|---|---|
| Structure | `structure/articles`, `structure/articles/{id}`, `structure/articles/{id}/move`, `structure/articles/{id}/copy`, `structure/categories`, `structure/categories/{id}`, `structure/categories/{id}/move` |
| Slices | `structure/articles/{id}/slices`, `structure/articles/{id}/slices/{slice_id}`, `structure/articles/{id}/slices/{slice_id}/move` |
| Media | `media`, `media/{filename}/info`, `media/{filename}/file`, `media/{filename}/update`, `media/{filename}/delete`, chunked upload `media/upload` |
| Media categories | `media/category`, `media/category/{id}` |
| Languages | `system/clangs`, `system/clangs/{id}` |
| Users | `users`, `users/{id}`, `users/{id}/role/{role_id}` |
| User roles | `users/roles`, `users/roles/{id}`, `users/roles/{id}/duplicate` |
| Modules | `modules`, `modules/{key}` *(read-only, the details describe the fields of the module)* |
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

### Media

`media` filters like the search of the media pool: `filter[term]` searches file name and title (words are
combined, `"quotes"` group, `type:jpg,png` restricts the extensions), `filter[types]` takes extensions,
`filter[category_id_path]` a category including its subcategories.

Uploads over the PHP limits answer `413` with the limits in effect (`upload_max_filesize`, `post_max_size`).
Larger files go through the **chunked upload**: `POST media/upload` with `filename` and `size` returns an
`upload_id` and the maximum chunk size, the chunks are sent to `media/upload/{upload_id}/chunk/{index}` (raw body
or multipart field `chunk`, zero-based, resending replaces), `GET media/upload/{upload_id}` reports what arrived,
`POST …/finalize` adds the file through the media pool (same extension and MIME checks and extension points as a
normal upload) and `DELETE media/upload/{upload_id}` aborts. All steps share the scope `media/upload`; an
upload belongs to the token that started it, unfinished ones are removed after 24 hours.

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
  (`meta_…`) instead of a numeric id; `filter[entity]` is `article`, `category`, `media` or `language`.
- Fields carry a `type` (the field class) instead of a `type_id`.
- `metainfo/types/list` and the field add/update/delete endpoints are gone.
- Values are keyed by the field key (the column without the `meta_` prefix) and validated by the field type like
  in the backend; fields a schema does not allow for the item (`isFieldAllowed()`) are neither shown nor writable.

**Languages, names and status.** Names, status and template of articles and categories, and the titles of media,
are per language: the endpoints take a `language_id` (default: the start language) and return it. Priorities and
the position in the tree apply to all languages. The field names follow the core (`language_id`,
`content_section_id`, `category_id`/`parent_id` with `0` or `null` for the root level).

**Slices carry their fields as `data`.** A module stores its fields as JSON, keyed by field key (the property name
in snake_case). `modules/{key}` lists these fields with type, label, whether they are required, choices and what a
picker stores (media file name, article id, list). Slices are read and written in exactly that shape:

```bash
curl -X POST -H 'Authorization: Bearer <token>' -H 'Content-Type: application/json' \
  -d '{"module": "text", "content_section_id": 1, "data": {"headline": "Hello", "image": "photo.jpg"}}' \
  https://example.org/api/structure/articles/42/slices
```

Written values run through the module form like a backend save: unknown fields, invalid choices, missing media or
articles and the module's own `validate()` are answered with `422` and the offending fields; `beforeSave()` and
`afterSave()` run, and the references of the slice are updated. An update only changes the fields it contains.
`status` sets a slice online/offline, `.../move` takes `direction` (`up`/`down`) or a target `priority`.

**Moving and copying.** `structure/articles/{id}/move` and `structure/categories/{id}/move` move an article or a
category with its content, `structure/articles/{id}/copy` copies an article into a category; for backend users the
permissions `moveArticle[]`, `moveCategory[]`, `copyArticle[]` and `moveSlice[]` apply like in the backend.

**Users and roles.** A user can have several roles (`rex_user_role_assignment`); `roles` lists their ids, and `role`
on create/update takes them comma separated.

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
