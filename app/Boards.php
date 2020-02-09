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
    public $title = "";
    public $note = "";
    private $columns = array();

    function __construct($id, $title, $note)
    {

        $this->id = $id;
        $this->title = $title;
        $this->note = $note;

        $ret = Flight::sql("SELECT * FROM `column` WHERE `board_id` ='$id'   ", true);
        foreach ($ret as $column) {
            $this->columns[(string) $column->id] = new Columns($column->id, $column->title, $column->note);
        }
    }

    public function get()
    {
        $arr = get_object_vars($this);
        $arr['column'] = [];
        foreach ($this->columns as $column) {
            $arr['column'][] = $column->get();
        }
        return $arr;
    }

    public function getColumns($column_id)
    {
        if (array_key_exists($column_id, $this->columns)) {
            return $this->columns[$column_id];
        } else {
            return false;
        }
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
        }else if (empty($ret)) {
            return [StatusCodes::FORBIDDEN, "Could not find board", null];
        } else {
            return [StatusCodes::OK, "OK", $ret];
        }
    }

    private static function creates($data)
    {
        $ret = Flight::sql("INSERT INTO `board`(`user_id`, `title`, `note`) VALUES ({$data->user_id}, '{$data->title}', '{$data->note}')  ");
        if ($ret === false) {
            return [StatusCodes::SERVICE_ERROR, "Fail to create by database error", Flight::db()->error];
        } else {
            $ret = Flight::sql("SELECT * FROM `board` WHERE `id`=LAST_INSERT_ID();  ");
            return [StatusCodes::OK, "OK", $ret];
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
            $ret = Flight::sql("SELECT * FROM `board` WHERE `id`={$data->board_id}  ");
            return [StatusCodes::OK, "OK", $ret];
        }
    }

    private static function deletes($data){
        $ret = Flight::sql("SELECT * FROM `board` WHERE `user_id`='{$data->user_id}' AND `id`='{$data->board_id}'  ");
        if (empty($ret)) {
            return [StatusCodes::FORBIDDEN, "Could not find board", null];
        }

        $ret = Flight::sql("DELETE `board`, `column`, `event` FROM `board` LEFT JOIN `column` ON `column`.`board_id` = `board`.`id` LEFT JOIN `event` ON `event`.`board_id` = `board`.`id` WHERE `board`.`id`={$data->board_id} ");
        if ($ret === false) {
            return [StatusCodes::SERVICE_ERROR, "Fail to delete by database error", Flight::db()->error];
        } else {
            return [StatusCodes::OK, null, null];
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
