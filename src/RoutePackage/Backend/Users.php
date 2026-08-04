<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use Override;

class Users extends AbstractMirror
{
    #[Override]
    protected function scopePrefix(): ?string
    {
        return 'users/';
    }
}
