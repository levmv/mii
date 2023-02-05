<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

namespace mii\valid;

use Mii;
use mii\db\SelectQuery;
use mii\util\Arr;
use mii\web\UploadedFile;

class Validator
{
    protected bool $stopOnFirstFailure = true;

    // Rules that are executed even when the value is empty
    protected array $emptyRules = ['required'];

    protected array $rules = [];

    protected array $requiredFields = [];

    // Error list (field => rule)
    protected array $_errors = [];

    // Error messages list (field => rule => message)
    protected array $_error_messages = [];

    protected array $data = [];


    public function __construct(array $data = [], array $rules = [], array $messages = [])
    {
        $this->setData($data);
        $this->setRules($rules);
        $this->setMessages($messages);
    }

    public function setData(array $array): void
    {
        $this->data = $array;
    }

    public function setRules(array $inputRules): void
    {
        foreach ($inputRules as $field => $rules) {

            if (isset($this->rules[$field])) {
                $this->rules[$field] = [];
            }

            if ($rules instanceof \Closure) {
                $this->rules[$field][] = $rules;
                continue;
            }

            $rules = explode('|', $rules);
            foreach ($rules as $rule) {
                if ($rule === 'required') {
                    $this->requiredFields[$field] = true;
                }
                $this->rules[$field][] = explode(':', $rule);
            }
        }
    }

    public function error(string $field, string $error): self
    {
        $this->_errors[$field] = $error;
        return $this;
    }


    public function validate(): bool
    {
        foreach ($this->rules as $field => $rules) {
            $value = $this->data[$field] ?? null;
            $passed = true;

            foreach ($rules as $ruleData) {

                if (!is_array($ruleData) && is_callable($ruleData)) {
                    $passed = \call_user_func_array($ruleData, [$this, $field, $value]);
                } else {
                    $rule = array_shift($ruleData);

                    if (!$this->validateRequired($field, $value) && !in_array($rule, $this->emptyRules)) {
                        continue;
                    }

                    $funcName = 'validate' . ucfirst($rule);
                    if (\method_exists(__CLASS__, $funcName)) { // Is it rule from default set?
                        if (!$this->$funcName($field, $value, ...$ruleData)) {
                            $passed = false;
                            $this->error($field, $rule);
                        }
                    } else {
                        throw new \RuntimeException("Unknown validator $rule");
                    }
                    if ($passed === false && $rule === 'required') {
                        // Stop pass through rules if required validation was failed
                        break;
                    }
                }
            }
            if ($passed === false && $this->stopOnFirstFailure) {
                break;
            }
        }
        return !$this->hasErrors();
    }


    public function validated(): array
    {
        return array_intersect_key(array_flip(array_keys($this->rules)), array_filter($this->data));
    }


    public function validateRequired(string $field, mixed $value): bool
    {
        if (\is_null($value)) {
            return false;
        } elseif (\is_string($value) && \trim($value) === '') {
            return false;
        } elseif (\is_countable($value) && \count($value) < 1) {
            return false;
        } elseif ($value instanceof UploadedFile) {
            return $value->isUploadedFile();
        }

        return true;
    }

    public function validateNumeric(string $field, mixed $value): bool
    {
        return is_numeric($value);
    }

    public function validateMax(string $field, mixed $value, mixed $limit): bool
    {
        $size = $this->getSize($field, $value);
        if (is_numeric($size)) {
            $size = 0 + $size;
        }
        return $size <= 0 + $limit;

    }

    public function validateMin(string $field, mixed $value, mixed $limit): bool
    {
        $size = $this->getSize($field, $value);

        if (is_numeric($size)) {
            $size = 0 + $size;
        }

        return $size >= 0 + $limit;
    }

    public function validateBetween(string $field, mixed $value, mixed $from, mixed $to): bool
    {
        $size = $this->getSize($field, $value);
        if (is_numeric($size)) {
            $size = 0 + $size;
        }

        if ($size <= 0 + $from || $size >= 0 + $to) {
            return false;
        }

        return true;
    }

