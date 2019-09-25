<?php

namespace mii\storage\FileSystems;

use mii\storage\Storage;
use mii\storage\FileSystemInterface;
use mii\web\UploadedFile;

class Local extends Storage implements FileSystemInterface {

    public function exist(string $path)
    {
        return file_exists($this->resolve($path));
    }

    public function get(string $path)
    {
        return file_get_contents($this->resolve($path));
    }

    public function put(string $path, $content)
    {
        if($content instanceof UploadedFile) {
            return $content->save_as($this->resolve($path));
        }
        return file_put_contents($this->resolve($path), $content);
    }

    public function put_file(string $path, string $from)
    {
        $this->copy($from, $path);
    }

    public function delete(string $path)
    {
        unlink($this->resolve($path));
    }

    public function size(string $path)
    {
        return filesize($this->resolve($path));
    }

    public function modified(string $path)
    {
        return filemtime($this->resolve($path));
    }

    public function copy(string $from, string $to)
    {
        copy($this->resolve($from), $this->resolve($to));
    }

    public function move(string $from, string $to)
    {
        rename($this->resolve($from), $this->resolve($to));
    }

    public function url(string $path)
    {
        return $this->url.'/'.$path;
    }

    public function files(string $path)
    {
        // TODO: Implement files() method.
    }

    public function mkdir(string $path, $mode = 0777)
    {
        mkdir($this->resolve($path), $mode, true);
    }

    public function path(string $path)
    {
        return $this->path . '/' . $path;
    }
}