<?php

session_start();

require 'vendor/autoload.php';
require_once 'util.php';

use App\Users;
use App\Kanban;
use App\Nodes;
use App\StatusCodes;


if (isset($_SESSION['user'])) {
    Users::$current = Kanban::$current = unserialize($_SESSION['user']);
    Kanban::fetch();
}

Flight::route('PUT /api/users/authentication', function () {
    Users::Authentication();
});

Flight::route('GET /api/users(/@id)', function ($id) {
    if($id == "me") $id = null;
    Users::Users($id);
});

Flight::route('POST /api/users', function () {
    Users::Registration();
});



Flight::route('GET /api/kanban', function () {
    if(Kanban::$current == null){
        Flight::ret(StatusCodes::UNAUTHORIZED, "Unauthenticated Access");
        return;
    }

    Kanban::Kanban();
});

Flight::route('GET|POST|PATCH|DELETE /api/@type(/@node_id:[0-9]+)', function($type, $node_id){
    if(Kanban::$current == null){
        Flight::ret(StatusCodes::UNAUTHORIZED, "Unauthorized");
        return;
    }
    $type = rtrim($type, "s");
    if(!in_array($type, array_values(Kanban::$typeList)) && $type != "user"){
        Flight::ret(StatusCodes::NOT_IMPLEMENTED, "Not Implemented");
        return;
    }

    $method = Flight::request()->method;
    Nodes::Nodes($method, $node_id, $type);
});

Flight::route('/api/dic', function () {
    Flight::ret(StatusCodes::OK, "OK", Kanban::$dictionary);
});


Flight::route('/api/*', function () {
    Flight::ret(StatusCodes::NOT_IMPLEMENTED, "Not Implemented");
});



Flight::start();
