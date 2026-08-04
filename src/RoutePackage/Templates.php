<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\ListHelper;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use InvalidArgumentException;
use Override;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Translation\I18n;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use const JSON_PRETTY_PRINT;

/**
 * Read-only access to the registered templates.
 *
 * Like modules, a REDAXO 6 template is a PHP class (annotated with `#[AsTemplate]`) discovered from the
 * source tree; there is no `rex_template` table, no numeric id and no backend CRUD. The identifier is the
 * template *key*, which is what `rex_article.template` stores.
 *
 * There are therefore no add/update/delete endpoints — the REDAXO 5 addon's `templates/add`,
 * `templates/update` and `templates/delete` scopes do not exist here.
 *
 * What a template *does* define in code is its content sections (REDAXO 5's "ctypes") and which modules
 * are allowed in them; both are exposed, because that is what a client needs in order to place slices via
 * `structure/articles/{id}/slices`.
 */
class Templates extends RoutePackage
{
    #[Override]
    public function loadRoutes(): void
    {
        // Templates List
        RouteCollection::registerRoute(
            'templates/list',
            new Route(
                'templates',
                [
                    '_controller' => self::class . '::handleTemplateList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'name' => ['type' => 'string', 'required' => false, 'default' => null],
                                'key' => ['type' => 'string', 'required' => false, 'default' => null],
                                'category_id' => ['type' => 'int', 'required' => false, 'default' => null, 'description' => 'Only templates allowed in this category (0 = root level)'],
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
            'Access to the list of templates',
            null,
            new BearerAuth(),
        );

        // Template Get Details
        RouteCollection::registerRoute(
            'templates/get',
            new Route(
                'templates/{key}',
                [
                    '_controller' => self::class . '::handleGetTemplate',
                ],
                ['key' => '[^/]+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get template details by key, including its content sections and the modules allowed in them',
            null,
            new BearerAuth(),
        );
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleTemplateList(array $parameter, array $route = []): Response
    {
        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $categoryId = $query['filter']['category_id'];

        if (null !== $categoryId) {
            $categoryId = (int) $categoryId;

            if ($categoryId > 0 && null === Category::get($categoryId)) {
                return new JsonResponse(['error' => 'Category not found'], 404);
            }

            $all = Template::getTemplatesForCategory($categoryId);
        } else {
            $all = Template::getAll();
        }

        $defaultKey = Template::getDefaultKey();
        $templates = [];

        foreach ($all as $template) {
            if (null !== $query['filter']['key'] && false === stripos($template->key, (string) $query['filter']['key'])) {
                continue;
            }

            if (null !== $query['filter']['name'] && false === stripos(self::nameOf($template), (string) $query['filter']['name'])) {
                continue;
            }

            $templates[] = [
                'key' => $template->key,
                'name' => self::nameOf($template),
                'class' => $template::class,
                'is_default' => $template->key === $defaultKey,
            ];
        }

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, ['key', 'name', 'class'], [['field' => 'name', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $perPage = 1 > $query['per_page'] ? 10 : (int) $query['per_page'];
        $page = 1 > $query['page'] ? 1 : (int) $query['page'];

        $result = ListHelper::paginateArray($templates, $sortDefs, $page, $perPage);

        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetTemplate(array $parameter, array $route = []): Response
    {
        $template = Template::get((string) $parameter['key']);

        if (null === $template) {
            return new JsonResponse(['error' => 'Template not found'], 404);
        }

        $sections = [];
        foreach ($template->getContentSections() as $section) {
            $allowedModules = [];
            foreach (Module::getAll() as $module) {
                if ($template->isModuleAllowed($section, $module)) {
                    $allowedModules[] = $module->key;
                }
            }

            $sections[] = [
                // "ctype_id" is the name the slice endpoints use for this, mirroring rex_article_slice.ctype_id
                'ctype_id' => $section->id,
                'name' => I18n::translate($section->name, false),
                'allowed_modules' => $allowedModules,
            ];
        }

        return new JsonResponse(json_encode([
            'key' => $template->key,
            'name' => self::nameOf($template),
            'class' => $template::class,
            'is_default' => $template->key === Template::getDefaultKey(),
            'content_sections' => $sections,
        ], JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * The template name, resolving a `translate:` key.
     *
     * Escaping is off: this goes into a JSON payload, not into HTML.
     */
    private static function nameOf(Template $template): string
    {
        return I18n::translate($template->name, false);
    }
}
