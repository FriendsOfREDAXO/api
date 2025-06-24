<?php

namespace FriendsOfRedaxo\Api\Auth;

use FriendsOfRedaxo\Api\Auth;
use rex_backend_login;
use rex_login;
use rex_user;

class BackendUser extends Auth
{
    private ?rex_user $User = null;

    public function isAuthorized($parameters): bool
    {
        rex_login::startSession();
        $login = new rex_backend_login();
        if ($login->checkLogin()) {
            $this->User = $login->getUser();
        }
        if (null === $this->User) {
            return false;
        }
        return true;
    }

    public function getAuthorizationObject(): mixed
    {
        return $this->User;
    }

    public static function getOpenApiConfig()
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
