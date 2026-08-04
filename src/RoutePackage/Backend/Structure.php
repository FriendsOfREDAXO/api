<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use Override;

class Structure extends AbstractMirror
{
    #[Override]
    protected function scopePrefix(): ?string
    {
        return 'structure/';
    }
}
