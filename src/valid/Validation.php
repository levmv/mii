<?php declare(strict_types=1);

namespace mii\valid;

use Mii;
use mii\util\Arr;

class Validation
{
    // Field rules
    protected array $_rules = [];

    // Field labels
    protected array $_labels = [];

    // Rules that are executed even when the value is empty
    protected array $_empty_rules = ['notEmpty', 'required', 'matches'];

    // Error list, field => rule
    protected array $_errors = [];

    // Error messages list field => rule => message
    protected array $_error_messages = [];

    // Array to validate
    protected array $_data = [];

    /**
     *
     * @param array $array array to validate
     * @return  void
     */
    public function __construct(array $array = [])
    {
        $this->_data = $array;
    }

    /**
     * Returns the array of data to be validated.
     *
     * @param array|null $data
     * @return mixed
     */
    public function data(array $data = null)
    {
        if ($data === null) {
            return $this->_data;
        }
        $this->_data = $data;
    }

    public function field($name)
    {
        return $this->_data[$name] ?? null;
    }

    /**
     * Sets or overwrites the label name for a field.
     *
     * @param string $field field name
     * @param string $label label
     * @return  $this
     */
    public function label(string $field, string $label): self
    {
        $this->_labels[$field] = $label;

        return $this;
    }

    /**
     * Sets labels using an array.
     *
     * @param array $labels list of field => label names
     * @return  $this
     */
    public function labels(array $labels): self
    {
        $this->_labels = $labels + $this->_labels;

        return $this;
    }

    /**
     * Overwrites or appends rules to a field. Each rule will be executed once.
     * All rules must be string names of functions method names in Rules class or collables.
     * Parameters must match the parameters of the callback function exactly
     *
     *     // The "username" must not be empty and have a minimum length of 4
     *     $validation->rule('username', 'required')
     *                ->rule('username', 'min', 4);
     *
     * [!!] Errors must be added manually when using closures!
     *
     * @param string $field field name
     * @param string|callable $rule valid PHP callback or closure
     * @param array $params extra parameters for the rule
     * @param string|null $message Optional error message
     * @return $this
     */
    public function rule(string $field, $rule, array $params = [], string $message = null): self
    {
        if (!isset($this->_labels[$field])) {
            // Set the field label to the field name
            $this->_labels[$field] = $field;
        }

        if ($message !== null && is_string($rule)) {
            $this->_error_messages["{$field}.{$rule}"] = $message;
        }

        // Store the rule and params for this rule
        $this->_rules[$field][] = [$rule, $params];

        return $this;
    }

    /**
     * Add rules using an array.
     *
     * @param array $rules list of rules
     * @return  $this
     */
    public function rules(array $rules): self
    {
        foreach ($rules as $row) {
            $field = $row[0];
            $rule = $row[1];
            $params = $row[2] ?? [];
            $message = $row['message'] ?? $row[3] ?? null;
            if (\is_int($params) || \is_string($params)) {
                $params = [$params];
            }

            if (\is_array($field)) {
                foreach ($field as $field_name) {
                    $this->rule($field_name, $rule, $params, $message);
                }
            } else {
                $this->rule($field, $rule, $params, $message);
            }
        }

        return $this;
    }

