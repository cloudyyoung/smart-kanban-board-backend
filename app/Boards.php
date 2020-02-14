<?php


namespace App;

use Flight;
use Throwable;


class Boards extends Nodes{

    public static function Boards($method, $board_id){
        self::Nodes($method, $board_id);
    }

}
