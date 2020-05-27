<?php declare(strict_types=1);

namespace mii\log;

use mii\core\Component;
use mii\core\Exception;
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

            if (!($this->levels & $msg[1]))
                continue;

            $pass = empty($this->categories);

            if (!$pass) {
                foreach ($this->categories as $category) {
                    if ($msg[2] === $category ||
                        (\substr_compare($category, '*', -1, 1) === 0 && \strpos($msg[2], \rtrim($category, '*')) === 0)) {

                        $pass = true;
                        break;
                    }
                }
            }

            if ($pass && !empty($this->except)) {
                foreach ($this->except as $category) {
                    $prefix = \rtrim($category, '*');
                    if (($msg[2] === $category || $prefix !== $category) && strpos($msg[2], $prefix) === 0) {
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

        if (!empty($messages))
            $this->process($messages);
    }

    abstract public function process(array $messages);

    public function format_message(array $message): string
    {
        [$msg, $level, $category, $timestamp] = $message;

        $level = Logger::$level_names[$level];

        $extended = '';

        if (!\is_string($msg)) {

            if ($msg instanceof \Throwable) {

                if ($this->exceptions_extended) {

                    if (\Mii::$app instanceof App) {
                        $extended = sprintf("\n%s%s %s",
                            \Mii::$app->request->method(),
                            \Mii::$app->request->is_ajax() ? '[Ajax]' : '',
                            $_SERVER['REQUEST_URI']
                        );
                    }

                    $extended .= "\n" . \mii\util\Debug::short_text_trace($msg->getTrace());
                }

                if ($msg instanceof \mii\web\NotFoundHttpException) {
                    $msg = static::short404_exeption_text($msg);
                } else {
                    $msg = Exception::text($msg);
                }

            } else {
                $msg = var_export($msg, true);
            }
        }

        $prefix = '';

        if (\Mii::$app instanceof App) {

            $request = \Mii::$app->request;
            $ip = ($request instanceof Request) ? $request->get_ip() : '-';

            $user_id = '-';

            if (\Mii::$app->has('auth')) {
                $user = \Mii::$app->auth->get_user();
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

        return "{$this->last_date}$prefix $level $msg$extended";
    }

    public static function short404_exeption_text(\Throwable $e): string
    {
        $name = get_class($e);
        $msg = $e->getMessage();
        $file = \mii\util\Debug::path($e->getFile());

        if (strpos($file, '/src/web/App.php') !== false) {
            $file = '';
        } else {
            $file = ' ~' . $file . '[' . $e->getLine() . ']';
        }

        return $name . ': ' . $msg . $file;
    }

}
