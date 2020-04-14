<?php

namespace App;

use Flight;
use Throwable;
use ReflectionClass;
use ReflectionProperty;

class Users
{

    public static $current = null; // current id

    public $username = "";
    public $id = 0;
    public $sessid = ""; // PHPSESSID
    public $authenticated = false;
    public $existing = false;
    public $availability = [0, 0, 0, 0, 0, 0, 0, 0];
    public $theme = null;
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
            $this->id = (int) $ret->id;
            $this->availability = json_decode($ret->availability);
        }

        if (self::$current != null && (self::$current->id == $this->id || self::$current->username == $this->username)) {
            $this->authenticated = true;
            $this->sessid = session_id();
        }
    }

    public function build($data){
        foreach($data as $key => $value){
            if($key == "availability"){
                $this->$key = json_decode($value);
            }else if(is_numeric($value)){
                $this->$key = (int) $value;
            }else if(is_bool($value)){
                $this->$key = (bool) $value;
            }else{
                $this->$key = $value;
            }
        }
    }

    public function save()
    {
        self::$current = $this;
        $_SESSION['user'] = serialize($this); // store user in current session
    }

    private function authenticate($username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        $ret = Flight::sql("SELECT * FROM `user` WHERE `username`='{$username}'  ");

        if(empty($username)){
            return -3;
        }else if (empty($ret)) {
            return -1;
        }else if(empty($password)){
            return -4;
        }else if($ret->password != $password){
            return -2;
        }

        $this->existing = true;
        foreach ($ret as $key => $value) {
            if(is_numeric($value)){
                $this->$key = (int) $value;
            }else if(is_bool($value)){
                $this->$key = (boolean) $value;
            }else if($key == "availability"){
                $this->$key = json_decode($value);
            }else{
                $this->$key = $value;
            }
        }

        $this->authenticated = true;
        $this->sessid = session_id();
        return true;
    }

    private function register($username, $password, $sec_ques, $sec_ans){
        $ret = Flight::sql("SELECT * FROM `user` WHERE `username` = '$username' ");
        if(empty($username)){
            return -4;
        }else if(!empty($ret)){
            return -1;
        }
        $reg = "/^(?!\D+$)(?![^a-zA-Z]+$)\S{8,20}$/";
        if(empty($password)){
            return -5;
        }else if(!preg_match($reg, $password)){
            return -2;
        }
        if(empty($sec_ques)){
            return -6;
        }else if(empty($sec_ans)){
            return -7;
        }
        $sec_ques = addslashes($sec_ques);
        $sec_ans = addslashes($sec_ans);
        $ret = Flight::sql("INSERT INTO `user`(`username`, `password`, `security_question`, `security_answer`, `availability`, `theme`) VALUES ('$username', '$password', '$sec_ques', '$sec_ans', '[12, 12, 12, 12, 12, 12, 12]', null)   ");
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

    private function get(){
        $ret = Flight::sql("SELECT * FROM `user` WHERE `id` ='{$this->id}'   ");
        $this->build($ret);

        return true;
    }

    private function update(){
        $values = [];
        
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach($properties as $property){
            $key = $property->name;
            $key_alias = $key;
            $value = $this->$key;

            if($key == "availability"){
                $value = json_encode(json_decode($value));
                if(empty($value)){
                    $value = "[12, 12, 12, 12, 12, 12, 12]";
                }
            }
            
            if(empty($value)){
                $values[] = "`$key_alias` = null";
            }else{
                $values[] = "`$key_alias` = '{$value}'";
            }
        }
        $sql = "UPDATE `user` SET " . implode(", ", $values) . " WHERE `id`='{$this->id}' ";
        $ret = Flight::sql($sql);
        if ($ret === false && Flight::db()->error != "") {
            return -2;
        }else{
            $this->get();
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
            Flight::ret(StatusCodes::CREATED, "Your account is successfully signed up", $user);
        }else if($ret == -1){
            Flight::ret(StatusCodes::PRECONDITION_FAILED, "This username has been taken");
        }else if($ret == -2){
            Flight::ret(StatusCodes::PRECONDITION_FAILED, "Please choose a password which has 8-20 characters, combination of digits and letters");
        }else if($ret == -4){
            Flight::ret(StatusCodes::NOT_ACCEPTABLE, "Please choose a username", Array("username", "password", "security_question", "security_answer"));
        }else if($ret == -5){
            Flight::ret(StatusCodes::NOT_ACCEPTABLE, "Please choose a password which has 8-20 characters, combination of digits and letters", Array("password", "security_question", "security_answer"));
        }else if($ret == -6){
            Flight::ret(StatusCodes::NOT_ACCEPTABLE, "Please choose a security question", Array("security_question", "security_answer"));
        }else if($ret == -7){
            Flight::ret(StatusCodes::NOT_ACCEPTABLE, "Please provide an answer for your security question", Array("security_answer"));
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

    public static function Authentication()
    {
        $user = new Users();

        $username = Flight::request()->data['username'];
        $password = Flight::request()->data['password'];

        $ret = $user->authenticate($username, $password);
        if ($ret === -1) {
            Flight::ret(StatusCodes::PRECONDITION_FAILED, "This account doesn't exist. Enter a different account");
        } else if ($ret === -2) {
            Flight::ret(StatusCodes::UNAUTHORIZED, "Your password to this account is incorrect");
        } else if($ret === -3){
            Flight::ret(StatusCodes::NOT_ACCEPTABLE, "Please enter your account username", Array("username", "password"));
        } else if($ret === -4){
            Flight::ret(StatusCodes::NOT_ACCEPTABLE, "Please enter your account password", Array("password"));
        } else {
            $user->save();
            Flight::ret(StatusCodes::OK, "OK", $user);
        }
    }

    public static function Gets($data){
        $user = null;
        $tryCurrent = false;

        if ($data->user_id != null) { // specific user
            $user = new Users($data->user_id);
        } else if (self::$current != null) { // currently has authenticated in user
            $user = self::$current;
        } else {
            $tryCurrent = true; // try visit /api/user/ (default user)
            $user = new Users();
        }

        if ($user->existing) { // existing user query
            Flight::ret(StatusCodes::OK, "OK", $user);
        } else if ($tryCurrent) {
            Flight::ret(StatusCodes::UNAUTHORIZED, "Unauthorized");
        } else {
            Flight::ret(StatusCodes::NOT_FOUND, "No matching user");
        }
    }

    public static function Updates(){
        $ret = self::$current->update();
        if($ret === -2){
            Flight::ret(StatusCodes::SERVICE_ERROR, "Fail to update by database error", Flight::db()->error);
            return;
        }
        Flight::ret(StatusCodes::ACCEPTED, "Accepted", self::$current->print());
    }

    public static function Users($user_id = null)
    {
        if(Users::$current == null){
            Flight::ret(StatusCodes::UNAUTHORIZED, "Unauthorized");
            return;
        }

        $func = null;
        $method = Flight::request()->method;

        
        switch ($method) {
            case "GET":
                $func = "Gets";
            break;
            case "POST":
                $func = "Registration";
            break;
            case "PUT":
            case "PATCH":
                $func = "Updates";
            break;
        }

        if($func == null){
            Flight::ret(StatusCodes::METHOD_NOT_ALLOWED, "Method Not Allowed");
            return;
        }


        $data = Flight::request()->data;
        $data->user_id = $user_id;
        
        // Escape
        foreach($data as $key => $each){
            $data->$key = addslashes($each);
        }

        self::$func($data);
    }
}
