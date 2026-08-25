<?php

use FriendsOfRedaxo\Api\Token;

$_csrf_key = 'api_token';

$table = rex::getTablePrefix() . 'api_token';
$bezeichner = rex_i18n::msg('api_token');

$func = rex_request('func', 'string', '');
$page = rex_request('page', 'string', '');
$data_id = rex_request('data_id', 'int');
$content = '';
$show_list = true;

/**
 * Legt fest, welches Ablaufdatum gespeichert wird.
 *
 * Läuft zwischen executeFields() und executeActions(): dort steht der Wert, den
 * die DB-Action schreibt. Ein Preset rechnet den Zeitpunkt aus, „nie" und ein
 * leer gelassenes Datum werden NULL. Das nachträglich zu korrigieren wäre
 * unzuverlässig -- beim Anlegen mit „übernehmen" speichert YForm zweimal.
 */
$applyExpiresAt = static function (rex_yform $form): void {
    if (1 != $form->objparams['send']) {
        // Nur beim Absenden -- beim bloßen Anzeigen gibt es nichts zu speichern und
        // ein unvollständiges Datum aus dem Altbestand darf keine Meldung auslösen.
        return;
    }

    $preset = Token::ExpiryPresetNever;
    $chosenDate = '';

    foreach ($form->objparams['values'] as $fieldValue) {
        if (!is_object($fieldValue) || !method_exists($fieldValue, 'getName') || !method_exists($fieldValue, 'getValue')) {
            continue;
        }

        $fieldName = (string) $fieldValue->getName();
        if ('expires_preset' === $fieldName) {
            $preset = (string) $fieldValue->getValue();
        } elseif ('expires_at' === $fieldName) {
            $chosenDate = (string) $fieldValue->getValue();
        }
    }

    if (Token::ExpiryPresetCustom === $preset) {
        // Jahr, Monat und Tag lassen sich einzeln leer lassen („00"). Daraus wird kein
        // Zeitpunkt, gegen den sich vergleichen lässt -- und still auf „läuft nie ab"
        // umzuschalten wäre bei einem Ablaufdatum die falsche Auslegung. Wer keinen
        // Ablauf möchte, wählt „nie".
        $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $chosenDate);

        if (false === $parsedDate || $parsedDate->format('Y-m-d H:i:s') !== $chosenDate) {
            $form->objparams['warning_messages'][] = rex_i18n::msg('api_token_expires_at_invalid');
            return;
        }

        $form->objparams['value_pool']['sql']['expires_at'] = $chosenDate;
        return;
    }

    // Für „nie" -- und für alles, was zu keinem Preset gehört -- gibt es keinen Zeitpunkt.
    $form->objparams['value_pool']['sql']['expires_at'] = Token::resolveExpiryDate($preset);
};

/** Vorauswahl im Formular: ein gespeichertes Datum bleibt ein selbst gewähltes Datum. */
$expiresPresetDefault = static function (int $tokenId) use ($table): string {
    if ($tokenId < 1) {
        return Token::ExpiryPresetNever;
    }

    $stored = rex_sql::factory()->getArray('select expires_at from ' . $table . ' where id = :id', ['id' => $tokenId]);
    $storedValue = (string) ($stored[0]['expires_at'] ?? '');

    if ('' !== $storedValue && 0 < (int) substr($storedValue, 0, 4)) {
        return Token::ExpiryPresetCustom;
    }

    return Token::ExpiryPresetNever;
};

/**
 * Feld-Definition für die Ablauf-Auswahl.
 *
 * Die Auswahl kommt als JSON in die Definition, weil die Labels den konkreten
 * Zeitpunkt enthalten und damit Kommas -- kommagetrennte Listen wären daran
 * zerbrochen. Senkrechte Striche werden aus den Labels entfernt: sie trennen die
 * Elemente der Definition.
 */
