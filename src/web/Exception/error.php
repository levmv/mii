<?php declare(strict_types=1);

use mii\util\Debug;
use mii\util\HTML;

$error_id = uniqid('error');

/**
 * @var Exception $exception
 */
?>
<style>
    #mii_error {
        background: #ddd;
        font-size: 1em;
        font-family: sans-serif;
        text-align: left;
        color: #111;
    }

    #mii_error h1,
    #mii_error h2 {
        margin: 0;
        padding: 1em;
        font-size: 1em;
        font-weight: normal;
        background: #911;
        color: #fff;
    }

    #mii_error h1 a,
    #mii_error h2 a {
        color: #fff;
    }

    #mii_error h2 {
        background: #222;
    }

    #mii_error h3 {
        margin: 0;
        padding: 0.4em 0 0;
        font-size: 1em;
        font-weight: normal;
    }

    #mii_error p {
        margin: 0;
        padding: 0.2em 0;
    }

    #mii_error a {
        color: #1b323b;
    }

    #mii_error pre {
        overflow: auto;
        white-space: pre-wrap;
    }

    #mii_error table {
        width: 100%;
        display: block;
        margin: 0 0 0.4em;
        padding: 0;
        border-collapse: collapse;
        background: #fff;
    }

    #mii_error table td {
        border: solid 1px #ddd;
        text-align: left;
        vertical-align: top;
        padding: 0.4em;
    }

    #mii_error div.content {
        padding: 0.4em 1em 1em;
        overflow: hidden;
    }

    #mii_error pre.source {
        margin: 0 0 1em;
        padding: 0.4em;
        background: #fff;
        border: dotted 1px #b7c680;
        line-height: 1.2em;
    }

    #mii_error pre.source span.line {
        display: block;
    }

    #mii_error pre.source span.highlight {
        background: #f0eb96;
    }

    #mii_error pre.source span.line span.number {
        color: #666;
    }

    #mii_error ol.trace {
        display: block;
        margin: 0 0 0 2em;
        padding: 0;
        list-style: decimal;
    }

    #mii_error ol.trace li {
        margin: 0;
        padding: 0;
    }

    .js .collapsed {
        display: none;
    }
</style>
<script type="text/javascript">
    document.documentElement.className = document.documentElement.className + ' js';

    function koggle(elem) {
        elem = document.getElementById(elem);

        let disp = document.defaultView.getComputedStyle(elem, null).getPropertyValue('display');

        // Toggle the state of the "display" style
        elem.style.display = disp === 'block' ? 'none' : 'block';
        return false;
    }
</script>
<div id="mii_error">
    <?php
    $ex = 0;
    do {
        $ex++;
        $class = $exception instanceof \mii\core\ErrorException ? $exception->getName() : $exception::class;
        $code = $exception->getCode();
        $message = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $exception->getTrace(); ?>

        <h1><span class="type"><?php echo $class ?> [ <?php echo $code ?> ]:</span> <span
                    class="message"><?php echo HTML::chars($message) ?></span></h1>
        <div id="<?php echo $error_id ?>" class="content">
            <p><span class="file"><?php echo Debug::path($file) ?> [ <?php echo $line ?> ]</span></p>
            <?php echo Debug::source($file, $line) ?>
            <ol class="trace">
                <?php foreach (Debug::trace($trace) as $i => $step): ?>
                    <li>
                        <p>
					<span class="file">
						<?php if ($step['file']): $source_id = $error_id . 'source' . $ex . '_' . $i; ?>
                            <a href="#<?php echo $source_id ?>"
                               onclick="return koggle('<?php echo $source_id ?>')"><?php echo Debug::path($step['file']) ?>
                                [ <?php echo $step['line'] ?> ]</a>
                        <?php else: ?>
                            {<?php echo 'PHP internal call' ?>}
                        <?php endif ?>
					</span>
                            &raquo;
                            <?php echo $step['function'] ?>
                            (<?php if ($step['args']): $args_id = $error_id . 'args' . $i; ?>
                                <a href="#<?php echo $args_id ?>"
                                   onclick="return koggle('<?php echo $args_id ?>')"><?php echo 'arguments' ?></a><?php endif ?>
                            )
                        </p>
                        <?php if (isset($args_id)): ?>
                            <div id="<?php echo $args_id ?>" class="collapsed">
                                <table>
                                    <?php foreach ($step['args'] as $name => $arg): ?>
                                        <tr>
                                            <td><code><?php echo $name ?></code></td>
                                            <td>
                                                <pre><?php echo Debug::dump($arg) ?></pre>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                </table>
                            </div>
                        <?php endif ?>
                        <?php if (isset($source_id)): ?>
                            <pre id="<?php echo $source_id ?>"
                                 class="source collapsed"><code><?php echo $step['source'] ?></code></pre>
                        <?php endif ?>
                    </li>
                    <?php unset($args_id, $source_id); ?>
                <?php endforeach ?>
            </ol>
        </div>
    <?php
    } while (null !== ($exception = $exception->getPrevious())); ?>

    <h2><a href="#<?php echo $env_id = $error_id . 'environment' ?>"
           onclick="return koggle('<?php echo $env_id ?>')"><?php echo 'Environment' ?></a></h2>
    <div id="<?php echo $env_id ?>" class="content collapsed">
        <?php $included = get_included_files() ?>
        <h3><a href="#<?php echo $env_id = $error_id . 'environment_included' ?>"
               onclick="return koggle('<?php echo $env_id ?>')"><?php echo 'Included files' ?></a>
            (<?php echo count($included) ?>)</h3>
        <div id="<?php echo $env_id ?>" class="collapsed">
            <table>
                <?php foreach ($included as $file): ?>
                    <tr>
                        <td><code><?php echo Debug::path($file) ?></code></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </div>
        <?php $included = get_loaded_extensions() ?>
        <h3><a href="#<?php echo $env_id = $error_id . 'environment_loaded' ?>"
               onclick="return koggle('<?php echo $env_id ?>')"><?php echo 'Loaded extensions' ?></a>
            (<?php echo count($included) ?>)</h3>
        <div id="<?php echo $env_id ?>" class="collapsed">
            <table>
                <?php foreach ($included as $file): ?>
                    <tr>
                        <td><code><?php echo Debug::path($file) ?></code></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </div>
        <?php foreach (['_SESSION', '_GET', '_POST', '_FILES', '_COOKIE', '_SERVER'] as $var): ?>
            <?php if (empty($GLOBALS[$var]) or !is_array($GLOBALS[$var])) {
        continue;
    } ?>
            <h3><a href="#<?php echo $env_id = $error_id . 'environment' . strtolower($var) ?>"
                   onclick="return koggle('<?php echo $env_id ?>')">$<?php echo $var ?></a></h3>
            <div id="<?php echo $env_id ?>" class="collapsed">
                <table>
                    <?php foreach ($GLOBALS[$var] as $key => $value): ?>
                        <tr>
                            <td><code><?php echo HTML::chars($key) ?></code></td>
                            <td>
                                <pre><?php echo Debug::dump($value) ?></pre>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </table>
            </div>
        <?php endforeach ?>
    </div>
</div>
