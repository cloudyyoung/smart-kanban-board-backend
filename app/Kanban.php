<?php

namespace App;

use Flight;
use Throwable;


class Kanban{

    public static $current = null;
    public static $nodes = null; // for current user
    public static $dictionary = null;
    public static $typeDictionary = Array(
        "board" => Array(
            "column" => [],
            "event" => [],
        ),
        "column" => Array(
            "board_id" => 0,
            "event" => [],
        ),
        "event" => Array(
            "board_id" => 0,
            "column_id" => 0,
        )
    );

    public static function fetch(){
        self::$nodes = [];
        self::$dictionary = Array( // dictionary of relationships
            "board" => [],
            "column" => [],
            "event" => [],
        );

        $user_id = self::$current->id;
        $ret = Flight::sql("SELECT * FROM `board` WHERE `user_id`='$user_id'  ", true);
        foreach($ret as $board){
            self::$nodes[$board->id] = new Boards($board->id, null, $board->title, $board->note, $board->user_id);
        }

        return true;
    }

    public static function print(){
        $arr['boards'] = [];
        foreach (self::$nodes as $board) {
            $arr['boards'][] = $board->print();
        }
        return $arr;
    }

    public static function find($type, $id){
        if(!array_key_exists($type, self::$dictionary)){
            return false;
        }else if(!array_key_exists($id, self::$dictionary[$type])){
            return false;
        }
        $node = self::$dictionary[$type][$id];
        
        if($type == "board"){
            $board_id = $id;
        }else if($type == "column"){
            $board_id = $node['board_id'];
            $column_id = $id;
        }else if($type == "event"){
            $board_id = $node['board_id'];
            $column_id = $node['column_id'];
            $event_id = $id;
        }

        $ret = null;
        if(isset($board_id)){
            $ret = self::getChild($board_id);
        }
        if(isset($column_id) && $ret !== false){
            $ret = $ret->getChild($column_id);
        }
        if(isset($event_id) && $ret !== false){
            $ret = $ret->getChild($event_id);
        }
        return $ret;
    }

    public static function getChild($id){
        if(array_key_exists($id, self::$nodes)){
            return self::$nodes[$id];
        }else{
            return false;
        }
    }



    public static function Kanban(){
        if(Kanban::$current == null){
            Flight::ret(StatusCodes::UNAUTHORIZED, "Unauthenticated Access");
            return;
        }
        $method = Flight::request()->method;
        if($method != "GET"){
            Flight::ret(StatusCodes::METHOD_NOT_ALLOWED, "Method Not Allowed");
            return;
        }
        Flight::ret(200, "OK", self::print());
    }

}