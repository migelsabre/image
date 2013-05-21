<?php

Yii::import('ext.image2.Image_Driver');

/**
 * Manipulate images using standard methods such as resize, crop, rotate, etc.
 * This class must be re-initialized for every image you wish to manipulate.
 *
 * $Id: Image.php 2010-01-10 0:0:0 Burzum $
 *
 * @package    Image
 * @author     Kohana Team
 * @copyright  (c) 2007-2008 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
class Image {

	// Master Dimension
	const NONE = 1;
	const AUTO = 2;
	const HEIGHT = 3;
	const WIDTH = 4;
	// Flip Directions
	const HORIZONTAL = 5;
	const VERTICAL = 6;

	// Allowed image types
	public static $allowed_types = array
	(
		IMAGETYPE_GIF => 'gif',
		IMAGETYPE_JPEG => 'jpg',
		IMAGETYPE_PNG => 'png',
		IMAGETYPE_TIFF_II => 'tiff',
		IMAGETYPE_TIFF_MM => 'tiff',
	);

	// Driver instance
	protected $driver;

	// Driver actions
	protected $actions = array();

	// Reference to the current image filename
	protected $image = '';

	/**
	 * Creates a new Image instance and returns it.
	 *
	 * @param   string   filename of image
	 * @param   array    non-default configurations
	 * @return  object
	 */
	public static function factory($image, $config = null)
	{
		return new Image($image, $config);
	}

	/**
	 * Creates a new image editor instance.
	 *
	 * @throws  Kohana_Exception
	 * @param   string   filename of image
	 * @param   array    non-default configurations
	 * @return  void
	 */
	public function __construct($image, $config = null)
	{
		static $check;

		// Make the check exactly once
		($check === null) and $check = function_exists('getimagesize');

		if ($check === false)
			throw new CException(Yii::t('Image.image', 'image getimagesize missing'));

		// Check to make sure the image exists
		if ( ! is_file($image))
			throw new CException(Yii::t('Image.image', 'image file not found'));

		// Disable error reporting, to prevent PHP warnings
		$ER = error_reporting(0);

		// Fetch the image size and mime type
		$image_info = getimagesize($image);

		// Turn on error reporting again
		error_reporting($ER);

		// Make sure that the image is readable and valid
		if ( ! is_array($image_info) OR count($image_info) < 3)
			throw new CException(Yii::t('Image.image', 'image file unreadable'));

		// Check to make sure the image type is allowed
		if ( ! isset(Image::$allowed_types[$image_info[2]]))
			throw new CException(Yii::t('Image.image', 'image type not allowed'));

		// Image has been validated, load it
		$this->image = array
		(
			'file' => str_replace('\\', '/', realpath($image)),
			'width' => $image_info[0],
			'height' => $image_info[1],
			'type' => $image_info[2],
			'ext' => Image::$allowed_types[$image_info[2]],
			'mime' => $image_info['mime']
		);

		// Load configuration
        if ($config === null){
            $this->config = array(
                'driver'=>'GD',
                'params'=>array(),
            );
        }
        else{
            $this->config = $config;
        }

		// Set driver class name
		$driver = 'Image_'.ucfirst($this->config['driver']).'_Driver';

        // Load the driver
        Yii::import("application.extensions.image.drivers.$driver");

		// Initialize the driver
		$this->driver = new $driver($this->config['params']);

		// Validate the driver
		if ( ! ($this->driver instanceof Image_Driver))
			throw new CException(Yii::t('Image.image', 'image driver must be implement Image_Driver class'));
	}

	/**
	 * Handles retrieval of pre-save image properties
	 *
	 * @param   string  property name
	 * @return  mixed
	 */
	public function __get($property)
	{
		if (isset($this->image[$property]))
		{
			return $this->image[$property];
		}
		else
		{
			throw new CException(Yii::t('Image.image', 'invalid property'));
		}
	}

	/**
	 * Resize an image to a specific width and height. By default, Kohana will
	 * maintain the aspect ratio using the width as the master dimension. If you
	 * wish to use height as master dim, set $image->master_dim = Image::HEIGHT
	 * This method is chainable.
	 *
	 * @throws  Kohana_Exception
	 * @param   integer  width
	 * @param   integer  height
	 * @param   integer  one of: Image::NONE, Image::AUTO, Image::WIDTH, Image::HEIGHT
	 * @return  object
	 */
	public function resize($width, $height, $master = null)
	{
		if ( ! $this->valid_size('width', $width))
			throw new CException(Yii::t('Image.image', 'image invalid width'));

		if ( ! $this->valid_size('height', $height))
			throw new CException(Yii::t('Image.image', 'image invalid height'));

		if (empty($width) AND empty($height))
			throw new CException(Yii::t('Image.image', 'image invalid dimensions'));

		if ($master === null)
		{
			// Maintain the aspect ratio by default
			$master = Image::AUTO;
		}
		elseif ( ! $this->valid_size('master', $master))
			throw new CException(Yii::t('Image.image', 'image invalid master'));

		$this->actions['resize'] = array
		(
			'width'  => $width,
			'height' => $height,
			'master' => $master,
		);

		return $this;
	}

	/**
	 * Apply watermark to image
	 * @param string filename
	 * @param string align (left|center|right)
	 * @param string valign (top|middle|bottom)
	 * @param integer hspace
	 * @param integer vspace
	 * @return object
	 * @throws CException
	 * @author burzum
	 */
	public function watermark($file, $align, $valign, $hspace, $vspace) {
		if ( ! file_exists($file) || !is_array(getimagesize($file)))
			throw new CException(Yii::t('Image.image', 'watermark file not exists or not image'), $file);

		if ( ! $this->valid_size('hspace', $hspace))
			throw new CException(Yii::t('Image.image', 'image invalid horizontal margin'), $hspace);

		if ( ! $this->valid_size('vspace', $vspace))
			throw new CException(Yii::t('Image.image', 'image invalid vertical margin'), $vspace);

		if ( ! $this->valid_size('align', $align))
			throw new CException(Yii::t('Image.image', 'image invalid horizontal align value'), $align);

		if ( ! $this->valid_size('valign', $valign))
			throw new CException(Yii::t('Image.image', 'image invalid vertical align value'), $valign);

		$this->actions['watermark'] = array(
			'file' => $file,
			'align' => $align,
			'valign' => $valign,
			'hspace' => $hspace,
			'vspace' => $vspace,
		);
		return $this;
	}

	/**
	 * Resize an image to a specific width and height if it's size greater then width or height specified, or skip processing otherwise.
	 * By default, Kohana will maintain the aspect ratio using the width as the master dimension.
	 * If you  wish to use height as master dim, set $image->master_dim = Image::HEIGHT
	 * This method is chainable.
	 *
	 * @throws  CException
	 * @param   integer  width
	 * @param   integer  height
	 * @param   integer  one of: Image::NONE, Image::AUTO, Image::WIDTH, Image::HEIGHT
	 * @return  object
	 * @author burzum
	 */
	public function reduce($width, $height, $master = null) {
		if ( ! $this->valid_size('width', $width))
			throw new CException(Yii::t('Image.image', 'image invalid width'));

		if ( ! $this->valid_size('height', $height))
			throw new CException(Yii::t('Image.image', 'image invalid height'));

		if (empty($width) AND empty($height))
			throw new CException(Yii::t('Image.image', 'image invalid dimensions'));

		if ($master === null)
		{
			// Maintain the aspect ratio by default
			$master = Image::AUTO;
		}
		elseif ( ! $this->valid_size('master', $master))
			throw new CException(Yii::t('Image.image', 'image invalid master'));

		$this->actions['reduce'] = array
		(
			'width'  => $width,
			'height' => $height,
			'master' => $master,
		);

		return $this;
	}

    /**
	 * Crop an image to a specific width and height. You may also set the top
	 * and left offset.
	 * This method is chainable.
	 *
	 * @throws  Kohana_Exception
	 * @param   integer  width
	 * @param   integer  height
	 * @param   integer  top offset, pixel value or one of: top, center, bottom
	 * @param   integer  left offset, pixel value or one of: left, center, right
	 * @return  object
	 */
	public function crop($width, $height, $top = 'center', $left = 'center')
	{
		if ( ! $this->valid_size('width', $width))
			throw new CException(Yii::t('Image.image', 'image invalid width'), $width);

		if ( ! $this->valid_size('height', $height))
			throw new CException(Yii::t('Image.image', 'image invalid height'), $height);

		if ( ! $this->valid_size('top', $top))
			throw new CException(Yii::t('Image.image', 'image invalid top'), $top);

		if ( ! $this->valid_size('left', $left))
			throw new CException(Yii::t('Image.image', 'image invalid left'), $left);

		if (empty($width) AND empty($height))
			throw new CException(Yii::t('Image.image', 'image invalid dimensions'));

		$this->actions['crop'] = array
		(
			'width'  => $width,
			'height' => $height,
			'top'    => $top,
			'left'   => $left,
		);

		return $this;
	}
	/**
	 * This method is chainable.
	 *
	 * @param   integer  $aspect_x
	 * @param   integer  $aspect_y
	 * @param   integer|string  top offset, pixel value or one of: top, center, bottom
	 * @param   integer|string  left offset, pixel value or one of: left, center, right
	 * @param int $hspace
	 * @param int $vspace
	 * @return  object
	 */
	public function frame($aspect_x, $aspect_y, $top = 'center', $left = 'center', $hspace = 0, $vspace = 0)
	{
		if ( ! $this->valid_size('width', $aspect_x))
			throw new CException(Yii::t('Image.image', 'image invalid width'), $width);

		if ( ! $this->valid_size('height', $aspect_y))
			throw new CException(Yii::t('Image.image', 'image invalid height'), $height);

		if ( ! $this->valid_size('top', $top)) {
			throw new CException(Yii::t('Image.image', 'image invalid top'), $top);
		}

		if ( ! $this->valid_size('left', $left)) {
			throw new CException(Yii::t('Image.image', 'image invalid left'), $left);
		}

		$this->actions['frame'] = array(
			'aspect_x'  => $aspect_x,
			'aspect_y' => $aspect_y,
			'top'    => $top,
			'left'   => $left,
			'hspace' => $hspace,
			'vspace' => $vspace,
		);

		return $this;
	}
    /**
	 * Allows rotation of an image by 180 degrees clockwise or counter clockwise.
	 *
	 * @param   integer  degrees
	 * @return  object
	 */
	public function rotate($degrees)
	{
		$degrees = (int) $degrees;

		if ($degrees > 180)
		{
			do
			{
				// Keep subtracting full circles until the degrees have normalized
				$degrees -= 360;
			}
			while($degrees > 180);
		}

		if ($degrees < -180)
		{
			do
			{
				// Keep adding full circles until the degrees have normalized
				$degrees += 360;
			}
			while($degrees < -180);
		}

		$this->actions['rotate'] = $degrees;

		return $this;
	}

    /**
	 * Flip an image horizontally or vertically.
	 *
	 * @throws  Kohana_Exception
	 * @param   integer  direction
	 * @return  object
	 */
	public function flip($direction)
	{
		if ($direction !== self::HORIZONTAL AND $direction !== self::VERTICAL)
			throw new CException(Yii::t('Image.image', 'image invalid flip'));

		$this->actions['flip'] = $direction;

		return $this;
	}

    /**
	 * Change the quality of an image.
	 *
	 * @param   integer  quality as a percentage
	 * @return  object
	 */
	public function quality($amount)
	{
		$this->actions['quality'] = max(1, min($amount, 100));
		return $this;
	}
	/**
	 * Convert image to greyscale.
	 * @return Image
	 */
	public function greyscale() {
		$this->actions['greyscale'] = true;
		return $this;
	}
	/**
	 * @param  int $r [1..255]
	 * @param  int $g [1..255]
	 * @param  int $b [1..255]
	 * @param int $a [1..127]
	 * @return Image
	 */
	public function colorize($r, $g, $b, $a = 127) {
		$this->actions['colorize'] = array(
			'r' => $r,
			'g' => $g,
			'b' => $b,
			'a' => $a
		);
		return $this;
	}
	public function brightness($amount) {
		$this->actions['brightness'] = $amount;
		return $this;
	}
	public function contrast($amount) {
		$this->actions['contrast'] = $amount;
		return $this;
	}
	public function blur() {
		$this->actions['blur'] = true;
		return $this;
	}
	/**
	 * Sharpen an image.
	 *
	 * @param   integer  amount to sharpen, usually ~20 is ideal
	 * @return  object
	 */
	public function sharpen($amount)
	{
		$this->actions['sharpen'] = max(1, min($amount, 100));
		return $this;
	}
	/**
	 * Puts image to blank image with size specified
	 * @param array $properties
	 * @return Image
	 */
	public function canvas($properties) {
		$this->actions['canvas'] = $properties;
		return $this;
	}
	/**
	 * Save the image to a new image or overwrite this image.
	 *
	 * @param string|bool $new_image  new image filename
	 * @param integer $chmod permissions for new image
	 * @param string $format
	 * @param boolean $keep_actions keep or discard image process actions
	 * @return object
	 * @throws CException
	 */
	public function save($new_image = false, $chmod = 0644, $format = 'jpeg', $keep_actions = false)
	{
		// If no new image is defined, use the current image
		empty($new_image) and $new_image = $this->image['file'];

		// Separate the directory and filename
		$dir  = pathinfo($new_image, PATHINFO_DIRNAME);
		$file = pathinfo($new_image, PATHINFO_BASENAME);

		// Normalize the path
		$dir = str_replace('\\', '/', realpath($dir)).'/';

		if ( ! is_writable($dir))
			throw new CException(Yii::t('Image.image', 'image directory unwritable'));

		if ($status = $this->driver->process($this->image, $this->actions, $dir, $file, $format))
		{
			if ($chmod !== false)
			{
				// Set permissions
				chmod($new_image, $chmod);
			}
		}

		// Reset actions. Subsequent save() or render() will not apply previous actions.
		if ($keep_actions === false)
			$this->actions = array();

		return $status;
	}

	/**
	 * Output the image to the browser.
	 *
	 * @param   boolean  keep or discard image process actions
	 * @return	object
	 */
	public function render($keep_actions = false)
	{
		$new_image = $this->image['file'];

		// Separate the directory and filename
		$dir  = pathinfo($new_image, PATHINFO_DIRNAME);
		$file = pathinfo($new_image, PATHINFO_BASENAME);

		// Normalize the path
		$dir = str_replace('\\', '/', realpath($dir)).'/';

		// Process the image with the driver
		$status = $this->driver->process($this->image, $this->actions, $dir, $file, $render = true);

		// Reset actions. Subsequent save() or render() will not apply previous actions.
		if ($keep_actions === false)
			$this->actions = array();

		return $status;
	}

	/**
	 * Sanitize a given value type.
	 *
	 * @param   string   type of property
	 * @param   mixed    property value
	 * @return  boolean
	 */
	protected function valid_size($type, & $value)
	{
		if (is_null($value))
			return true;

		if ( ! is_scalar($value))
			return false;

		switch ($type)
		{
			case 'hspace':
			case 'vspace':
			case 'width':
			case 'height':
				if (is_string($value) AND ! ctype_digit($value))
				{
					// Only numbers and percent signs
					if ( ! preg_match('/^[0-9]++%$/D', $value))
						return false;
				}
				else
				{
					$value = (int) $value;
				}
			break;
			case 'valign':
			case 'top':
				if (is_string($value) AND ! ctype_digit($value))
				{
					if ( ! in_array($value, array('top', 'bottom', 'center', 'middle')))
						return false;
				}
				else
				{
					$value = (int) $value;
				}
			break;
			case 'align':
			case 'left':
				if (is_string($value) AND ! ctype_digit($value))
				{
					if ( ! in_array($value, array('left', 'right', 'center')))
						return false;
				}
				else
				{
					$value = (int) $value;
				}
			break;
			case 'master':
				if ($value !== Image::NONE AND
				    $value !== Image::AUTO AND
				    $value !== Image::WIDTH AND
				    $value !== Image::HEIGHT)
					return false;
			break;
		}

		return true;
	}

} // End Image
