<?php declare(strict_types=1);

namespace mii\web;

class HttpException extends Exception
{
    public mixed $status_code = 500;

    public static array $messages = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
    ];

    public function __construct($status = 500, $message = '', $code = 0, \Exception $previous = null)
    {
        $this->status_code = $status;

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return self::$messages[$this->status_code] ?? 'Error';
    }
}
