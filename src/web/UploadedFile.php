<?php declare(strict_types=1);

namespace mii\web;

class UploadedFile
{

    /**
     * @var string the original name of the file being uploaded
     */
    public string $name;

    /**
     * @var string the path of the uploaded file on the server.
     * Note, this is a temporary file which will be automatically deleted by PHP
     * after the current request is processed.
     */
    public string $tmp_name;

    /**
     * @var string the MIME-type of the uploaded file (such as "image/gif").
     * Since this MIME type is not checked on the server-side, do not take this value for granted.
     */
    public string $type;

    /**
     * @var int the actual size of the uploaded file in bytes
     */
    public int $size;

    /**
     * @var int an error code describing the status of this file uploading.
     */
    public int $error;


    public function __toString(): string
    {
        return $this->name;
    }


    public function __construct($config)
    {
        foreach ($config as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * Saves the uploaded file.
     * Note that this method uses php's move_uploaded_file() method. If the target file `$file`
     * already exists, it will be overwritten.
     * @param string $file the file path used to save the uploaded file
     * @param bool $delete_tmp whether to delete the temporary file after saving.
     * If true, you will not be able to save the uploaded file again in the current request.
     * @return bool true whether the file is saved successfully
     */
    public function saveAs(string $file, bool $delete_tmp = true): bool
    {
        $file = \Mii::resolve($file);

        if ($this->error === \UPLOAD_ERR_OK) {
            if ($delete_tmp) {
                return \move_uploaded_file($this->tmp_name, $file);
            }

            if (\is_uploaded_file($this->tmp_name)) {
                return \copy($this->tmp_name, $file);
            }
        }
        return false;
    }

    public function basename(): string
    {
        return \basename($this->name);
    }

    public function hasError(): bool
    {
        return $this->error !== \UPLOAD_ERR_OK;
    }

    public function isUploadedFile(): bool
    {
        return \is_uploaded_file($this->tmp_name);
    }


    public function extension(): string
    {
        return \strtolower(\pathinfo($this->name, \PATHINFO_EXTENSION));
    }

    public function filename(): string
    {
        return \strtolower(\pathinfo($this->name, \PATHINFO_FILENAME));
    }


    public static function getFile(string $name): ?self
    {
        $files = self::loadFiles();

        return isset($files[$name])
            ? new static($files[$name])
            : null;
    }

    /**
     * @return UploadedFile[]
     */
    public static function getFiles(string $name): array
    {
        $files = self::loadFiles();

        if (isset($files[$name])) {
            return [new static($files[$name])];
        }
        $results = [];
        foreach ($files as $key => $file) {
            if (\str_starts_with($key, "{$name}[")) {
                $results[] = new static($file);
            }
        }
        return $results;
    }

    /**
     * @return UploadedFile[]
     */
    public static function allFiles(): array
    {
        return array_map(fn(array $file) => new static($file), self::loadFiles());
    }


    private static ?array $_files = null;

    private static function loadFiles(): array
    {
        if (static::$_files === null) {
            self::$_files = [];
            if (!empty($_FILES)) {
                foreach ($_FILES as $class => $info) {
                    self::loadFilesRecursive($class, $info['name'], $info['tmp_name'], $info['type'], $info['size'], $info['error']);
                }
            }
        }
        return self::$_files;
    }


    /**
     * Creates UploadedFile instances from $_FILE recursively.
     * @param string $key key for identifying uploaded file: class name and sub-array indexes
     * @param mixed $names file names provided by PHP
     * @param mixed $tmp_names temporary file names provided by PHP
     * @param mixed $types file types provided by PHP
     * @param mixed $sizes file sizes provided by PHP
     * @param mixed $errors uploading issues provided by PHP
     * @copyright Copyright (c) 2008 Yii Software LLC
     */
    private static function loadFilesRecursive(string $key, mixed $names, mixed $tmp_names, mixed $types, mixed $sizes, mixed $errors): void
    {
        if (\is_array($names)) {
            foreach ($names as $i => $name) {
                self::loadFilesRecursive($key . '[' . $i . ']', $name, $tmp_names[$i], $types[$i], $sizes[$i], $errors[$i]);
            }
        } elseif ((int)$errors !== \UPLOAD_ERR_NO_FILE) {
            self::$_files[$key] = [
                'name' => $names,
                'tmp_name' => $tmp_names,
                'type' => $types,
                'size' => $sizes,
                'error' => $errors,
            ];
        }
    }
}
