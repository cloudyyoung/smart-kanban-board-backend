<?php

namespace App;


use Flight;
use Throwable;
use ReflectionClass;
use ReflectionProperty;

use App\Kanban;
use App\Users;

abstract class Nodes
{

    public $id;
    public $title;
    public $note;
    public $parent_id;
    private $nodes = [];
    private $type = "";
    private $class = "";
    private $authorized = false;
    private $existing = false;
    private static $typeList = array(
        0 => "user",
        1 => "board",
        2 => "column",
        3 => "event",
    );

    function __construct($id)
    {
        $this->id = (int) $id;
        $this->class = get_class($this);
        $this->type = rtrim(strtolower(explode('\\', $this->class)[1]), "s");
        if ($this->type == "kanban") {
            $this->type = "user";
        }
    }

    public function build($data)
    {
        foreach ($data as $key => $value) {
            if ($key == $this->getParentType() . '_id') {
                $key = "parent_id";
            } else if (($key == "due_date" || $key == "lastGneratedDate") && $value != null && strtotime($value) !== false) {
                $value = strtotime($value);
            }

            if (is_numeric($value)) {
                $this->$key = (int) $value;
            } else if (is_bool($value)) {
                $this->$key = (bool) $value;
            } else {
                $this->$key = $value;
            }
        }
    }

    public function get()
    {
        if ($this->id < 100) return -999;

        $this->checkAuthority();
        if (!$this->authorized) {
            return -999;
        }

        $ret = Flight::sql("SELECT * FROM `{$this->type}` WHERE `id` ='{$this->id}'   ");
        $this->build($ret);

        $this->nodes = [];
        $children = $this->getChildrenType();

        if ($children !== false) {
            $nodesClass = $this->typeClass($this->getChildrenType());
            $ret = Flight::sql("SELECT `id` FROM `$children` WHERE `{$this->type}_id` ='{$this->id}'   ", true);
            if ($ret !== false && !empty($ret)) {
                foreach ($ret as $node) {
                    $node = new $nodesClass($node->id);
                    $node->get();
                    $this->nodes[$node->id] = $node;
                }
            }
        }

        foreach ($this->nodes as $key => $value) {
            if (!$value->existing || !$value->authorized) {
                unset($this->nodes->$key);
            }
        }

        return true;
    }

    public function create()
    {
        if ($this->id == 0) return -999;

        $this->checkAuthority();
        if (!$this->authorized) {
            return -999;
        }

        $parent_type = self::getParentTypeStatic($this->type);

        if ($parent_type != "user") {
            $parent_type_class = self::typeClass($parent_type);
            $parent_node = new $parent_type_class($this->parent_id);
            $parent_node->get();
            if ($parent_node->authorized == false) {
                return -1;
            }
        } else {
            if (Users::$current == null) {
                return -1;
            }
        }

        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $keys = [];
        $values = [];
        foreach ($properties as $property) {
            $key = $property->name;
            $value = $this->$key;

            if ($key == "parent_id") {
                $key = $this->getParentType() . "_id";
            } else if ($key == "id") {
                continue;
            } else if (($key == "due_date" || $key == "lastGneratedDate") && !empty($value)) {
                $value = date("Y-m-d H:i:s", $value);
            }

            if (empty($value)) {
            } else {
                $keys[] = $key;
                $values[] = "'{$value}'";
            }
        }

        $sql = "INSERT INTO `{$this->type}` (" . implode(", ", $keys) . ") VALUES ( " . implode(", ", $values) . ")  ";
        $ret = Flight::sql($sql);
        if ($ret === false && Flight::db()->error != "") {
            return -2;
        } else {
            $this->id = Flight::db()->insert_id;
            $this->get();
        }
    }

    public function update()
    {
        if ($this->id < 100) return -999;

        $this->checkAuthority();
        if (!$this->authorized) {
            return -999;
        }

        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $values = [];
        foreach ($properties as $property) {
            $key = $property->name;
            $key_alias = $key;
            $value = $this->$key;

            if ($key == "parent_id") {
                $key_alias = $this->getParentType() . "_id";
            } else if ($key == "id") {
                continue;
            } else if (($key == "due_date" || $key == "lastGneratedDate") && !empty($value)) {
                $value = date("Y-m-d H:i:s", $value);
            }

            if (empty($value)) {
                $values[] = "`$key_alias` = null";
            } else {
                $values[] = "`$key_alias` = '{$value}'";
            }
        }
        $sql = "UPDATE `{$this->type}` SET " . implode(", ", $values) . " WHERE `id`='{$this->id}' ";
        $ret = Flight::sql($sql);
        if ($ret === false && Flight::db()->error != "") {
            return -2;
        } else {
            $this->get();
        }
    }

