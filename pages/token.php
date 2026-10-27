<?php

/** @var ApiAddon $this */

use FriendsOfRedaxo\Api\ApiAddon;
use FriendsOfRedaxo\Api\Backend\TokenData;
use FriendsOfRedaxo\Api\Token;
use Redaxo\Core\Backend\Accesskey;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Component\SectionVariant;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$func = Request::request('func', 'string');
$dataId = Request::request('data_id', 'int', 0);
$csrf = CsrfToken::factory('api_token');
$table = Token::TABLE;

if ('delete' === $func) {
    if (!$csrf->isValid()) {
        echo Message::error(I18n::msg('csrf_token_invalid'));
    } else {
        Sql::factory()->setQuery('DELETE FROM ' . $table . ' WHERE id = ? LIMIT 1', [$dataId]);
        echo Message::success($this->i18n('token_deleted'));
    }
    $func = '';
}

if ('add' === $func || 'edit' === $func) {
    $row = 'edit' === $func ? (Sql::factory()->getArray('SELECT * FROM ' . $table . ' WHERE id = ?', [$dataId])[0] ?? null) : null;

    if (null !== $row) {
        $data = TokenData::fromRow($row);
    } else {
        $data = new TokenData();
        // a fresh random token, so nobody has to come up with one
        $data->token = bin2hex(random_bytes(16));
    }

    $form = new Form(
        $data,
        name: 'api_token',
        title: null !== $row ? $this->i18n('token_update') : $this->i18n('token_create'),
        cancelUrl: Url::currentBackendPage(),
    );

    if ($form->handleRequest()) {
        $exists = Sql::factory()->getArray('SELECT id FROM ' . $table . ' WHERE token = ? AND id <> ?', [$data->token, null !== $row ? $dataId : 0]);
        if ([] !== $exists) {
            $form->addError($this->i18n('token_token_exists'), 'token');
        }

        if ($form->isValid()) {
            $sql = Sql::factory();
            $sql->setTable($table);
            $sql->setValues($data->toRow());
            if (null !== $row) {
                $sql->setWhere(['id' => $dataId]);
                $sql->update();
            } else {
                $sql->insert();
            }
            Response::sendRedirect(Url::currentBackendPage(['info' => null !== $row ? 'updated' : 'added']));
        }
    }

    echo $form->capture(static function (Form $form): void {
        $body = Html::capture(static function () use ($form): void {
            foreach ($form->getFormErrors() as $error) {
                echo Message::error((string) Html::from($error));
            }
            echo $form->rest();
        });
        echo new Section(
            $form->title ?? '',
            $body,
            footer: Html::raw('<div class="rex-form-panel-footer">' . $form->buttons() . '</div>'),
            variant: SectionVariant::Edit,
        )->render();
    });

    return;
}

$info = Request::get('info', 'string', '');
if ('added' === $info) {
    echo Message::success($this->i18n('token_added'));
} elseif ('updated' === $info) {
    echo Message::success($this->i18n('token_updated'));
}

$list = new DataList('SELECT id, status, name, scopes FROM ' . $table . ' ORDER BY name', rowsPerPage: 100);
$list->addParam('page', 'api/token');
$list->empty = $this->i18n('token_not_found');
$editUrl = static fn (array $row): string => $list->getUrl(['func' => 'edit', 'data_id' => $row['id']]);

$list->column(
    'icon',
    Html::raw('<a href="' . escape($list->getUrl(['func' => 'add'])) . '"' . Accesskey::attributes($this->i18n('token_create'), 'add') . '><i class="rex-icon rex-icon-add"></i></a>'),
    static fn (): Html => Html::raw('<i class="rex-icon rex-icon-user-secret"></i>'),
    type: ColumnType::Icon,
    link: $editUrl,
);
$list->column('id', I18n::msg('id'), sortable: true, link: $editUrl);
$list->column('name', $this->i18n('token_name'), sortable: true, link: $editUrl);
$list->column(
    'status',
    $this->i18n('token_status'),
    static fn (array $row): Html => 1 == $row['status']
        ? Html::raw('<span class="rex-online">' . escape(I18n::msg('api_token_active')) . '</span>')
        : Html::raw('<span class="rex-offline">' . escape(I18n::msg('api_token_inactive')) . '</span>'),
);
$list->column('scopes', $this->i18n('token_token_scopes'), static function (array $row): Html {
    $scopes = Token::splitScopes((string) $row['scopes']);

    return Html::raw([] === $scopes ? '&ndash;' : implode('<br>', array_map(escape(...), $scopes)));
});
$list->column(
    'delete',
    I18n::msg('function'),
    static fn (): Html => Html::raw('<i class="rex-icon rex-icon-delete"></i> ' . escape(I18n::msg('delete'))),
    type: ColumnType::Action,
    link: static fn (array $row): string => $list->getUrl(['data_id' => $row['id'], 'func' => 'delete'] + $csrf->getUrlParams()),
    linkAttributes: ['data-confirm' => $this->i18n('token_delete_confirm')],
);

echo new Section($this->i18n('token_caption'), content: $list)->render();
