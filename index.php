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
    Flight::ret(500, "Internal Server Error");
    if (CONFIG['app']['debug']) {
        echo $e;
        echo $e->getTraceAsString();
    }
});

Flight::map('notFound', function () {
    // Handle not found
    
});



Flight::register('db', 'mysqli', array(CONFIG['database']['host'], CONFIG['database']['username'], CONFIG['database']['password'], CONFIG['database']['database']), function ($db) {
    $db->set_charset("utf8");
});

Flight::map('sql', function ($sql, $fetch_all = false) {
    $db = Flight::db();
    $res = $db->query($sql);

    if (is_bool($res)) {
        return $res;
    }

    $ret = [];
    while ($row = $res->fetch_assoc()) {
        $ret[] = (object) $row;
    }

    if ($fetch_all) {
        return $ret;
    }else{
        if(!empty($ret)){
            return (object)$ret[0];
        }else{
            return [];
        }
    }
    
});

// RESTful
Flight::map('ret', function ($code = 204, $message = '', $array = null) {
    $message = ucwords($message);
    header("HTTP/1.1 $code $message");
    if (!empty($array)) {
        Flight::json($array);
    }
    Flight::stop();
});




use App\User;
use App\Kanban;

if (isset($_SESSION['user'])) {
    User::$current = unserialize($_SESSION['user']);
    Kanban::$current = User::$current;
}


Flight::route('PUT /api/user/authentication', function () {
    User::Authentication();
});

Flight::route('GET /api/user(/@id)', function ($id) {
    User::User($id);
});


Flight::route('GET /api/kanban', function () {

    if(Kanban::$current == null){
        Flight::ret(403, "Unauthorized");
        return;
    }

    Kanban::Kanban();
});

Flight::route('GET /api/boards(/@board_id)', function($board_id){
    $method = Flight::request()->method;
    Kanban::Boards($method, $board_id);
});




Flight::route('/', function () {
    echo 'Kanban whah???';
});



Flight::start();
