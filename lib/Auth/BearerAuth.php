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

    /** Gesetzt, wenn mehrere Routen sich einen Scope teilen; sonst gilt der Routenname. */
    private ?string $scope = null;

    /**
     * @param bool $requireScope false: every valid token is authorized, no scope needed (discovery routes)
     * @param string|null $scope shared scope for several routes; null means the route name
     */
    public function __construct(bool $requireScope = true, ?string $scope = null)
    {
        parent::__construct();
        $this->requireScope = $requireScope;
        $this->scope = $scope;
    }

    public function requiresScope(): bool
    {
        return $this->requireScope;
    }

    public function getScope(string $routeName): string
    {
        return $this->scope ?? $routeName;
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
        if (in_array($this->getScope($parameters['_route']), $this->Token->getScopes(), true)) {
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
