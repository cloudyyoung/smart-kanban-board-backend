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

    public static function Kanban($user){ // give user

        if($user == null){
            Flight::ret(404, "Not Found");
            return;
        }

        self::fetch($user->id);

        $boards = [];
        foreach(self::$board as $board){
            $boards[] = $board->get();
        }

        $result = Array("board" => $boards);

        Flight::ret(200, "OK", $result);

    }

}