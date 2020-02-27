<?php

namespace App;

use Flight;

class Users
{

    public static $current = null; // current id

    public $username = "";
    public $id = 0;
    public $sessid = ""; // PHPSESSID
    public $authenticated = false;
    public $existing = false;
    private $password = "";
    private $security_question = "";
    private $security_answer = "";

    public function __construct($id = null)
    {

        $ret = [];
        if (is_numeric($id)) { // giving id or not
            $ret = Flight::sql("SELECT `username`, `id` FROM `user` WHERE `id` = '$id'  ");
        } else if ($id != null) { // giving username
            $ret = Flight::sql("SELECT `username`, `id` FROM `user` WHERE `username` = '$id'  ");
        } else {
            return;
        }

        if (!empty($ret)) {
            $this->existing = true;
            $this->username = $ret->username;
            $this->id = $ret->id;
        }

        if (self::$current != null && (self::$current->id == $this->id || self::$current->username == $this->username)) {
            $this->authenticated = true;
            $this->sessid = session_id();
        }
    }

    private function authenticate($username, $password)
    {

        if ($username == null || $password == null) {
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
        $this->sessid = session_id();
        return true;
    }

    private function register($username, $password, $sec_ques, $sec_ans){
        if(empty($username)){
            return -4;
        }
        $ret = Flight::sql("SELECT * FROM `user` WHERE `username` = '$username' ");
        if(!empty($ret)){
            return -1;
        }
        $reg = "/^(?!\D+$)(?![^a-zA-Z]+$)\S{8,20}$/";
        if(!preg_match($reg, $password)){
            return -2;
        }
        if(empty($sec_ques) || empty($sec_ans)){
            return -3;
        }
        $sec_ques = addslashes($sec_ques);
        $sec_ans = addslashes($sec_ans);
        $ret = Flight::sql("INSERT INTO `user`(`username`, `password`, `security_question`, `security_answer`) VALUES ('$username', '$password', '$sec_ques', '$sec_ans')   ");
        if($ret !== false){
            $this->authenticate($username, $password);
            return true;
        }else{
            return false;
        }
    }
    
    public static function Registration(){
        $user = new Users();

        $username = Flight::request()->data['username'];
        $password = Flight::request()->data['password'];
        $sec_ques = Flight::request()->data['security_question'];
        $sec_ans = Flight::request()->data['security_answer'];

        $ret = $user->register($username, $password, $sec_ques, $sec_ans);
        
        if($ret === false){
            Flight::ret(StatusCodes::SERVICE_ERROR, "Service Error");
        }else if($ret === true){
            Flight::ret(StatusCodes::OK, "Successfully signed up", $user);
        }else if($ret == -1){
            Flight::ret(StatusCodes::PRECONDITION_FAILED, "Username has been taken");
        }else if($ret == -2){
            Flight::ret(StatusCodes::PRECONDITION_FAILED, "Please choose a password that has 8-20 characters, combination of digits and letters");
        }else if($ret == -3){
            Flight::ret(StatusCodes::PRECONDITION_FAILED, "Security question and answer has to be correctly filled in");
        }else if($ret == -4){
            Flight::ret(StatusCodes::PRECONDITION_FAILED, "Username is invalid");
        }
    }

    public function save()
    {
        self::$current = $this;
        $_SESSION['user'] = serialize($this); // store user in current session
    }

    public static function Authentication()
    {

        $user = new Users();

        $username = Flight::request()->data['username'];
        $password = Flight::request()->data['password'];

        if (!$user->authenticate($username, $password)) {
            Flight::ret(403, "Failed Authentication");
        } else {
            $user->save();
            Flight::ret(200, "OK", $user);
        }
    }

    public static function Users($id = null)
    {

        $user = null;
        $tryCurrent = false;

        if ($id != null) { // specific user
            $user = new Users($id);
        } else if (self::$current != null) { // currently has authenticated in user
            $user = self::$current;
        } else {
            $tryCurrent = true; // try visit /api/user/ (default user)
            $user = new Users();
        }

        if ($user->existing) { // existing user query
            Flight::ret(200, "OK", $user);
        } else if ($tryCurrent) {
            Flight::ret(401, "Unauthorized");
        } else {
            Flight::ret(404, "No matching user");
        }
    }
}
