<?php
declare(strict_types=1);

namespace mii\web;

use mii\db\ORM;
use mii\util\HTML;
use mii\valid\Validator;
use function is_string;
use function trim;

class Form2
{
    public ?Validator $validation = null;

    public ?string $message_file = null;

    protected ?ORM $_model = null;

    protected ?array $_data = null;

    protected ?array $_defaults = [];


    public function __construct($data = null)
    {
        if ($data instanceof ORM) {
            $this->_model = $data;
        }

        $this->validation = new Validator([], $this->rules());
    }

    public function prepare($model): void
    {
    }

    public function setDefault(string $key, string|array $value): void
    {
        $this->_defaults[$key] = $value;
    }

    public function ok(): bool
    {
        return $this->load() && $this->validate();
    }


    public function load(array $data = null): bool
    {
        if ($data === null) {
            $data = \Mii::$app->request->post();
        }

        if (!empty($data)) {
            $this->_data = $data;
            return true;
        }

        if ($this->_model) {
            $this->prepare($this->_model);
        }

        return false;
    }

    public function data(): array
    {
        return $this->_data;
    }

    protected function rules(): array
    {
        return [];
    }

    protected function messages(): array
    {
        return [];
    }

    public function model(): ?ORM
    {
        return $this->_model;
    }

    public function has(string $name): bool
    {
        return isset($this->_data[$name]);
    }


    public function set(string $name, string|array|null $value): void
    {
        $this->_data[$name] = $value;
    }

    public function get(string $name)
    {
        if (isset($this->_data[$name])) {
            return $this->_data[$name];
        }

        if ($this->_model && $this->_model->has($name)) {
            return $this->_model->get($name);
        }
        return $this->_defaults[$name] ?? $this->$name ?? null;
    }


    public function validate(): bool
    {
        $this->validation->setData($this->data());
        $this->validation->setMessages($this->messages());

        return $this->validation
            ->filter($this->filter(...))
            ->validate();
    }

    public function validated(array $params = null): array
    {
        return $this->validation->validated($params);
    }


    public function filter(mixed $field, mixed $value): mixed
    {
        if(!is_string($value)) {
            return $value;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }


    public function errors(): array
    {
        if (!$this->validation) {
            return [];
        }
        return $this->validation->errors($this->message_file);
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
        return $this->field('select', $name, $attributes, $data);
    }

    public function password($name, $attributes = null): string
    {
        return $this->field('password', $name, $attributes);
    }

    public function field($type, $name, $attributes = null, array $selectData = null): string
    {
        $value = $this->get($name);

        if (!in_array($name, ['hidden', 'checkbox', 'file']) && $this->validation->isFieldRequired($name)) {
            // Set `required` attribute, if it's not set already

            if ($attributes === null || !isset($attributes['required'])) {
                $attributes[] = 'required';
            }
            // TODO: min/max length attributes
        }

        switch ($type) {
            case 'input':
                return HTML::input($name, $value, $attributes);
            case 'hidden':
                return HTML::hidden($name, $value, $attributes);
            case 'textarea':
                return HTML::textarea($name, $value, $attributes);
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

                return $hidden . HTML::checkbox($name, 1, (bool)$value, $attributes);
            case 'password':
                return HTML::password($name, $value, $attributes);
            case 'select':
                if ($attributes) {
                    if ((isset($attributes['multiple']) && $attributes['multiple'] !== false) || in_array('multiple', $attributes)) {
                        $name .= '[]';
                    }
                }

                return HTML::select(
                    $name,
                    $selectData ?? [],
                    $value,
                    $attributes
                );
            case 'file':
                return HTML::file($name, $attributes);
        }
        throw new \RuntimeException("Wrong field type $type");
    }

}