    public function validateEmail(string $field, mixed $value): bool
    {
        if (\mb_strlen((string)$value) > 254) {
            return false;
        }
        $expression = '/^[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})$/iD';

        return (bool)\preg_match($expression, $value);
    }

    public function validateArray(string $field, mixed $value): bool
    {
        return is_array($value);
    }

    public function validatePhone(string $field, mixed $value, array $lengths = null): bool
    {
        if (!$lengths) {
            $lengths = [7, 10, 11];
        }

        // Remove all non-digit characters from the number
        $number = \preg_replace('/\D+/', '', $value);

        // Check if the number is within range
        return \in_array(\strlen($number), $lengths, true);
    }

    /**
     * Checks whether a string consists of alphabetical characters, numbers, underscores and dashes only.
     */
    public static function validateAlpha_dash(string $field, mixed $value): bool
    {
        return (bool)\preg_match('/^[-a-z0-9_]++$/iD', (string)$value);
    }

    /**
     * For new `Validator`, temporary under new name for this release
     */
    public static function validateUnique(string $field, mixed $value, string $table, string $key = null, string|int $id = null): bool
    {
        if ($key === null) {
            $key = $field;
        }

        if ($id) {
            $res = (new SelectQuery())->from($table)->where($key, '=', $value)->one();
            return (null === $res) || ($res->id === $id);
        }

        return null === (new SelectQuery())->from($table)->where($key, '=', $value)->one();
    }


    /**
     * Get the size of a field.
     *
     * @param string $field
     * @param mixed $value
     * @return int|float|string
     */
    protected function getSize(string $field, mixed $value): int|float|string
    {
        $hasNumeric = $this->hasRule($field, 'numeric');

        if (is_numeric($value) && $hasNumeric) {
            return is_string($value) ? trim($value) : $value;
        } elseif (is_array($value)) {
            return count($value);
        } elseif ($value instanceof UploadedFile) {
            return $value->size;
        } elseif (is_null($value)) {
            return 0;
        };
        return mb_strlen((string)$value);
    }


    public function hasRule(string $field, string $name): bool
    {
        foreach ($this->rules[$field] as $rule) {
            if ($rule[0] === $name) {
                return true;
            }
        }
        return false;
    }

    public function isFieldRequired(string $field): bool
    {
        return isset($this->requiredFields[$field]);
    }


    public function hasErrors(): bool
    {
        return !empty($this->_errors);
    }

    /**
     * Returns the error messages. If no file is specified, the error message
     * will be in the format `field.rule`. When a file is specified, the
     * message will be loaded from "field/rule", or if no rule-specific message
     * exists, "field/default" will be used. If neither is set, the returned
     * message will be "file/field.rule".
     *
     * By default, all messages used as is. If second argument is true then "__" function
     * will be used for translation.
     *
     * @param string|null $file file to load error messages from
     * @param mixed $translate translate the message
     * @throws \Exception
     * @noinspection PhpUndefinedFunctionInspection
     */
    public function errors(string $file = null, bool $translate = false): array
    {
        /* if ($file === null && empty($this->_error_messages)) {
             // Nothing to do, so just return the error list
             return $this->_errors;
         }*/

        // Create a new message list
        $messages = [];

        foreach ($this->_errors as $field => $error) {

            $label = $field;

            if ($translate) {
                // Translate the label
                $label = __($label);
            }

            // Start the translation values list
            $values = [
                ':field' => $label,
            ];

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

    public function setMessages(array $messages): void
    {
        $this->_error_messages = $messages;
    }


    public function addMessage(string $field, string $rule, string $message): void
    {
        $this->_error_messages["{$field}.{$rule}"] = $message;
    }

    /**
     * Returns the error values.
     */
    public function errorsValues(): array
    {
        return $this->_errors;
    }
}
