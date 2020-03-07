<?php

namespace App;


use Flight;
use Throwable;
use ReflectionClass;
use ReflectionProperty;

use App\Kanban;

class Nodes{

    public $id = 0;
    protected $parent_id = null;
    protected $grandparent_id = null;
    public $title = "";
    public $note = "";
    private $nodes = [];
    private $type = ""; // whether board, column or event
    private $class = "";

    function __construct($id, $parent_id, $title = "", $note = ""){
        $this->id = (int)$id;
        $this->class = get_class($this);
        $this->type = rtrim(strtolower(explode('\\',$this->class)[1]), "s");
        $this->parent_id = isset($parent_id) ? (int)$parent_id : null;
        $this->set($title, $note);
        $this->setDictionary();
        $this->fetch();
    }

    public function set($title, $note){
        $this->title = $title;
        $this->note = $note;
    }

    private function setDictionary(){
        // Add into category
        Kanban::$dictionary[$this->type][$this->id] = Kanban::$typeDictionary[$this->type];

        // Add into parent
        $parent = $this->getParentType();
        if($parent !== false && isset($this->parent_id)){
            Kanban::$dictionary[$this->type][$this->id][$parent."_id"] = $this->parent_id;
            Kanban::$dictionary[$parent][$this->parent_id][$this->type][] = $this->id;
        }

        $grandparent = $this->getParentType(2);
        if($grandparent !== false && isset($this->grandparent_id)){
            Kanban::$dictionary[$this->type][$this->id][$grandparent."_id"] = $this->grandparent_id;
            Kanban::$dictionary[$grandparent][$this->grandparent_id][$this->type][] = $this->id;
        }
    }

    private function getParentType($level = 1){
        return Kanban::getParentType($this->type, $level);
    }

    private function getChildrenType($level = 1){
        return Kanban::getChildrenType($this->type, $level);
    }

    public function fetch($childOnly = true){
        if(!$childOnly){
            $ret = Flight::sql("SELECT `title`, `note` FROM `{$this->type}` WHERE `id` ='{$this->id}'   ", true);
            $this->set($ret->title, $ret->note);
        }

        $this->nodes = [];
        $tableName = $this->getChildrenType();
        $nodesClass = "App\\" . ucwords($this->getChildrenType()) . "s";

        if($tableName === false){
            return;
        }

        $ret = Flight::sql("SELECT * FROM `$tableName` WHERE `{$this->type}_id` ='{$this->id}'   ", true);
        foreach ($ret as $node) {
            $this->nodes[$node->id] = new $nodesClass($node->id, $this->id, $node->title, $node->note, $this->parent_id);
        }
    }

    public function getChild($id = null){
        if(array_key_exists($id, $this->nodes)){
            return $this->nodes[$id];
        }else if(!isset($id)){
            return $this->nodes;
        }else{
            return false;
        }
    }

    public function print($node_id = null){
        if(isset($node_id)){
            if (array_key_exists($node_id, $this->nodes)) {
                return $this->nodes[$node_id]->print();
            } else {
                return false;
            }
        }
        
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $arr = [];
        foreach($properties as $property){
            $key = $property->name;
            $arr[$key] = $this->$key;
        }

        $nodesType = $this->getChildrenType();
        if($nodesType !== false){
            $nodesType .= "s";
            $arr[$nodesType] = [];
            foreach ($this->nodes as $node) {
                $arr[$nodesType][] = $node->print();
            }
        }
        
        return $arr;
    }

    private static function Get($data){
        $node = Kanban::find($data->type, $data->node_id);
        if($node === false){
            return [StatusCodes::NOT_FOUND, "Node Not Found", null];
        }
        return [StatusCodes::OK, "OK", $node->print()];
    }

    private static function Create($data){
        $parent_type = Kanban::getParentType($data->type);
        $parent_node = Kanban::find(Kanban::getParentType($data->type), $data->parent_id);
        if($parent_node === false && $parent_type != "user"){
            return [StatusCodes::NOT_FOUND, "Node Parent " . $parent_type . " Not Found", null];
        }

        $parent_name = Kanban::getParentType($data->type) . "_id";
        $parent_id = $data->$parent_name;
        $ret = Flight::sql("INSERT INTO `{$data->type}` (`{$parent_name}`, `title`, `note`) VALUES ({$parent_id}, '{$data->title}', '{$data->note}')  ");
        if ($ret === false && Flight::db()->error != "") {
            return [StatusCodes::SERVICE_ERROR, "Fail to create by database error", Flight::db()->error];
        }

        return [StatusCodes::OK, "OK", null];
    }

