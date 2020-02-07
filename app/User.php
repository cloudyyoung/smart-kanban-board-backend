<?php


namespace App;

use Flight;
use Throwable;

use \App\Board;

class User extends Base{

    public static $current = null; // current id

    public $username = "";
    public $id = 0;
    public $sessid = ""; // PHPSESSID
    public $authenticated = false;
    public $existing = false;
    private $password = "";


    function __construct($id = null){

        $this->sessid = session_id();

        $ret = [];
        if(is_numeric($id)){ // giving id or not
            $ret = Flight::sql("SELECT `username`, `id` FROM `user` WHERE `id` = '$id'  ");
        }else if($id != null){ // giving username
            $ret = Flight::sql("SELECT `username`, `id` FROM `user` WHERE `username` = '$id'  ");
        }else{
            return;
        }

        if(!empty($ret)){
            $this->existing = true;
            $this->username = $ret->username;
            $this->id = $ret->id;
        }

        if(self::$current != null && (self::$current->id == $this->id || self::$current->username == $this->username)){
            $this->authenticated = true;
        }

    }


    private function authenticate($username, $password){

        if($username == null || $password == null){
            return false;
        }

        $this->username = $username;
        $this->password = $password;

        $ret = Flight::sql("SELECT * FROM `user` WHERE `username`='$username' AND `password`='$password'  ");

        if (empty($ret)) {
            return false;
        }

        $this->existing = true;
        foreach ($ret as $key => $value) {
            $this->$key = $value;
        }

        $this->authenticated = true;
        return true;
    }

    public function save(){
        self::$current = $this;
        $_SESSION['user'] = serialize($this); // store user in current session
    }

    public static function Signin(){

        $user = new User();

        $username = Flight::request()->data['username'];
        $password = Flight::request()->data['password'];

        if(!$user->authenticate($username, $password)){
            Flight::ret(403, "Incorrect User");
        }else{
            $user->save();
            Flight::ret(200, "OK");
        }

    }

    public static function User($id = null){

        $user = null;
        $tryCurrent = false;

        if($id != null){ // specific user
            $user = new User($id);
        }else if(self::$current != null){ // currently has authenticated in user
            $user = self::$current;
        }else{
            $tryCurrent = true; // try visit /api/user/ (default user)
            $user = new User();
        }
        
        if($user->existing){ // existing user query
            Flight::ret(200, "OK", $user);
        }else if($tryCurrent){
            Flight::ret(401, "Unauthorized");
        }else{
            Flight::ret(403, "Incorrect User");
        }

    }


}