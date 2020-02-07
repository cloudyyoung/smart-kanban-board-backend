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

}