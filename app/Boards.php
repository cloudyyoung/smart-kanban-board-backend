<?php


namespace App;

use Flight;
use Throwable;

use App\Columns;
use App\Kanban;
use App\StatusCodes;

class Boards
{

    public static $uid = 0;

    public $id = 0;
    public $user_id = 0;
    public $title = "";
    public $note = "";
    private $columns = array();

    function __construct($id, $title, $note, $user_id)
    {

        $this->id = (int)$id;
        $this->user_id = (int)$user_id;
        $this->title = $title;
        $this->note = $note;

        Kanban::$dictionary['boards'][(string)$this->id] = Array(
            "user_id" => $this->user_id,
            "columns" => Array(),
            "events" => Array(),
        );

        $this->fetch();
    }

    public function set($title, $note){
        $this->title = $title;
        $this->note = $note;
        Kanban::save();
    }

    public function fetch(){
        $this->columns = [];
        $ret = Flight::sql("SELECT * FROM `column` WHERE `board_id` ='{$this->id}'   ", true);
        foreach ($ret as $column) {
            $this->columns[(string)$column->id] = new Columns($column->id, $column->title, $column->note, $this->id);
        }
    }

    public function print($column_id = null)
    {   
        if(isset($column_id)){
            if (array_key_exists($column_id, $this->columns)) {
                return $this->columns[$column_id]->print();
            } else {
                return false;
            }
        }
        $arr = get_object_vars($this);
        $arr['columns'] = [];
        foreach ($this->columns as $column) {
            $arr['columns'][] = $column->print();
        }
        return $arr;
    }



    private static function gets($data)
    {
        if ($data->board_id != null) {
            $ret = Flight::sql("SELECT * FROM `board` WHERE `user_id`='{$data->user_id}' AND `id`='{$data->board_id}'  ");
        }else{
            $ret = Flight::sql("SELECT * FROM `board` WHERE `user_id`='{$data->user_id}' ", true);
        }
        
        if ($ret === false) {
            return [StatusCodes::SERVICE_ERROR, "Fail to get by database error", Flight::db()->error];
        }else if (empty($ret) && $data->board_id != null) {
            return [StatusCodes::FORBIDDEN, "Could not find board", null];
        }else if (empty($ret) && $data->board_id == null) {
            return [StatusCodes::OK, "OK", Kanban::print()];
        }else{
            if($data->board_id != null){
                $node = Kanban::find(false, $ret->id);
                $node->set($ret->title, $ret->note);
                if(isset(Flight::request()->query->force)){
                    $node->fetch();
                }
                $node = $node->print();
            }else{
                foreach($ret as $each){
                    $node = Kanban::find(false, $each->id);
                    $node->set($each->title, $each->note);
                    if(isset(Flight::request()->query->force)){
                        $node->fetch();
                    }
                }
                $node = Kanban::print();
            }
            
            return [StatusCodes::OK, "OK", $node];
        }
    }

    private static function creates($data)
    {
        $ret = Flight::sql("INSERT INTO `board`(`user_id`, `title`, `note`) VALUES ({$data->user_id}, '{$data->title}', '{$data->note}')  ");
        if ($ret === false) {
            return [StatusCodes::SERVICE_ERROR, "Fail to create by database error", Flight::db()->error];
        } else {
            $ret = Flight::sql("SELECT * FROM `board` WHERE `id`=LAST_INSERT_ID();  ");
            $node = new Boards($ret->id, $ret->user_id, $ret->title, $ret->note);
            Kanban::$boards[] = $node;
            $node->set($ret->title, $ret->note);
            return [StatusCodes::CREATED, "OK", $node->print()];
        }
    }

    private static function updates($data)
    {
        $ret = Flight::sql("SELECT * FROM `board` WHERE `id`={$data->board_id} AND `user_id`={$data->user_id}");
        if(empty($ret)){
            return [StatusCodes::FORBIDDEN, "Could not find board", null];
        }

        $vars = [];
        if ($data->title != null) {
            $vars[] =  "`title`='$data->title'";
        }
        if ($data->note != null) {
            $vars[] =  "`note`='$data->note'";
        }

        $ret = Flight::sql("UPDATE `board` SET " . implode(", ", $vars) . " WHERE `id`={$data->board_id} AND `user_id`={$data->user_id}   ");
        if ($ret === false) {
            return [StatusCodes::SERVICE_ERROR, "Fail to update by database error", Flight::db()->error];
        } else {
            $node = Kanban::find(false, $data->board_id);
            $node->set($ret->title, $ret->note);
            return [StatusCodes::OK, "OK", $node];
        }
    }

    private static function deletes($data){
        $ret = Flight::sql("SELECT * FROM `board` WHERE `user_id`='{$data->user_id}' AND `id`='{$data->board_id}'  ");
        if (empty($ret)) {
            return [StatusCodes::FORBIDDEN, "Could not find board", null];
        }
        $events_id = Kanban::$dictionary['boards'][$data->board_id]['events'];
        $columns_id = Kanban::$dictionary['boards'][$data->board_id]['columns'];

        $sql = "DELETE FROM `board` WHERE `id`={$data->board_id}; ";

        foreach($columns_id as $each_column_id){
            $sql .= "DELETE FROM `column` WHERE `id`={$each_column_id}; ";
        }

        foreach($events_id as $each_event_id){
            $sql .= "DELETE FROM `event` WHERE `id`={$each_event_id}; ";
        }

        $ret = Flight::sql($sql, true);
        if ($ret === false) {
            return [StatusCodes::SERVICE_ERROR, "Fail to delete by database error", Flight::db()->error];
        } else {
            $ret = Kanban::fetch();
            if(!$ret){
                return [StatusCodes::SERVICE_ERROR, "database fetch fail", Flight::db()->error];
            }
            return [StatusCodes::NO_CONTENT, null, null];
        }
    }



    public static function Boards($method, $board_id)
    {
        $funct = null;
        $args = array();

        switch ($method) {
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

        if($func == null){
            Flight::ret(StatusCodes::NOT_IMPLEMENTED, "Not Implemented");
            return;
        }

        $miss = [];
        $data = Flight::request()->data;
        $data->board_id = $board_id;
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
