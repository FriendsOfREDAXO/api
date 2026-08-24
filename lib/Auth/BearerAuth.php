<?php

namespace FriendsOfRedaxo\Api\Auth;

use FriendsOfRedaxo\Api\Auth;
use FriendsOfRedaxo\Api\Token;

use function in_array;

class BearerAuth extends Auth
{
    private ?Token $Token = null;

    /**
     * Declared with a class default instead of a promoted constructor property:
     * a subclass in another addon may override __construct() without calling
     * parent::__construct(), and requiresScope() must still work.
     */
    private bool $requireScope = true;

    /**
     * @param bool $requireScope false: every valid token is authorized, no scope needed (discovery routes)
     */
    public function __construct(bool $requireScope = true)
    {
        parent::__construct();
        $this->requireScope = $requireScope;
    }

    public function requiresScope(): bool
    {
        return $this->requireScope;
    }

    public function isAuthorized($parameters): bool
    {
        $this->Token = Token::getFromBearerToken();
        if (null === $this->Token) {
            return false;
        }
        if (!$this->requireScope) {
            return true;
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