    private static function Update($data){
        $node = Kanban::find($data->type, $data->node_id);
        if($node === false){
            return [StatusCodes::NOT_FOUND, "Node " . $data->type . " Not Found", null];
        }

        $list = ["title", "note"];
        $vars = [];
        foreach($list as $each){
            if($data->$each != null && $data->$each != $node->each){
                $vars[] = "`title`='{$data->$each}'";
            }
        }

        if(empty($vars)){
            return [StatusCodes::NOT_MODIFIED, "Not Modified", null];
        }

        $sql = "UPDATE `{$data->type}` SET " . implode(", ", $vars) . " WHERE `id`={$data->node_id}   ";
        $ret = Flight::sql($sql);
        if ($ret === false && Flight::db()->error != "") {
            return [StatusCodes::SERVICE_ERROR, "Fail to update by database error", Flight::db()->error];
        }

        return [StatusCodes::OK, "OK", null];
    }

    private static function Delete($data){
        $node = Kanban::find($data->type, $data->node_id);
        if($node === false){
            return [StatusCodes::NOT_FOUND, "Node " . $data->type . " Not Found", null];
        }

        $child = Kanban::getChildrenType($data->type);
        $grandchild = Kanban::getChildrenType($data->type, 2);
        $children_id = [];
        $grandchildren_id = [];
        if($child !== false){
            $children_id = Kanban::$dictionary[$data->type][$data->node_id][$child];
        }
        if($grandchild !== false){
            $grandchildren_id = Kanban::$dictionary[$data->type][$data->node_id][$grandchild];
        }

        $sql = "DELETE FROM `board` WHERE `id`={$data->node_id}; ";
        foreach($children_id as $each_id){
            $sql .= "DELETE FROM `{$child}` WHERE `id`={$each_id}; ";
        }
        foreach($grandchildren_id as $each_id){
            $sql .= "DELETE FROM `{$grandchild}` WHERE `id`={$each_id}; ";
        }

        $ret = Flight::sql($sql, true);
        if ($ret === false && Flight::db()->error != "") {
            return [StatusCodes::SERVICE_ERROR, "Fail to delete by database error", Flight::db()->error];
        }

        return [StatusCodes::NO_CONTENT, null, null];
    }

    
    public static function Nodes($node_id, $type)
    {

        if(Kanban::$current == null){
            Flight::ret(StatusCodes::UNAUTHORIZED, "Unauthorized");
            return;
        }
    
        $type = rtrim($type, "s");
        if(!in_array($type, array_values(Kanban::$typeList)) && $type != "user"){
            Flight::ret(StatusCodes::NOT_IMPLEMENTED, "Not Implemented");
            return;
        }

        $func = null;
        $args = array();
        $method = Flight::request()->method;

        switch ($method) {
            case "GET":
                $func = "Get";
                $args = ["node_id"];
            break;
            case "POST":
                $func = "Create";
                $args = ["title", Kanban::getParentType($type) . "_id"];
            break;
            case "PATCH":
                $func = "Update";
                $args = ["node_id"];
            break;
            case "DELETE":
                $func = "Delete";
                $args = ["node_id"];
            break;
        }

        if($func == null){
            Flight::ret(StatusCodes::METHOD_NOT_ALLOWED, "Method Not Allowed");
            return;
        }

        $miss = [];
        $data = Flight::request()->data;
        $data->node_id = $node_id;
        $data->type = $type;
        $data->user_id = Kanban::$current->id;
        
        foreach ($args as $key => $param) {
            if (!isset($data->$param)) {
                array_push($miss, $param);
            }
        }

        // Escape
        foreach($data as $key => $each){
            $data->$key = addslashes($each);
        }

        if (!empty($miss)) {
            Flight::ret(StatusCodes::RETRY_WITH, "Missing Param", array("missing" => $miss));
            return;
        }

        list($code, $message, $array) = self::$func($data);
        Flight::ret($code, $message, $array);
        
    }

}