$expiresPresetField = static function (string $default): string {
    $choices = [];
    foreach (Token::getExpiryChoices() as $label => $value) {
        $choices[str_replace('|', ' ', $label)] = $value;
    }

    return implode('|', [
        'choice',
        'expires_preset',
        'translate:api_token_expires',
        (string) json_encode($choices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        '0', // expanded: ein Select, keine Radiobuttons
        '0', // multiple
        $default,
        '', // group_by
        '', // preferred_choices
        '', // placeholder
        '', // group_attributes
        '{"class":"api-token-expires"}', // attributes
        '{"never":{"class":"text-danger"}}', // choice_attributes
        '', // notice
        'no_db',
    ]);
};

if ('delete' == $func && !rex_csrf_token::factory($_csrf_key)->isValid()) {
    echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
} elseif ('delete' == $func) {
    $delsql = rex_sql::factory();
    $delsql->setQuery('delete from ' . $table . ' where id = :id', ['id' => $data_id]);
    $content = rex_view::success(rex_i18n::msg('api_token_deleted'));
} elseif ('edit' == $func || 'add' == $func) {
    $form_data = [];
    $form_data[] = 'checkbox|status|translate:api_token_status';
    $form_data[] = 'text|name|translate:api_token_name';
    $form_data[] = 'validate|empty|name|translate:api_token_name_validate';
    $form_data[] = 'text|token|translate:api_token_token|#notice:' . rex_i18n::msg('api_token_token_notice', bin2hex(random_bytes((32 - (32 % 2)) / 2)));
    $form_data[] = $expiresPresetField('edit' == $func ? $expiresPresetDefault($data_id) : Token::ExpiryPresetNever);
    $form_data[] = 'datetime|expires_at|translate:api_token_expires_at|' . date('Y') . '|+10|Y-m-d H:i:s|1||select|||+1 year';
    $form_data[] = 'validate|empty|token|translate:api_token_token_validate';
    // Die Spalte trägt einen Unique-Index (siehe install.php). Ohne diesen Validator
    // scheitert der INSERT still: YForm meldet nichts und springt zurück zur Liste,
    // als wäre gespeichert worden.
    $form_data[] = 'validate|unique|token|' . rex_i18n::msg('api_token_token_unique') . '|' . $table;
    $form_data[] = 'choice|scopes|translate:api_token_token_scopes|' . implode(',', Token::getAvailableScopes()) . '||1';

    $yform = rex_yform::factory();
    $yform->setObjectparams('form_action', 'index.php?page=api/token');
    $yform->setObjectparams('form_name', 'api-token-form');

    $yform->setFormData(implode("\n", $form_data));
    $yform->setObjectparams('form_showformafterupdate', 1);

    /** @var rex_yform $yform_clone */
    $yform_clone = clone $yform;

    if ('edit' == $func) {
        $title = rex_i18n::msg('api_token_update');
        $yform->setValueField('submit', ['name' => 'submit', 'labels' => rex_i18n::msg('yform_save') . ',' . rex_i18n::msg('yform_save_apply'), 'values' => '1,2', 'no_db' => true, 'css_classes' => 'btn-save,btn-apply']);
        $yform->setHiddenField('data_id', $data_id);
        $yform->setHiddenField('func', $func);
        $yform->setActionField('db', [$table, "id=$data_id"]);
        $yform->setActionField('showtext', [rex_view::success(rex_i18n::msg('api_token_updated')), '', '', 1]);
        $yform->setObjectparams('main_id', $data_id);
        $yform->setObjectparams('main_where', "id=$data_id");
        $yform->setObjectparams('main_table', $table);
        $yform->setObjectparams('getdata', true);
    } else {
        $yform->setHiddenField('func', $func);
        $title = rex_i18n::msg('api_token_create');
        $yform->setValueField('submit', ['name' => 'submit', 'labels' => rex_i18n::msg('yform_add') . ',' . rex_i18n::msg('yform_add_apply'), 'values' => '1,2', 'no_db' => true, 'css_classes' => 'btn-save,btn-apply']);
        $yform->setActionField('db', [$table]);
        $yform->setActionField('showtext', [rex_view::success(rex_i18n::msg('api_token_info_added')), '', '', 1]);
    }

    $yform->executeFields();
    $applyExpiresAt($yform);

    $submit_type = 1; // normal, 2=apply
    foreach ($yform->objparams['values'] as $f) {
        if ('submit' == $f->getName()) {
            if (2 == $f->getValue()) { // apply
                $submit_type = 2;
            }
        }
    }

    $content = $yform->executeActions();

    if ($yform->objparams['actions_executed']) {
        switch ($func) {
            case 'edit':
                if (2 == $submit_type) {
                    $fragment = new rex_fragment();
                    $fragment->setVar('class', 'edit', false);
                    $fragment->setVar('title', $title);
                    $fragment->setVar('body', $content, false);
                    $content = $fragment->parse('core/page/section.php');

                    $show_list = false;
                } else {
                    $content = rex_view::success(rex_i18n::msg('api_token_updated'));
                }
                break;
            case 'add':
            default:
                if (2 == $submit_type) {
                    $title = rex_i18n::msg('yform_email_update');
                    $data_id = $yform->objparams['main_id'];
                    $func = 'edit';

                    /** @var rex_yform $yform */
                    $yform = $yform_clone;
                    $yform->setHiddenField('func', $func);
                    $yform->setHiddenField('data_id', $data_id);
                    $yform->setActionField('db', [$table, "id=$data_id"]);
                    $yform->setObjectparams('main_id', $data_id);
                    $yform->setObjectparams('main_where', "id=$data_id");
                    $yform->setObjectparams('main_table', $table);
                    $yform->setObjectparams('getdata', true);
                    $yform->setValueField('submit', ['name' => 'submit', 'labels' => rex_i18n::msg('yform_save') . ',' . rex_i18n::msg('yform_save_apply'), 'values' => '1,2', 'no_db' => true, 'css_classes' => 'btn-save,btn-apply']);
                    $yform->executeFields();
                    // Der Klon speichert erneut -- also auch hier das Ablaufdatum setzen.
                    $applyExpiresAt($yform);

                    $content = $yform->executeActions();
                    $fragment = new rex_fragment();
                    $fragment->setVar('class', 'edit', false);
                    $fragment->setVar('title', $title);
                    $fragment->setVar('body', $content, false);
                    $content = rex_view::success(rex_i18n::msg('api_token_added')) . $fragment->parse('core/page/section.php');

                    $show_list = false;
                } else {
                    $content = rex_view::success(rex_i18n::msg('api_token_added'));
                }
                break;
        }
    } else {
        $fragment = new rex_fragment();
        $fragment->setVar('class', 'edit', false);
        $fragment->setVar('title', $title);
        $fragment->setVar('body', $content, false);
        $content = $fragment->parse('core/page/section.php');

        $show_list = false;
    }
}

echo $content;

if ($show_list) {
    $link = '';
    // Der Ablauf wird in der Datenbank ausgewertet, genau wie in Token::isExpired():
    // weichen PHP- und DB-Zeitzone ab, urteilen beide Wege sonst unterschiedlich.
    $list = rex_list::factory(
        'select *, (expires_at is not null and year(expires_at) > 0 and expires_at <= now()) as expired from ' . $table,
        defaultSort: ['name' => 'asc'],
    );
    $list->addTableAttribute('summary', rex_i18n::msg('api_token_header_summary'));
    $list->addTableAttribute('class', 'table-striped');

    $tdIcon = '<i class="rex-icon rex-icon-template"></i>';
    $thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['func' => 'add']) . '"' . rex::getAccesskey(rex_i18n::msg('create_token'), 'add') . ' title="' . rex_i18n::msg('create_template') . '"><i class="rex-icon rex-icon-add-template"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);

    $list->setColumnLabel('id', 'Id');
    $list->setColumnLayout('id', ['<th class="rex-small">###VALUE###</th>', '<td class="rex-small">###VALUE###</td>']);

    $list->removeColumn('token');
    $list->removeColumn('expired');

    $list->setColumnFormat('status', 'custom', static function ($params) {
        return (1 == $params['subject']) ? rex_i18n::msg('api_active') : rex_i18n::msg('api_inactive');
    });

    $list->setColumnFormat('expires_at', 'custom', static function ($params) {
        $expiresAt = (string) $params['subject'];
        if ('' === $expiresAt || 0 === (int) substr($expiresAt, 0, 4)) {
            return '<span class="text-muted">' . rex_i18n::msg('api_token_expires_never') . '</span>';
        }

        $formatted = rex_escape(rex_formatter::intlDateTime($expiresAt));

        /** @var rex_list $list */
        $list = $params['list'];
        if ('1' === (string) $list->getValue('expired')) {
            return '<span class="text-danger">' . $formatted . ' · ' . rex_i18n::msg('api_token_expired') . '</span>';
        }

        return $formatted;
    });

    $list->setColumnLabel('name', rex_i18n::msg('api_token_name'));
    $list->setColumnLabel('expires_at', rex_i18n::msg('api_token_expires_at'));
    $list->setColumnParams('name', ['page' => $page, 'func' => 'edit', 'data_id' => '###id###']);

    $list->setColumnFormat('scopes', 'custom', static function ($params) {
        return str_replace(',', '<br />', $params['subject']);
    });

    $list->addColumn(rex_i18n::msg('function'), rex_i18n::msg('yform_delete'));
    $list->setColumnParams(rex_i18n::msg('function'), ['page' => $page, 'func' => 'delete', 'data_id' => '###id###'] + rex_csrf_token::factory($_csrf_key)->getUrlParams());
    $list->addLinkAttribute(rex_i18n::msg('function'), 'onclick', 'return confirm(\' id=###id### ' . rex_i18n::msg('yform_delete') . ' ?\')');

    $list->setNoRowsMessage(rex_i18n::msg('api_token_not_found'));

    $content = $list->get();

    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('api_token_caption'));
    $fragment->setVar('content', $content, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
}
