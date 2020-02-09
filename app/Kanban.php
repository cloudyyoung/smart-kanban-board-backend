<?php


namespace App;

use Flight;
use Throwable;
use App;


class Kanban{

    public static $current = null;
    public static $boards = null; // for current user
    public static $dictionary = Array( // dictionary of relationships
        "boards" => [],
        "columns" => [],
        "events" => [],
    );

    private static function fetch(){
        self::$boards = [];
        self::$dictionary = [];

        $id = self::$current->id;
        $ret = Flight::sql("SELECT * FROM `board` WHERE `user_id`='$id'  ", true);
        foreach($ret as $board){
            self::$boards[(string)$board->id] = new Boards($board->id, $board->title, $board->note, $board->user_id);
        }

        $_SESSION['kanban'] = serialize(self::$boards);
        $_SESSION['dictionary'] = serialize(self::$dictionary);
    }

    public static function get($board_id = null){
        if(isset($board_id)){
            if (array_key_exists($board_id, self::$boards)) {
                return self::$boards[$board_id]->get();
            } else {
                return false;
            }
        }
        
        $arr['boards'] = [];
        foreach (self::$boards as $board) {
            $arr['boards'][] = $board->get();
        }
        return $arr;
    }

    public static function find($cascade = true, $board_id = null, $column_id = null, $event_id = null){
        if(!isset(self::$boards)){
            self::fetch(self::$current->id);
        }

        $ret = false;
        if(isset($board_id)){
            $ret = self::get();
            if(isset($column_id) && $ret !== false){
                $ret = $ret->get($column_id);
                if(isset($event_id) && $ret !== false){
                    $ret = $ret->get($event_id);
                }
            }
            return $ret->get();
        }
    }

    public static function Kanban(){
        if(!isset(self::$boards) || isset(Flight::request()->query->force)){
            self::fetch();
        }
        $result = self::get();
        echo json_encode(self::$dictionary);
        // Flight::ret(200, "OK", $result);
    }

}