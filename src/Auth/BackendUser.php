<?php

namespace FriendsOfRedaxo\Api\Auth;

use FriendsOfRedaxo\Api\Auth;
use Override;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\User;

class BackendUser extends Auth
{
    private ?User $user = null;

    #[Override]
    public function isAuthorized(array $parameters): bool
    {
        $this->user = BackendLogin::createUser();

        return null !== $this->user;
    }

    #[Override]
    public function getAuthorizationObject(): ?User
    {
        return $this->user;
    }

    #[Override]
    public function getAuthType(): string
    {
        return 'cookie';
    }

    /** @return array<string, string> */
    #[Override]
    public static function getOpenApiConfig(): array
    {
        return [
            'securityScheme' => 'cookieAuth',
            'name' => 'PHPSESSID',
            'type' => 'apiKey',
            'in' => 'cookie',
            'description' => 'PHP Session Cookie',
        ];
    }
}
