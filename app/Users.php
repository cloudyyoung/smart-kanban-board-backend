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

    private function security_answer_check($username, $sec_ans){
        if(empty($username)){
            return -1;
        }
        $ret = Flight::sql("SELECT * FROM `user` WHERE `username`='$username' AND `security_answer`='$sec_ans'   ");
        if(empty($ret)){
            return -2;
        }else{
            $this->username = $username;
            return true;
        }
    }

    private function reset($password){
        if(empty($password)){
            return -1;
        }
        $username = $this->username;
        $ret = Flight::sql("UPDATE `user` SET `password`='$password' WHERE `username`='$username' ");
        if($ret === false){
            return -2;
        }else{
            return true;
        }
    }
    
    public static function Registration(){
        $username = Flight::request()->data['username'];
        $password = Flight::request()->data['password'];
        $sec_ques = Flight::request()->data['security_question'];
        $sec_ans = Flight::request()->data['security_answer'];

        $user = new Users();
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

    public static function ResetPassword(){
        $sec_ans = Flight::request()->data['security_answer'];
        $username = Flight::request()->data['username'];
        $password = Flight::request()->data['new_password'];

        $user = new Users();
        $ret = $user->security_answer_check($username, $sec_ans);
        if($ret){

        }else if($ret == -1){
            Flight::ret(StatusCodes::NOT_ACCEPTABLE, "Lack of params", Array("username", $username));
            return;
        }else if($ret == -2){
            Flight::ret(StatusCodes::PRECONDITION_FAILED, "Security answer not match");
            return;
        }

        $ret = $user->reset($password);
        if($ret){
            Flight::ret(StatusCodes::NO_CONTENT, "No Content");
        }else if($ret == -1){
            Flight::ret(StatusCodes::NOT_ACCEPTABLE, "Lack of params", Array("new_password"));
        }else if($ret == -2){
            Flight::ret(StatusCodes::SERVICE_ERROR, "Service error");
        }else{
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
            Flight::ret(401, "Failed Authentication");
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
