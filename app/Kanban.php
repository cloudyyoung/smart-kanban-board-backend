<?php


namespace App;

use Flight;
use Throwable;
use App;


class Kanban{

    public static $current = null;
    public static $boards = null; // for current user
    public static $dictionary = null;

    public static function fetch(){
        self::$boards = [];
        self::$dictionary = Array( // dictionary of relationships
            "boards" => [],
            "columns" => [],
            "events" => [],
        );

        $id = self::$current->id;
        $ret = Flight::sql("SELECT * FROM `board` WHERE `user_id`='$id'  ", true);
        foreach($ret as $board){
            self::$boards[(string)$board->id] = new Boards($board->id, $board->title, $board->note, $board->user_id);
        }

        self::save();
        return true;
    }

    public static function save(){
        $_SESSION['kanban'] = serialize(self::$boards);
        $_SESSION['dictionary'] = serialize(self::$dictionary);
    }

    public static function print($board_id = null){
        if(isset($board_id)){
            if (array_key_exists($board_id, self::$boards)) {
                return self::$boards[$board_id]->print();
            } else {
                return false;
            }
        }
        
        $arr['boards'] = [];
        foreach (self::$boards as $board) {
            $arr['boards'][] = $board->print();
        }
        return $arr;
    }

    public static function find($cascade = true, $board_id = null, $column_id = null, $event_id = null){
        if(!isset(self::$boards)){
            self::fetch(self::$current->id);
        }

        if($cascade){ // find node by given levels
            $ret = false;
            if(isset($board_id) && array_key_exists($board_id, self::$boards)){
                $ret = self::$boards[$board_id];
                if(isset($column_id) && array_key_exists($column_id, self::$boards[$board_id]->columns)){
                    $ret = self::$boards[$board_id]->columns[$column_id];
                    if(isset($event_id) && array_key_exists($column_id, self::$boards[$board_id]->columns[$column_id]->events)){
                        $ret = self::$boards[$board_id]->columns[$column_id]->events[$event_id];
                    }
                }
            }
            return $ret;
        }else{ // find node by given id
            $ret = false;
            if(isset($board_id) && array_key_exists($board_id, self::$dictionary['boards'])){
                $node = self::$dictionary['boards'][$board_id];
                if(isset($node)){
                    $ret = self::$boards[$board_id];
                }
            }else if(isset($column_id) && array_key_exists($column_id, self::$boards[$node->board_id]->column)){
                $node = self::$dictionary['columns'][$column_id];
                if(isset($node)){
                    $ret = self::$boards[$node->board_id]->column[$column_id];
                }
            }else if(isset($event_id) && array_key_exists($event_id, self::$boards[$node->board_id]->column[$node->column_id]->events)){
                $node = self::$dictionary['events'][$event_id];
                if(isset($node)){
                    $ret = self::$boards[$node->board_id]->column[$node->column_id]->events[$event_id];
                }
            }
            return $ret;
        }
    }

    public static function Kanban(){
        if(!isset(self::$boards) || isset(Flight::request()->query->force)){
            if(!self::fetch()){
                Flight::ret(540, "Service Error", Flight::db()->error);
                return;
            }
        }
        $result = self::print();
        Flight::ret(200, "OK", $result);
    }

}