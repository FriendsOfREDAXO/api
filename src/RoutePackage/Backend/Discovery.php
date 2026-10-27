<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use Override;

/** `/api/backend/me`: the self-description for the backend session. */
class Discovery extends AbstractMirror
{
    #[Override]
    protected function scopes(): ?array
    {
        return ['me'];
    }
}
