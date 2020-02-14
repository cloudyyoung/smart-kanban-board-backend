<?php

namespace App;

class Events extends Nodes{

    function __construct($id, $parent_id, $title = "", $note = "", $grandparent_id = null){
        $this->grandparent_id = isset($grandparent_id) ? (int)$grandparent_id : null;
        parent::__construct($id, $parent_id, $title, $note);
    }

}