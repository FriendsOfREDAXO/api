<?php

use FriendsOfRedaxo\Api\Form\TokenForm;
use FriendsOfRedaxo\Api\Token;
use FriendsOfRedaxo\Api\View\TokenList;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Field\SelectField;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Validator\ValidationRule;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

/** @var Redaxo\Core\Addon\Addon $this */

$csrfKey = 'api_token';
$table = Core::getTable('api_token');

$func = Request::request('func', 'string');
$dataId = Request::request('data_id', 'int');

$message = '';
$content = '';

if ('delete' === $func) {
    if (!CsrfToken::factory($csrfKey)->isValid()) {
        $message = Message::error(I18n::msg('csrf_token_invalid'));
    } else {
        Sql::factory()->setQuery('DELETE FROM ' . $table . ' WHERE id = :id LIMIT 1', ['id' => $dataId]);
        $message = Message::success($this->i18n('token_deleted'));
    }

    $func = '';
}

if ('add' === $func || 'edit' === $func) {
    $isEditMode = 'edit' === $func;
    $title = $isEditMode ? $this->i18n('token_update') : $this->i18n('token_create');

    $form = TokenForm::factory($table, '', 'id = ' . $dataId);
    $form->addParam('data_id', $dataId);
    $form->setApplyUrl(Url::currentBackendPage());
    $form->setEditMode($isEditMode);
    $form->addErrorMessage(TokenForm::ERROR_VIOLATE_UNIQUE_KEY, $this->i18n('token_token_exists'));

    $field = $form->addCheckboxField('status');
    $field->addOption($this->i18n('token_status'), 1);
    $field->setDefaultSaveValue(0);

    $field = $form->addTextField('name');
    $field->setLabel($this->i18n('token_name'));
    $field->getValidator()
        ->add(ValidationRule::NOT_EMPTY, $this->i18n('token_name_validate'))
        ->add(ValidationRule::MAX_LENGTH, null, 191);

    $field = $form->addTextField('token');
    $field->setLabel($this->i18n('token_token'));
    $field->setNotice($this->i18n('token_token_notice', bin2hex(random_bytes(16))));
    $field->getValidator()
        ->add(ValidationRule::NOT_EMPTY, $this->i18n('token_token_validate'))
        ->add(ValidationRule::MAX_LENGTH, null, 191);

    $scopes = Token::getAvailableScopes();

    /** @var SelectField $field */
    $field = $form->addSelectField('scopes');
    $field->setLabel($this->i18n('token_token_scopes'));
    $field->setNotice($this->i18n('token_scopes_notice'));
    $field->setSeparator(',');
    $select = $field->getSelect();
    $select->setMultiple(true);
    $select->setSize(min(25, max(3, count($scopes))));
    $select->addArrayOptions($scopes, false);

    $content = $form->get();

    $fragment = new Fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $title);
    $fragment->setVar('body', $content, false);
    $content = $fragment->parse('core/page/section.php');
} else {
    $list = TokenList::factory('SELECT id, status, name, scopes FROM ' . $table, 100, defaultSort: ['name' => 'asc']);
    $list->addTableAttribute('summary', $this->i18n('token_header_summary'));
    $list->addTableAttribute('class', 'table-striped table-hover');

    $tdIcon = '<i class="rex-icon rex-icon-user-secret"></i>';
    $thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['func' => 'add']) . '"' . Core::getAccesskey($this->i18n('token_create'), 'add') . ' title="' . $this->i18n('token_create') . '"><i class="rex-icon rex-icon-add"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'data_id' => '###id###']);

    $list->setColumnLabel('id', I18n::msg('id'));
    $list->setColumnLayout('id', ['<th class="rex-table-id">###VALUE###</th>', '<td class="rex-table-id">###VALUE###</td>']);

    $list->setColumnLabel('status', $this->i18n('token_status'));
    $list->setColumnFormat('status', 'custom', static fn (array $params) => 1 == $params['subject'] ? I18n::msg('api_token_active') : I18n::msg('api_token_inactive'));

    $list->setColumnLabel('name', $this->i18n('token_name'));
    $list->setColumnParams('name', ['func' => 'edit', 'data_id' => '###id###']);

    $list->setColumnLabel('scopes', $this->i18n('token_token_scopes'));
    $list->setColumnFormat('scopes', 'custom', static function (array $params) {
        $scopes = TokenForm::splitPipes((string) $params['subject']);

        return '' === implode('', $scopes) ? '&ndash;' : implode('<br />', array_map('Redaxo\Core\View\escape', $scopes));
    });

    $list->addColumn('funcs', '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete'));
    $list->setColumnLabel('funcs', I18n::msg('function'));
    $list->setColumnLayout('funcs', ['<th class="rex-table-action">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('funcs', ['func' => 'delete', 'data_id' => '###id###'] + CsrfToken::factory($csrfKey)->getUrlParams());
    $list->addLinkAttribute('funcs', 'data-confirm', $this->i18n('token_delete_confirm'));

    $list->setNoRowsMessage($this->i18n('token_not_found'));

    $content = $list->get();

    $fragment = new Fragment();
    $fragment->setVar('title', $this->i18n('token_caption'));
    $fragment->setVar('content', $content, false);
    $content = $fragment->parse('core/page/section.php');
}

echo $message;
echo $content;
