<?php

namespace FriendsOfRedaxo\Api\Form;

use Override;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Form\Form;

/**
 * Token form.
 *
 * The core form stores checkbox and multi-select values in the `|a|b|` pipe notation. The api token
 * table predates that: `status` is a `tinyint` and `scopes` a comma separated list (which is also the
 * format {@see \FriendsOfRedaxo\Api\Token::getScopes()} reads and what the REDAXO 5 addon wrote), so
 * both values are normalised on the way into the database.
 */
class TokenForm extends Form
{
    /**
     * @param string $fieldsetName
     * @param string $fieldName
     * @param string|int|null $fieldValue
     * @return string|int|null
     */
    #[Override]
    protected function preSave($fieldsetName, $fieldName, $fieldValue, Sql $saveSql)
    {
        $fieldValue = parent::preSave($fieldsetName, $fieldName, $fieldValue, $saveSql);

        if ('status' === $fieldName) {
            return '' === trim((string) $fieldValue, '|') ? 0 : 1;
        }

        if ('scopes' === $fieldName) {
            return implode(',', self::splitPipes((string) $fieldValue));
        }

        return $fieldValue;
    }

    /** @return list<string> */
    public static function splitPipes(string $value): array
    {
        $parts = preg_split('/[|,]+/', trim($value, '|, ')) ?: [];

        return array_values(array_filter($parts, static fn (string $part) => '' !== $part));
    }
}
