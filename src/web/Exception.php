<?php

namespace mii\web;


use mii\log\Logger;

class Exception extends \mii\core\Exception {


    /**
     * @var  string  error rendering view
     */
    public static $error_block = 'error_default';

    /**
     * @var  string  error view content type
     */
    public static $error_view_content_type = 'text/html';



    public static function handler(\Exception $e) {

        try
        {
            // Generate the response

            \Mii::error(Exception::text($e), 'mii');
            \Mii::flush_logs();

            if(config('debug')) {
                static::response($e)->send();
            } else {

                $class = config('error_controller');

                $code    = $e->getCode();
                $status = ($e instanceof HttpException) ? $code : 500;

                if($status === 500) {
                    for ($level = ob_get_level(); $level > 0; --$level) {
                        if (!@ob_end_clean()) {
                            ob_clean();
                        }
                    }
                }

                \Mii::$app->request->action('error_page');
                \Mii::$app->request->params(['code' => $status]);

                $response = new Response();
                $controller = new $class(\Mii::$app->request, $response);
                $controller->execute();

                $response->status($status);
                $response->send();
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

    /**
     * Get a Response object representing the exception
     *
     * @uses    Kohana_Exception::text
     * @param   Exception  $e
     * @return  Response
     */
    public static function response(\Exception $e)
    {

        try
        {
            // Get the exception information
            $class   = get_class($e);
            $code    = $e->getCode();
            $message = $e->getMessage();
            $file    = $e->getFile();
            $line    = $e->getLine();
            $trace   = $e->getTrace();


            if ( ! headers_sent())
            {
                // Make sure the proper http header is sent
                $http_header_status = ($e instanceof HTTP_Exception) ? $code : 500;
            }


            if ($e instanceof ErrorException)
            {

                /**
                 * If XDebug is installed, and this is a fatal error,
                 * use XDebug to generate the stack trace
                 */
                if (function_exists('xdebug_get_function_stack') AND $code == E_ERROR)
                {
                    $trace = array_slice(array_reverse(xdebug_get_function_stack()), 4);

                    foreach ($trace as & $frame)
                    {
                        /**
                         * XDebug pre 2.1.1 doesn't currently set the call type key
                         * http://bugs.xdebug.org/view.php?id=695
                         */
                        if ( ! isset($frame['type']))
                        {
                            $frame['type'] = '??';
                        }

                        // XDebug also has a different name for the parameters array
                        if (isset($frame['params']) AND ! isset($frame['args']))
                        {
                            $frame['args'] = $frame['params'];
                        }
                    }
                }

                if (isset(Exception::$php_errors[$code]))
                {
                    // Use the human-readable error name
                    $code = Exception::$php_errors[$code];
                }
            }

            /**
             * The stack trace becomes unmanageable inside PHPUnit.
             *
             * The error view ends up several GB in size, taking
             * serveral minutes to render.
             */
            if (defined('PHPUnit_MAIN_METHOD'))
            {
                $trace = array_slice($trace, 0, 2);
            }

            // Instantiate the error view.

            // Prepare the response object.
            $response = new Response();

            $status = ($e instanceof HttpException) ? $code : 500;
            // Set the response status
            $response->status($status);

            // Set the response headers
            $response->set_header('Content-Type', Exception::$error_view_content_type.'; charset=utf-8');

            //$response->send_headers();
            // Set the response body


           /* if(Mii::$environment === Mii::PRODUCTION) {

                if (extension_loaded('newrelic')) {

                    $text = sprintf('%s [ %s ]: %s ~ %s [ %d ]', get_class($e), $e->getCode(), strip_tags($e->getMessage()), Debug::path($e->getFile()), $e->getLine());
                    newrelic_notice_error($text, $e);
                }
                $controller = new \Controller_Error(Request::instance(), $response);

                $controller->request->action($status);
                $controller->execute();


                //$response->body(B::i(Exception::$error_block)->set(get_defined_vars()));
            } else {
                $response->body(B::i(Exception::$error_block)->set(get_defined_vars()));
            }*/

            include \Mii::path('mii').'/web/Exception/error.php';
            //$response->body();


        }
        catch (Exception $e)
        {
            /**
             * Things are going badly for us, Lets try to keep things under control by
             * generating a simpler response object.
             */
            echo Exception::text($e);
            $response = new Response;
            $response->status(500);
            $response->set_header('Content-Type', 'text/plain');
            $response->body(Exception::text($e));
            echo $response->body();
        }

        return $response;
    }


}