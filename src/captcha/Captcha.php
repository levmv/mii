<?php

namespace mii\captcha;

use Mii;
use mii\core\ErrorException;
use mii\util\Text;
use mii\util\URL;

/**
 * @author      Lev Morozov, 2015
 * @author        Michael Lavers
 * @author        Kohana Team
 * @copyright    (c) 2008-2010 Kohana Team
 */
class Captcha
{
    protected $id = '';

    protected $width = 150;

    protected $height = 50;

    protected $complexity = 5;

    protected $background;

    protected $fontpath = '/usr/share/fonts/truetype/ttf-dejavu/';

    protected $fonts = ['DejaVuSerif.ttf'];

    protected $promote = false;

    /**
     * @var string The correct Captcha challenge answer
     */
    protected $response;

    /**
     * @var string Image resource identifier
     */
    protected $image;

    /**
     * @var string Image type ("png", "gif" or "jpeg")
     */
    protected $image_type = 'png';


    /**
     * Constructs a new Captcha object.
     *
     * @throws Exception
     * @param string Config group name
     * @return void
     */
    public function __construct($config = []) {
        $this->fontpath = __DIR__;

        foreach ($config as $key => $value) {
            $this->$key = $value;
        }

        // If using a background image, check if it exists
        if ($this->background) {
            $this->background = str_replace('\\', '/', realpath($this->background));

            if (!is_file($this->background))
                throw new ErrorException('The specified file, :file, was not found.',
                    [':file' => $this->background]);
        }

        // If using any fonts, check if they exist
        if ($this->fonts) {
            $this->fontpath = str_replace('\\', '/', realpath($this->fontpath)) . '/';

            foreach ($this->fonts as $font) {
                if (!is_file($this->fontpath . $font))
                    throw new ErrorException('The specified file, :file, was not found.',
                        [':file' => $this->fontpath . $font]);
            }
        }

        // Generate a new challenge
        $this->response = $this->generate_challenge();
    }

    /**
     * Update captcha response session variable.
     *
     * @return void
     */
    public function update_response_session() {
        // Store the correct Captcha response in a session
        Mii::$app->session->set('captcha_response', sha1(mb_strtoupper($this->response, 'utf-8')));
    }


    /**
     * Validates user's Captcha response and updates response counter.
     *
     * @staticvar integer $counted Captcha attempts counter
     * @param string $response User's captcha response
     * @return boolean
     */
    public function valid($response) {
        // Maximum one count per page load
        static $counted;

        // User has been promoted, always TRUE and don't count anymore
        if ($this->promoted())
            return TRUE;

        // Challenge result
        $result = (bool)(sha1(mb_strtoupper($response, 'utf-8')) === Mii::$app->session->get('captcha_response'));

        // Increment response counter
        if ($counted !== TRUE) {
            $counted = TRUE;

            // Valid response
            if ($result === TRUE) {
                Mii::$app->captcha->valid_count(Mii::$app->session->get('captcha_valid_count') + 1);
            } // Invalid response
            else {
                Mii::$app->captcha->invalid_count(Mii::$app->session->get('captcha_invalid_count') + 1);
            }
        }

        return $result;
    }

    /**
     * Gets or sets the number of valid Captcha responses for this session.
     *
     * @param integer $new_count New counter value
     * @param boolean $invalid Trigger invalid counter (for internal use only)
     * @return integer Counter value
     */
    public function valid_count($new_count = NULL, $invalid = FALSE) {
        // Pick the right session to use
        $session = ($invalid === TRUE) ? 'captcha_invalid_count' : 'captcha_valid_count';

        // Update counter
        if ($new_count !== NULL) {
            $new_count = (int)$new_count;

            // Reset counter = delete session
            if ($new_count < 1) {
                Mii::$app->session->delete($session);
            } // Set counter to new value
            else {
                Mii::$app->session->set($session, (int)$new_count);
            }

            // Return new count
            return (int)$new_count;
        }

        // Return current count
        return (int)Mii::$app->session->get($session);
    }

