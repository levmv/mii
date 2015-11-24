<?php

namespace mii\console;

use mii\core\Exception;

class CliException extends \mii\core\Exception {

    public function __construct($message = "", array $variables = NULL, $code = 0) {
        parent::__construct($message, $variables, $code);
    }



    public static function handler(\Exception $e) {

        try
        {
            // Generate the response

            \Mii::error(Exception::text($e), 'mii');
            \Mii::flush_logs();

            if(config('debug')) {
                echo Exception::text($e);
            } else {

                $class = config('error_controller');

                $code    = $e->getCode();
                $status = ($e instanceof HttpException) ? $code : 500;

                if($status == 500) {
                    for ($level = ob_get_level(); $level > 0; --$level) {
                        if (!@ob_end_clean()) {
                            ob_clean();
                        }
                    }
                }

                echo Exception::text($e);

            }

            exit(1);
        }
        catch (\Exception $e)
        {

            /**
             * Things are going *really* badly for us, We now have no choice
             * but to bail. Hard.
             */
            // Clean the output buffer if one exists
            ob_get_level() AND ob_clean();

            // Set the Status code to 500, and Content-Type to text/plain.
            header('Content-Type: text/plain; charset=utf-8', TRUE, 500);

            $text = Exception::text($e);

            //\Mii::log(Logger::CRITICAL, $text, 'mii');
            //\Mii::flush_logs();

            echo $text;

            exit(1);
        }


    }

}
