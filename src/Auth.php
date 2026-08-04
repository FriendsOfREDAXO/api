<?php

namespace FriendsOfRedaxo\Api;

use Redaxo\Core\Core;

abstract class Auth
{
    /** @param array<string, mixed> $parameters */
    abstract public function isAuthorized(array $parameters): bool;

    public function getAuthorizationObject(): mixed
    {
        return null;
    }

    public function getAuthType(): string
    {
        return 'none';
    }

    /**
     * The OpenAPI security scheme of this auth type, or `null` when it cannot be expressed.
     *
     * @return array<string, string>|null
     */
    public static function getOpenApiConfig(): ?array
    {
        return null;
    }

    public function getBearerToken(): ?string
    {
        $bearerToken = str_ireplace('Bearer ', '', Core::getRequest()->headers->get('Authorization') ?? '');

        if ('' === $bearerToken) {
            return null;
        }

        return $bearerToken;
    }
}
