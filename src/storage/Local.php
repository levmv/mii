<?php declare(strict_types=1);

namespace mii\storage;

use mii\core\Component;
use mii\util\Misc;
use mii\web\UploadedFile;

class Local extends Component implements StorageInterface
{
    protected string $path = '';
    protected string $url = '';

    protected int $levels = 0;

    protected function resolve(string $path): string
    {
        $path = \ltrim($path, \DIRECTORY_SEPARATOR);

        if ($this->levels) {
            $level = '';

            for ($i = 0; $i < $this->levels; $i++) {
                $level .= \substr($path, $i, 1) . \DIRECTORY_SEPARATOR;
            }

            $path = \substr($path, $i);

            if (!\is_dir($this->path . $level)) {
                Misc::mkdir($this->path . $level);
            }

            $path = $level . $path;
        }

        return $this->path . $path;
    }

    public function init(array $config = []): void
    {
        parent::init($config);
        if ($this->path) {
            $this->path = \Mii::resolve($this->path) . \DIRECTORY_SEPARATOR;
        }
    }

    public function exist(string $path): bool
    {
        return \file_exists($this->resolve($path));
    }

    public function get(string $path)
    {
        return \file_get_contents($this->resolve($path));
    }

    /**
     * @param string $path
     * @param string|\Stringable|resource $content
     * @return bool
     */
    public function put(string $path, $content): bool
    {
        if ($content instanceof UploadedFile) {
            return $content->saveAs($this->resolve($path));
        }
        return false !== \file_put_contents($this->resolve($path), $content);
    }

    public function putFile(string $path, string $from): bool
    {
        return $this->copy($from, $path);
    }

    public function delete(string $path): bool
    {
        return \unlink($this->resolve($path));
    }

    public function size(string $path)
    {
        return \filesize($this->resolve($path));
    }

    public function modified(string $path)
    {
        return \filemtime($this->resolve($path));
    }

    public function copy(string $from, string $to): bool
    {
        return \copy($this->resolve($from), $this->resolve($to));
    }

    public function move(string $from, string $to): bool
    {
        return \rename($this->resolve($from), $this->resolve($to));
    }

    public function url(string $path): string
    {
        return $this->url . '/' . $path;
    }

    public function files(string $path)
    {
        // TODO: Implement files() method.
    }

    public function mkdir(string $path, $mode = 0777)
    {
        \mkdir($this->resolve($path), $mode, true);
    }

    public function path(string $path): string
    {
        return $this->path . '/' . $path;
    }
}
