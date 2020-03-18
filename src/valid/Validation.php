<?php

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
    protected $_empty_rules = ['not_empty', 'matches'];

    // Error list, field => rule
    protected $_errors = [];

    // Error messages list field => rule => message
    protected $_error_messages = [];

    // Array to validate
    protected $_data = [];

    /**
     * Sets the unique "any field" key and creates an ArrayObject from the
     * passed array.
     *
     * @param   array $array array to validate
     * @return  void
     */
    public function __construct(array $array = []) {
        $this->_data = $array;
    }


    /**
     * Returns the array of data to be validated.
     *
     * @return mixed
     */
    public function data($data = null) {
        if ($data === null)
            return $this->_data;
        $this->_data = $data;
    }

    public function field($name) {
        if (isset($this->_data[$name]))
            return $this->_data[$name];

        return null;
    }

    /**
     * Sets or overwrites the label name for a field.
     *
     * @param   string $field field name
     * @param   string $label label
     * @return  $this
     */
    public function label($field, $label) {
        // Set the label for this field
        $this->_labels[$field] = $label;

        return $this;
    }

    /**
     * Sets labels using an array.
     *
     * @param   array $labels list of field => label names
     * @return  $this
     */
    public function labels(array $labels) {
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
     * @param   string $field field name
     * @param   callback $rule valid PHP callback or closure
     * @param   array $params extra parameters for the rule
     * @return  $this
     */
    public function rule($field, $rule, array $params = []) {
        if ($field !== true AND !isset($this->_labels[$field])) {
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
     * @param   array $rules list of rules
     * @return  $this
     */
    public function rules(array $rules) {
        foreach ($rules as $row) {
            $field = $row[0];
            $rule = $row[1];
            $params = isset($row[2]) ? $row[2] : [];

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
    public function check() {
        $benchmark = false;
        if (config('debug')) {
            // Start a new benchmark
            $benchmark = \mii\util\Profiler::start('Validation', __FUNCTION__);
        }

        $this->_errors = [];

        // Get a list of the expected fields
        $expected = Arr::merge(array_keys($this->_data), array_keys($this->_labels));

        // Import the rules locally
        $rules = $this->_rules;

        $data = [];

        foreach ($expected as $field) {
            // Use the submitted value or NULL if no data exists
            $data[$field] = isset($this->_data[$field]) ? $this->_data[$field] : null;

        }
        // Overload the current array with the new one
        $this->_data = $data;


        // Execute the rules
        foreach ($rules as $field => $set) {
            // Get the field value
            $value = $this->_data[$field];


            foreach ($set as $array) {
                // Rules are defined as array($rule, $params)
                list($rule, $params) = $array;

                array_unshift($params, $value);

                // Default the error name to be the rule (except array and lambda rules)
                $error_name = $rule;
                if (\is_array($rule)) {

                    // Allows rule('field', array(':model', 'some_rule'));
                    if (\is_string($rule[0]) AND \array_key_exists($rule[0], $this->_bound)) {
                        // Replace with bound value
                        $rule[0] = $this->_bound[$rule[0]];
                    }

                    // This is an array callback, the method name is the error name
                    $error_name = $rule[1];
                    $passed = \call_user_func_array($rule, $params);
                } elseif (!\is_string($rule)) {
                    // This is a lambda function, there is no error name (errors must be added manually)
                    $error_name = FALSE;
                    array_unshift($params, $field);
                    array_unshift($params, $this);
                    $passed = \call_user_func_array($rule, $params);
                } elseif (method_exists('mii\valid\Rules', $rule)) {
                    // Use a method in this object
                    $method = new \ReflectionMethod('mii\valid\Rules', $rule);

                    // Call static::$rule($this[$field], $param, ...) with Reflection

                    $passed = $method->invokeArgs(NULL, $params);

                } elseif (strpos($rule, '::') === FALSE) {

                    // Use a function call
                    $function = new \ReflectionFunction($rule);

                    // Call $function($this[$field], $param, ...) with Reflection
                    $passed = $function->invokeArgs($params);
                } else {
                    // Split the class and method of the rule
                    list($class, $method) = explode('::', $rule, 2);

                    // Use a static method call
                    $method = new \ReflectionMethod($class, $method);


                    // Call $Class::$method($this[$field], $param, ...) with Reflection
                    $passed = $method->invokeArgs(NULL, $params);

                }

                // Ignore return values from rules when the field is empty
                if (!\in_array($rule, $this->_empty_rules) AND !Rules::not_empty($value))
                    continue;

                if ($passed === FALSE AND $error_name !== FALSE) {
                    // Add the rule to the errors
                    $this->error($field, $error_name, $params);

                    // This field has an error, stop executing rules
                    break;
                } elseif (isset($this->_errors[$field])) {
                    // The callback added the error manually, stop checking rules
                    break;
                }

            }
        }


        if ($benchmark) {
            // Stop benchmarking
            \mii\util\Profiler::stop($benchmark);
        }

        return empty($this->_errors);
    }

    /**
     * Add an error to a field.
     *
     * @param   string $field field name
     * @param   string $error error message
     * @param   array $params
     * @return  $this
     */
    public function error($field, $error, array $params = NULL) {
        $this->_errors[$field] = $error;

        return $this;
    }

    public function add_error_message($field, $error, $message) {
        $this->_error_messages[$field][$error] = $message;
    }


    public function has_errors() {
        return \count($this->_errors);
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
     * @param mixed $translate translate the message
     * @return  array
     * @throws \Exception
     */
    public function errors($file = null, $translate = false) {
        if ($file === NULL) {
            // Return the error list
            return $this->_errors;
        }

        // Create a new message list
        $messages = [];

        foreach ($this->_errors as $field => $set) {
            if (\is_array($set)) {
                list($error, $params) = $set;
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
                $values[':value'] = implode(', ', Arr::flatten($values[':value']));
            }

            if ($params) {
                foreach ($params as $key => $value) {
                    if (\is_array($value)) {
                        // All values must be strings
                        $value = implode(', ', Arr::flatten($value));
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


            if ($message = Arr::path($this->_error_messages, "{$field}.{$error}") AND \is_string($message)) {

            } elseif ($message = Mii::message($file, "{$field}.{$error}") AND \is_string($message)) {

                // Found a message for this field and error
            } elseif ($message = Mii::message($file, "{$field}.default") AND \is_string($message)) {
                // Found a default message for this field
            } elseif ($message = Mii::message($file, $error) AND \is_string($message)) {
                // Found a default message for this error
            } elseif ($message = Mii::message('validation', $error) AND \is_string($message)) {
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
                $message = strtr($message, $values);
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
    public function errors_values() {
        return $this->_errors;
    }

}
