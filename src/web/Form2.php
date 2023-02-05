<?php
declare(strict_types=1);

namespace mii\web;

use mii\db\ORM;
use mii\util\HTML;
use mii\valid\Validator;

class Form2
{
    public ?Validator $validation = null;

    protected Request $request;

    public ?string $message_file = null;

    protected ?ORM $_model = null;

    protected ?array $_data = null;


    public function __construct($data = null)
    {
        if ($data instanceof ORM) {
            $this->_model = $data;
        }

        $this->validation = new Validator($this->rules());

        $this->request = \Mii::$app->request;
    }

    public function prepare(): void
    {
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

        $this->prepare();

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


    public function get(string $name)
    {
        if (\Mii::$app->request->has($name)) {
            return \Mii::$app->request->input($name);
        }

        if ($this->_model && $this->_model->has($name)) {
            return $this->_model->get($name);
        }

        if (isset($this->$name)) {
            return $this->$name;
        }

        return null;
    }


    public function validate(): bool
    {
        $this->validation->setData($this->data());
        $this->validation->setMessages($this->messages());
        return $this->validation->validate();
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
                if ($attributes && isset($attributes['multiple']) && $attributes['multiple'] !== false) {
                    return HTML::select(
                        $name . '[]',
                        $selectData ?? [],
                        $value,
                        $attributes
                    );
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
        throw new \mii\core\Exception("Wrong field type $type");
    }

}
