<?php declare(strict_types=1);

namespace mii\util;

use mii\web\Request;

class HTML
{

    /**
     * @var  array  preferred order of attributes
     */
    public static array $attribute_order = [
        'action',
        'method',
        'type',
        'id',
        'name',
        'value',
        'href',
        'src',
        'width',
        'height',
        'cols',
        'rows',
        'size',
        'maxlength',
        'rel',
        'media',
        'accept',
        'tabindex',
        'alt',
        'title',
        'class',
        'style',
        'selected',
        'checked',
        'readonly',
        'disabled',
    ];

    public static array $void_elements = [
        'area' => 1,
        'base' => 1,
        'br' => 1,
        'col' => 1,
        'command' => 1,
        'embed' => 1,
        'hr' => 1,
        'img' => 1,
        'input' => 1,
        'link' => 1,
        'meta' => 1,
        'param' => 1,
        'source' => 1,
        'track' => 1,
        'wbr' => 1,
    ];

    /**
     * @var  boolean  automatically target external URLs to a new window?
     */
    public static bool $windowed_urls = false;


    /**
     * Convert special characters to HTML entities. All untrusted content
     * should be passed through this method to prevent XSS injections.
     *
     *     echo HTML::chars($username);
     *
     * @param string  $value string to convert
     * @param boolean $double_encode encode existing entities
     */
    public static function chars($value, bool $double_encode = true): string
    {
        return \htmlspecialchars((string) $value, \ENT_QUOTES, 'utf-8', $double_encode);
    }

    /**
     * Convert all applicable characters to HTML entities. All characters
     * that cannot be represented in HTML with the current character set
     * will be converted to entities.
     *
     *     echo HTML::entities($username);
     *
     * @param string  $value string to convert
     * @param boolean $double_encode encode existing entities
     */
    public static function entities($value, bool $double_encode = true): string
    {
        return \htmlentities((string) $value, \ENT_QUOTES, 'utf-8', $double_encode);
    }

    public static function tag($name, $content = '', array $attributes = null): string
    {
        $html = "<$name" . static::attributes($attributes) . '>';
        return isset(static::$void_elements[\strtolower($name)]) ? $html : "$html$content</$name>";
    }


    /**
     * Create HTML link anchors. Note that the title is not escaped, to allow
     * HTML elements within links (images, etc.)
     *
     *     echo HTML::anchor('/user/profile', 'My Profile');
     *
     * @param string $uri URL or URI string
     * @param null $title link text
     * @param array|null $attributes HTML anchor attributes
     * @param mixed|null $protocol protocol to pass to URL::base()
     */
    public static function anchor(string $uri, $title = null, array $attributes = null, mixed $protocol = null): string
    {
        if ($title === null) {
            // Use the URI as the title
            $title = $uri;
        }

        if ($uri === '') {
            // Only use the base URL
            $uri = Url::base($protocol);
        } else if (\str_contains($uri, '://')) {
            if (self::$windowed_urls === true && empty($attributes['target'])) {
                // Make the link open in a new window
                $attributes['target'] = '_blank';
            }
        } elseif ($uri[0] !== '#') {
            // Make the URI absolute for non-id anchors
            $uri = Url::site($uri, $protocol);
        }

        // Add the sanitized link to the attributes
        $attributes['href'] = $uri;

        return '<a' . self::attributes($attributes) . '>' . $title . '</a>';
    }


    /**
     * Creates a style sheet link element.
     *
     *     echo HTML::style('media/css/screen.css');
     *
     * @param string $file file name
     * @param array|null $attributes default attributes
     * @param mixed|null $protocol protocol to pass to URL::base()
     */
    public static function style(string $file, array $attributes = null, mixed $protocol = null): string
    {
        if (!\str_contains($file, '://')) {
            // Add the base URL
            $file = Url::site($file, $protocol);
        }

        // Set the stylesheet link
        $attributes['href'] = $file;

        // Set the stylesheet rel
        $attributes['rel'] = empty($attributes['rel']) ? 'stylesheet' : $attributes['rel'];

        // Set the stylesheet type
        $attributes['type'] = 'text/css';

        return '<link' . self::attributes($attributes) . ' />';
    }

