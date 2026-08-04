<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use Override;

class Media extends AbstractMirror
{
    #[Override]
    protected function scopePrefix(): ?string
    {
        return 'media/';
    }
}
