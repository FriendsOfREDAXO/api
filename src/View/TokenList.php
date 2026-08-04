<?php

namespace FriendsOfRedaxo\Api\View;

use Override;
use Redaxo\Core\Http\Request;
use Redaxo\Core\View\DataList;

/**
 * Token list.
 *
 * Works around a bug in `Redaxo\Core\View\DataList::getSortColumn()`: it reads the `sort` request
 * parameter with `null` as default and passes the result straight into `hasColumnOption(string ...)`, so a
 * request that carries `list=<name>` but no `sort` crashes with a TypeError. That is exactly the shape of
 * the row-action links a DataList generates (`?…&func=delete&id=…&list=<name>`), which makes every delete
 * link fail — core's own user-roles page included.
 *
 * Overriding the method keeps the addon working on an unpatched core; it can go away once core is fixed.
 */
class TokenList extends DataList
{
    #[Override]
    public function getSortColumn(?string $default = null): ?string
    {
        if (Request::request('list', 'string') === $this->getName()) {
            $sortColumn = Request::request('sort', 'string', $default);

            if (null !== $sortColumn && '' !== $sortColumn && $this->hasColumnOption($sortColumn, REX_LIST_OPT_SORT)) {
                return $sortColumn;
            }
        }

        return $default;
    }
}
