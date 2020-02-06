<?php


namespace App;

use Flight;
use Throwable;

use \App\Column;

class Board extends Base{

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

    public function column(){
        $ret = array_values($this->column);
        $index = 0;
        foreach($this->column as $column){
            $ret[$index] = get_object_vars($ret[$index]);
            $ret[$index]['event'] = $column->event();
            $index ++;
        }
        return $ret;
    }


}