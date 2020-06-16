<?php declare(strict_types=1);

namespace mii\web;

class UploadedFile
{

    /**
     * @var string the original name of the file being uploaded
     */
    public $name;

    /**
     * @var string the path of the uploaded file on the server.
     * Note, this is a temporary file which will be automatically deleted by PHP
     * after the current request is processed.
     */
    public $tmp_name;

    /**
     * @var string the MIME-type of the uploaded file (such as "image/gif").
     * Since this MIME type is not checked on the server-side, do not take this value for granted.
     */
    public $type;

    /**
     * @var int the actual size of the uploaded file in bytes
     */
    public $size;

    /**
     * @var int an error code describing the status of this file uploading.
     */
    public $error;


    public function __toString()
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
     * @param bool   $delete_tmp whether to delete the temporary file after saving.
     * If true, you will not be able to save the uploaded file again in the current request.
     * @return bool true whether the file is saved successfully
     */
    public function saveAs($file, $delete_tmp = true): bool
    {
        $file = \Mii::resolve($file);

        if ($this->error === \UPLOAD_ERR_OK) {
            if ($delete_tmp) {
                return move_uploaded_file($this->tmp_name, $file);
            }

            if (is_uploaded_file($this->tmp_name)) {
                return copy($this->tmp_name, $file);
            }
        }
        return false;
    }

    public function basename(): string
    {
        return basename($this->name);
    }

    public function hasError(): bool
    {
        return $this->error !== UPLOAD_ERR_OK;
    }

    public function isUploadedFile(): bool
    {
        return is_uploaded_file($this->tmp_name);
    }


    public function extension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }
}
