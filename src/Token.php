<?php

namespace FriendsOfRedaxo\Api;

use FriendsOfRedaxo\Api\Auth\BearerAuth;
use IntlDateFormatter;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;

use function count;

class Token
{
    public const string TABLE = Core::TABLE_PREFIX . 'api_token';

    public const string EXPIRY_CUSTOM = 'custom';
    public const string EXPIRY_NEVER = 'never';

    private int $id;
    private string $name;
    private bool $status;
    private string $scopes;
    private string $token;
    private ?string $expiresAt;
    private ?bool $expired = null;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
        $this->status = 1 == $data['status'];
        $this->scopes = (string) ($data['scopes'] ?? '');
        $this->token = (string) $data['token'];
        $expiresAt = (string) ($data['expires_at'] ?? '');
        $this->expiresAt = '' === $expiresAt || str_starts_with($expiresAt, '0000') ? null : $expiresAt;
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

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    /**
     * Compared in the database, not with `time()`: {@see self::getByToken()} filters with `now()` and the expiry date is
     * entered in database time, so both ways judge alike even if the PHP and MySQL time zones differ.
     */
    public function isExpired(): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        return $this->expired ??= (bool) Sql::factory()->getArray('SELECT ? <= now() AS expired', [$this->expiresAt])[0]['expired'];
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
        $rows = Sql::factory()->getArray('select * from ' . self::TABLE . ' where token = ? and status = ? and (expires_at is null or expires_at > now())', [$token, 1]);

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
     * Expiry presets of the token page: form value => modifier for `strtotime()`; `custom` (choose a date) and `never`
     * have none. The translation key of a preset is `api_token_expires_preset_` plus its value.
     *
     * @return array<string, string|null>
     */
    public static function getExpiryPresets(): array
    {
        return [
            '3h' => '+3 hours',
            '1d' => '+1 day',
            '7d' => '+7 days',
            '30d' => '+30 days',
            '90d' => '+90 days',
            '1y' => '+1 year',
            self::EXPIRY_CUSTOM => null,
            self::EXPIRY_NEVER => null,
        ];
    }

    /**
     * Choices for the token form, each preset labelled with the point in time it leads to — otherwise one has to work
     * out what "30 days" means today.
     *
     * @return array<string, string>
     */
    public static function getExpiryChoices(): array
    {
        $now = self::getDatabaseTime();
        $choices = [];

        foreach (self::getExpiryPresets() as $preset => $modifier) {
            $label = I18n::msg('api_token_expires_preset_' . $preset);
            if (null !== $modifier) {
                $label .= ' · ' . self::formatExpiryHint((int) strtotime($modifier, $now), $now);
            }
            $choices[$preset] = $label;
        }

        return $choices;
    }

    /** The point in time a preset leads to, as database value; `null` for `never`, `custom` and unknown presets. */
    public static function resolveExpiryDate(string $preset): ?string
    {
        $modifier = self::getExpiryPresets()[$preset] ?? null;

        return null === $modifier ? null : date('Y-m-d H:i:s', (int) strtotime($modifier, self::getDatabaseTime()));
    }

    /** Today only the time, within the year weekday and date, beyond that with the year. */
    private static function formatExpiryHint(int $timestamp, int $now): string
    {
        if (date('Y-m-d', $timestamp) === date('Y-m-d', $now)) {
            return Formatter::intlTime($timestamp);
        }

        if (date('Y', $timestamp) === date('Y', $now)) {
            return Formatter::intlDate($timestamp, 'EEE d. MMM');
        }

        return Formatter::intlDate($timestamp, IntlDateFormatter::MEDIUM);
    }

    /**
     * The wall time of the database: the expiry is checked against MySQL's `now()`, so a point in time computed with
     * the PHP time would be off by the difference of the time zones.
     */
    private static function getDatabaseTime(): int
    {
        $now = Sql::factory()->getArray('SELECT now() AS `now`');

        return (int) strtotime((string) ($now[0]['now'] ?? 'now'));
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
            if ($route['authorization'] instanceof BearerAuth && $route['authorization']->requiresScope) {
                $scopes[] = $route['authorization']->scope ?? $route['scope'];
            }
        }

        return array_values(array_unique($scopes));
    }
}
