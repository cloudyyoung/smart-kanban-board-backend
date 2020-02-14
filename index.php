<?php

define('CONFIG', parse_ini_file('config.ini', true, INI_SCANNER_TYPED));
define('ROOT', __DIR__);

session_start();

require 'vendor/autoload.php';

Flight::set('flight.log_errors', true);
Flight::set('flight.views.path', __DIR__ . '/view');


if (CONFIG['app']['debug']) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}


Flight::map('error', function (Throwable $e) {
    // Handle error
    $array = null;
    $message = $e->getMessage();
    if (CONFIG['app']['debug']) {
        echo $e;
        echo $e->getTraceAsString();
        $array = $e->getTrace();
    }
    Flight::ret(500, $message, $array);
});

Flight::map('notFound', function () {
    // Handle not found
});



Flight::register('db', 'mysqli', array(CONFIG['database']['host'], CONFIG['database']['username'], CONFIG['database']['password'], CONFIG['database']['database']), function ($db) {
    $db->set_charset("utf8");
});

Flight::map('sql', function($sql, $fetch_all = false){
    $db = Flight::db();
    $res = $db->multi_query($sql);

    if ($res === false) {
        return false;
    }

    $ret = [];
    do {
        $result = $db->store_result();
        if ($result instanceof mysqli_result){ //select
            $temp = [];
            while ($row = $result->fetch_assoc()){
                $temp[] = (object)$row;
            }
            $ret[] = $temp;
        }else{ //insert/update/delete
            $ret[] = Array($result);
        }
 
    } while ($db->more_results() && $db->next_result());  //must invoke more_result() before next_result()
    
    if(count($ret) == 1){ // one query
        $ret = $ret[0];
        if($fetch_all == false && count($ret) >= 1){ // only first row
            $ret = $ret[0];
        }
    }
    

    return $ret;
});


use App\StatusCodes;

// RESTful
Flight::map('ret', function ($code = StatusCodes::NO_CONTENT, $message = '', $array = null) {
    header(StatusCodes::httpHeaderFor($code));
    http_response_code($code);

    if($code >= StatusCodes::errorCodesBeginAt){
        $message = ucwords($message);
        Flight::json(Array(
            "error" => Array(
                "code" => $code,
                "message" => $message,
                "details" => $array,
            )
        ));
    }else if (!empty($array)) {
        Flight::json($array);
    }

    Flight::stop();
});


use App\Users;
use App\Kanban;
use App\Boards;

if (isset($_SESSION['user'])) {
    Users::$current = Kanban::$current = unserialize($_SESSION['user']);
}

if(isset($_SESSION['kanban']) && isset($_SESSION['dictionary'])){
    Kanban::$nodes = unserialize($_SESSION['kanban']);
    Kanban::$dictionary = unserialize($_SESSION['dictionary']);
}


Flight::route('PUT /api/users/authentication', function () {
    Users::Authentication();
});

Flight::route('GET /api/users(/@id)', function ($id) {
    if($id == "me") $id = null;
    Users::Users($id);
});


Flight::route('GET /api/kanban', function () {
    if(Kanban::$current == null){
        Flight::ret(StatusCodes::UNAUTHORIZED, "Unauthenticated Access");
        return;
    }

    Kanban::Kanban();
});

Flight::route('GET|POST|PATCH|DELETE /api/boards(/@board_id:[0-9]+)', function($board_id){
    if(Kanban::$current == null){
        Flight::ret(StatusCodes::UNAUTHORIZED, "Unauthorized");
        return;
    }

    $method = Flight::request()->method;
    Boards::Boards($method, $board_id);
});

Flight::route('/api/dic', function () {
    Flight::ret(StatusCodes::OK, "OK", Kanban::$dictionary);
});


Flight::route('/api/*', function () {
    Flight::ret(StatusCodes::NOT_IMPLEMENTED, "Not Implemented");
});



Flight::start();
