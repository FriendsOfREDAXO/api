<?php

use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\Token;

$_csrf_key = 'api_token';

$table = rex::getTablePrefix() . 'api_token';
$bezeichner = rex_i18n::msg('api_token');

$func = rex_request('func', 'string', '');
$page = rex_request('page', 'string', '');
$data_id = rex_request('data_id', 'int');
$content = '';
$show_list = true;

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
    $form_data[] = 'validate|empty|token|translate:api_token_token_validate';
    $form_data[] = 'choice|scopes|translate:api_token_token_scopes|' . implode(',', Token::getAvailableScopes()) . '||1';

    $yform = rex_yform::factory();
    $yform->setObjectparams('form_action', 'index.php?page=api/token');
    $yform->setObjectparams('form_name', 'api-token-form');

    $yform->setFormData(implode("\n", $form_data));
    $yform->setObjectparams('form_showformafterupdate', 1);

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
    $list = rex_list::factory('select * from ' . $table, defaultSort: [
        'name' => 'asc',
    ]);
    $list->addTableAttribute('summary', rex_i18n::msg('api_token_header_summary'));
    $list->addTableAttribute('class', 'table-striped');

    $tdIcon = '<i class="rex-icon rex-icon-template"></i>';
    $thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['func' => 'add']) . '"' . rex::getAccesskey(rex_i18n::msg('create_token'), 'add') . ' title="' . rex_i18n::msg('create_template') . '"><i class="rex-icon rex-icon-add-template"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);

    $list->setColumnLabel('id', 'Id');
    $list->setColumnLayout('id', ['<th class="rex-small">###VALUE###</th>', '<td class="rex-small">###VALUE###</td>']);

    $list->removeColumn('token');

    $list->setColumnFormat('status', 'custom', static function ($params) {
        return (1 == $params['subject']) ? rex_i18n::msg('active') : rex_i18n::msg('inactive');
    });

    $list->setColumnLabel('name', rex_i18n::msg('api_token_name'));
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
