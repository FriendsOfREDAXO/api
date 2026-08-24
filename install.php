<?php

$table = rex_sql_table::get(rex::getTable('api_token'));
$tableExisted = $table->exists();

$table
->ensurePrimaryIdColumn()
->ensureColumn(new rex_sql_column('name', 'varchar(191)'))
->ensureColumn(new rex_sql_column('token', 'varchar(191)'))
->ensureColumn(new rex_sql_column('status', 'tinyint(1)', false, '1'))
->ensureColumn(new rex_sql_column('scopes', 'text'))
->ensureColumn(new rex_sql_column('expires_at', 'datetime', true));

// Der Token-Wert muss eindeutig sein: Token::getByToken() nimmt den ersten Treffer,
// bei einem doppelten Wert wäre der zweite Eintrag samt seiner Scopes unwirksam.
// Bei einer bestehenden Installation kann die Spalte aber schon Duplikate enthalten;
// dann würde das Anlegen des Index das Update abbrechen. In dem Fall bleibt der Index
// aus und der Vorgang wird protokolliert, damit die Duplikate von Hand aufgelöst
// werden können — beim nächsten Update greift er dann.
$duplicates = [];
if ($tableExisted) {
    $duplicates = rex_sql::factory()->getArray(
        'SELECT token, COUNT(*) AS amount FROM ' . rex::getTable('api_token') . ' GROUP BY token HAVING amount > 1',
    );
}

if ([] === $duplicates) {
    $table->ensureIndex(new rex_sql_index('token', ['token'], rex_sql_index::UNIQUE));
} else {
    rex_logger::factory()->warning(
        'api: Unique-Index auf api_token.token nicht angelegt, es gibt {amount} doppelte Token-Werte. '
        . 'Doppelte Tokens auf der Seite API > Token auflösen, dann das AddOn erneut aktualisieren.',
        ['amount' => count($duplicates)],
    );
}

$table->ensure();

// Altbestände aus früheren Versionen normalisieren: die Spalte wird nullable
// angelegt, ein bestehendes NOT-NULL-Feld kann aber 0000-00-00 00:00:00 enthalten.
rex_sql::factory()->setQuery('UPDATE ' . rex::getTable('api_token') . ' SET expires_at = NULL WHERE YEAR(expires_at) < 1');
