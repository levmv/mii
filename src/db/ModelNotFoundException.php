<?php declare(strict_types=1);

namespace mii\db;


class ModelNotFoundException extends DatabaseException {

    public string $model = '';

    public string $id = '';


    public function set_model(string $model, string $id = '') : self
    {
        $this->model = $model;
        $this->id = $id;

        $this->message = "No results for model {$model}($id)";

        return $this;
    }
}
