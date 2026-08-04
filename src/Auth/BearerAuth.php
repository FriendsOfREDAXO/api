<?php

namespace FriendsOfRedaxo\Api\Auth;

use FriendsOfRedaxo\Api\Auth;
use FriendsOfRedaxo\Api\Token;
use Override;

use function in_array;

class BearerAuth extends Auth
{
    private ?Token $token = null;

    #[Override]
    public function isAuthorized(array $parameters): bool
    {
        $this->token = Token::getFromBearerToken();

        if (null === $this->token) {
            return false;
        }

        return in_array($parameters['_route'], $this->token->getScopes(), true);
    }

    #[Override]
    public function getAuthorizationObject(): ?Token
    {
        return $this->token;
    }

    #[Override]
    public function getAuthType(): string
    {
        return 'bearer';
    }

    /** @return array<string, string> */
    #[Override]
    public static function getOpenApiConfig(): array
    {
        return [
            'securityScheme' => 'bearerAuth',
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ];
    }
}
