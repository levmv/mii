<?php declare(strict_types=1);

namespace mii\valid;

use mii\util\Text;
use mii\web\UploadedFile;

class Rules
{


    public static function validCaptcha($value)
    {
        return \Mii::$app->captcha->valid($value);
    }

    /**
     * Checks if a value is unique in database.
     *
     * @param $value
     * @param $key
     * @param $model
     * @param $id
     * @return bool
     */
    public static function unique($value, $key, $model, $id = null)
    {
        if ($id) {
            $res = (new $model)->select_query()->where($key, '=', $value)->one();
            return (null === $res) or ($res->id == $id);
        }
        return null === (new $model)->select_query()->where($key, '=', $value)->one();
    }

    /**
     * Checks if a field is not empty.
     *
     * @param $value
     * @return  boolean
     */
    public static function notEmpty($value)
    {
        if (\is_object($value) && $value instanceof \ArrayObject) {
            // Get the array from the ArrayObject
            $value = $value->getArrayCopy();
        }

        // Value cannot be NULL, FALSE, '', or an empty array
        return !\in_array($value, array(NULL, FALSE, '', array()), TRUE);
    }

    /**
     * Checks a field against a regular expression.
     *
     * @param string $value value
     * @param string $expression regular expression to match (including delimiters)
     * @return  boolean
     */
    public static function regex($value, $expression)
    {
        return (bool)preg_match($expression, (string)$value);
    }

    /**
     * Checks that a field is long enough.
     *
     * @param string  $value value
     * @param integer $length minimum length required
     * @return  boolean
     */
    public static function min($value, $length)
    {

        return static::_checkSize($value, $length, 1);
    }

    /**
     * Checks that a field is short enough.
     *
     * @param string  $value value
     * @param integer $length maximum length required
     * @return  boolean
     */
    public static function max($value, $length)
    {
        return static::_checkSize($value, $length, -1);
    }


    public static function _checkSize($value, $length, $dir)
    {
        if (\is_object($value) && $value instanceof UploadedFile)
            return static::fileSize($value, $length, $dir);

        if (\is_string($value)) {
            return (mb_strlen($value) <=> $length) === $dir;
        }

        return ($value <=> $length) === $dir;
    }


    /**
     * Checks that a field is exactly the right length.
     *
     * @param string        $value value
     * @param integer|array $length exact length required, or array of valid lengths
     * @return  boolean
     */
    public static function exactLength($value, $length)
    {
        if (\is_array($length)) {
            foreach ($length as $strlen) {
                if (mb_strlen($value) === $strlen)
                    return TRUE;
            }
            return FALSE;
        }

        return static::_checkSize($value, $length, 0);
    }

    /**
     * Checks that a field is exactly the value required.
     *
     * @param string $value value
     * @param string $required required value
     * @return  boolean
     */
    public static function equals($value, $required)
    {
        return ($value === $required);
    }

    /**
     * Check an email address for correct format.
     *
     * @link  http://www.iamcal.com/publish/articles/php/parsing_email/
     * @link  http://www.w3.org/Protocols/rfc822/
     *
     * @param string  $email email address
     * @param boolean $strict strict RFC compatibility
     * @return  boolean
     */
    public static function email($email, $strict = FALSE)
    {
        if (mb_strlen($email) > 254) {
            return FALSE;
        }

        if ($strict === TRUE) {
            $qtext = '[^\\x0d\\x22\\x5c\\x80-\\xff]';
            $dtext = '[^\\x0d\\x5b-\\x5d\\x80-\\xff]';
            $atom = '[^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+';
            $pair = '\\x5c[\\x00-\\x7f]';

            $domain_literal = "\\x5b($dtext|$pair)*\\x5d";
            $quoted_string = "\\x22($qtext|$pair)*\\x22";
            $sub_domain = "($atom|$domain_literal)";
            $word = "($atom|$quoted_string)";
            $domain = "$sub_domain(\\x2e$sub_domain)*";
            $local_part = "$word(\\x2e$word)*";

            $expression = "/^$local_part\\x40$domain$/D";
        } else {
            $expression = '/^[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})$/iD';
        }

        return (bool)preg_match($expression, (string)$email);
    }

    /**
     * Validate the domain of an email address by checking if the domain has a
     * valid MX record.
     *
     * @link  http://php.net/checkdnsrr  not added to Windows until PHP 5.3.0
     *
     * @param string $email email address
     * @return  boolean
     */
    public static function emailDomain($email)
    {
        if (!Rules::notEmpty($email))
            return FALSE; // Empty fields cause issues with checkdnsrr()

        // Check if the email domain has a valid MX record
        return (bool)checkdnsrr(preg_replace('/^[^@]++@/', '', $email), 'MX');
    }

