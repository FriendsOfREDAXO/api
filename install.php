<?php

rex_sql_table::get(rex::getTable('api_token'))
->ensurePrimaryIdColumn()
->ensureColumn(new rex_sql_column('name', 'varchar(191)'))
->ensureColumn(new rex_sql_column('token', 'varchar(191)'))
->ensureColumn(new rex_sql_column('status', 'tinyint(1)', false, '1'))
->ensureColumn(new rex_sql_column('scopes', 'text'))
->ensure();
