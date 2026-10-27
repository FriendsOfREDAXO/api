<?php

namespace FriendsOfRedaxo\Api;

use const ENT_HTML5;
use const ENT_QUOTES;

abstract class RoutePackage
{
    public function loadRoutes(): void {}

    /** Core messages (e.g. of a `UserMessageException`) are meant for the backend and may contain markup. */
    protected static function plain(string $message): string
    {
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['<br />', '<br>'], ' ', $message)), ENT_QUOTES | ENT_HTML5)));
    }
}
