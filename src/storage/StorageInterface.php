<?php declare(strict_types=1);

namespace mii\storage;

interface StorageInterface
{
    public function exist(string $path): bool;

    public function get(string $path);

    public function put(string $path, $content): bool;

    public function putFile(string $path, string $from): bool;

    public function delete(string $path);

    public function size(string $path);

    public function modified(string $path);

    public function copy(string $from, string $to): bool;

    public function move(string $from, string $to): bool;

    public function url(string $path): string;

    public function files(string $path);

    public function mkdir(string $path);
}