    /**
     * Validate a URL.
     *
     * @param string $url URL
     * @return  boolean
     */
    public static function url($url)
    {
        // Based on http://www.apps.ietf.org/rfc/rfc1738.html#sec-5
        if (!preg_match(
            '~^

            # scheme
            [-a-z0-9+.]++://

            # username:password (optional)
            (?:
                    [-a-z0-9$_.+!*\'(),;?&=%]++   # username
                (?::[-a-z0-9$_.+!*\'(),;?&=%]++)? # password (optional)
                @
            )?

            (?:
                # ip address
                \d{1,3}+(?:\.\d{1,3}+){3}+

                | # or

                # hostname (captured)
                (
                         (?!-)[-a-z0-9]{1,63}+(?<!-)
                    (?:\.(?!-)[-a-z0-9]{1,63}+(?<!-)){0,126}+
                )
            )

            # port (optional)
            (?::\d{1,5}+)?

            # path (optional)
            (?:/.*)?

            $~iDx', $url, $matches))
            return FALSE;

        // We matched an IP address
        if (!isset($matches[1]))
            return TRUE;

        // Check maximum length of the whole hostname
        // http://en.wikipedia.org/wiki/Domain_name#cite_note-0
        if (\strlen($matches[1]) > 253)
            return FALSE;

        // An extra check for the top level domain
        // It must start with a letter
        $tld = ltrim(substr($matches[1], (int)strrpos($matches[1], '.')), '.');
        return ctype_alpha($tld[0]);
    }

    /**
     * Validate an IP.
     *
     * @param string  $ip IP address
     * @param boolean $allow_private allow private IP networks
     * @return  boolean
     */
    public static function ip($ip, $allow_private = TRUE)
    {
        // Do not allow reserved addresses
        $flags = FILTER_FLAG_NO_RES_RANGE;

        if ($allow_private === FALSE) {
            // Do not allow private or reserved addresses
            $flags |= FILTER_FLAG_NO_PRIV_RANGE;
        }

        return (bool)filter_var($ip, FILTER_VALIDATE_IP, $flags);
    }

    /**
     * Checks if a phone number is valid.
     *
     * @param string $number phone number to check
     * @param array  $lengths
     * @return  boolean
     */
    public static function phone($number, $lengths = NULL)
    {
        if (!\is_array($lengths)) {
            $lengths = array(7, 10, 11);
        }

        // Remove all non-digit characters from the number
        $number = preg_replace('/\D+/', '', $number);

        // Check if the number is within range
        return \in_array(strlen($number), $lengths);
    }

    /**
     * Tests if a string is a valid date string.
     *
     * @param string $str date to check
     * @return  boolean
     */
    public static function date($str)
    {
        return (strtotime($str) !== FALSE);
    }

    /**
     * Checks whether a string consists of alphabetical characters only.
     *
     * @param string  $str input string
     * @param boolean $utf8 trigger UTF-8 compatibility
     * @return  boolean
     */
    public static function alpha($str, $utf8 = FALSE)
    {
        $str = (string)$str;

        if ($utf8 === TRUE) {
            return (bool)preg_match('/^\pL++$/uD', $str);
        }

        return ctype_alpha($str);
    }

    /**
     * Checks whether a string consists of alphabetical characters and numbers only.
     *
     * @param string  $str input string
     * @param boolean $utf8 trigger UTF-8 compatibility
     * @return  boolean
     */
    public static function alphaNumeric($str, $utf8 = FALSE)
    {
        if ($utf8 === TRUE) {
            return (bool)preg_match('/^[\pL\pN]++$/uD', $str);
        }

        return ctype_alnum($str);
    }

    /**
     * Checks whether a string consists of alphabetical characters, numbers, underscores and dashes only.
     *
     * @param string  $str input string
     * @param boolean $utf8 trigger UTF-8 compatibility
     * @return  boolean
     */
    public static function alphaDash($str, $utf8 = FALSE)
    {
        if ($utf8 === TRUE) {
            $regex = '/^[-\pL\pN_]++$/uD';
        } else {
            $regex = '/^[-a-z0-9_]++$/iD';
        }

        return (bool)preg_match($regex, $str);
    }

    /**
     * Checks whether a string consists of digits only (no dots or dashes).
     *
     * @param string  $str input string
     * @param boolean $utf8 trigger UTF-8 compatibility
     * @return  boolean
     */
    public static function digit($str, $utf8 = FALSE)
    {
        if ($utf8 === TRUE) {
            return (bool)preg_match('/^\pN++$/uD', $str);
        }

        return (\is_int($str) and $str >= 0) or ctype_digit($str);
    }

    /**
     * Checks whether a string is a valid number (negative and decimal numbers allowed).
     *
     * Uses {@link http://www.php.net/manual/en/function.localeconv.php locale conversion}
     * to allow decimal point to be locale specific.
     *
     * @param string $str input string
     * @return  boolean
     */
    public static function numeric($str)
    {
        // Get the decimal point for the current locale
        list($decimal) = array_values(localeconv());

        // A lookahead is used to make sure the string contains at least one digit (before or after the decimal point)
        return (bool)preg_match('/^-?+(?=.*[0-9])[0-9]*+' . preg_quote($decimal) . '?+[0-9]*+$/D', (string)$str);
    }

    /**
     * Tests if a number is within a range.
     *
     * @param string  $number number to check
     * @param integer $min minimum value
     * @param integer $max maximum value
     * @param integer $step increment size
     * @return  boolean
     */
    public static function range($number, $min, $max, $step = NULL)
    {
        if ($number <= $min || $number >= $max) {
            // Number is outside of range
            return FALSE;
        }

        if (!$step) {
            // Default to steps of 1
            $step = 1;
        }

        // Check step requirements
        return (($number - $min) % $step === 0);
    }

    /**
     * Checks if a string is a proper decimal format. Optionally, a specific
     * number of digits can be checked too.
     *
     * @param string  $str number to check
     * @param integer $places number of decimal places
     * @param integer $digits number of digits
     * @return  boolean
     */
    public static function decimal($str, $places = 2, $digits = NULL)
    {
        if ($digits > 0) {
            // Specific number of digits
            $digits = '{' . ((int)$digits) . '}';
        } else {
            // Any number of digits
            $digits = '+';
        }

        // Get the decimal point for the current locale
        list($decimal) = array_values(localeconv());

        return (bool)preg_match('/^[+-]?[0-9]' . $digits . preg_quote($decimal) . '[0-9]{' . ((int)$places) . '}$/D', $str);
    }

    /**
     * Checks if a string is a proper hexadecimal HTML color value. The validation
     * is quite flexible as it does not require an initial "#" and also allows for
     * the short notation using only three instead of six hexadecimal characters.
     *
     * @param string $str input string
     * @return  boolean
     */
    public static function color($str)
    {
        return (bool)preg_match('/^#?+[0-9a-f]{3}(?:[0-9a-f]{3})?$/iD', $str);
    }

    /**
     * Checks if a field matches the value of another field.
     *
     * @param array  $array array of values
     * @param string $field field name
     * @param string $match field name to match
     * @return  boolean
     */
    public static function matches($array, $field, $match)
    {
        return ($array[$field] === $array[$match]);
    }


    public static function uploaded($file): bool
    {
        return \is_object($file) &&
            $file instanceof UploadedFile &&
            !$file->hasError() &&
            $file->isUploadedFile();
    }


    /**
     * Test if an uploaded file is an allowed file type, by extension.
     *
     *     $array->rule('file', 'file_type', [':value', ['jpg', 'png', 'gif']]);
     *
     * @param array $file $_FILES item
     * @param array $allowed allowed file extensions
     * @return  bool
     */
    public static function fileType($file, array $allowed): bool
    {

        if (!$file instanceof UploadedFile)
            return true;

        if ($file->hasError())
            return true;

        $ext = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));

        return \in_array($ext, $allowed);
    }

    /**
     * Validation rule to test if an uploaded file is allowed by file size.
     * File sizes are defined as: SB, where S is the size (1, 8.5, 300, etc.)
     * and B is the byte unit (K, MiB, GB, etc.). All valid byte units are
     * defined in Text::$byte_units
     *
     *     $array->rule('file', 'file_size', array(':value', '1M'))
     *     $array->rule('file', 'file_size', array(':value', '2.5KiB'))
     *
     * @param UploadedFile $file $_FILES item
     * @param string       $size maximum file size allowed
     * @param int          $dir
     * @return  bool
     * @throws \mii\core\Exception
     */
    public static function fileSize(UploadedFile $file, $size, $dir = -1)
    {
        if ($file->error === UPLOAD_ERR_INI_SIZE) {
            // Upload is larger than PHP allowed size (upload_max_filesize)
            return FALSE;
        }

        if ($file->error !== UPLOAD_ERR_OK) {
            // The upload failed, no size to check
            return TRUE;
        }

        // Convert the provided size to bytes for comparison
        $size = Text::bytes($size);


        return ($file->size <=> $size) === $dir;
    }

    public static function recaptcha()
    {
        $recaptcha = new \ReCaptcha\ReCaptcha(config('google.recaptcha.secret'));
        $response = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
        return $response->isSuccess();

    }

}