    public function delete()
    {
        if ($this->id < 100) return -999;

        $this->checkAuthority();
        if (!$this->authorized) {
            return -999;
        }

        // DELETE `board`,`column`,`event` from `board` LEFT JOIN `column` ON `column`.`board_id`=`board`.`id` LEFT JOIN `event` ON `event`.`column_id`=`column`.`id` WHERE `board`.`id`=38
        $child = $this->type;
        $grandchild = $this->type;

        $tables = ["`$grandchild`"];
        $sql = "";
        while ($this->getChildrenType($grandchild) !== false) {
            $child = $grandchild;
            $grandchild = $this->getChildrenType($grandchild);
            $tables[] = "`$grandchild`";
            $sql .= "LEFT JOIN `$grandchild` ON `$grandchild`.`{$child}_id` = `$child`.`id` ";
        }
        $sql = "DELETE " . implode(", ", $tables) . " FROM `{$this->type}` " . $sql . " WHERE `{$this->type}`.`id` = {$this->id} ";
        $ret = Flight::sql($sql);
        if ($ret === false && Flight::db()->error != "") {
            return -2;
        } else {
            $this->get();
        }
    }

    private function checkAuthority()
    {
        $parent = $this->type;
        $grandparent = $this->type;
        $sql = "SELECT `user`.`id` FROM `$parent` ";
        while ($this->getParentType($grandparent) !== false) {
            $parent = $grandparent;
            $grandparent = $this->getParentType($grandparent);
            $sql .= "INNER JOIN `$grandparent` ON `$grandparent`.`id` = `$parent`.`{$grandparent}_id` ";
        }
        $sql .= "WHERE `$this->type`.`id` = {$this->id} ";
        // SELECT * FROM `event` INNER JOIN `column` ON `column`.`id`=`event`.`column_id` INNER JOIN `board` ON `board`.`id` = `column`.`board_id` INNER JOIN `user` ON `user`.`id` = `board`.`user_id` WHERE `event`.`id`=1 AND `user`.`id`=1
        $ret = Flight::sql($sql);
        if ($ret !== false && !empty($ret) && $ret->id == Users::$current->id) {
            $this->authorized = true;
            $this->existing = true;
        } else if ($ret !== false && !empty($ret)) {
            $this->authorized = false;
            $this->existing = true;
        } else {
            $this->authorized = false;
            $this->existing = false;
        }
    }

    private function getParentType($type = null, $level = 1)
    {
        if ($type == null) {
            $type = $this->type;
        }
        return self::getParentTypeStatic($type, $level);
    }

    private function getChildrenType($type = null, $level = 1)
    {
        if ($type == null) {
            $type = $this->type;
        }
        return self::getChildrenTypeStatic($type, $level);
    }

    private static function getParentTypeStatic($type, $level = 1)
    {
        $value = array_flip(self::$typeList)[$type];
        return (array_key_exists($value - $level, self::$typeList)) ? self::$typeList[$value - $level] : false;
    }

    private static function getChildrenTypeStatic($type, $level = 1)
    {
        $value = array_flip(self::$typeList)[$type];
        return (array_key_exists($value + $level, self::$typeList)) ? self::$typeList[$value + $level] : false;
    }


    private static function typeClass($type)
    {
        return "App\\" . self::typeProper(self::typePlural($type));
    }

    private static function typePlural($type)
    {
        return strlen($type) <= 0 ? $type : rtrim($type, "s") . "s";
    }

    private static function typeSingular($type)
    {
        return strlen($type) > 0 ? rtrim($type, "s") : $type;
    }

    private static function typeProper($type)
    {
        return ucwords($type);
    }

    private static function typeLower($type)
    {
        return strtolower($type);
    }

    private static function typeUpper($type)
    {
        return strtoupper($type);
    }

    private function getChild($id = null)
    {
        if (array_key_exists($id, $this->nodes)) {
            return $this->nodes[$id];
        } else if (!isset($id)) {
            return $this->nodes;
        } else {
            return false;
        }
    }

    public function print()
    {
        if (!$this->existing || !$this->authorized) {
            return null;
        }

        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $arr = [];
        foreach ($properties as $property) {
            if (!$property->isStatic()) {
                $key = $property->name;
                $key_alias = $key;
                $value = $this->$key;

                if ($this->type == "user" && $key != "id") {
                    continue;
                }
                if ($key == "parent_id") {
                    $key_alias = $this->getParentType() . "_id";
                }

                $arr[$key_alias] = $value;
            }
        }
        if ($this->type == "user") {
            $arr['type'] = self::typeProper("kanban");
        } else {
            $arr['type'] = self::typeProper($this->type);
        }

        $nodesType = $this->getChildrenType();
        if ($nodesType !== false) {
            $nodesType = self::typePlural($nodesType);
            $arr[$nodesType] = [];
            foreach ($this->nodes as $node) {
                if ($node->print() != null) {
                    $arr[$nodesType][] = $node->print();
                }
            }
        }

        return $arr;
    }

