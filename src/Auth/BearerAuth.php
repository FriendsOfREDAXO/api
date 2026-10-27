<?php

namespace FriendsOfRedaxo\Api\Auth;

use FriendsOfRedaxo\Api\Auth;
use FriendsOfRedaxo\Api\Token;
use Override;

use function in_array;

class BearerAuth extends Auth
{
    private ?Token $token = null;

    /**
     * @param string|null $scope the scope a token needs; `null` for the scope of the route itself. Routes that are only
     *     usable together (like the steps of a chunked upload) share one scope.
     */
    public function __construct(
        public readonly ?string $scope = null,
        /** `false` for routes every valid token may call, like the self-description `/me`. */
        public readonly bool $requiresScope = true,
    ) {}

    #[Override]
    public function isAuthorized(array $parameters): bool
    {
        $this->token = Token::getFromBearerToken();

        if (null === $this->token) {
            return false;
        }

        return !$this->requiresScope || in_array($this->scope ?? $parameters['_route'], $this->token->getScopes(), true);
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
