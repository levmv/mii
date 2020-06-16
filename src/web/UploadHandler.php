<?php declare(strict_types=1);

namespace mii\web;

use mii\core\Component;

class UploadHandler extends Component
{

    private $_files;

    public function init(array $config = []): void
    {
        parent::init($config);

        $this->files();
    }

    public function getFile(string $name): ?UploadedFile
    {
        return $this->_files[$name] ?? null;
    }


    /**
     * @param string $name
     * @return array UploadedFile
     */
    public function getFiles(string $name): array
    {
        if (isset($this->_files[$name])) {
            return [$this->_files[$name]];
        }
        $results = [];
        foreach ($this->_files as $key => $file) {
            if (strpos($key, "{$name}[") === 0) {
                $results[] = $file;
            }
        }
        return $results;
    }


    public function files(): array
    {

        if ($this->_files === null) {
            $this->_files = [];
            if (isset($_FILES) && \is_array($_FILES)) {
                foreach ($_FILES as $class => $info) {
                    $this->loadFilesRecursive($class, $info['name'], $info['tmp_name'], $info['type'], $info['size'], $info['error']);
                }
            }
        }
        return $this->_files;
    }


    /**
     * Creates UploadedFile instances from $_FILE recursively.
     * @param string $key key for identifying uploaded file: class name and sub-array indexes
     * @param mixed  $names file names provided by PHP
     * @param mixed  $tmp_names temporary file names provided by PHP
     * @param mixed  $types file types provided by PHP
     * @param mixed  $sizes file sizes provided by PHP
     * @param mixed  $errors uploading issues provided by PHP
     */
    private function loadFilesRecursive($key, $names, $tmp_names, $types, $sizes, $errors): void
    {
        if (\is_array($names)) {
            foreach ($names as $i => $name) {
                static::loadFilesRecursive($key . '[' . $i . ']', $name, $tmp_names[$i], $types[$i], $sizes[$i], $errors[$i]);
            }
        } elseif ((int)$errors !== UPLOAD_ERR_NO_FILE) {
            $this->_files[$key] = new UploadedFile([
                'name' => $names,
                'tmp_name' => $tmp_names,
                'type' => $types,
                'size' => $sizes,
                'error' => $errors,
            ]);
        }
    }
}
