<?php

namespace App;

use Flight;
use Throwable;


class Kanban extends Nodes{

    public static function Kanban(){
        if(Users::$current == null){
            Flight::ret(StatusCodes::UNAUTHORIZED, "Unauthenticated Access");
            return;
        }
        $method = Flight::request()->method;
        if($method != "GET"){
            Flight::ret(StatusCodes::METHOD_NOT_ALLOWED, "Method Not Allowed");
            return;
        }
        $kanban = new Kanban(Users::$current->id);
        $kanban->get();
        Flight::ret(200, "OK", $kanban->print());
    }

}