<?php

namespace App;


class Boards extends Nodes{

    public static function Boards($method, $board_id){
        self::Nodes($method, $board_id, "board");
    }

}
