<?php declare(strict_types=1);

namespace mii\db;

/**
 * Database expressions can be used to add unescaped SQL fragments to a
 * [Query] object.
 *
 * @copyright  (c) 2015 Lev Morozov
 * @copyright  (c) 2009 Kohana Team
 */
class Expression
{

    // Unquoted parameters
    protected array $params;

    // Raw expression string
    protected string $value;

    /**
     * Sets the expression string.
     *
     *     $expression = new Expression('COUNT(users.id)');
     *
     * @param string $value raw SQL expression string
     * @param array $parameters unquoted parameter values
     * @return  void
     */
    public function __construct(string $value, array $parameters = [])
    {
        // Set the expression string
        $this->value = $value;
        $this->params = $parameters;
    }

    /**
     * Bind a variable to a parameter.
     *
     * @param string $param parameter key to replace
     * @param mixed  $var variable to use
     * @return  $this
     */
    public function bind(string $param, &$var)
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
     *
     * @return  string
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Return the value of the expression as a string.
     *
     *     echo $expression;
     *
     * @return  string
     */
    public function __toString()
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
            $params = \array_map([$db, 'quote'], $this->params);

            // Replace the values in the expression
            $value = \strtr($value, $params);
        }

        return $value;
    }
}
