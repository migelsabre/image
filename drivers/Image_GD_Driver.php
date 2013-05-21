<?php

/**
 * GD Image Driver.
 *
 * $Id: GD.php 3769 2008-12-15 00:48:56Z zombor $
 *
 * @package    Image
 * @author     Kohana Team
 * @copyright  (c) 2007-2008 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
class Image_GD_Driver extends Image_Driver {
	// A transparent PNG as a string
	protected static $blank_png;
	protected static $blank_png_width;
	protected static $blank_png_height;

	public function __construct() {
		// Make sure that GD2 is available
		if(!function_exists('gd_info')) {
			throw new CException(Yii::t('Image_Driver.image', 'image gd requires v2'));
		}
		// Get the GD information
		$info = gd_info();
		// Make sure that the GD2 is installed
		if(strpos($info['GD Version'], '2.') === false) {
			throw new CException(Yii::t('Image_Driver.image', 'image gd requires v2'));
		}
	}

	public function process($image, $actions, $dir, $file, $format = null, $render = false) {
		// Set the "create" function
		switch($image['type']) {
			case IMAGETYPE_JPEG:
				$create = 'imagecreatefromjpeg';
				break;
			case IMAGETYPE_GIF:
				$create = 'imagecreatefromgif';
				break;
			case IMAGETYPE_PNG:
				$create = 'imagecreatefrompng';
				break;
		}

		// Set the "save" function
		$saveMethod = $format ? $format : strtolower(substr(strrchr($file, '.'), 1));
		switch($saveMethod) {
			case 'jpg':
			case 'jpeg':
				$save = 'imagejpeg';
				break;
			case 'gif':
				$save = 'imagegif';
				break;
			case 'png':
				$save = 'imagepng';
				break;
		}
		// Make sure the image type is supported for import
		if(empty($create) OR !function_exists($create)) {
			throw new CException(Yii::t('Image_Driver.image', 'image type not allowed'));
		}
		// Make sure the image type is supported for saving
		if(empty($save) OR !function_exists($save)) {
			throw new CException(Yii::t('Image_Driver.image', 'image type not allowed'));
		}
		// Load the image
		$this->image = $image;
		// Create the GD image resource
		$this->tmp_image = $create($image['file']);
		// Get the quality setting from the actions
		$quality = CArray::remove('quality', $actions);
		if($status = $this->execute($actions)) {
			// Prevent the alpha from being lost
			imagealphablending($this->tmp_image, true);
			imagesavealpha($this->tmp_image, true);
			switch($save) {
				case 'imagejpeg':
					// Default the quality to 95
					($quality === null) and $quality = 95;
					break;
				case 'imagegif':
					// Remove the quality setting, GIF doesn't use it
					unset($quality);
					break;
				case 'imagepng':
					// Always use a compression level of 9 for PNGs. This does not
					// affect quality, it only increases the level of compression!
					$quality = 9;
					break;
			}
			if($render === false) {
				// Set the status to the save return value, saving with the quality requested
				$status = isset($quality) ? $save($this->tmp_image, $dir . $file, $quality) : $save($this->tmp_image, $dir . $file);
			} else {
				// Output the image directly to the browser
				switch($save) {
					case 'imagejpeg':
						header('Content-Type: image/jpeg');
						break;
					case 'imagegif':
						header('Content-Type: image/gif');
						break;
					case 'imagepng':
						header('Content-Type: image/png');
						break;
				}
				$status = isset($quality) ? $save($this->tmp_image, null, $quality) : $save($this->tmp_image);
			}
			// Destroy the temporary image
			imagedestroy($this->tmp_image);
		}
		return $status;
	}

	public function flip($direction) {
		// Get the current width and height
		$width = imagesx($this->tmp_image);
		$height = imagesy($this->tmp_image);
		// Create the flipped image
		$flipped = $this->imagecreatetransparent($width, $height);
		if($direction === Image::HORIZONTAL) {
			for($x = 0; $x < $width; $x++) {
				$status = imagecopy($flipped, $this->tmp_image, $x, 0, $width - $x - 1, 0, 1, $height);
			}
		} elseif($direction === Image::VERTICAL) {
			for($y = 0; $y < $height; $y++) {
				$status = imagecopy($flipped, $this->tmp_image, 0, $y, 0, $height - $y - 1, $width, 1);
			}
		} else {
			// Do nothing
			return true;
		}
		if($status === true) {
			// Swap the new image for the old one
			imagedestroy($this->tmp_image);
			$this->tmp_image = $flipped;
		}
		return $status;
	}

	public function crop($properties) {
		// Sanitize the cropping settings
		$this->sanitize_geometry($properties);
		// Get the current width and height
		$width = imagesx($this->tmp_image);
		$height = imagesy($this->tmp_image);
		// Create the temporary image to copy to
		$img = $this->imagecreatetransparent($properties['width'], $properties['height']);
		// Execute the crop
		if($status = imagecopyresampled($img, $this->tmp_image, 0, 0, $properties['left'], $properties['top'], $width, $height, $width, $height)) {
			// Swap the new image for the old one
			imagedestroy($this->tmp_image);
			$this->tmp_image = $img;
		}
		return $status;
	}

	public function watermark($properties) {
		$width = imagesx($this->tmp_image);
		$height = imagesy($this->tmp_image);
		$image = getimagesize($properties['file']);
		switch($image[2]) {
			case IMAGETYPE_JPEG:
				$create = 'imagecreatefromjpeg';
				break;
			case IMAGETYPE_GIF:
				$create = 'imagecreatefromgif';
				break;
			case IMAGETYPE_PNG:
				$create = 'imagecreatefrompng';
				break;
		}
		$mark = $create($properties['file']);
		imagealphablending($this->tmp_image, true);
		imagesavealpha($this->tmp_image, true);
		$left = 0;
		$top = 0;
		switch($properties['align']) {
			case 'left':
				$left = $properties['hspace'];
				break;
			case 'center':
				$left = ($width - $image[0]) / 2 + $properties['hspace'];
				break;
			case 'right':
				$left = $width - $image[0] - $properties['hspace'];
				break;
		}
		switch($properties['valign']) {
			case 'top':
				$top = $properties['vspace'];
				break;
			case 'middle':
				$top = ($height - $image[1]) / 2 + $properties['vspace'];
				break;
			case 'bottom':
				$top = $height - $image[1] - $properties['vspace'];
				break;
		}
		$status = imagecopy($this->tmp_image, $mark, $left, $top, 0, 0, $image[0], $image[1]);
		imagedestroy($mark);
		return $status;
	}
	/**
	 * Вписывает прямоугольник с заданным соотношением сторон в заданный прямоугольник
	 * @param int $w ширина заданного прямоугольника
	 * @param int $h высота заданного прямоугольника
	 * @param int $aw коэффициент ширины вписываемого прямоугольника
	 * @param int $ah коэффициент высоты вписываемого прямоугольника
	 * @return array ширина и высота вписанного прямоугольника
	 */
	protected function fitRectangle($w, $h, $aw, $ah) {
		$ks = $w / $h;
		$ka = $aw / $ah;

		if($ks < $ka) {
			$nw = $w;
			$nh = $nw * $ah / $aw;
		} else {
			$nh = $h;
			$nw = $nh * $aw / $ah;
		}
		return array(round($nw), round($nh));
	}
	/**
	 * Кадрирование (вырезает прямоугольник заданных пропорций максимального размера)
	 * @param array $properties
	 * @return bool
	 */
	public function frame($properties) {
		$width = imagesx($this->tmp_image);
		$height = imagesy($this->tmp_image);
		$aspectX = round($properties['aspect_x']);
		$aspectY = round($properties['aspect_y']);
		$max_width = $width - 2 * $properties['hspace'];
		$max_height = $height - 2 * $properties['vspace'];
		list($new_width, $new_height) = $this->fitRectangle($max_width, $max_height, $aspectX, $aspectY);

		$left = 0;
		$top = 0;
		if(is_int($properties['left'])) {
			$left = $properties['left'];
		} else {
			switch($properties['left']) {
				case 'left':
					$left = $properties['hspace'];
					break;
				case 'center':
					$left = ($width - $new_width) / 2 + $properties['hspace'];
					break;
				case 'right':
					$left = $width - $new_width - $properties['hspace'];
					break;
			}
		}
		if(is_int($properties['top'])) {
			$top = $properties['top'];
		} else {
			switch($properties['top']) {
				case 'top':
					$top = $properties['vspace'];
					break;
				case 'middle':
					$top = ($height - $new_height) / 2 + $properties['vspace'];
					break;
				case 'bottom':
					$top = $height - $new_height - $properties['vspace'];
					break;
			}
		}
		$top = round($top);
		$left = round($left);
		//var_dump(get_defined_vars()); exit;
		// Create the temporary image to copy to
		$img = $this->imagecreatetransparent($new_width, $new_height);
		if($status = imagecopy($img, $this->tmp_image, 0, 0, $left, $top, $new_width, $new_height)) {
			imagedestroy($this->tmp_image);
			$this->tmp_image = $img;
		}
		return $status;
	}

	/**
	 * @param array $properties
	 * @return int
	 */
	public function canvas($properties) {
		$width = imagesx($this->tmp_image);
		$height = imagesy($this->tmp_image);
		$new_width = $properties['width'];
		$new_height = $properties['height'];

		$img = $this->imagecreatetransparent($new_width, $new_height);
		imagealphablending($img, true);
		imagesavealpha($img, true);
		$left = 0;
		$top = 0;
		switch($properties['align']) {
			case 'left':
				$left = $properties['hspace'];
				break;
			case 'center':
				$left = ($new_width - $width) / 2 + $properties['hspace'];
				break;
			case 'right':
				$left = $new_width - $width - $properties['hspace'];
				break;
		}
		switch($properties['valign']) {
			case 'top':
				$top = $properties['vspace'];
				break;
			case 'middle':
				$top = ($new_height - $height) / 2 + $properties['vspace'];
				break;
			case 'bottom':
				$top = $new_height - $height - $properties['vspace'];
				break;
		}
		$top = round($top);
		$left = round($left);
		if(isset($properties['background'])) {
			$color = imagecolorallocatealpha($img, $properties['background'][0], $properties['background'][1] , $properties['background'][2] , $properties['background'][3]);
			imagefill($img, 0, 0, $color);
		}
		if(isset($properties['image'])) {
			$image = getimagesize($properties['image']);
			switch($image[2]) {
				case IMAGETYPE_JPEG:
					$create = 'imagecreatefromjpeg';
					break;
				case IMAGETYPE_GIF:
					$create = 'imagecreatefromgif';
					break;
				case IMAGETYPE_PNG:
					$create = 'imagecreatefrompng';
					break;
			}
			$mark = $create($properties['file']);
			imagealphablending($mark, true);
			imagesavealpha($mark, true);
			if($status = imagecopy($img, $mark, 0, 0, 0, 0, $image[0], $image[1])) {
				imagedestroy($mark);
			}
		}
		if($status = imagecopy($img, $this->tmp_image, $left, $top, 0, 0, $width, $height)) {
			imagedestroy($this->tmp_image);
			$this->tmp_image = $img;
		}
		return $status;
	}
	/**
	 * @param array $properties
	 * @return bool
	 */
	public function reduce($properties) {
		$width = imagesx($this->tmp_image);
		$height = imagesy($this->tmp_image);
		if($width > $properties['width'] || $height > $properties['height']) {
			return $this->resize($properties);
		} else {
			return true;
		}
	}

	public function resize($properties) {
		// Get the current width and height
		$width = imagesx($this->tmp_image);
		$height = imagesy($this->tmp_image);
		if(substr($properties['width'], -1) === '%') {
			// Recalculate the percentage to a pixel size
			$properties['width'] = round($width * (substr($properties['width'], 0, -1) / 100));
		}
		if(substr($properties['height'], -1) === '%') {
			// Recalculate the percentage to a pixel size
			$properties['height'] = round($height * (substr($properties['height'], 0, -1) / 100));
		}
		// Recalculate the width and height, if they are missing
		empty($properties['width'])  and $properties['width'] = round($width * $properties['height'] / $height);
		empty($properties['height']) and $properties['height'] = round($height * $properties['width'] / $width);
		if($properties['master'] === Image::AUTO) {
			// Change an automatic master dim to the correct type
			$properties['master'] = (($width / $properties['width']) > ($height / $properties['height'])) ? Image::WIDTH : Image::HEIGHT;
		}
		if(empty($properties['height']) OR $properties['master'] === Image::WIDTH) {
			// Recalculate the height based on the width
			$properties['height'] = round($height * $properties['width'] / $width);
		}
		if(empty($properties['width']) OR $properties['master'] === Image::HEIGHT) {
			// Recalculate the width based on the height
			$properties['width'] = round($width * $properties['height'] / $height);
		}
		// Test if we can do a resize without resampling to speed up the final resize
		if($properties['width'] > $width / 2 AND $properties['height'] > $height / 2) {
			// Presize width and height
			$pre_width = $width;
			$pre_height = $height;
			// The maximum reduction is 10% greater than the final size
			$max_reduction_width = round($properties['width'] * 1.1);
			$max_reduction_height = round($properties['height'] * 1.1);
			// Reduce the size using an O(2n) algorithm, until it reaches the maximum reduction
			while($pre_width / 2 > $max_reduction_width AND $pre_height / 2 > $max_reduction_height) {
				$pre_width /= 2;
				$pre_height /= 2;
			}
			// Create the temporary image to copy to
			$img = $this->imagecreatetransparent($pre_width, $pre_height);
			if($status = imagecopyresized($img, $this->tmp_image, 0, 0, 0, 0, $pre_width, $pre_height, $width, $height)) {
				// Swap the new image for the old one
				imagedestroy($this->tmp_image);
				$this->tmp_image = $img;
			}
			// Set the width and height to the presize
			$width = $pre_width;
			$height = $pre_height;
		}
		// Create the temporary image to copy to
		$img = $this->imagecreatetransparent($properties['width'], $properties['height']);
		// Execute the resize
		if($status = imagecopyresampled($img, $this->tmp_image, 0, 0, 0, 0, $properties['width'], $properties['height'], $width, $height)) {
			// Swap the new image for the old one
			imagedestroy($this->tmp_image);
			$this->tmp_image = $img;
		}
		return $status;
	}

	public function rotate($amount) {
		// Use current image to rotate
		$img = $this->tmp_image;
		// White, with an alpha of 0
		$transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
		// Rotate, setting the transparent color
		$img = imagerotate($img, 360 - $amount, $transparent, -1);
		// Fill the background with the transparent "color"
		imagecolortransparent($img, $transparent);
		// Merge the images
		if($status = imagecopymerge($this->tmp_image, $img, 0, 0, 0, 0, imagesx($this->tmp_image), imagesy($this->tmp_image), 100)) {
			// Prevent the alpha from being lost
			imagealphablending($img, true);
			imagesavealpha($img, true);
			// Swap the new image for the old one
			imagedestroy($this->tmp_image);
			$this->tmp_image = $img;
		}
		return $status;
	}

	public function sharpen($amount) {
		// Make sure that the sharpening function is available
		if(!function_exists('imageconvolution')) {
			throw new CException(Yii::t('Image_Driver.image', 'image unsupported method'));
		}
		// Amount should be in the range of 18-10
		$amount = round(abs(-18 + ($amount * 0.08)), 2);
		// Gaussian blur matrix
		$matrix = array(
			array(-1, -1, -1), array(-1, $amount, -1), array(-1, -1, -1),
		);
		// Perform the sharpen
		return imageconvolution($this->tmp_image, $matrix, $amount - 8, 0);
	}

	/**
	 * Convert image to greyscale
	 * @return bool
	 */
	public function greyscale() {
		return imagefilter($this->tmp_image, IMG_FILTER_GRAYSCALE);
	}
	public function blur() {
		return imagefilter($this->tmp_image, IMG_FILTER_GAUSSIAN_BLUR);
	}
	public function colorize($properties) {
		return imagefilter($this->tmp_image, IMG_FILTER_COLORIZE, $properties['r'], $properties['g'], $properties['b'], $properties['a']);
	}

	public function brightness($amount) {
		return imagefilter($this->tmp_image, IMG_FILTER_BRIGHTNESS, $amount);
	}

	public function contrast($amount) {
		return imagefilter($this->tmp_image, IMG_FILTER_CONTRAST, $amount);
	}

	protected function properties() {
		return array(imagesx($this->tmp_image), imagesy($this->tmp_image));
	}

	/**
	 * Returns an image with a transparent background. Used for rotating to
	 * prevent unfilled backgrounds.
	 *
	 * @param   integer  image width
	 * @param   integer  image height
	 *
	 * @return  resource
	 */
	protected function imagecreatetransparent($width, $height) {
		if(self::$blank_png === null) {
			// Decode the blank PNG if it has not been done already
			self::$blank_png = imagecreatefromstring(base64_decode('iVBORw0KGgoAAAANSUhEUgAAACgAAAAoCAYAAACM/rhtAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29' . 'mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAADqSURBVHjaYvz//z/DYAYAAcTEMMgBQAANegcCBN' . 'CgdyBAAA16BwIE0KB3IEAADXoHAgTQoHcgQAANegcCBNCgdyBAAA16BwIE0KB3IEAADXoHAgTQoHcgQ' . 'AANegcCBNCgdyBAAA16BwIE0KB3IEAADXoHAgTQoHcgQAANegcCBNCgdyBAAA16BwIE0KB3IEAADXoH' . 'AgTQoHcgQAANegcCBNCgdyBAAA16BwIE0KB3IEAADXoHAgTQoHcgQAANegcCBNCgdyBAAA16BwIE0KB' . '3IEAADXoHAgTQoHcgQAANegcCBNCgdyBAgAEAMpcDTTQWJVEAAAAASUVORK5CYII='));
			// Set the blank PNG width and height
			self::$blank_png_width = imagesx(self::$blank_png);
			self::$blank_png_height = imagesy(self::$blank_png);
		}
		$img = imagecreatetruecolor($width, $height);
		// Resize the blank image
		imagecopyresized($img, self::$blank_png, 0, 0, 0, 0, $width, $height, self::$blank_png_width, self::$blank_png_height);
		// Prevent the alpha from being lost
		imagealphablending($img, false);
		imagesavealpha($img, true);
		return $img;
	}
} // End Image GD Driver
