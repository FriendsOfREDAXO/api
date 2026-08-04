<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use Override;

/**
 * Mirrors only the metainfo *value* endpoints (article/category/media/clang).
 *
 * The field definitions stay bearer-only on purpose: they are read-only anyway (a REDAXO 6 meta field is
 * declared in code), and reading the schema is an integration concern rather than something a backend
 * editor session needs. Editors should be able to maintain values on content, which is what these scopes
 * cover; the handlers check the matching permission per entity (structure permission for
 * articles/categories, media permission for media, admin for languages — mirroring core's own pages).
 */
class Metainfo extends AbstractMirror
{
    #[Override]
    protected function scopes(): ?array
    {
        return [
            'metainfo/articles/values/get',
            'metainfo/articles/values/update',
            'metainfo/categories/values/get',
            'metainfo/categories/values/update',
            'metainfo/media/values/get',
            'metainfo/media/values/update',
            'metainfo/clangs/values/get',
            'metainfo/clangs/values/update',
        ];
    }
}
