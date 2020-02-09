<?php


namespace App;

use Flight;
use Throwable;

use App\Events;

class Columns{

    public static $uid = 0;

    public $id = 0;
    public $title = "";
    public $note = "";
    private $events = Array();

    function __construct($id, $title, $note){

        $this->id = $id;
        $this->title = $title;
        $this->note = $note;

        $ret = Flight::sql("SELECT * FROM `event` WHERE `column_id` ='$id'   ", true);
        foreach($ret as $event){
            $this->events[(string)$event->id] = new Events($event->id, $event->title, $event->note);
        }

    }
    
    public function get(){
        $arr = get_object_vars($this);
        $arr['event'] = [];
        foreach($this->events as $event){
            $arr['event'][] = $event->get();
        }
        return $arr;
    }

    public function getEvents($event_id){
        if(array_key_exists($event_id, $this->events)){
            return $this->column[$event_id];
        }else{
            return false;
        }
    }




    private static function gets($data)
    {
        if ($data->column_id != null) {
            $ret = Flight::sql("SELECT * FROM `column` WHERE `user_id`='{$data->user_id}' AND `id`='{$data->column_id}'  ");
        }else{
            $ret = Flight::sql("SELECT * FROM `column` WHERE `user_id`='{$data->user_id}' ", true);
        }
        
        if ($ret === false) {
            return [StatusCodes::SERVICE_ERROR, "Fail to get by database error", Flight::db()->error];
        }else if (empty($ret)) {
            return [StatusCodes::FORBIDDEN, "Could not find column", null];
        } else {
            return [StatusCodes::OK, "OK", $ret];
        }
    }

    private static function creates($data)
    {
        $ret = Flight::sql("INSERT INTO `column`(`board_id`, `user_id`, `title`, `note`) VALUES ({$data->board_id}, {$data->user_id}, '{$data->title}', '{$data->note}')  ");
        if ($ret === false) {
            return [StatusCodes::SERVICE_ERROR, "Fail to create by database error", Flight::db()->error];
        } else {
            $ret = Flight::sql("SELECT * FROM `column` WHERE `id`=LAST_INSERT_ID();  ");
            return [StatusCodes::OK, "OK", $ret];
        }
    }

    private static function updates($data)
    {
        $ret = Flight::sql("SELECT * FROM `column` WHERE `id`={$data->column_id} AND `user_id`={$data->user_id}");
        if(empty($ret)){
            return [StatusCodes::FORBIDDEN, "Could not find column", null];
        }

        $vars = [];
        if ($data->title != null) {
            $vars[] =  "`title`='$data->title'";
        }
        if ($data->note != null) {
            $vars[] =  "`note`='$data->note'";
        }

        $ret = Flight::sql("UPDATE `column` SET " . implode(", ", $vars) . " WHERE `id`={$data->column_id} AND `user_id`={$data->user_id}   ");
        if ($ret === false) {
            return [StatusCodes::SERVICE_ERROR, "Fail to update by database error", Flight::db()->error];
        } else {
            $ret = Flight::sql("SELECT * FROM `column` WHERE `id`={$data->column_id}  ");
            return [StatusCodes::OK, "OK", $ret];
        }
    }

    private static function deletes($data){
        $ret = Flight::sql("SELECT * FROM `column` WHERE `user_id`='{$data->user_id}' AND `id`='{$data->column_id}'  ");
        if (empty($ret)) {
            return [StatusCodes::FORBIDDEN, "Could not find board", null];
        }

        $ret = Flight::sql("DELETE `column`, `event` FROM `column` LEFT JOIN `event` ON `event`.`column_id` = `column`.`id` WHERE `column`.`id`={$data->column_id} ");
        if ($ret === false) {
            return [StatusCodes::SERVICE_ERROR, "Fail to delete by database error", Flight::db()->error];
        } else {
            return [StatusCodes::OK, null, null];
        }
    }



    public static function Columns($method, $column_id)
    {
        $funct = null;
        $args = array();

        switch ($method) {
            case "GET":
                $func = "gets";
                break;
            case "POST":
                $func = "creates";
                $args = ["board_id", "title"];
                break;
            case "PATCH":
                $func = "updates";
                $args = ["column_id"];
                break;
            case "DELETE":
                $func = "deletes";
                $args = ["column_id"];
                break;
        }

        if($func == null){
            Flight::ret(StatusCodes::NOT_IMPLEMENTED, "Not Implemented");
            return;
        }

        $miss = [];
        $data = Flight::request()->data;
        $data->column_id = $column_id;
        $data->user_id = Kanban::$current->id;
        
        foreach ($args as $key => $param) {
            if (!isset($data->$param)) {
                array_push($miss, $param);
            }
        }

        // Escape
        foreach($data as $key => $each){
            $data->$key = addslashes($each);
        }

        if (!empty($miss)) {
            Flight::ret(StatusCodes::NOT_ACCEPTABLE, "Missing Params", array("missing" => $miss));
            return;
        }

        list($code, $message, $array) = self::$func($data);
        Flight::ret($code, $message, $array);
        
    }

}