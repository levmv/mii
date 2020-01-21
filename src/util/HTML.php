<?php

namespace mii\util;

use mii\web\Form;
use mii\web\Request;

class HTML
{

    /**
     * @var  array  preferred order of attributes
     */
    public static $attribute_order = [
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
        'accept-charset',
        'accept',
        'tabindex',
        'accesskey',
        'alt',
        'title',
        'class',
        'style',
        'selected',
        'checked',
        'readonly',
        'disabled',
    ];

    public static $void_elements = [
        'area' => 1,
        'base' => 1,
        'br' => 1,
        'col' => 1,
        'command' => 1,
        'embed' => 1,
        'hr' => 1,
        'img' => 1,
        'input' => 1,
        'keygen' => 1,
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
    public static $windowed_urls = FALSE;


    /**
     * Convert special characters to HTML entities. All untrusted content
     * should be passed through this method to prevent XSS injections.
     *
     *     echo HTML::chars($username);
     *
     * @param   string $value string to convert
     * @param   boolean $double_encode encode existing entities
     * @return  string
     */
    public static function chars($value, $double_encode = TRUE) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'utf-8', $double_encode);
    }

    /**
     * Convert all applicable characters to HTML entities. All characters
     * that cannot be represented in HTML with the current character set
     * will be converted to entities.
     *
     *     echo HTML::entities($username);
     *
     * @param   string $value string to convert
     * @param   boolean $double_encode encode existing entities
     * @return  string
     */
    public static function entities($value, $double_encode = TRUE) {
        return htmlentities((string)$value, ENT_QUOTES, 'utf-8', $double_encode);
    }

    public static function tag($name, $content = '', array $attributes = null) {
        $html = "<$name" . static::attributes($attributes) . '>';
        return isset(static::$void_elements[strtolower($name)]) ? $html : "$html$content</$name>";
    }


    /**
     * Create HTML link anchors. Note that the title is not escaped, to allow
     * HTML elements within links (images, etc).
     *
     *     echo HTML::anchor('/user/profile', 'My Profile');
     *
     * @param   string $uri URL or URI string
     * @param   string $title link text
     * @param   array $attributes HTML anchor attributes
     * @param   mixed $protocol protocol to pass to URL::base()
     * @param   boolean $index include the index page
     * @return  string
     * @uses    URL::base
     * @uses    URL::site
     * @uses    HTML::attributes
     */
    public static function anchor($uri, $title = NULL, array $attributes = NULL, $protocol = NULL) {
        if ($title === NULL) {
            // Use the URI as the title
            $title = $uri;
        }

        if ($uri === '') {
            // Only use the base URL
            $uri = URL::base($protocol);
        } else {
            if (strpos($uri, '://') !== FALSE) {
                if (HTML::$windowed_urls === TRUE AND empty($attributes['target'])) {
                    // Make the link open in a new window
                    $attributes['target'] = '_blank';
                }
            } elseif ($uri[0] !== '#') {
                // Make the URI absolute for non-id anchors
                $uri = URL::site($uri, $protocol);
            }
        }

        // Add the sanitized link to the attributes
        $attributes['href'] = $uri;

        return '<a' . HTML::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Creates an HTML anchor to a file. Note that the title is not escaped,
     * to allow HTML elements within links (images, etc).
     *
     *     echo HTML::file_anchor('media/doc/user_guide.pdf', 'User Guide');
     *
     * @param   string $file name of file to link to
     * @param   string $title link text
     * @param   array $attributes HTML anchor attributes
     * @param   mixed $protocol protocol to pass to URL::base()
     * @param   boolean $index include the index page
     * @return  string
     * @uses    URL::base
     * @uses    HTML::attributes
     */
    public static function file_anchor($file, $title = NULL, array $attributes = NULL, $protocol = NULL) {
        if ($title === NULL) {
            // Use the file name as the title
            $title = basename($file);
        }

        // Add the file link to the attributes
        $attributes['href'] = URL::site($file, $protocol);

        return '<a' . HTML::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Creates an email (mailto:) anchor. Note that the title is not escaped,
     * to allow HTML elements within links (images, etc).
     *
     *     echo HTML::mailto($address);
     *
     * @param   string $email email address to send to
     * @param   string $title link text
     * @param   array $attributes HTML anchor attributes
     * @return  string
     * @uses    HTML::attributes
     */
    public static function mailto($email, $title = NULL, array $attributes = NULL) {
        if ($title === NULL) {
            // Use the email address as the title
            $title = $email;
        }

        return '<a href="&#109;&#097;&#105;&#108;&#116;&#111;&#058;' . $email . '"' . HTML::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Creates a style sheet link element.
     *
     *     echo HTML::style('media/css/screen.css');
     *
     * @param   string $file file name
     * @param   array $attributes default attributes
     * @param   mixed $protocol protocol to pass to URL::base()
     * @param   boolean $index include the index page
     * @return  string
     * @uses    URL::base
     * @uses    HTML::attributes
     */
    public static function style($file, array $attributes = NULL, $protocol = NULL) {
        if (strpos($file, '://') === FALSE) {
            // Add the base URL
            $file = URL::site($file, $protocol);
        }

        // Set the stylesheet link
        $attributes['href'] = $file;

        // Set the stylesheet rel
        $attributes['rel'] = empty($attributes['rel']) ? 'stylesheet' : $attributes['rel'];

        // Set the stylesheet type
        $attributes['type'] = 'text/css';

        return '<link' . HTML::attributes($attributes) . ' />';
    }

    /**
     * Creates a script link.
     *
     *     echo HTML::script('media/js/jquery.min.js');
     *
     * @param   string $file file name
     * @param   array $attributes default attributes
     * @param   mixed $protocol protocol to pass to URL::base()
     * @param   boolean $index include the index page
     * @return  string
     * @uses    URL::base
     * @uses    HTML::attributes
     */
    public static function script($file, array $attributes = NULL, $protocol = NULL) {
        if (strpos($file, '://') === FALSE && strpos($file, '//') !== 0) {
            // Add the base URL
            $file = URL::site($file, $protocol);
        }

        // Set the script link
        $attributes['src'] = $file;

        // Set the script type
        $attributes['type'] = 'text/javascript';

        return '<script' . HTML::attributes($attributes) . '></script>';
    }

    /**
     * Creates a image link.
     *
     *     echo HTML::image('media/img/logo.png', array('alt' => 'My Company'));
     *
     * @param   string $file file name
     * @param   array $attributes default attributes
     * @param   mixed $protocol protocol to pass to URL::base()
     * @param   boolean $index include the index page
     * @return  string
     * @uses    URL::base
     * @uses    HTML::attributes
     */
    public static function image($file, array $attributes = NULL, $protocol = NULL, $index = FALSE) {
        if (strpos($file, '://') === FALSE) {
            // Add the base URL
            $file = URL::site($file, $protocol, $index);
        }

        // Add the image link
        $attributes['src'] = $file;

        return '<img' . HTML::attributes($attributes) . ' />';
    }

    /**
     * Compiles an array of HTML attributes into an attribute string.
     * Attributes will be sorted using HTML::$attribute_order for consistency.
     *
     *     echo '<div'.HTML::attributes($attrs).'>'.$content.'</div>';
     *
     * @param   array $attributes attribute list
     * @return  string
     */
    public static function attributes(array $attributes = NULL) {
        if (empty($attributes))
            return '';

        $sorted = array();
        foreach (HTML::$attribute_order as $key) {
            if (isset($attributes[$key])) {
                // Add the attribute to the sorted list
                $sorted[$key] = $attributes[$key];
            }
        }

        // Combine the sorted attributes
        $attributes = $sorted + $attributes;

        $compiled = '';
        foreach ($attributes as $key => $value) {
            if ($value === NULL) {
                // Skip attributes that have NULL values
                continue;
            }

            if (\is_int($key)) {
                // Assume non-associative keys are mirrored attributes
                $key = $value;
                $value = FALSE;
            }

            // Add the attribute key
            $compiled .= ' ' . $key;

            if ($value OR $value === '0') {
                // Add the attribute value
                $compiled .= '="' . HTML::chars($value) . '"';
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
     * @param   mixed $action form action, defaults to the current request URI, or [Request] class to use
     * @param   array $attributes html attributes
     * @return  string
     * @uses    Request::instance
     * @uses    URL::site
     * @uses    HTML::attributes
     */
    public static function open($action = NULL, array $attributes = NULL) {
        if ($action instanceof Request) {
            // Use the current URI
            $action = $action->uri();
        }

        if (!$action) {
            // Allow empty form actions (submits back to the current url).
            $action = '';
        } elseif (strpos($action, '://') === FALSE) {
            // Make the URI absolute
            $action = URL::site($action);
        }

        // Add the form action to the attributes
        $attributes['action'] = $action;

        // Only accept the default character set
        $attributes['accept-charset'] = 'utf-8';

        if (!isset($attributes['method'])) {
            // Use POST method
            $attributes['method'] = 'post';
        }

        return '<form' . HTML::attributes($attributes) . '>';
    }

    /**
     * Creates the closing form tag.
     *
     *     echo Form::close();
     *
     * @return  string
     */
    public static function close() {
        return '</form>';
    }

    /**
     * Creates a form input. If no type is specified, a "text" type input will
     * be returned.
     *
     *     echo Form::input('username', $username);
     *
     * @param   string $name input name
     * @param   string $value input value
     * @param   array $attributes html attributes
     * @return  string
     * @uses    HTML::attributes
     */
    public static function input($name, $value = NULL, array $attributes = NULL) {
        // Set the input name
        $attributes['name'] = $name;

        // Set the input value
        $attributes['value'] = $value;

        if (!isset($attributes['type'])) {
            // Default type is text
            $attributes['type'] = 'text';
        }

        return '<input' . HTML::attributes($attributes) . '>';
    }

    /**
     * Creates a hidden form input.
     *
     *     echo Form::hidden('csrf', $token);
     *
     * @param   string $name input name
     * @param   string $value input value
     * @param   array $attributes html attributes
     * @return  string
     * @uses    Form::input
     */
    public static function hidden($name, $value = NULL, array $attributes = NULL) {
        $attributes['type'] = 'hidden';

        return HTML::input($name, $value, $attributes);
    }

    /**
     * Creates a password form input.
     *
     *     echo Form::password('password');
     *
     * @param   string $name input name
     * @param   string $value input value
     * @param   array $attributes html attributes
     * @return  string
     * @uses    Form::input
     */
    public static function password($name, $value = NULL, array $attributes = NULL) {
        $attributes['type'] = 'password';

        return HTML::input($name, $value, $attributes);
    }

    /**
     * Creates a file upload form input. No input value can be specified.
     *
     *     echo Form::file('image');
     *
     * @param   string $name input name
     * @param   array $attributes html attributes
     * @return  string
     * @uses    Form::input
     */
    public static function file($name, array $attributes = NULL) {
        $attributes['type'] = 'file';

        return HTML::input($name, NULL, $attributes);
    }

    /**
     * Creates a checkbox form input.
     *
     *     echo Form::checkbox('remember_me', 1, (bool) $remember);
     *
     * @param   string $name input name
     * @param   string $value input value
     * @param   boolean $checked checked status
     * @param   array $attributes html attributes
     * @return  string
     * @uses    Form::input
     */
    public static function checkbox($name, $value = NULL, $checked = FALSE, array $attributes = NULL) {
        $attributes['type'] = 'checkbox';

        if ($checked === TRUE) {
            // Make the checkbox active
            $attributes[] = 'checked';
        }

        return HTML::input($name, $value, $attributes);
    }

    /**
     * Creates a radio form input.
     *
     *     echo Form::radio('like_cats', 1, $cats);
     *     echo Form::radio('like_cats', 0, ! $cats);
     *
     * @param   string $name input name
     * @param   string $value input value
     * @param   boolean $checked checked status
     * @param   array $attributes html attributes
     * @return  string
     * @uses    Form::input
     */
    public static function radio($name, $value = NULL, $checked = FALSE, array $attributes = NULL) {
        $attributes['type'] = 'radio';

        if ($checked === TRUE) {
            // Make the radio active
            $attributes[] = 'checked';
        }

        return HTML::input($name, $value, $attributes);
    }

    /**
     * Creates a textarea form input.
     *
     *     echo Form::textarea('about', $about);
     *
     * @param   string $name textarea name
     * @param   string $body textarea body
     * @param   array $attributes html attributes
     * @param   boolean $double_encode encode existing HTML characters
     * @return  string
     * @uses    HTML::attributes
     * @uses    HTML::chars
     */
    public static function textarea($name, $body = '', array $attributes = NULL, $double_encode = TRUE) {
        // Set the input name
        $attributes['name'] = $name;

        // Add default rows and cols attributes (required)
        $attributes += array('rows' => 10, 'cols' => 50);

        return '<textarea' . HTML::attributes($attributes) . '>' . HTML::chars($body, $double_encode) . '</textarea>';
    }

    /**
     * Creates a select form input.
     *
     *     echo Form::select('country', $countries, $country);
     *
     * [!!] Support for multiple selected options was added in v3.0.7.
     *
     * @param   string $name input name
     * @param   array $options available options
     * @param   mixed $selected selected option string, or an array of selected options
     * @param   array $attributes html attributes
     * @return  string
     * @uses    HTML::attributes
     */
    public static function select($name, array $options = NULL, $selected = NULL, array $attributes = NULL) {
        // Set the input name
        $attributes['name'] = $name;


        if (\is_array($selected)) {
            // This is a multi-select, god save us!
            $attributes[] = 'multiple';
        }

        if (!\is_array($selected)) {
            if ($selected === NULL) {
                // Use an empty array
                $selected = array();
            } else {
                // Convert the selected options to an array
                $selected = array((string)$selected);
            }
        }

        if (empty($options)) {
            // There are no options
            $options = '';
        } else {
            foreach ($options as $value => $name) {
                if (\is_array($name)) {
                    // Create a new optgroup
                    $group = array('label' => $value);

                    // Create a new list of options
                    $_options = array();

                    foreach ($name as $_value => $_name) {
                        $_value = HTML::chars($_value);

                        if (\in_array($_value, $selected)) {
                            // This option is selected
                            $_value = '"' . $_value . '" selected';
                        } else {
                            $_value = '"' . $_value . '"';
                        }

                        // Change the option to the HTML string
                        $_options[] = '<option value=' . $_value . '>' . HTML::chars($_name, FALSE) . '</option>';
                    }

                    // Compile the options into a string
                    $_options = "\n" . implode("\n", $_options) . "\n";

                    $r_options[] = '<optgroup' . HTML::attributes($group) . '>' . $_options . '</optgroup>';
                } else {
                    // Force value to be string
                    $value = HTML::chars($value);

                    if (\in_array($value, $selected)) {
                        // This option is selected
                        $value = '"' . $value . '" selected';
                    } else {
                        $value = '"' . $value . '"';
                    }

                    // Change the option to the HTML string
                    $r_options[] = '<option value=' . $value . '>' . HTML::chars($name, FALSE) . '</option>';
                }
            }

            // Compile the options into a single string
            $options = "\n" . implode("\n", $r_options) . "\n";
        }


        return '<select' . HTML::attributes($attributes) . '>' . $options . '</select>';
    }

    /**
     * Creates a submit form input.
     *
     *     echo Form::submit(NULL, 'Login');
     *
     * @param   string $name input name
     * @param   string $value input value
     * @param   array $attributes html attributes
     * @return  string
     * @uses    Form::input
     */
    public static function submit($name, $value, array $attributes = NULL) {
        $attributes['type'] = 'submit';

        return HTML::input($name, $value, $attributes);
    }


    /**
     * Creates a button form input. Note that the body of a button is NOT escaped,
     * to allow images and other HTML to be used.
     *
     *     echo Form::button('save', 'Save Profile', array('type' => 'submit'));
     *
     * @param   string $name input name
     * @param   string $body input value
     * @param   array $attributes html attributes
     * @return  string
     * @uses    HTML::attributes
     */
    public static function button($name, $body, array $attributes = NULL) {
        // Set the input name
        $attributes['name'] = $name;

        return '<button' . HTML::attributes($attributes) . '>' . $body . '</button>';
    }

    /**
     * Creates a form label. Label text is not automatically translated.
     *
     *     echo Form::label('username', 'Username');
     *
     * @param   string $input target input
     * @param   string $text label text
     * @param   array $attributes html attributes
     * @return  string
     * @uses    HTML::attributes
     */
    public static function label($input, $text = NULL, array $attributes = NULL) {
        if ($text === NULL) {
            // Use the input name as the text
            $text = ucwords(preg_replace('/[\W_]+/', ' ', $input));
        }

        // Set the label target
        $attributes['for'] = $input;

        return '<label' . HTML::attributes($attributes) . '>' . $text . '</label>';
    }


    public static function csrf_meta() {
        $request = \Mii::$app->get('request');
        if ($request->csrf_validation && \Mii::$app->controller && \Mii::$app->controller->csrf_validation) {
            $token = $request->csrf_token();
            $name = $request->csrf_token_name;
            return "<meta name='csrf-token-name' content='$name'>\n<meta name='csrf-token' content='$token'>";
        } else {
            return '';
        }

    }

}
