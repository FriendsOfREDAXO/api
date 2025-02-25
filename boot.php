<?php

use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage\Modules;
use FriendsOfREDAXO\API\RoutePackage\Structure;
use FriendsOfREDAXO\API\RoutePackage\Modules;
use FriendsOfREDAXO\API\RoutePackage\Templates;

RouteCollection::registerRoutePackage(new Modules());
RouteCollection::registerRoutePackage(new Structure());
RouteCollection::registerRoutePackage(new Modules());
RouteCollection::registerRoutePackage(new Templates());

rex_extension::register('YREWRITE_PREPARE', static function (rex_extension_point $ep) {
    RouteCollection::handle();
}, rex_extension::EARLY);
