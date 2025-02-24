<?php

use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage\Structure;
use FriendsOfREDAXO\API\RoutePackage\Module;
use FriendsOfREDAXO\API\RoutePackage\Template;

RouteCollection::registerRoutePackage(new Structure());
RouteCollection::registerRoutePackage(new Module());
RouteCollection::registerRoutePackage(new Template());

rex_extension::register('YREWRITE_PREPARE', static function (rex_extension_point $ep) {
    RouteCollection::handle();
}, rex_extension::EARLY);
