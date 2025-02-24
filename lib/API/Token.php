<?php

namespace FriendsOfREDAXO\API;

use rex;
use rex_sql;
use function count;

class Token
{
    private bool $status = false;
    private string $scopes = '';
    private ?int $id = null;
    private string $name = '';
    private string $token = '';

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
        $this->status = (1 == $data['status']) ? true : false;
        $this->scopes = $data['scopes'];
        $this->token = $data['token'];
    }

    public function getScopes(): array
    {
        return ('' === $this->scopes) ? [] : explode(',', $this->scopes);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function isActive(): bool
    {
        return $this->status;
    }

    public static function get(int $Id)
    {
        $Token = rex_sql::factory()->getArray('select * from ' . rex::getTable('api_token') . ' where id = ? and status = ?,', [$Id, 1]);
        if (0 == count($Token)) {
            return null;
        }
        return new self($Token[0]);
    }

    public static function getByToken(string $Token)
    {
        $Token = rex_sql::factory()->getArray('select * from ' . rex::getTable('api_token') . ' where token = ? and status = ?', [$Token, 1]);
        if (0 == count($Token)) {
            return null;
        }
        return new self($Token[0]);
    }

    public static function getFromBearerToken(): ?self
    {
        $Request = rex::getRequest();

        $BearerToken = str_ireplace('Bearer ', '', $Request->headers->get('Authorization') ?? '');
        if ('' == $BearerToken) {
            return null;
        }

        return self::getByToken($BearerToken);
    }
}
