<?php declare(strict_types=1);

namespace mii\web;

use mii\db\ORM;
use mii\util\HTML;
use mii\valid\Validation;

abstract class Form
{
    public Validation $validation;

    public array $_fields = [];

    public array $labels = [];

    public ?string $message_file = null;

    protected array $_changed = [];

    protected array $_select_data = [];

    protected ?ORM $_model = null;

    protected bool $isPrepared = false;

    protected bool $_loaded = false;

    /**
     * @param null $data Initial data for form. If null then request->post() will be used
     */
    public function __construct($data = null)
    {
        $this->validation = new Validation();

        $this->labels = $this->labels();
        $this->_fields = $this->fields();

        if ($data instanceof ORM) {
            $this->_model = $data;
            $data = $data->toArray();
        }

        if (\is_array($data)) {
            foreach (\array_intersect_key($data, $this->_fields) as $key => $value) {
                $initier = 'init'.ucfirst($key);
                if(\method_exists($this, $initier)) {
                    $value = $this->$initier($value);
                }
                $this->set($key, $value);
            }
        }
    }

    public function ok(): bool
    {
        return $this->load() && $this->validate();
    }

    /**
     * Load form from _POST values or $data values
     * Return true if form is loaded
     * @param null $data
     * @return bool
     */
    public function load($data = null): bool
    {
        if ($this->_loaded && $data === null) {
            return true;
        }

        if ($data instanceof ORM) {
            $this->_model = $data;
            $data = $data->toArray();
        }

        if (\is_array($data) && \count($data)) {
            $this->loadFields($data);
        } elseif ($this->posted()) {
            $data = \Mii::$app->request->post();

            $this->loadFields($data);

            // try to detect possible file fields and load them

            $unchanged = \array_diff(\array_keys($this->_fields), $this->_changed);
            foreach ($unchanged as $key) {
                $file = \Mii::$app->upload->getFile($key);
                if ($file !== null) {
                    $this->set($key, $file);
                }
            }
        }

        if (!$this->_loaded && !$this->isPrepared) {
            $this->prepare();
            $this->isPrepared = true;
        }

        return $this->_loaded;
    }

    private function loadFields($data): void
    {
        foreach (\array_intersect_key($data, $this->_fields) as $key => $value) {
            $this->set($key, $value);
        }
        $this->_loaded = true;
    }


    public function beforeValidate()
    {
    }


    public function prepare()
    {
    }


    public function loaded(): bool
    {
        return $this->_loaded;
    }

    public function posted(): bool
    {
        return \Mii::$app->request->method() === Request::POST;
    }

    abstract public function rules(): array;

    public function fields(): array
    {
        return [];
    }

    public function labels(): array
    {
        return [];
    }

    public function model(): ?ORM
    {
        return $this->_model;
    }

    public function changedFields(): array
    {
        return \array_intersect_key($this->_fields, $this->_changed);
    }

    /**
     * Checks if the field (or any) was changed
     *
     * @param array|string|null $field_name
     * @return bool
     */

    public function changed(array|string $field_name = null): bool
    {
        if ($field_name === null) {
            return \count($this->_changed) > 0;
        }

        if (\is_array($field_name)) {
            return \count(\array_intersect($field_name, \array_keys($this->_changed))) > 0;
        }

        return isset($this->_changed[$field_name]);
    }

    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * @param string $key the property to test
     * @return bool
     */
    public function __isset(string $key)
    {
        return \array_key_exists($key, $this->_fields);
    }


    public function get(string $name)
    {
        return $this->_fields[$name];
    }

    public function set($name, $value = null) : void
    {
        if (!\is_array($name)) {
            $name = [$name => $value];
        }

        foreach ($name as $key => $val) {
            $this->_changed[$key] = true;
            $this->_fields[$key] = $val;
        }
    }


    public function validate(): bool
    {
        $this->beforeValidate();

        $this->validation->rules($this->rules());
        if(!empty($this->labels)) {
            $this->validation->labels($this->labels);

        }

        $this->validation->data($this->_fields);

        return $this->validation->check();
    }

    public function error($field, $error) : self
    {
        $this->validation->error($field, $error);

        return $this;
    }

    public function errors(): array
    {
        return $this->validation->errors($this->message_file);
    }

    public function errorsValues(): array
    {
        return $this->validation->errorsValues();
    }

    public function hasErrors(): bool
    {
        return $this->validation->hasErrors() > 0;
    }

    public function open($action = null, ?array $attributes = null): string
    {
        return HTML::open($action, $attributes);
    }

    public function close(): string
    {
        return HTML::close();
    }

    public function input($name, $attributes = null): string
    {
        return $this->field('input', $name, $attributes);
    }

    public function textarea($name, $attributes = null): string
    {
        return $this->field('textarea', $name, $attributes);
    }

    public function redactor($name, $attributes = null, $block = 'redactor', $options = [])
    {
        if ($attributes === null || !isset($attributes['id'])) {
            $attributes['id'] = '__form_redactor__' . $name;
        }

        return block($block)
            ->set('textarea', $this->field('textarea', $name, $attributes))
            ->set('id', $attributes['id'])
            ->set('options', $options);
    }

    public function checkbox($name, $attributes = null): string
    {
        return $this->field('checkbox', $name, $attributes);
    }

    public function hidden($name, $attributes = null): string
    {
        return $this->field('hidden', $name, $attributes);
    }

    public function file($name, $attributes = null): string
    {
        return $this->field('file', $name, $attributes);
    }


    public function select($name, $data = null, $attributes = null): string
    {
        if ($data !== null) {
            $this->_select_data[$name] = $data;
        }
        return $this->field('select', $name, $attributes);
    }

    public function password($name, $attributes = null): string
    {
        return $this->field('password', $name, $attributes);
    }

    public function field($type, $name, $attributes = null): string
    {
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
                if ($attributes && isset($attributes['multiple']) && $attributes['multiple'] !== false) {
                    return HTML::select(
                        $name . '[]',
                        $this->_select_data[$name] ?? [],
                        $this->_fields[$name],
                        $attributes
                    );
                }
                return HTML::select(
                    $name,
                    $this->_select_data[$name] ?? [],
                    $this->_fields[$name],
                    $attributes
                );
            case 'file':
                return HTML::file($name, $attributes);
        }
        throw new \mii\core\Exception("Wrong field type $type");
    }

    public function label($field_name, $label_name, $attributes = null): string
    {
        $this->labels[$field_name] = $label_name;

        return HTML::label($field_name, $label_name, $attributes);
    }
}
