<?php

namespace App;

use Flight;
use Throwable;

class Base
{

    public static function set($attribute, $value)
    {
        $this->$attribute = $value;
        return $this;
    }

    public static function get($attribute)
    {
        return $this->$attribute;
    }

    public static function read()
    {
        Flight::ret(200, "OK", $this);
    }

    # Run a function in the class
    public static function run($funcName, $funcName2)
    {
        try {
            if ($funcName == null) {
                $funcName = 'read';
            }
            if (substr($funcName, 0, 1) == "_" || substr($funcName, 0, 3) == "get" || substr($funcName, 0, 3) == "set" || substr($funcName, 0, 3) == "run") {
                throw new Throwable('denied');
            }

            if ($funcName2 != null) {
                $this->$funcName . '_' . $funcName2();
            } else {
                $this->$funcName();
            }
        } catch (Throwable $e) {
            Flight::ret(405, "method not allowed");
        }
    }

    public static function view($viewName, $viewName2 = null)
    {
        try {
            if ($viewName == null) {
                $viewName = 'read';
            }
            if (substr($viewName, 0, 1) == "_") {
                throw new Throwable('denied');
            }

            if ($viewName2 != null) {
                Flight::render(ucwords($viewName) . ucwords($viewName2), array('user' => Flight::get('user')), 'body');
            } else {
                Flight::render(ucwords($viewName), array('user' => Flight::get('user')), 'body');
            }

            Flight::render('_layout');
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
