<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use FriendsOfRedaxo\Api\Auth\BackendUser;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use Override;

use function in_array;

/**
 * Mirrors already registered bearer-token routes into the `/api/backend/…` path, authenticated via the
 * REDAXO backend session instead of a token.
 *
 * The mirrored routes keep the very same controllers, so a backend user gets exactly the same behaviour —
 * with one difference the handlers implement themselves: for a session-authenticated request
 * {@see RouteCollection::getBackendUser()} returns a user, and the handlers then apply that user's
 * permissions. Under a bearer token there is no user and the token's scopes are the only authorisation.
 *
 * Mirrors must be registered *after* the packages they mirror, because they read the routes registered so
 * far.
 */
abstract class AbstractMirror extends RoutePackage
{
    /**
     * Scope prefix to mirror, e.g. `media/`. Every registered route whose scope starts with it is mirrored.
     *
     * Return `null` when {@see self::scopes()} selects the routes explicitly instead.
     */
    protected function scopePrefix(): ?string
    {
        return null;
    }

    /**
     * Explicit list of scopes to mirror, for packages where only a subset should be reachable from the
     * backend.
     *
     * @return list<string>|null
     */
    protected function scopes(): ?array
    {
        return null;
    }

    #[Override]
    final public function loadRoutes(): void
    {
        $prefix = $this->scopePrefix();
        $scopes = $this->scopes();

        foreach (RouteCollection::getRoutes() as $registered) {
            $scope = $registered['scope'];

            $mirror = null !== $scopes
                ? in_array($scope, $scopes, true)
                : (null !== $prefix && str_starts_with($scope, $prefix));

            if (!$mirror) {
                continue;
            }

            $route = clone $registered['route'];
            $route->setPath('backend' . $route->getPath());

            RouteCollection::registerRoute(
                'backend/' . $scope,
                $route,
                $registered['description'],
                $registered['responses'],
                new BackendUser(),
                ['backend'],
            );
        }
    }
}
