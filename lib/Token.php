<?php

namespace FriendsOfRedaxo\Api;

use FriendsOfRedaxo\Api\Auth\BearerAuth;
use IntlDateFormatter;
use rex;
use rex_formatter;
use rex_i18n;
use rex_sql;

use function count;

class Token
{
    /** Wert des Formularfelds, bei dem das Datum von Hand gewählt wird. */
    public const ExpiryPresetCustom = 'custom';

    /** Wert des Formularfelds für „läuft nicht ab". */
    public const ExpiryPresetNever = 'never';

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
     * Vorgaben für das Ablaufdatum auf der Token-Seite.
     *
     * Schlüssel ist der Wert im Formular, Wert der Modifier für `strtotime()`.
     * `custom` (Datum selbst wählen) und `never` (läuft nicht ab) haben keinen
     * Modifier. Der Sprachschlüssel eines Presets ist `api_token_expires_preset_`
     * plus Schlüssel.
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
            self::ExpiryPresetCustom => null,
            self::ExpiryPresetNever => null,
        ];
    }

    /**
     * Auswahlliste für das Formular: Label => Wert, wie YForm es für `choice` erwartet.
     *
     * Jedes Preset trägt den Zeitpunkt, auf den es hinausläuft, im Label — sonst
     * muss der Benutzer selbst rechnen, was „30 Tage" heute bedeutet.
     *
     * @return array<string, string>
     */
    public static function getExpiryChoices(): array
    {
        $Now = self::getDatabaseTime();
        $Choices = [];

        foreach (self::getExpiryPresets() as $Preset => $Modifier) {
            $Label = rex_i18n::msg('api_token_expires_preset_' . $Preset);
            if (null !== $Modifier) {
                $Label .= ' · ' . self::formatExpiryHint((int) strtotime($Modifier, $Now), $Now);
            }
            $Choices[$Label] = $Preset;
        }

        return $Choices;
    }

    /**
     * Der Zeitpunkt, auf den ein Preset hinausläuft, als DB-Wert.
     *
     * Für `never`, `custom` und unbekannte Werte gibt es keinen Zeitpunkt.
     */
    public static function resolveExpiryDate(string $Preset): ?string
    {
        $Modifier = self::getExpiryPresets()[$Preset] ?? null;
        if (null === $Modifier) {
            return null;
        }

        return date('Y-m-d H:i:s', (int) strtotime($Modifier, self::getDatabaseTime()));
    }

    /**
     * Kurzer Hinweis auf den Zeitpunkt: heute nur die Uhrzeit, im laufenden Jahr
     * Wochentag und Datum, darüber hinaus mit Jahr.
     */
    private static function formatExpiryHint(int $Timestamp, int $Now): string
    {
        if (date('Y-m-d', $Timestamp) === date('Y-m-d', $Now)) {
            return rex_formatter::intlTime($Timestamp);
        }

        if (date('Y', $Timestamp) === date('Y', $Now)) {
            return rex_formatter::intlDate($Timestamp, 'EEE d. MMM');
        }

        return rex_formatter::intlDate($Timestamp, IntlDateFormatter::MEDIUM);
    }

    /**
     * Wandzeit der Datenbank als Timestamp.
     *
     * Der Ablauf wird gegen MySQLs `now()` geprüft (siehe isExpired()). Weichen
     * PHP- und DB-Zeitzone voneinander ab, wäre ein in PHP berechneter Zeitpunkt
     * um genau diesen Versatz verschoben — deshalb ist die DB-Zeit die Basis für
     * Anzeige und Berechnung.
     */
    private static function getDatabaseTime(): int
    {
        $Now = rex_sql::factory()->getArray('select now() as `now`');

        return (int) strtotime((string) ($Now[0]['now'] ?? 'now'));
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