    public static function Gets($data)
    {
        $node = self::MakeInstance($data->node_id, $data->type);
        $ret = $node->get();
        $typeUpper = self::typeProper($data->type);
        if ($ret === -1) {
            Flight::ret(StatusCodes::NOT_FOUND, "$typeUpper Not Found", null);
            return;
        } else if ($ret === -999) {
            Flight::ret(StatusCodes::NOT_FOUND, "$typeUpper Not Found", null);
            return;
        }
        Flight::ret(StatusCodes::OK, "OK", $node->print());
    }

    public static function Creates($data)
    {
        $parent_type = self::getParentTypeStatic($data->type);
        $parent_typeUpper = self::typeProper($parent_type);
        $typeClass = self::typeClass($data->type);

        $node = self::MakeInstance($data->node_id, $data->type);
        $node->build($data);
        $ret = $node->create();
        if ($ret === -1) {
            Flight::ret(StatusCodes::NOT_FOUND, $parent_typeUpper . " Not Found", null);
        } else if ($ret === -2) {
            Flight::ret(StatusCodes::SERVICE_ERROR, "Service Error", Flight::db()->error);
        } else if ($ret === -999) {
            Flight::ret(StatusCodes::NOT_FOUND, "$parent_typeUpper Not Found", null);
            return;
        } else {
            Flight::ret(StatusCodes::CREATED, "Created", $node->print());
        }
    }

    public static function Updates($data)
    {
        $node = self::MakeInstance($data->node_id, $data->type);
        $ret = $node->get();
        $typeUpper = self::typeProper($node->type);
        if ($ret === -1) {
            Flight::ret(StatusCodes::NOT_FOUND, "$typeUpper Not Found", null);
            return;
        }

        $node->build($data);
        $ret = $node->update();
        if ($ret === -2) {
            Flight::ret(StatusCodes::SERVICE_ERROR, "Fail to update by database error", Flight::db()->error);
            return;
        } else if ($ret === -999) {
            Flight::ret(StatusCodes::NOT_FOUND, "$typeUpper Not Found", null);
            return;
        }
        Flight::ret(StatusCodes::ACCEPTED, "Accepted", $node->print());
    }

    public static function Deletes($data)
    {
        $node = self::MakeInstance($data->node_id, $data->type);
        $ret = $node->get();
        $typeUpper = self::typeProper($node->type);
        if ($ret === -1) {
            Flight::ret(StatusCodes::NOT_FOUND, "$typeUpper Not Found", null);
            return;
        } else if ($ret === -999) {
            Flight::ret(StatusCodes::NOT_FOUND, "$typeUpper Not Found", null);
            return;
        }

        $ret = $node->delete();
        if ($ret == -2) {
            Flight::ret(StatusCodes::SERVICE_ERROR, "Fail to update by database error", Flight::db()->error);
            return;
        }
        Flight::ret(StatusCodes::NO_CONTENT, "No Content");
    }


    private static function MakeInstance($id, $type)
    {
        $type = self::typeClass($type);
        $node = new $type($id);
        return $node;
    }

    public static function Nodes($node_id, $type)
    {

        if (Users::$current == null) {
            Flight::ret(StatusCodes::UNAUTHORIZED, "Unauthorized");
            return;
        }

        $type = rtrim($type, "s");
        if (!in_array($type, array_values(self::$typeList)) && $type != "user") {
            Flight::ret(StatusCodes::NOT_IMPLEMENTED, "Not Implemented");
            return;
        }

        $func = null;
        $args = array();
        $method = Flight::request()->method;

        switch ($method) {
            case "GET":
                $func = "Gets";
                $args = ["node_id"];
                break;
            case "POST":
                $func = "Creates";
                $args = ["title", self::getParentTypeStatic($type) . "_id"];
                break;
            case "PUT":
            case "PATCH":
                $func = "Updates";
                $args = ["node_id"];
                break;
            case "DELETE":
                $func = "Deletes";
                $args = ["node_id"];
                break;
        }

        if ($func == null) {
            Flight::ret(StatusCodes::METHOD_NOT_ALLOWED, "Method Not Allowed");
            return;
        }

        $miss = [];
        $data = Flight::request()->data;
        $data->node_id = $node_id;
        $data->type = $type;
        $data->user_id = Users::$current->id;

        foreach ($args as $key => $param) {
            if (!isset($data->$param)) {
                array_push($miss, $param);
            }
        }

        // Escape
        foreach ($data as $key => $each) {
            $data->$key = addslashes($each);
        }

        if (!empty($miss)) {
            Flight::ret(StatusCodes::RETRY_WITH, "Missing Parameters", array("missing" => $miss));
            return;
        }

        self::$func($data);
    }
}
