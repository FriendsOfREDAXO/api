<?php

use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage\Media;
use FriendsOfREDAXO\API\RoutePackage\Modules;
use FriendsOfREDAXO\API\RoutePackage\Structure;
use FriendsOfREDAXO\API\RoutePackage\Templates;
use FriendsOfREDAXO\API\RoutePackage\Users;

RouteCollection::registerRoutePackage(new Media());
RouteCollection::registerRoutePackage(new Modules());
RouteCollection::registerRoutePackage(new Structure());
RouteCollection::registerRoutePackage(new Templates());
RouteCollection::registerRoutePackage(new Users());

rex_extension::register('YREWRITE_PREPARE', static function (rex_extension_point $ep) {
    RouteCollection::handle();
}, rex_extension::EARLY);
