<?php declare(strict_types=1);

namespace mii\web;

class RedirectHttpException extends HttpException
{
    public function __construct(public string $url)
    {
        parent::__construct(302);
    }
}
