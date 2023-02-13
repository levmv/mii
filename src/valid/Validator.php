<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

namespace mii\valid;

use Mii;
use mii\db\SelectQuery;
use mii\util\Arr;
use mii\web\UploadedFile;
use function in_array;
use function is_countable;
use function is_string;
use function is_null;
use function is_numeric;
use function explode;
use function method_exists;
use function preg_split;
use function trim;
use function ucfirst;
use function count;
use function mb_strlen;

class Validator
{
    protected bool $stopOnFirstFailure = true;

    protected ?\Closure $filterFunction = null;

    // Rules that are executed even when the value is empty
    protected array $emptyRules = ['required', 'requiredWith'];

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

            if (is_string($rules)) {
                $rules = explode('|', $rules);
            } elseif ($rules instanceof \Closure) {
                $rules = [$rules];
            }

            foreach ($rules as $rule) {
                if ($rule instanceof \Closure) {
                    $this->rules[$field][] = $rule;
                    continue;
                }

                if ($rule === 'required') {
                    $this->rules[$field][] = [$rule, ''];
                    $this->requiredFields[$field] = true;
                } else {
                    $parsed = explode(':', $rule, 2);
                    $this->rules[$field][] = [$parsed[0], $parsed[1] ?? ''];
                }
            }
        }
    }

    public function error(string $field, string $error): self
    {
        $this->_errors[$field] = $error;
        return $this;
    }

    public function filter(callable $callback): self
    {
        $this->filterFunction = $callback(...);
        return $this;
    }


    public function validate(): bool
    {
        $filter = $this->filterFunction ?: fn($field, $value) => is_string($value) ? trim($value) : $value;

        foreach ($this->rules as $field => $rules) {

            if(isset($this->data[$field])) {
                $this->data[$field] = $value = $filter($field, $this->data[$field]);
            } else {
                $value = null;
            }

            $passed = true;

            $isEmpty = !$this->validateRequired($field, $value);

            foreach ($rules as $ruleData) {

                if ($ruleData instanceof \Closure) {
                    if (!$isEmpty) {
                        $passed = \call_user_func_array($ruleData, [$this, $field, $value]);
                    }
                } else {
                    $rule = $ruleData[0];

                    if (empty($rule)) {
                        continue;
                    }

                    if ($isEmpty && !in_array($rule, $this->emptyRules)) {
                        continue;
                    }

                    $funcName = 'validate' . ucfirst($rule);
                    if (method_exists(self::class, $funcName)) { // Is it rule from default set?
                        if (!$this->$funcName($field, $value, ...$this->parseRuleParams($ruleData[1]))) {
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

    private function parseRuleParams(string $paramStr): array
    {
        if (empty($paramStr)) {
            return [];
        }

        return preg_split('~(?<!\\\)\,~', $paramStr);
    }


    public function field(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }


    public function validated(array $params = null): array
    {
        $validated = array_intersect_key($this->data, array_flip(array_keys($this->rules)));
        if ($params) {
            return Arr::only($validated, $params);
        }
        return $validated;
    }


    public function validateRequired(string $field, mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        } elseif (is_string($value) && trim($value) === '') {
            return false;
        } elseif (is_countable($value) && count($value) < 1) {
            return false;
        } elseif ($value instanceof UploadedFile) {
            return $value->isUploadedFile();
        }

        return true;
    }

    public function validateRequiredWith(string $field, mixed $value, string $anotherField): bool
    {
        if ($this->validateRequired($anotherField, $this->data[$anotherField] ?? null)) {

            return $this->validateRequired($field, $value);
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
        if (mb_strlen((string)$value) > 254) {
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
        return in_array(\strlen($number), $lengths, true);
    }

    /**
     * Checks whether a string consists of alphabetical characters, numbers, underscores and dashes only.
     */
    public function validateAlpha_dash(string $field, mixed $value): bool
    {
        return (bool)\preg_match('/^[-a-z0-9_]++$/iD', (string)$value);
    }

    /**
     * For new `Validator`, temporary under new name for this release
     */
    public function validateUnique(string $field, mixed $value, string $table, string|int $id = null, string $key = null): bool
    {
        if ($key === null) {
            $key = $field;
        }

        if ($id) {
            $res = (new SelectQuery())->select('id')->from($table)->where($key, '=', $value)->limit(2)->get();
            if ($res->count() > 1) {
                return false;
            }
            return $res->count() === 0 || ($res->column('id') !== $id);
        }

        return null === (new SelectQuery())->from($table)->where($key, '=', $value)->exists();
    }

    public function validateEqual(string $field, mixed $value, string $compareWith): bool
    {
        return isset($this->data[$compareWith]) && $this->data[$compareWith] === $value;
    }


    public function validateDate(string $field, mixed $value): bool
    {
        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        try {
            if ((!is_string($value) && !is_numeric($value)) || strtotime($value) === false) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        $date = date_parse($value);

        return checkdate($date['month'], $date['day'], $date['year']);
    }

    /**
     * Validate that an attribute matches a date format.
     *
     */
    public function validateDateFormat(string $field, mixed $value, mixed $format): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        $date = \DateTime::createFromFormat('!' . $format, $value);

        return ($date && $date->format($format) == $value);
    }


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
        }
        return mb_strlen((string)$value);
    }


    public function hasRule(string $field, string $name): bool
    {
        foreach ($this->rules[$field] as $rule) {
            if (is_array($rule) && $rule[0] === $name) {
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
                if (($message = Mii::message($file, "{$field}.{$error}")) && is_string($message)) {
                    // Found a message for this field and error
                } elseif (($message = Mii::message($file, "{$field}.default")) && is_string($message)) {
                    // Found a default message for this field
                } elseif (($message = Mii::message($file, $error)) && is_string($message)) {
                    // Found a default message for this error
                } else {

                    // No message exists, display the path expected
                    $message = "{$file}.{$field}.{$error}";
                }
            } else {
                $message = $this->_error_messages["{$field}.{$error}"]
                    ?? $this->_error_messages["{$field}.default"]
                    ?? $this->_error_messages["{$error}"]
                    ?? "{$field}.{$error}";
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
