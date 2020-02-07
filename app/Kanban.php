<?php


namespace App;

use Flight;
use Throwable;
use App;


class Kanban{

    public static $current = null;
    private static $boards = []; // for current user

    private static function fetch($id){ // give user id
        self::$boards = Array();
        $ret = Flight::sql("SELECT * FROM `board` WHERE `user_id`='$id'  ", true);
        foreach($ret as $board){
            self::$boards[(string)$board->id] = new Boards($board->id, $board->title, $board->note);
        }
        return true;
    }

    public static function Kanban(){
        self::fetch(self::$current->id);

        $boards = [];
        foreach(self::$boards as $board){
            $boards[] = $board->get();
        }
        $result = Array("board" => $boards);

        Flight::ret(200, "OK", $result);
    }


    public static function Boards($method, $board_id){
        switch($method){
            case "GET":
                $ret = Boards::getBoards(self::$current->id, $board_id);
                if($ret === false){
                    Flight::ret(404, "Not Found");
                }else{
                    Flight::ret(200, "OK", $ret);
                }
            break;
            case "POST":
                
            break;
        }
    }
}