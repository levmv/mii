<?php declare(strict_types=1);

namespace mii\web;

class RedirectHttpException extends \RuntimeException
{
    public string $url = '';

    public function __construct($url)
    {
        $this->url = $url;

        parent::__construct();
    }
}
