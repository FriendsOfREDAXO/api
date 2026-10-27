<?php

namespace FriendsOfRedaxo\Api;

use FriendsOfRedaxo\Api\Auth\BearerAuth;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;

use function count;

class Token
{
    public const string TABLE = Core::TABLE_PREFIX . 'api_token';

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
     * @return list<string>
     */
    public function getScopes(): array
    {
        return self::splitScopes($this->scopes);
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
        $token = Sql::factory()->getArray('select * from ' . self::TABLE . ' where id = ? and status = ?', [$id, 1]);

        if (0 === count($token)) {
            return null;
        }

        return new self($token[0]);
    }

    public static function getByToken(string $token): ?self
    {
        $rows = Sql::factory()->getArray('select * from ' . self::TABLE . ' where token = ? and status = ?', [$token, 1]);

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
     * Splits a stored scope list. Comma separated is the stored format; pipes are accepted too, as written by the
     * multi-select notation of the REDAXO 5 core form.
     *
     * @return list<string>
     */
    public static function splitScopes(string $value): array
    {
        $parts = preg_split('/[|,]+/', trim($value, '|, ')) ?: [];

        return array_values(array_filter($parts, static fn (string $part) => '' !== $part));
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
