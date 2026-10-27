<?php

namespace FriendsOfRedaxo\Api\Backend;

use FriendsOfRedaxo\Api\Token;
use Redaxo\Core\Form\Field\Checkbox;
use Redaxo\Core\Form\Field\Choice;
use Redaxo\Core\Form\Field\DateTimePicker;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Translation\Translatable;

/**
 * Form data of a row of `rex_api_token` (backend page "Token").
 *
 * The table predates the core form: `status` is a `tinyint` and `scopes` a comma separated list (the format
 * {@see Token::getScopes()} reads and the REDAXO 5 addon wrote), so the row is mapped explicitly.
 *
 * @internal
 */
final class TokenData
{
    #[Checkbox(new Translatable('api_token_status'))]
    public bool $status = true;

    #[Text(new Translatable('api_token_name'), required: true, maxLength: 191)]
    public string $name = '';

    #[Text(new Translatable('api_token_token'), required: true, maxLength: 191)]
    public string $token = '';

    #[Choice(new Translatable('api_token_expires'), choices: Token::getExpiryChoices(...))]
    public string $expiresPreset = Token::EXPIRY_NEVER;

    #[DateTimePicker(new Translatable('api_token_expires_at'))]
    public ?string $expiresAt = null;

    /** @var list<string> */
    #[Choice(new Translatable('api_token_token_scopes'), choices: self::scopeChoices(...), multiple: true, note: new Translatable('api_token_scopes_notice'))]
    public array $scopes = [];

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $data = new self();
        $data->status = 1 == $row['status'];
        $data->name = (string) $row['name'];
        $data->token = (string) $row['token'];
        // scopes of routes that no longer exist would make the choice invalid, so they are dropped on edit
        $data->scopes = array_values(array_intersect(Token::splitScopes((string) ($row['scopes'] ?? '')), Token::getAvailableScopes()));
        $expiresAt = (string) ($row['expires_at'] ?? '');
        if ('' !== $expiresAt && !str_starts_with($expiresAt, '0000')) {
            // an existing date is kept as is, a preset would move it
            $data->expiresPreset = Token::EXPIRY_CUSTOM;
            $data->expiresAt = $expiresAt;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'status' => $this->status ? 1 : 0,
            'name' => $this->name,
            'token' => $this->token,
            'scopes' => implode(',', $this->scopes),
            'expires_at' => match ($this->expiresPreset) {
                Token::EXPIRY_CUSTOM => $this->expiresAt,
                Token::EXPIRY_NEVER => null,
                default => Token::resolveExpiryDate($this->expiresPreset),
            },
        ];
    }

    /** @return array<string, string> */
    public static function scopeChoices(): array
    {
        $scopes = Token::getAvailableScopes();

        return array_combine($scopes, $scopes);
    }
}
