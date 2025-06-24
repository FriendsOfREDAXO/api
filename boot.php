<?php

use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage\Backend\Media as BackendMedia;
use FriendsOfRedaxo\Api\RoutePackage\Clangs;
use FriendsOfRedaxo\Api\RoutePackage\Media;
use FriendsOfRedaxo\Api\RoutePackage\Modules;
use FriendsOfRedaxo\Api\RoutePackage\Structure;
use FriendsOfRedaxo\Api\RoutePackage\Templates;
use FriendsOfRedaxo\Api\RoutePackage\Users;

RouteCollection::registerRoutePackage(new Clangs());
RouteCollection::registerRoutePackage(new Modules());
RouteCollection::registerRoutePackage(new Structure());
RouteCollection::registerRoutePackage(new Templates());
RouteCollection::registerRoutePackage(new Media());
RouteCollection::registerRoutePackage(new Users());
RouteCollection::registerRoutePackage(new BackendMedia());

if (!rex::getConsole()) {
    rex_extension::register('YREWRITE_PREPARE', static function (rex_extension_point $ep) {
        RouteCollection::handle();
    }, rex_extension::EARLY);

    if (rex::isBackend() && 'api/openapi' === rex_be_controller::getCurrentPage()) {
        $addon = rex_addon::get('api');
        rex_view::addCssFile($addon->getAssetsUrl('vendor/swagger-ui/css/swagger-ui.css'));
        rex_view::addCssFile($addon->getAssetsUrl('css/swagger-ui-redaxo-theme.css'));
        rex_view::addJsFile($addon->getAssetsUrl('vendor/swagger-ui/js/swagger-ui-bundle.js'));
    }
}
