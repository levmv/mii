<?php

namespace mii\storage;


interface StorageInterface {

    public function exist(string $path);

    public function get(string $path);

    public function put(string $path, $content);

    public function put_file(string $path, string $from);

    public function delete(string $path);

    public function size(string $path);

    public function modified(string $path);

    public function copy(string $from, string $to);

    public function move(string $from, string $to);

    public function url(string $path);

    public function files(string $path);

    public function mkdir(string $path);

}