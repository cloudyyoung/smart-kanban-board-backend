<?php


namespace App;

use Flight;
use Throwable;

use App\Kanban;
use App\Events;

class Columns{

    public static $uid = 0;

    public $id = 0;
    public $board_id = 0;
    public $title = "";
    public $note = "";
    private $events = Array();

    function __construct($id, $title, $note, $board_id){

        $this->id = (int)$id;
        $this->board_id = (int)$board_id;
        $this->title = $title;
        $this->note = $note;

        Kanban::$dictionary['boards'][(string)$this->board_id]["columns"][] = $this->id;
        Kanban::$dictionary['columns'][(string)$this->id] = Array(
            "board_id" => $this->board_id,
            "events" => [],
        );

        $this->fetch();
    }

    public function fetch(){
        $this->events = [];
        $ret = Flight::sql("SELECT * FROM `event` WHERE `column_id` ='{$this->id}'   ", true);
        foreach($ret as $event){
            $this->events[(string)$event->id] = new Events($event->id, $event->title, $event->note, $this->board_id, $this->id);
        }
    }
    
    public function print($event_id = null){
        if(isset($event_id)){
            if(array_key_exists($event_id, $this->events)){
                return $this->column[$event_id]->print();
            }else{
                return false;
            }
        }
        $arr = get_object_vars($this);
        $arr['events'] = [];
        foreach($this->events as $event){
            $arr['events'][] = $event->print();
        }
        return $arr;
    }


}