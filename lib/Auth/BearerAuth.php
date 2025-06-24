<?php

namespace FriendsOfRedaxo\Api\Auth;

use FriendsOfRedaxo\Api\Auth;
use FriendsOfRedaxo\Api\Token;

use function in_array;

class BearerAuth extends Auth
{
    private ?Token $Token = null;

    public function isAuthorized($parameters): bool
    {
        $this->Token = Token::getFromBearerToken();
        if (null === $this->Token) {
            return false;
        }
        if (in_array($parameters['_route'], $this->Token->getScopes(), true)) {
            return true;
        }
        return false;
    }

    public function getAuthorizationObject(): ?Token
    {
        return $this->Token;
    }

    public static function getOpenApiConfig()
    {
        return [
            'securityScheme' => 'bearerAuth',
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ];
    }
}
