<?php

use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage\Module;
use FriendsOfREDAXO\API\RoutePackage\Structure;

RouteCollection::registerRoutePackage(new Module());
RouteCollection::registerRoutePackage(new Structure());

rex_extension::register('YREWRITE_PREPARE', static function (rex_extension_point $ep) {
    RouteCollection::handle();
}, rex_extension::EARLY);
