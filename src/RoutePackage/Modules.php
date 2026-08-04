<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\ListHelper;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use InvalidArgumentException;
use Override;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Security\User;
use Redaxo\Core\Translation\I18n;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use const JSON_PRETTY_PRINT;

/**
 * Read-only access to the registered modules.
 *
 * In REDAXO 6 a module is a PHP class annotated with `#[AsModule]` and discovered from the project's (or
 * an addon's) source tree — there is no `rex_module` table, no numeric id and no backend CRUD anymore.
 * The identifier is the module *key*, which is what `rex_article_slice.module` stores.
 *
 * Consequently there are no add/update/delete endpoints: creating a module means writing a PHP class, so
 * it is a deployment concern, not an API operation. The REDAXO 5 addon's `modules/add`, `modules/update`
 * and `modules/delete` scopes therefore do not exist here.
 */
class Modules extends RoutePackage
{
    #[Override]
    public function loadRoutes(): void
    {
        // Modules List
        RouteCollection::registerRoute(
            'modules/list',
            new Route(
                'modules',
                [
                    '_controller' => self::class . '::handleModulesList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'name' => ['type' => 'string', 'required' => false, 'default' => null],
                                'key' => ['type' => 'string', 'required' => false, 'default' => null],
                            ],
                            'type' => 'array',
                            'required' => false,
                            'default' => [],
                        ],
                        'page' => ['type' => 'int', 'required' => false, 'default' => 1],
                        'per_page' => ['type' => 'int', 'required' => false, 'default' => 100],
                        'sort' => ['type' => 'string', 'required' => false, 'default' => null],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['GET'],
            ),
            'Access to the list of modules',
            null,
            new BearerAuth(),
        );

        // Module Get Details
        RouteCollection::registerRoute(
            'modules/get',
            new Route(
                'modules/{key}',
                [
                    '_controller' => self::class . '::handleGetModule',
                ],
                ['key' => '[^/]+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get module details by key',
            null,
            new BearerAuth(),
        );
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleModulesList(array $parameter, array $route = []): Response
    {
        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $user = RouteCollection::getBackendUser($route);
        $modules = [];

        foreach (Module::getAll() as $module) {
            if (!self::isPermitted($user, $module->key)) {
                continue;
            }

            if (null !== $query['filter']['key'] && false === stripos($module->key, (string) $query['filter']['key'])) {
                continue;
            }

            if (null !== $query['filter']['name'] && false === stripos(self::nameOf($module), (string) $query['filter']['name'])) {
                continue;
            }

            $modules[] = self::moduleToArray($module);
        }

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, ['key', 'name', 'class'], [['field' => 'name', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $perPage = 1 > $query['per_page'] ? 10 : (int) $query['per_page'];
        $page = 1 > $query['page'] ? 1 : (int) $query['page'];

        $result = ListHelper::paginateArray($modules, $sortDefs, $page, $perPage);

        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetModule(array $parameter, array $route = []): Response
    {
        $module = Module::get((string) $parameter['key']);

        if (null === $module) {
            return new JsonResponse(['error' => 'Module not found'], 404);
        }

        if (!self::isPermitted(RouteCollection::getBackendUser($route), $module->key)) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        return new JsonResponse(json_encode(self::moduleToArray($module), JSON_PRETTY_PRINT), 200, [], true);
    }

    private static function isPermitted(?User $user, string $moduleKey): bool
    {
        if (null === $user || $user->admin) {
            return true;
        }

        $perm = $user->getComplexPerm('modules');

        return $perm instanceof ModulePermission && $perm->hasPerm($moduleKey);
    }

    /**
     * The module name, resolving a `translate:` key like the backend module select does.
     *
     * Escaping is off: this goes into a JSON payload, not into HTML.
     */
    private static function nameOf(Module $module): string
    {
        return I18n::translate($module->name, false);
    }

    /** @return array<string, string> */
    private static function moduleToArray(Module $module): array
    {
        return [
            'key' => $module->key,
            'name' => self::nameOf($module),
            'class' => $module::class,
        ];
    }
}
