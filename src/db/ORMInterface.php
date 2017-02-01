<?php

namespace mii\db;

interface ORMInterface {


    public static function find();

    public static function find_by_id($id);

    public static function query();

    public function select_query($with_order = true);

    public static function all();

    public static function select_list($key, $display, $first = NULL);

    public function get($key);

    public function set($values, $value = NULL);

    public function to_array();

    public function fields();

    public function get_table();

    public function changed($field_name = null);

    public function update($validation = NULL);


}