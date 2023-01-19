<?php declare(strict_types=1);

namespace mii\log;

use mii\core\Component;
use mii\util\Debug;
use mii\web\App;
use mii\web\Request;

abstract class Target extends Component
{
    protected int $levels = Logger::ALL;

    protected array $categories = [];

    protected array $except = [];

    protected bool $exceptions_extended = true;

    private string $last_date = '';
    private int $last_time = 0;

    protected function filter(array $messages): array
    {
        foreach ($messages as $i => $msg) {
            if (!($this->levels & $msg[1])) {
                unset($messages[$i]);
                continue;
            }

            $pass = empty($this->categories);

            if (!$pass) {
                foreach ($this->categories as $category) {
                    if ($msg[2] === $category ||
                        (str_ends_with($category, '*') && \str_starts_with($msg[2], \rtrim($category, '*')))) {
                        $pass = true;
                        break;
                    }
                }
            }

            if ($pass && !empty($this->except)) {
                foreach ($this->except as $category) {
                    $prefix = \rtrim($category, '*');
                    if (($msg[2] === $category || $prefix !== $category) && \str_starts_with($msg[2], $prefix)) {
                        $pass = false;
                        break;
                    }
                }
            }

            if (!$pass) {
                unset($messages[$i]);
            }
        }

        return $messages;
    }

    public function collect(array $messages): void
    {
        $messages = $this->filter($messages);

        if (!empty($messages)) {
            $this->process($messages);
        }
    }

    abstract public function process(array $messages): void;

    /** @noinspection PhpFullyQualifiedNameUsageInspection */
    public function formatMessage(array $message): string
    {
        [$msg, $level, $category, $timestamp] = $message;

        $level = Logger::$level_names[$level];

        $extended = '';

        if (!\is_string($msg)) {
            if ($msg instanceof \Throwable) {
                if ($this->exceptions_extended) {
                    if (\Mii::$app instanceof App) {
                        $extended = \sprintf(
                            "\n%s%s %s",
                            \Mii::$app->request->method(),
                            \Mii::$app->request->isAjax() ? '[Ajax]' : '',
                            $_SERVER['REQUEST_URI']
                        );
                    }

                    $extended .= "\n" . Debug::shortTextTrace($msg->getTrace());
                }

                if ($msg instanceof \mii\web\NotFoundHttpException) {
                    $msg = static::shortExceptionText($msg);
                } else {
                    $msg = Debug::exceptionToText($msg);
                }
            } else {
                $msg = \var_export($msg, true);
            }
        }

        $prefix = '';

        if (\Mii::$app instanceof App) {
            $request = \Mii::$app->request;
            $ip = ($request instanceof Request) ? $request->getIp() : '-';

            $user_id = '-';

            if (\Mii::$app->has('auth', true)) {
                $user = \Mii::$app->auth->getUser(false);
                if ($user !== null) {
                    $user_id = $user->id;
                }
            }
            $prefix = " $ip $user_id";
        }

        if ($timestamp !== $this->last_time) {
            $this->last_time = $timestamp;
            $this->last_date = \date('y-m-d H:i:s', $timestamp);
        }

        return "$this->last_date$prefix $level $msg$extended";
    }

    /** @noinspection PhpFullyQualifiedNameUsageInspection */
    public static function shortExceptionText(\Throwable $e): string
    {
        $name = $e::class;
        $msg = $e->getMessage();
        $file = Debug::path($e->getFile());

        if (\str_contains($file, '{mii}')) {
            $file = '';
        } else {
            $file = ' ~' . $file . '[' . $e->getLine() . ']';
        }

        return $name . ': ' . $msg . $file;
    }
}
