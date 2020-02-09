<?php


namespace App;

use Flight;
use Throwable;

use App\Kanban;

class Events{

    public static $uid = 0;

    public $id = 0;
    public $board_id = 0;
    public $column_id = 0;
    public $title = "";
    public $note = "";

    function __construct($id, $title, $note, $board_id, $column_id){

        $this->id = (int)$id;
        $this->board_id = (int)$board_id;
        $this->column_id = (int)$column_id;
        $this->title = $title;
        $this->note = $note;

        Kanban::$dictionary['columns'][(string)$this->column_id]["events"][] = $this->id;
        Kanban::$dictionary['boards'][(string)$this->board_id]["events"][] = $this->id;

    }

    public function print(){
        return get_object_vars($this);
    }


}