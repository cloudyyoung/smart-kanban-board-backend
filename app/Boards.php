<?php


namespace App;

use Flight;
use Throwable;

use \App\Columns;

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

    public static function GetBoards($user_id, $board_id = null){
        if($board_id == null){
            return Flight::sql("SELECT * FROM `board` WHERE `user_id`='$user_id'  ", true);
        }else{
            return Flight::sql("SELECT * FROM `board` WHERE `user_id`='$user_id' AND `id`='$board_id'  ", true);
        }
    }

}