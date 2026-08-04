<?php

namespace FriendsOfRedaxo\Api\Auth;

use FriendsOfRedaxo\Api\Auth;
use Override;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\Login;
use Redaxo\Core\Security\User;

class BackendUser extends Auth
{
    private ?User $user = null;

    #[Override]
    public function isAuthorized(array $parameters): bool
    {
        Login::startSession();

        $login = new BackendLogin();

        if ($login->checkLogin()) {
            $user = $login->getUser();
            $this->user = $user instanceof User ? $user : null;
        }

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
