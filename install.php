<?php

rex_sql_table::get(rex::getTable('api_token'))
->ensurePrimaryIdColumn()
->ensureColumn(new rex_sql_column('name', 'varchar(191)'))
->ensureColumn(new rex_sql_column('token', 'varchar(191)'))
->ensureColumn(new rex_sql_column('status', 'tinyint(1)', false, '1'))
->ensureColumn(new rex_sql_column('scopes', 'text'))
->ensureColumn(new rex_sql_column('expires_at', 'datetime', true))
->ensure();

rex_sql::factory()->setQuery(
    'UPDATE ' . rex::getTable('api_token') . ' SET expires_at = NULL WHERE expires_at = ? OR expires_at = ?',
    ['0000-00-00 00:00:00', ''],
);
