<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use Override;

class Modules extends AbstractMirror
{
    #[Override]
    protected function scopePrefix(): ?string
    {
        return 'modules/';
    }
}
