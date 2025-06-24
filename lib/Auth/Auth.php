<?php

namespace FriendsOfRedaxo\Api;

use rex;

abstract class Auth
{
    public function __construct()
    {
        // Constructor logic if needed
    }

    abstract public function isAuthorized(array $parameters): bool;

    public function getAuthorizationObject(): mixed
    {
        return null;
    }

    public function getAuthType(): string
    {
        return 'none';
    }

    public function getBearerToken()
    {
        $Request = rex::getRequest();

        $BearerToken = str_ireplace('Bearer ', '', $Request->headers->get('Authorization') ?? '');
        if ('' == $BearerToken) {
            return null;
        }
        return $BearerToken;
    }
}