    /**
     * Gets or sets the number of invalid Captcha responses for this session.
     *
     * @param integer $new_count New counter value
     * @return integer Counter value
     */
    public function invalid_count($new_count = NULL) {
        return $this->valid_count($new_count, TRUE);
    }

    /**
     * Resets the Captcha response counters and removes the count sessions.
     *
     * @return void
     */
    public function reset_count() {
        $this->valid_count(0);
        $this->valid_count(0, TRUE);
    }

    /**
     * Checks whether user has been promoted after having given enough valid responses.
     *
     * @param integer $threshold Valid response count threshold
     * @return boolean
     */
    public function promoted($threshold = null) {
        // Promotion has been disabled
        if ($this->promote === false)
            return false;

        // Use the config threshold
        if ($threshold === null) {
            $threshold = $this->promote;
        }

        // Compare the valid response count to the threshold
        return ($this->valid_count() >= $threshold);
    }

    /**
     * Magically outputs the Captcha challenge.
     *
     * @return mixed
     */
    public function __toString() {
        return $this->render(true);
    }

    /**
     * Returns the image type.
     *
     * @param string $filename Filename
     * @return string|boolean Image type ("png", "gif" or "jpeg")
     */
    public function image_type($filename) {
        switch (strtolower(substr(strrchr($filename, '.'), 1))) {
            case 'png':
                return 'png';

            case 'gif':
                return 'gif';

            case 'jpg':
            case 'jpeg':
                // Return "jpeg" and not "jpg" because of the GD2 function names
                return 'jpeg';

            default:
                return FALSE;
        }
    }

    /**
     * Creates an image resource with the dimensions specified in config.
     * If a background image is supplied, the image dimensions are used.
     *
     * @throws \Exception If no GD2 support
     * @param string $background Path to the background image file
     * @return void
     */
    public function image_create($background = NULL) {
        // Check for GD2 support
        if (!function_exists('imagegd2'))
            throw new \Exception('captcha.requires_GD2');

        // Create a new image (black)
        $this->image = imagecreatetruecolor($this->width, $this->height);

        // Use a background image
        if (!empty($background)) {
            // Create the image using the right function for the filetype
            $function = 'imagecreatefrom' . $this->image_type($background);
            $this->background_image = $function($background);

            // Resize the image if needed
            if (imagesx($this->background_image) !== $this->width
                or imagesy($this->background_image) !== $this->height) {
                imagecopyresampled
                (
                    $this->image, $this->background_image, 0, 0, 0, 0,
                    $this->width, $this->height,
                    imagesx($this->background_image), imagesy($this->background_image)
                );
            }

            // Free up resources
            imagedestroy($this->background_image);
        }
    }

    /**
     * Fills the background with a gradient.
     *
     * @param resource $color1 GD image color identifier for start color
     * @param resource $color2 GD image color identifier for end color
     * @param string $direction Direction: 'horizontal' or 'vertical', 'random' by default
     * @return void
     */
    public function image_gradient($color1, $color2, $direction = NULL) {
        $directions = ['horizontal', 'vertical'];

        // Pick a random direction if needed
        if (!in_array($direction, $directions)) {
            $direction = $directions[array_rand($directions)];

            // Switch colors
            if (mt_rand(0, 1) === 1) {
                $temp = $color1;
                $color1 = $color2;
                $color2 = $temp;
            }
        }

        // Extract RGB values
        $color1 = imagecolorsforindex($this->image, $color1);
        $color2 = imagecolorsforindex($this->image, $color2);

        // Preparations for the gradient loop
        $steps = ($direction === 'horizontal') ? $this->width : $this->height;

        $r1 = ($color1['red'] - $color2['red']) / $steps;
        $g1 = ($color1['green'] - $color2['green']) / $steps;
        $b1 = ($color1['blue'] - $color2['blue']) / $steps;

        if ($direction === 'horizontal') {
            $x1 =& $i;
            $y1 = 0;
            $x2 =& $i;
            $y2 = $this->height;
        } else {
            $x1 = 0;
            $y1 =& $i;
            $x2 = $this->width;
            $y2 =& $i;
        }

        // Execute the gradient loop
        for ($i = 0; $i <= $steps; $i++) {
            $r2 = $color1['red'] - floor($i * $r1);
            $g2 = $color1['green'] - floor($i * $g1);
            $b2 = $color1['blue'] - floor($i * $b1);
            $color = imagecolorallocate($this->image, $r2, $g2, $b2);

            imageline($this->image, $x1, $y1, $x2, $y2, $color);
        }
    }

