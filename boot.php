<?php

use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage\Media;
use FriendsOfREDAXO\API\RoutePackage\Modules;
use FriendsOfREDAXO\API\RoutePackage\Structure;
use FriendsOfREDAXO\API\RoutePackage\Templates;

RouteCollection::registerRoutePackage(new Modules());
RouteCollection::registerRoutePackage(new Structure());
RouteCollection::registerRoutePackage(new Templates());
RouteCollection::registerRoutePackage(new Media());

rex_extension::register('YREWRITE_PREPARE', static function (rex_extension_point $ep) {
    RouteCollection::handle();
}, rex_extension::EARLY);

if (rex::isBackend()) {
    $addon = rex_addon::get('api');
    rex_view::addCssFile($addon->getAssetsUrl('css/swagger-ui.css'));
    rex_view::addCssFile($addon->getAssetsUrl('css/swagger-ui-redaxo-theme.css'));
    rex_view::addJsFile($addon->getAssetsUrl('js/swagger-ui-bundle.js'));
}
