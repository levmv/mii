<?php

namespace mii\web;

use mii\db\ORM;
use mii\util\HTML;
use mii\util\Upload;
use mii\valid\Validation;

class Form
{
    /**
     * @var Validation
     */
    public $validation;

    public $_fields = [];

    public $labels = [];

    public $message_file;

    protected $scenario;

    protected $_changed = [];

    protected $_select_data = [];

    protected $_model;

    protected $is_prepared = false;

    protected $_loaded = false;

    /**
     * @param null $data Initial data for form. If null then request->post() will be used
     */
    public function __construct($data = null) {

        $this->validation = new Validation();

        $this->_fields = $this->fields();

        if (is_object($data) AND $data instanceof ORM) {
            $this->_model = $data;
            $data = $data->to_array();
        }

        if (is_array($data)) {
            foreach (array_intersect_key($data, $this->_fields) as $key => $value) {
                $this->set($key, $value);
            }
        }

        $this->_changed = [];
    }

    /**
     * Load form from _POST values or $data values
     * Return true if request method is post
     * @return bool
     */

    public function load($data = null): bool {

        if ($this->_loaded AND $data === null)
            return true;

        if (is_object($data) AND $data instanceof ORM) {
            $this->_model = $data;
            $data = $data->to_array();
        }

        if (is_array($data) AND count($data)) {

            $this->load_fields($data);

        } elseif ($this->posted()) {
            $data = \Mii::$app->request->post();

            $this->load_fields($data);

            // try to detect possible file fields and load them

            $unchanged = array_diff(array_keys($this->_fields), $this->_changed);
            foreach ($unchanged as $key) {
                $file = \Mii::$app->upload->get_file($key);
                if ($file !== null)
                    $this->set($key, $file);
            }
        }

        if (!$this->_loaded AND !$this->is_prepared) {
            $this->prepare();
            $this->is_prepared = true;
        }

        return $this->_loaded;
    }

    private function load_fields($data): void {
        foreach (array_intersect_key($data, $this->_fields) as $key => $value) {
            $this->set($key, $value);
        }
        $this->_loaded = true;
    }


    public function before_validate() {

    }


    public function after_validate($passed) {

    }

    public function prepare() {

    }


    public function loaded(): bool {
        return $this->_loaded;
    }

    public function posted(): bool {
        return \Mii::$app->request->method() === Request::POST;
    }

    public function rules(): array {
        return [];
    }

    public function set_fields($fields) {
        $this->_fields = $fields;
    }

    public function fields(): array {
        return [];
    }

    public function model(): ?ORM {
        return $this->_model;
    }

    public function changed_fields(): array {
        return array_intersect_key($this->_fields, $this->_changed);
    }

    /**
     * Checks if the field (or any) was changed
     *
     * @param string|array|null $field_name
     * @return bool
     */

    public function changed($field_name = null): bool {
        if ($field_name === null) {
            return count($this->_changed) > 0;
        }

        if (is_array($field_name)) {
            return count(array_intersect($field_name, array_keys($this->_changed)));
        }

        return isset($this->_changed[$field_name]);
    }

    public function __set($name, $value) {
        $this->set($name, $value);
    }

    public function __get($name) {
        return $this->get($name);
    }


    public function get(string $name) {
        return $this->_fields[$name];
    }

    public function set($name, $value = null) {

        if (!is_array($name)) {
            $name = [$name => $value];
        }

        foreach ($name as $key => $value) {
            $this->_changed[$key] = true;
            $this->_fields[$key] = $value;
        }
    }


    public function validate($scenario = null): bool {

        $this->before_validate();

        $this->scenario = $scenario;

        $this->validation->rules($this->rules());

        $this->validation->data($this->_fields);

        $passed = $this->validation->check();

        $this->after_validate($passed);

        return $passed;
    }

    public function error($field, $error, array $params = null) {
        $this->validation->error($field, $error, $params);

        return $this;
    }

    public function errors(): array {
        return $this->validation->errors($this->message_file);
    }

    public function errors_values(): array {
        return $this->validation->errors_values();
    }

    public function has_errors(): bool {
        return $this->validation->has_errors() > 0;
    }

    public function open($action = null, ?array $attributes = null): string {

        $out = HTML::open($action, $attributes);

        $is_post = $attributes === null ||
            !isset($attributes['method']) ||
            strcasecmp($attributes['method'], 'post') === 0;

        if (\Mii::$app->request->csrf_validation && $is_post) {

            $out .= HTML::hidden(\Mii::$app->request->csrf_token_name, \Mii::$app->request->csrf_token());
        }

        return $out;
    }

    public function close(): string {
        return HTML::close();
    }

    public function input($name, $attributes = null): string {
        return $this->field('input', $name, $attributes);
    }

    public function textarea($name, $attributes = null): string {
        return $this->field('textarea', $name, $attributes);
    }

    public function redactor($name, $attributes = null, $block = 'redactor', $options = []) {
        if ($attributes === null OR !isset($attributes['id'])) {
            $attributes['id'] = '__form_redactor__' . $name;
        }

        return block($block)
            ->set('textarea', $this->field('textarea', $name, $attributes))
            ->set('id', $attributes['id'])
            ->set('options', $options);
    }

    public function checkbox($name, $attributes = null): string {
        return $this->field('checkbox', $name, $attributes);
    }

    public function hidden($name, $attributes = null): string {
        return $this->field('hidden', $name, $attributes);
    }

    public function file($name, $attributes = null): string {
        return $this->field('file', $name, $attributes);
    }


    public function select($name, $data = null, $attributes = null): string {
        if ($data !== null)
            $this->_select_data[$name] = $data;
        return $this->field('select', $name, $attributes);
    }

    public function password($name, $attributes = null): string {
        return $this->field('password', $name, $attributes);
    }

    public function field($type, $name, $attributes = null): string {

        if (!\array_key_exists($name, $this->_fields)) {
            $this->_fields[$name] = null;
        }

        switch ($type) {
            case 'input':
                return HTML::input($name, $this->_fields[$name], $attributes);
            case 'hidden':
                return HTML::hidden($name, $this->_fields[$name], $attributes);
            case 'textarea':
                return HTML::textarea($name, $this->_fields[$name], $attributes);
            case 'checkbox':
                $uncheck = '0';
                $hidden = '';

                if (isset($attributes['uncheck'])) {
                    $uncheck = $attributes['uncheck'];
                    unset($attributes['uncheck']);
                }

                if ($uncheck !== null && $uncheck !== false) {
                    $hidden = HTML::hidden($name, $uncheck);
                }

                return $hidden . HTML::checkbox($name, 1, (bool)$this->_fields[$name], $attributes);
            case 'password':
                return HTML::password($name, $this->_fields[$name], $attributes);
            case 'select':
                if ($attributes AND isset($attributes['multiple']) AND $attributes['multiple'] !== false) {
                    return HTML::select(
                        $name . '[]',
                        $this->_select_data[$name] ?? [],
                        $this->_fields[$name],
                        $attributes
                    );
                }
                return HTML::select(
                    $name,
                    isset($this->_select_data[$name]) ? $this->_select_data[$name] : [],
                    $this->_fields[$name],
                    $attributes
                );
            case 'file':
                return HTML::file($name, $attributes);
        }
        throw new FormException("Wrong field type $type");
    }

    public function label($field_name, $label_name, $attributes = null): string {
        $this->labels[$field_name] = $label_name;

        return HTML::label($field_name, $label_name, $attributes);
    }

    public function uploaded($name): bool {

        return isset($_FILES[$name]) AND Upload::not_empty($_FILES[$name]) AND Upload::valid($_FILES[$name]);

    }


}
