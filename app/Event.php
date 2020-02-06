<?php


namespace App;

use Flight;
use Throwable;


class Event extends Base{

    public static $uid = 0;

    public $id = 0;
    public $title = "";
    public $note = "";

    function __construct($id, $title, $note){

        $this->id = $id;
        $this->title = $title;
        $this->note = $note;

    }


}