    /**
     * Returns the img html element or outputs the image to the browser.
     *
     * @param boolean $html Output as HTML
     * @return mixed HTML, string or void
     */
    public function image_render($html) {
        // Output html element

    }


    /**
     * Generates a new Captcha challenge.
     *
     * @return string The challenge answer
     */
    public function generate_challenge() {
        // Complexity setting is used as character count
        return Text::random('distinct', max(1, $this->complexity));

    }

    /**
     * Outputs the Captcha image.
     *
     * @param boolean $html Html output
     * @return mixed
     */
    public function render($html = true) {
        if ($html === true) {
            return '<img src="' . URL::site('captcha/' . $this->id) . '" width="' . $this->width . '" height="' . $this->height . '" alt="Captcha" class="captcha" />';
        }

        // Creates $this->image
        $this->image_create($this->background);

        // Add a random gradient
        if (!$this->background) {
            $color1 = imagecolorallocate($this->image, mt_rand(0, 105), mt_rand(0, 110), mt_rand(0, 110));
            $color2 = imagecolorallocate($this->image, mt_rand(0, 105), mt_rand(0, 110), mt_rand(0, 110));
            $this->image_gradient($color1, $color2);
        }

        // Add a few random circles
        for ($i = 0, $count = mt_rand(10, $this->complexity * 3); $i < $count; $i++) {
            $color = imagecolorallocatealpha($this->image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255), mt_rand(80, 120));
            $size = mt_rand(5, $this->height / 3);
            imagefilledellipse($this->image, mt_rand(0, $this->width), mt_rand(0, $this->height), $size, $size, $color);
        }

        // Calculate character font-size and spacing
        $default_size = min($this->width, $this->height * 2) / strlen($this->response);
        $spacing = (int)($this->width * 0.9 / strlen($this->response));

        // Background alphabetic character attributes
        $color_limit = mt_rand(96, 162);
        $chars = 'ABEFGJKLPQRTVY';

        // Draw each Captcha character with varying attributes
        for ($i = 0, $strlen = strlen($this->response); $i < $strlen; $i++) {
            // Use different fonts if available
            $font = $this->fontpath . $this->fonts[array_rand($this->fonts)];

            $angle = mt_rand(-42, 24);
            // Scale the character size on image height
            $size = $default_size / 10 * mt_rand(7, 13);
            $box = imageftbbox($size, $angle, $font, $this->response[$i]);

            // Calculate character starting coordinates
            $x = $spacing / 4 + $i * $spacing;
            $y = $this->height / 2 + ($box[2] - $box[5]) / 4;

            // Draw captcha text character
            // Allocate random color, size and rotation attributes to text
            $color = imagecolorallocate($this->image, mt_rand(149, 255), mt_rand(199, 255), mt_rand(0, 255));

            // Write text character to image
            imagefttext($this->image, $size, $angle, $x, $y, $color, $font, $this->response[$i]);

            // Draw "ghost" alphabetic character
            $text_color = imagecolorallocatealpha($this->image, mt_rand($color_limit + 8, 255), mt_rand($color_limit + 8, 255), mt_rand($color_limit + 8, 255), mt_rand(70, 120));
            $char = $chars[mt_rand(0, 13)];
            imagettftext($this->image, $size * 2, mt_rand(-45, 45), ($x - (mt_rand(5, 10))), ($y + (mt_rand(5, 10))), $text_color, $font, $char);
        }


        // Send the correct HTTP header
        Mii::$app->response->set_header('Content-Type', 'image/' . $this->image_type);
        Mii::$app->response->set_header('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        Mii::$app->response->set_header('Pragma', 'no-cache');
        Mii::$app->response->set_header('Connection', 'close');

        // Pick the correct output function
        $function = 'image' . $this->image_type;
        $function($this->image);

        // Free up resources
        imagedestroy($this->image);
    }

}
