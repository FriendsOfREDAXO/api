<?php

namespace FriendsOfRedaxo\Api;

use FriendsOfRedaxo\Api\Auth\BearerAuth;
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
    private ?string $expiresAt = null;
    private ?bool $expired = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
        $this->status = (1 == $data['status']) ? true : false;
        $this->scopes = $data['scopes'];
        $this->token = $data['token'];
        $expiresAt = (string) ($data['expires_at'] ?? '');
        if ('' !== $expiresAt && '0000-00-00 00:00:00' !== $expiresAt) {
            $this->expiresAt = $expiresAt;
        }
    }

    /**
     * @return list<string>
     */
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
        return $this->status && !$this->isExpired();
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        // Der Vergleich läuft bewusst über die Datenbank und nicht über time():
        // getByToken() filtert mit now(), und das Ablaufdatum wird im Backend in
        // DB-Zeit eingegeben. Weichen PHP- und MySQL-Zeitzone voneinander ab,
        // würden beide Wege sonst unterschiedlich urteilen.
        if (null === $this->expired) {
            $sql = rex_sql::factory();
            $sql->setQuery('select ? <= now() as expired', [$this->expiresAt]);
            $this->expired = (bool) $sql->getValue('expired');
        }

        return $this->expired;
    }

    public static function get(int $Id): ?self
    {
        $Token = rex_sql::factory()->getArray('select * from ' . rex::getTable('api_token') . ' where id = ? and status = ?', [$Id, 1]);
        if (0 == count($Token)) {
            return null;
        }
        return new self($Token[0]);
    }

    public static function getByToken(string $Token): ?self
    {
        $Token = rex_sql::factory()->getArray(
            'select * from ' . rex::getTable('api_token') . ' where token = ? and status = ? and (expires_at is null or expires_at = ? or expires_at > now())',
            [$Token, 1, '0000-00-00 00:00:00'],
        );
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

    /**
     * @return list<string>
     */
    public static function getAvailableScopes(): array
    {
        $Scopes = [];
        foreach (RouteCollection::getRoutes() as $RouteScope => $Route) {
            if ($Route['authorization'] instanceof BearerAuth && $Route['authorization']->requiresScope()) {
                // Mehrere Routen koennen sich einen Scope teilen (Chunked Upload):
                // auf der Token-Seite darf er nur einmal als Checkbox erscheinen.
                $Scopes[] = $Route['authorization']->getScope((string) $Route['scope']);
            }
        }
        return array_values(array_unique($Scopes));
    }
}
