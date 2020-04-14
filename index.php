<?php

session_start();

require 'vendor/autoload.php';
require_once 'util.php';

use App\Users;
use App\Kanban;
use App\Nodes;
use App\StatusCodes;


if (isset($_SESSION['user'])) {
    Users::$current = unserialize($_SESSION['user']);
}

Flight::route('PUT /api/users/authentication', function () {
    Users::Authentication();
});

Flight::route('/api/users(/@id)', function ($id) {
    if($id == "me") $id = null;
    Users::Users($id);
});

Flight::route('POST /api/users/reset/password', function () {
    Users::ResetPassword();
});


Flight::route('/api/kanban', function () {
    Kanban::Kanban();
});

Flight::route('/api/@type(/@node_id:[0-9]+)', function($type, $node_id){
    Nodes::Nodes($node_id, $type);
});


Flight::route('/api/*', function () {
    Flight::ret(StatusCodes::NOT_IMPLEMENTED, "Not Implemented");
});



Flight::start();
