<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use Override;

class Templates extends AbstractMirror
{
    #[Override]
    protected function scopePrefix(): ?string
    {
        return 'templates/';
    }
}
