<?php

namespace FriendsOfRedaxo\Api;

use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\Form\TokenForm;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;

use function count;

class Token
{
    private int $id;
    private string $name;
    private bool $status;
    private string $scopes;
    private string $token;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
        $this->status = 1 == $data['status'];
        $this->scopes = (string) ($data['scopes'] ?? '');
        $this->token = (string) $data['token'];
    }

    /**
     * Scopes granted to this token.
     *
     * Comma separated is the stored format; pipes are accepted too so a value written by the core form's
     * multi-select notation still works.
     *
     * @return list<string>
     */
    public function getScopes(): array
    {
        return TokenForm::splitPipes($this->scopes);
    }

    public function getId(): int
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

    public static function get(int $id): ?self
    {
        $token = Sql::factory()->getArray('select * from ' . Core::getTable('api_token') . ' where id = ? and status = ?', [$id, 1]);

        if (0 === count($token)) {
            return null;
        }

        return new self($token[0]);
    }

    public static function getByToken(string $token): ?self
    {
        $rows = Sql::factory()->getArray('select * from ' . Core::getTable('api_token') . ' where token = ? and status = ?', [$token, 1]);

        if (0 === count($rows)) {
            return null;
        }

        return new self($rows[0]);
    }

    public static function getFromBearerToken(): ?self
    {
        $bearerToken = str_ireplace('Bearer ', '', Core::getRequest()->headers->get('Authorization') ?? '');

        if ('' === $bearerToken) {
            return null;
        }

        return self::getByToken($bearerToken);
    }

    /**
     * All route scopes that can be granted to a token, i.e. every bearer-authenticated route.
     *
     * @return list<string>
     */
    public static function getAvailableScopes(): array
    {
        $scopes = [];

        foreach (RouteCollection::getRoutes() as $route) {
            if ($route['authorization'] instanceof BearerAuth) {
                $scopes[] = $route['scope'];
            }
        }

        return $scopes;
    }
}