    /**
     * Creates a script link.
     *
     *     echo HTML::script('media/js/jquery.min.js');
     *
     * @param string $file file name
     * @param array|null $attributes default attributes
     * @param mixed|null $protocol protocol to pass to URL::base()
     */
    public static function script(string $file, array $attributes = null, mixed $protocol = null): string
    {
        if (!\str_contains($file, '://') && !\str_starts_with($file, '//')) {
            // Add the base URL
            $file = Url::site($file, $protocol);
        }

        // Set the script link
        $attributes['src'] = $file;

        return '<script' . self::attributes($attributes) . '></script>';
    }

    /**
     * Creates an image link.
     *
     * @param string $file file name
     * @param array|null $attributes default attributes
     * @param mixed|null $protocol protocol to pass to URL::base()
     */
    public static function image(string $file, array $attributes = null, mixed $protocol = null): string
    {
        if (!\str_contains($file, '://')) {
            // Add the base URL
            $file = Url::site($file, $protocol);
        }

        // Add the image link
        $attributes['src'] = $file;

        return '<img' . self::attributes($attributes) . ' />';
    }

    /**
     * Compiles an array of HTML attributes into an attribute string.
     * Attributes will be sorted using HTML::$attribute_order for consistency.
     *
     * @param array|null $attributes attribute list
     */
    public static function attributes(array $attributes = null): string
    {
        if (empty($attributes)) {
            return '';
        }

        $sorted = [];
        foreach (self::$attribute_order as $key) {
            if (isset($attributes[$key])) {
                // Add the attribute to the sorted list
                $sorted[$key] = $attributes[$key];
            }
        }

        // Combine the sorted attributes
        $attributes = $sorted + $attributes;

        $compiled = '';
        foreach ($attributes as $key => $value) {
            if ($value === null) {
                // Skip attributes that have NULL values
                continue;
            }

            if (\is_int($key)) {
                // Assume non-associative keys are mirrored attributes
                $key = $value;
                $value = false;
            }

            // Add the attribute key
            $compiled .= ' ' . $key;

            if ($value || $value === '0') {
                // Add the attribute value
                $compiled .= '="' . self::chars($value) . '"';
            }
        }

        return $compiled;
    }

    /**
     * Generates an opening HTML form tag.
     *
     *     // Form will submit back to the current page using POST
     *     echo Form::open();
     *
     *     // Form will submit to 'search' using GET
     *     echo Form::open('search', array('method' => 'get'));
     *
     *     // When "file" inputs are present, you must include the "enctype"
     *     echo Form::open(NULL, array('enctype' => 'multipart/form-data'));
     *
     * @param mixed|null $action form action, defaults to the current request URI, or [Request] class to use
     * @param array|null $attributes html attributes
     */
    public static function open(mixed $action = null, array $attributes = null): string
    {
        if ($action instanceof Request) {
            // Use the current URI
            $action = $action->uri();
        }

        if (!$action) {
            // Allow empty form actions (submits back to the current url).
            $action = '';
        } elseif (!\str_contains((string) $action, '://')) {
            // Make the URI absolute
            $action = Url::site($action);
        }

        // Add the form action to the attributes
        $attributes['action'] = $action;

        // Only accept the default character set
        $attributes['accept-charset'] = 'utf-8';

        if (!isset($attributes['method'])) {
            // Use POST method
            $attributes['method'] = 'post';
        }

        return '<form' . self::attributes($attributes) . '>';
    }

    /**
     * Creates the closing form tag.
     */
    public static function close(): string
    {
        return '</form>';
    }