    /**
     * Executes all validation rules. This should
     * typically be called within an if/else block.
     *
     *     if ($validation->check())
     *     {
     *          // The data is valid, do something here
     *     }
     *
     * @return  boolean
     */
    public function check(): bool
    {
        \assert((config('debug') && ($benchmark = \mii\util\Profiler::start('Validation', __FUNCTION__))) || 1);

        $this->_errors = [];

        // Get a list of the expected fields
        $expected = Arr::merge(\array_keys($this->_data), \array_keys($this->_labels));

        // Import the rules locally
        $rules = $this->_rules;

        $data = [];

        foreach ($expected as $field) {
            // Use the submitted value or NULL if no data exists
            $data[$field] = $this->_data[$field] ?? null;
        }
        // Overload the current array with the new one
        $this->_data = $data;


        // Execute the rules
        foreach ($rules as $field => $set) {
            // Get the field value
            $value = $this->_data[$field];

            foreach ($set as [$rule, $params]) {
                \array_unshift($params, $value);

                // Default the error name to be the rule (except array and lambda rules)
                $error_name = $rule;
                if (\is_array($rule)) {
                    // This is an array callback, the method name is the error name
                    $error_name = $rule[1];
                    $passed = \call_user_func_array($rule, $params);
                } elseif (!\is_string($rule)) {
                    // This is a lambda function, there is no error name (errors must be added manually)
                    $error_name = false;
                    \array_unshift($params, $field);
                    \array_unshift($params, $this);
                    $passed = \call_user_func_array($rule, $params);
                } elseif (\method_exists(Rules::class, $rule)) {
                    // Use a method in this object
                    $method = new \ReflectionMethod(Rules::class, $rule);

                    // Call static::$rule($this[$field], $param, ...) with Reflection

                    $passed = $method->invokeArgs(null, $params);
                } elseif (!\str_contains($rule, '::')) {

                    // Use a function call
                    $function = new \ReflectionFunction($rule);

                    // Call $function($this[$field], $param, ...) with Reflection
                    $passed = $function->invokeArgs($params);
                } else {
                    // Split the class and method of the rule
                    [$class, $method] = \explode('::', $rule, 2);

                    // Use a static method call
                    $method = new \ReflectionMethod($class, $method);

                    // Call $Class::$method($this[$field], $param, ...) with Reflection
                    $passed = $method->invokeArgs(null, $params);
                }

                // Ignore return values from rules when the field is empty
                if (!\in_array($rule, $this->_empty_rules, true) && !Rules::notEmpty($value)) {
                    continue;
                }

                if ($passed === false && $error_name !== false) {
                    // Add the rule to the errors
                    $this->error($field, $error_name);

                    // This field has an error, stop executing rules
                    break;
                }

                if (isset($this->_errors[$field])) {
                    // The callback added the error manually, stop checking rules
                    break;
                }
            }
        }
        \assert((isset($benchmark) && \mii\util\Profiler::stop($benchmark)) || 1);

        return empty($this->_errors);
    }

    /**
     * Add an error to a field.
     *
     * @param string $field field name
     * @param string $error error message
     * @return  $this
     */
    public function error(string $field, string $error): self
    {
        $this->_errors[$field] = $error;

        return $this;
    }


    public function hasErrors(): bool
    {
        return !empty($this->_errors);
    }

    /**
     * Returns the error messages. If no file is specified, the error message
     * will be the name of the rule that failed. When a file is specified, the
     * message will be loaded from "field/rule", or if no rule-specific message
     * exists, "field/default" will be used. If neither is set, the returned
     * message will be "file/field/rule".
     *
     * By default all messages used as is. If second argument is true than "__" function
     * will be used for translation.
     *
     * @param string|null $file file to load error messages from
     * @param mixed $translate translate the message
     * @return  array
     * @throws \Exception
     */
    public function errors(string $file = null, bool $translate = false): array
    {
        if ($file === null && empty($this->_error_messages)) {
            // Nothing to do, so just return the error list
            return $this->_errors;
        }

        // Create a new message list
        $messages = [];

        foreach ($this->_errors as $field => $error) {

            // Get the label for this field
            $label = $this->_labels[$field];

            if ($translate) {
                // Translate the label
                $label = __($label);
            }

            // Start the translation values list
            $values = [
                ':field' => $label,
                ':value' => $this->field($field),
            ];

            if (\is_array($values[':value'])) {
                // All values must be strings
                $values[':value'] = \implode(', ', Arr::flatten($values[':value']));
            }

            if ($file) {
                if (($message = Mii::message($file, "{$field}.{$error}")) && \is_string($message)) {
                    // Found a message for this field and error
                } elseif (($message = Mii::message($file, "{$field}.default")) && \is_string($message)) {
                    // Found a default message for this field
                } elseif (($message = Mii::message($file, $error)) && \is_string($message)) {
                    // Found a default message for this error
                } else {

                    // No message exists, display the path expected
                    $message = "{$file}.{$field}.{$error}";
                }
            } else {
                $message = $this->_error_messages["{$field}.{$error}"] ?? "{$field}.{$error}";
            }

            if ($translate) {
                // Translate the message using the default language
                $message = __($message, $values);
            } else {
                // Do not translate, just replace the values
                $message = \strtr($message, $values);
            }

            // Set the message for this field
            $messages[$field] = $message;
        }

        return $messages;
    }

    /**
     * Returns the error values.
     *
     * @return  array
     */
    public function errorsValues(): array
    {
        return $this->_errors;
    }
}
