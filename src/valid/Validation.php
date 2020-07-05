<?php declare(strict_types=1);

namespace mii\valid;

use Mii;
use mii\util\Arr;

class Validation
{

    // Field rules
    protected $_rules = [];

    // Field labels
    protected $_labels = [];

    // Rules that are executed even when the value is empty
    protected array $_empty_rules = ['notEmpty', 'required', 'matches'];

    // Error list, field => rule
    protected array $_errors = [];

    // Error messages list field => rule => message
    protected $_error_messages = [];

    // Array to validate
    protected array $_data = [];

    /**
     * Sets the unique "any field" key and creates an ArrayObject from the
     * passed array.
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
    public function label($field, $label) : self
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
    public function labels(array $labels) : self
    {
        $this->_labels = $labels + $this->_labels;

        return $this;
    }

    /**
     * Overwrites or appends rules to a field. Each rule will be executed once.
     * All rules must be string names of functions method names. Parameters must
     * match the parameters of the callback function exactly
     *
     *     // The "username" must not be empty and have a minimum length of 4
     *     $validation->rule('username', 'not_empty')
     *                ->rule('username', 'min_length', [4]);
     *
     *     // The "password" field must match the "password_repeat" field
     *     $validation->rule('password', 'matches', array(':validation', 'password', 'password_repeat'));
     *
     *     // Using closure (anonymous function)
     *     $validation->rule('index',
     *         function(Validation $array, $field, $value)
     *         {
     *             if ($value > 6 AND $value < 10)
     *             {
     *                 $array->error($field, 'custom');
     *             }
     *         }
     *         , array(':validation', ':field', ':value')
     *     );
     *
     * [!!] Errors must be added manually when using closures!
     *
     * @param string   $field field name
     * @param callback $rule valid PHP callback or closure
     * @param array    $params extra parameters for the rule
     * @return  $this
     */
    public function rule($field, $rule, array $params = []) : self
    {
        if ($field !== true && !isset($this->_labels[$field])) {
            // Set the field label to the field name
            $this->_labels[$field] = $field;
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
    public function rules(array $rules) : self
    {
        foreach ($rules as $row) {
            $field = $row[0];
            $rule = $row[1];
            $params = $row[2] ?? [];

            if (\is_array($field)) {
                foreach ($field as $field_name) {
                    $this->rule($field_name, $rule, $params);
                }
            } else {
                $this->rule($field, $rule, $params);
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
    public function check() : bool
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

                    // Allows rule('field', array(':model', 'some_rule'));
                    if (\is_string($rule[0]) && \array_key_exists($rule[0], $this->_bound)) {
                        // Replace with bound value
                        $rule[0] = $this->_bound[$rule[0]];
                    }

                    // This is an array callback, the method name is the error name
                    $error_name = $rule[1];
                    $passed = \call_user_func_array($rule, $params);
                } elseif (!\is_string($rule)) {
                    // This is a lambda function, there is no error name (errors must be added manually)
                    $error_name = false;
                    \array_unshift($params, $field);
                    \array_unshift($params, $this);
                    $passed = \call_user_func_array($rule, $params);
                } elseif (\method_exists('mii\valid\Rules', $rule)) {
                    // Use a method in this object
                    $method = new \ReflectionMethod('mii\valid\Rules', $rule);

                    // Call static::$rule($this[$field], $param, ...) with Reflection

                    $passed = $method->invokeArgs(null, $params);
                } elseif (\strpos($rule, '::') === false) {

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
                    $this->error($field, $error_name, $params);

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
     * @param array  $params
     * @return  $this
     */
    public function error($field, $error, array $params = null) : self
    {
        $this->_errors[$field] = $error;

        return $this;
    }

    public function add_error_message($field, $error, $message)
    {
        $this->_error_messages[$field][$error] = $message;
    }

    public function hasErrors() : bool
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
     * By default all messages are translated using the default language.
     * A string can be used as the second parameter to specified the language
     * that the message was written in.
     *
     *     // Get errors from messages/forms/login.php
     *     $errors = $Validation->errors('forms/login');
     *
     * @param string $file file to load error messages from
     * @param mixed  $translate translate the message
     * @return  array
     * @throws \Exception
     */
    public function errors($file = null, bool $translate = false) : array
    {
        if ($file === null) {
            // Return the error list
            return $this->_errors;
        }

        // Create a new message list
        $messages = [];

        foreach ($this->_errors as $field => $set) {
            if (\is_array($set)) {
                [$error, $params] = $set;
            } else {
                $error = $set;
                $params = [];
            }

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

            if ($params) {
                foreach ($params as $key => $value) {
                    if (\is_array($value)) {
                        // All values must be strings
                        $value = \implode(', ', Arr::flatten($value));
                    } elseif (\is_object($value)) {
                        // Objects cannot be used in message files
                        continue;
                    }

                    // Check if a label for this parameter exists
                    if (isset($this->_labels[$value])) {
                        // Use the label as the value, eg: related field name for "matches"
                        $value = $this->_labels[$value];

                        if ($translate) {
                            // Translate the value
                            $value = __($value);
                        }
                    }

                    // Add each parameter as a numbered value, starting from 1
                    $values[':param' . ($key + 1)] = $value;
                }
            }


            if (($message = Arr::path($this->_error_messages, "{$field}.{$error}")) && \is_string($message)) {
            } elseif (($message = Mii::message($file, "{$field}.{$error}")) && \is_string($message)) {

                // Found a message for this field and error
            } elseif (($message = Mii::message($file, "{$field}.default")) && \is_string($message)) {
                // Found a default message for this field
            } elseif (($message = Mii::message($file, $error)) && \is_string($message)) {
                // Found a default message for this error
            } elseif (($message = Mii::message('validation', $error)) && \is_string($message)) {
                // Found a default message for this error
            } else {
                // No message exists, display the path expected
                $message = "{$file}.{$field}.{$error}";
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
    public function errorsValues() : array
    {
        return $this->_errors;
    }
}