    /**
     * Creates a form input. If no type is specified, a "text" type input will
     * be returned.
     *
     *     echo Form::input('username', $username);
     *
     * @param string $name input name
     * @param null $value input value
     * @param array|null $attributes html attributes
     */
    public static function input(string $name, $value = null, array $attributes = null): string
    {
        // Set the input name
        $attributes['name'] = $name;

        // Set the input value
        $attributes['value'] = $value;

        if (!isset($attributes['type'])) {
            // Default type is text
            $attributes['type'] = 'text';
        }

        return '<input' . self::attributes($attributes) . '>';
    }

    /**
     * Creates a hidden form input.
     *
     *     echo Form::hidden('csrf', $token);
     *
     * @param string $name input name
     * @param mixed|null $value input value
     * @param array|null $attributes html attributes
     */
    public static function hidden(string $name, mixed $value = null, array $attributes = null): string
    {
        $attributes['type'] = 'hidden';

        return self::input($name, $value, $attributes);
    }

    /**
     * Creates a password form input.
     *
     *     echo Form::password('password');
     *
     * @param string $name input name
     * @param null $value input value
     * @param array|null $attributes html attributes
     */
    public static function password(string $name, $value = null, array $attributes = null): string
    {
        $attributes['type'] = 'password';

        return self::input($name, $value, $attributes);
    }

    /**
     * Creates a file upload form input. No input value can be specified.
     *
     *     echo Form::file('image');
     *
     * @param string $name input name
     * @param array|null $attributes html attributes
     */
    public static function file(string $name, array $attributes = null): string
    {
        $attributes['type'] = 'file';

        return self::input($name, null, $attributes);
    }

    /**
     * Creates a checkbox form input.
     *
     *     echo Form::checkbox('remember_me', 1, (bool) $remember);
     *
     * @param string $name input name
     * @param null $value input value
     * @param boolean $checked checked status
     * @param array|null $attributes html attributes
     */
    public static function checkbox(string $name, $value = null, bool $checked = false, array $attributes = null): string
    {
        $attributes['type'] = 'checkbox';

        if ($checked === true) {
            // Make the checkbox active
            $attributes[] = 'checked';
        }

        return self::input($name, $value, $attributes);
    }

    /**
     * Creates a radio form input.
     *
     *     echo Form::radio('like_cats', 1, $cats);
     *     echo Form::radio('like_cats', 0, !$cats);
     *
     * @param string $name input name
     * @param null $value input value
     * @param boolean $checked checked status
     * @param array|null $attributes html attributes
     */
    public static function radio(string $name, $value = null, bool $checked = false, array $attributes = null): string
    {
        $attributes['type'] = 'radio';

        if ($checked === true) {
            // Make the radio active
            $attributes[] = 'checked';
        }

        return self::input($name, $value, $attributes);
    }

    /**
     * Creates a textarea form input.
     *
     *     echo Form::textarea('about', $about);
     *
     * @param string $name textarea name
     * @param string $body textarea body
     * @param array|null $attributes html attributes
     * @param boolean $double_encode encode existing HTML characters
     */
    public static function textarea(string $name, $body = '', array $attributes = null, bool $double_encode = true): string
    {
        // Set the input name
        $attributes['name'] = $name;

        // Add default rows and cols attributes (required)
        $attributes += ['rows' => 10, 'cols' => 50];

        return '<textarea' . self::attributes($attributes) . '>' . self::chars($body, $double_encode) . '</textarea>';
    }

