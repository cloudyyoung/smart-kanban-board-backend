<?php


namespace App;

use Flight;
use Throwable;

use App\Columns;
use App\Kanban;
use App\StatusCodes;

class Boards{

    public static $uid = 0;

    public $id = 0;
    public $title = "";
    public $note = "";
    private $columns = Array();

    function __construct($id, $title, $note){

        $this->id = $id;
        $this->title = $title;
        $this->note = $note;

        $ret = Flight::sql("SELECT * FROM `column` WHERE `board_id` ='$id'   ", true);
        foreach($ret as $column){
            $this->columns[(string)$column->id] = new Columns($column->id, $column->title, $column->note);
        }

    }

    public function get(){
        $arr = get_object_vars($this);
        $arr['column'] = [];
        foreach($this->columns as $column){
            $arr['column'][] = $column->get();
        }
        return $arr;
    }

    public function getColumns($column_id){
        if(array_key_exists($column_id, $this->columns)){
            return $this->columns[$column_id];
        }else{
            return false;
        }
    }

    private static function gets($user_id, $board_id = null){
        if($board_id == null){
            return Flight::sql("SELECT * FROM `board` WHERE `user_id`='$user_id'  ", true);
        }else{
            return Flight::sql("SELECT * FROM `board` WHERE `user_id`='$user_id' AND `id`='$board_id'  ", true);
        }
    }

    private static function creates($user_id, $title, $note){
        $ret = Flight::sql("INSERT INTO `board`(`user_id`, `title`, `note`) VALUES ($user_id, '$title', '$note')  ");
        if($ret === false){
            return false;
        }else{
            $ret = Flight::sql("SELECT * FROM `board` WHERE `id`=LAST_INSERT_ID();  ");
            return $ret;
        }
    }

    private static function updates($user_id, $board_id, $title, $note){
        $vars = [];
        if($title != null){
            $vars[] =  "`title`='$title'";
        }
        if($note != null){
            $vars[] =  "`note`='$note'";
        }
        
        $ret = Flight::sql("UPDATE `board` SET " . implode(", ", $vars) . " WHERE `id`=$board_id AND `user_id`=$user_id   ");
        if($ret === false){
            return false;
        }else{
            $ret = Flight::sql("SELECT * FROM `board` WHERE `id`=$board_id  ");
            return $ret;
        }
    }

    private static function deletes($user_id, $board_id){
        $ret = Flight::sql("SELECT * FROM `board` WHERE `user_id`='$user_id' AND `id`='$board_id'  ");
        if(empty($ret)){
            return [StatusCodes::FORBIDDEN, "Could not find board", null];
        }
        
        $ret = Flight::sql(<<<SQL
            BEGIN;
            DELETE FROM `board` WHERE `id`=$board_id AND `user_id`=$user_id;
            DELETE FROM `column` WHERE `board_id`=$board_id;
            DELETE FROM `event` WHERE `board_id`=$board_id;
            COMMIT;
        SQL);
        if($ret === false){
            return list(StatusCodes::SERVICE_ERROR, "Fail to delete by database error", null);
        }else{
            return list(StatusCodes::OK, null, null);
        }
    }

    

    public static function Boards($method, $board_id){
        $user_id = Kanban::$current->id;
        $data = Flight::request()->data;

        $funct = "";
        $args = Array();
        
        switch($method){
            case "GET":
                $func = "gets";
            break;
            case "POST":
                $func = "creates";
                $args = ["title"];
            break;
            case "PATCH":
                $func = "updates";
                $args = ["board_id"];
            break;
            case "DELETE":
                $func = "deletes";
                $args = ["board_id"];
            break;
        }

        $miss = [];
        $data->board_id = $board_id;
        foreach($args as $key => $param){
            if(!isset($data->$param)){
                array_push($miss, $param);
            }
        }

        if(!empty($miss)){
            Flight::ret(StatusCodes::NOT_ACCEPTABLE, "Missing Params", Array("missing" => $miss));
            return;
        }

        list($code, $message, $array) = self::$func($user_id, $board_id);
        if($code > StatusCodes::errorCodesBeginAt){
            Flight::ret($code, $message, $array);
        }else{
            Flight::ret($code);
        }

    }

}
