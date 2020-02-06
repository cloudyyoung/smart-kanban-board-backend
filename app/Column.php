<?php


namespace App;

use Flight;
use Throwable;

use \App\Event;

class Column extends Base{

    public static $uid = 0;

    public $id = 0;
    public $title = "";
    public $note = "";
    private $event = Array();

    function __construct($id, $title, $note){

        $this->id = $id;
        $this->title = $title;
        $this->note = $note;

        $ret = Flight::sql("SELECT * FROM `event` WHERE `column_id` ='$id'   ", true);
        foreach($ret as $event){
            $this->event[(string)$event->id] = new Event($event->id, $event->title, $event->note);
        }

    }
    
    public function event($event_id = null){
        return array_values($this->event);
    }

}