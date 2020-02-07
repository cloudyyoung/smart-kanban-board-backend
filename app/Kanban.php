<?php


namespace App;

use Flight;
use Throwable;

use App\Board;

class Kanban{

    public static $current = null;
    private static $board = []; // for current user

    private static function fetch($id){ // give user id
        self::$board = Array();
        $ret = Flight::sql("SELECT * FROM `board` WHERE `user_id`='$id'  ", true);
        foreach($ret as $board){
            self::$board[(string)$board->id] = new Board($board->id, $board->title, $board->note);
        }
        return true;
    }

    private static function getBoard($board_id = null){
        if($board_id == null){
            return array_values(self::$board);
        }else if(array_key_exists($board_id, self::$board) && $board_id != null){
            return self::$board[$board_id];
        }else{
            return false;
        }
    }

    public static function Kanban(){
        self::fetch(self::$current->id);

        $boards = [];
        foreach(self::$board as $board){
            $boards[] = $board->get();
        }
        $result = Array("board" => $boards);

        Flight::ret(200, "OK", $result);
    }


    public static function Boards($method, $board_id){
        self::fetch(self::$current->id);

        switch($method){
            case "GET":
                $ret = self::getBoard($board_id);
                if($ret === false){
                    Flight::ret(404, "Not Found");
                }else{
                    Flight::ret(200, "OK", $ret);
                }
            break;
        }
    }
}