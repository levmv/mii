<?php declare(strict_types=1);

namespace mii\web;

class HttpException extends \RuntimeException
{
    public static array $messages = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
    ];

    public function __construct(private readonly int $statusCode = 500, $message = '', $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return self::$messages[$this->statusCode] ?? 'Error';
    }
}
