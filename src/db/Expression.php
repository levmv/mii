<?php declare(strict_types=1);

namespace mii\db;

/**
 * Database expressions can be used to add unescaped SQL fragments to a
 * [Query] object.
 *
 * @copyright  (c) 2015 Lev Morozov
 * @copyright  (c) 2009 Kohana Team
 */
class Expression implements \Stringable
{
    /**
     * Sets the expression string.
     * $expression = new Expression('COUNT(users.id)');
     */
    public function __construct(
        // Raw expression string
        protected string $value,
        // Unquoted parameters_
        protected array $params = [])
    {
    }

    /**
     * Bind a variable to a parameter.
     *
     * @param string $param parameter key to replace
     * @param mixed  $var variable to use
     * @return  $this
     */
    public function bind(string $param, mixed &$var): static
    {
        $this->params[$param] =&$var;

        return $this;
    }

    /**
     * Set the value of a parameter.
     *
     * @param string $param parameter key to replace
     * @param mixed  $value value to use
     */
    public function param(string $param, mixed $value): self
    {
        $this->params[$param] = $value;

        return $this;
    }

    /**
     * Add multiple parameter values.
     *
     * @param array $params list of parameter values
     * @return  $this
     */
    public function parameters(array $params): self
    {
        $this->params = $params + $this->params;

        return $this;
    }

    /**
     * Get the expression value as a string.
     *
     *     $sql = $expression->value();
     */
    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value();
    }

    /**
     * Compile the SQL expression and return it. Replaces any parameters with
     * their given values.
     */
    public function compile(Database $db = null): string
    {
        if ($db === null) {
            $db = \Mii::$app->db;
        }

        $value = $this->value();

        if (!empty($this->params)) {
            // Quote all the parameter values
            $params = \array_map($db->quote(...), $this->params);

            // Replace the values in the expression
            $value = \strtr($value, $params);
        }

        return $value;
    }
}
