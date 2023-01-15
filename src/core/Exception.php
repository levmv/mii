<?php declare(strict_types=1);

namespace mii\core;


class Exception extends \Exception implements \Stringable
{
    public function __toString(): string
    {
        return static::text($this);
    }

    /**
     * Get a single line of text representing the exception:
     *
     * Error [Code]: Message ~ File [ Line ]
     *
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public static function text(\Throwable $e): string
    {
        $code = $e->getCode();

        return \sprintf(
            '%s%s: %s ~ %s[%d]',
            (new \ReflectionClass($e))->getShortName(),
            $code !== 0 ? "[$code]" : '',
            \strip_tags($e->getMessage()),
            \mii\util\Debug::path($e->getFile()),
            $e->getLine()
        );
    }
}