    /**
     * Creates a select form input.
     *
     * @param string $name input name
     * @param array|null $options available options
     * @param mixed $selected selected option string, or an array of selected options
     * @param array|null $attributes html attributes
     */
    public static function select(string $name, array $options = null, mixed $selected = null, array $attributes = null): string
    {
        // Set the input name
        $attributes['name'] = $name;

        if (\is_array($selected)) {
            // This is a multi-select, god save us!
            if(!in_array('multiple', $attributes)) {
                // TODO: Not sure if need this
                $attributes[] = 'multiple';
            }
        } elseif ($selected === null) {
            $selected = [];
        } else {
            $selected = [(string) $selected];
        }

        if (empty($options)) {
            // There are no options
            $s_options = '';
        } else {
            $r_options = [];
            foreach ($options as $value => $optName) {
                if (\is_array($optName)) {
                    // Create a new optgroup
                    $group = ['label' => $value];

                    // Create a new list of options
                    $_options = [];

                    foreach ($optName as $_value => $_name) {
                        $_value = HTML::chars($_value);

                        if (\in_array($_value, $selected)) {
                            // This option is selected
                            $_value = '"' . $_value . '" selected';
                        } else {
                            $_value = '"' . $_value . '"';
                        }

                        // Change the option to the HTML string
                        $_options[] = '<option value=' . $_value . '>' . self::chars($_name, false) . '</option>';
                    }

                    // Compile the options into a string
                    $_options = "\n" . \implode("\n", $_options) . "\n";

                    /** @noinspection RequiredAttributes */
                    $r_options[] = '<optgroup' . self::attributes($group) . '>' . $_options . '</optgroup>';
                } else {
                    // Force value to be string
                    $value = self::chars($value);

                    if (\in_array($value, $selected)) {
                        // This option is selected
                        $value = '"' . $value . '" selected';
                    } else {
                        $value = '"' . $value . '"';
                    }

                    // Change the option to the HTML string
                    $r_options[] = '<option value=' . $value . '>' . self::chars($optName, false) . '</option>';
                }
            }

            // Compile the options into a single string
            $s_options = "\n" . \implode("\n", $r_options) . "\n";
        }


        return '<select' . self::attributes($attributes) . '>' . $s_options . '</select>';
    }

    /**
     * Creates a `submit` form input.
     *
     *     echo Form::submit(NULL, 'Login');
     *
     * @param string $name input name
     * @param string $value input value
     * @param array|null $attributes html attributes
     */
    public static function submit($name, $value, array $attributes = null): string
    {
        $attributes['type'] = 'submit';

        return self::input($name, $value, $attributes);
    }


    /**
     * Creates a button form input. Note that the body of a button is NOT escaped,
     * to allow images and other HTML to be used.
     *
     * @param string $name input name
     * @param string $body input value
     * @param array|null $attributes html attributes
     */
    public static function button(string $name, $body, array $attributes = null): string
    {
        // Set the input name
        $attributes['name'] = $name;

        return '<button' . self::attributes($attributes) . '>' . $body . '</button>';
    }

    /**
     * Creates a form label. Label text is not automatically translated.
     *
     * @param string $input target input
     * @param null $text label text
     * @param array|null $attributes html attributes
     */
    public static function label(string $input, $text = null, array $attributes = null): string
    {
        if ($text === null) {
            // Use the input name as the text
            $text = \ucwords(\preg_replace('/[\W_]+/', ' ', $input));
        }

        // Set the label target
        $attributes['for'] = $input;

        return '<label' . self::attributes($attributes) . '>' . $text . '</label>';
    }


    public static function csrfMeta(): string
    {
        $token = \Mii::$app->get('request')->csrf_token();
        $name = \Mii::$app->get('request')->csrf_token_name;
        return "<meta name='csrf-token-name' content='$name'>\n<meta name='csrf-token' content='$token'>";
    }

    public static function text_avatar($name, array $attributes = []): string
    {
        $fl = '';

        if (\is_array($name)) {
            $fl = \mb_strtoupper(\mb_substr($name[0], 0, 1)) .
                \mb_strtoupper(\mb_substr($name[1], 0, 1));
            $name = $name[0] . ' ' . $name[1];
        } else {
            $words = \preg_split('/\W+/u', $name, -1, \PREG_SPLIT_NO_EMPTY);

            for ($i = 0; $i < 2; $i++) {
                if (isset($words[$i])) {
                    $fl .= \mb_strtoupper(\mb_substr($words[$i], 0, 1));
                }
            }
        }

        $color = 'hsl(' . (\crc32($name) % 360) . ', 34%, 77%)';

        $params = [
            'style' => 'background-color:' . $color,
        ];

        return static::tag('span', $fl, \array_replace($params, $attributes));
    }
}
