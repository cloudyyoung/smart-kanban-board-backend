<?php


namespace App;

use Flight;
use Throwable;

use \App\Board;

class Account extends Base{

    public static $current = null; // current id

    public $username = "";
    public $id = 0;
    public $sessid = ""; // PHPSESSID
    public $authenticated = false;
    public $existing = false;

    private $board = Array();
    private $password = "";


    function __construct($id = null){

        $this->sessid = session_id();

        $ret = [];
        if(is_numeric($id)){ // giving id or not
            $ret = Flight::sql("SELECT `username`, `id` FROM `account` WHERE `id` = '$id'  ");
        }else if($id != null){ // giving username
            $ret = Flight::sql("SELECT `username`, `id` FROM `account` WHERE `username` = '$id'  ");
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

    function __toarray(){
        return Array();
    }


    private function authenticate($username, $password){

        if($username == null || $password == null){
            return false;
        }

        $this->username = $username;
        $this->password = $password;

        $ret = Flight::sql("SELECT * FROM `account` WHERE `username`='$username' AND `password`='$password'  ");

        if (empty($ret)) {
            return false;
        }

        $this->existing = true;
        foreach ($ret as $key => $value) {
            $this->$key = $value;
        }

        $this->fetchKanban();

        $this->authenticated = true;
        return true;
    }

    public function save(){
        self::$current = $this;
        $_SESSION['user'] = serialize($this); // store user in current session
    }

    public function fetchKanban(){
        $this->board = Array();
        $id = $this->id;
        $ret = Flight::sql("SELECT * FROM `board` WHERE `account_id`='$id'  ", true);
        foreach($ret as $board){
            $this->board[(string)$board->id] = new Board($board->id, $board->title, $board->note);
        }
        $this->save();
    }

    public function board(){
        $ret = array_values($this->board);
        $index = 0;
        foreach($this->board as $board){
            $ret[$index] = get_object_vars($ret[$index]);
            $ret[$index]['column'] = $board->column();
            $index ++;
        }
        return $ret;
    }


    public static function Signin(){

        $user = new Account();

        $username = Flight::request()->data['username'];
        $password = Flight::request()->data['password'];

        if(!$user->authenticate($username, $password)){
            Flight::ret(403, "Incorrect Account");
        }else{
            $user->save();
            Flight::ret(200, "OK");
        }

    }

    public static function User($id = null){

        $user = null;
        $tryCurrent = false;

        if($id != null){ // specific user
            $user = new Account($id);
        }else if(self::$current != null){ // currently has authenticated in user
            $user = self::$current;
        }else{
            $tryCurrent = true; // try visit /api/user/ (default user)
            $user = new Account();
        }
        
        if($user->existing){ // existing account query
            Flight::ret(200, "OK", $user);
        }else if($tryCurrent){
            Flight::ret(401, "Unauthorized");
        }else{
            Flight::ret(403, "Incorrect Account");
        }

    }

    public static function Kanban(){

        $user = self::$current;
        $user->fetchKanban();

        $result = $user->board();

        if($result !== false){
            Flight::ret(200, "OK", $result);
        }else{
            Flight::ret(404, "Not Found");
        }
        

    }


}