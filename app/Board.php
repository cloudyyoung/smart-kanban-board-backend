<?php


namespace App;

use Flight;
use Throwable;

use \App\Column;

class Board{

    public static $uid = 0;

    public $id = 0;
    public $title = "";
    public $note = "";
    private $column = Array();

    function __construct($id, $title, $note){

        $this->id = $id;
        $this->title = $title;
        $this->note = $note;

        $ret = Flight::sql("SELECT * FROM `column` WHERE `board_id` ='$id'   ", true);
        foreach($ret as $column){
            // var_dump($column->title);
            $this->column[(string)$column->id] = new Column($column->id, $column->title, $column->note);
        }

    }


    public function get(){
        $arr = get_object_vars($this);
        $arr['column'] = [];
        foreach($this->column as $column){
            $arr['column'][] = $column->get();
        }
        return $arr;
    }

    public function getColumn($column_id){
        if(array_key_exists($column_id, $this->column)){
            return $this->column[$column_id];
        }else{
            return false;
        }
    }

}