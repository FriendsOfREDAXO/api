<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use Override;

class Clangs extends AbstractMirror
{
    #[Override]
    protected function scopePrefix(): ?string
    {
        return 'system/clangs/';
    }
}
