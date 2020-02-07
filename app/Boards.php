<?php


namespace App;

use Flight;
use Throwable;

use App\Columns;
use App\Kanban;

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

    

    public static function Boards($method, $board_id){
        $user = Kanban::$current;
        $data = Flight::request()->data;
        
        switch($method){
            case "GET":
                $ret = self::gets($user->id, $board_id);
                if($ret === false){
                    Flight::ret(404, "Not Found");
                }else{
                    Flight::ret(200, "OK", $ret);
                }
            break;
            case "POST":
                if(!isset($data->title)){
                    Flight::ret(406, "Lack of Param");
                    return;
                }
                $title = addslashes($data->title);
                $note = addslashes($data->note);
                $ret = self::creates($user->id, $title, $note);
                if($ret === false){
                    Flight::ret(540, "Error Occured");
                }else{
                    Flight::ret(200, "OK", $ret);
                }
            break;
            case "PATCH":
                
            break;
        }
    }

}