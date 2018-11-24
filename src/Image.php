<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico;

use InvalidArgumentException;

/**
 * Image class.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/magicsunday/pico/
 */
class Image
{
    /**
     * The image resource.
     *
     * @var resource
     */
    private $image;

    /**
     * The width of the image.
     *
     * @var int
     */
    private $width;

    /**
     * The height of the image.
     *
     * @var int
     */
    private $height;

    /**
     * Image constructor.
     *
     * @param string $filename The image file to load.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $filename)
    {
        $this->image  = $this->load($filename);
        $this->width  = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        imagedestroy($this->image);
    }

    /**
     * Returns the underlying image resouce.
     *
     * @return resource
     */
    public function getResource()
    {
        return $this->image;
    }

    /**
     * Returns the width of the image.
     *
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Returns the height of the image.
     *
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Loads an image file. The file must be either of type JPG, PNG or GIF.
     *
     * @param string $filename The image file to load.
     *
     * @return resource
     *
     * @throws InvalidArgumentException
     */
    private function load(string $filename)
    {
        if (!file_exists($filename)) {
            throw new InvalidArgumentException('File "' . $filename . '" not found.');
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        switch ($ext) {
            case 'jpeg':
            case 'jpg':
                return imagecreatefromjpeg($filename);

            case 'png':
                return imagecreatefrompng($filename);

            case 'gif':
                return imagecreatefromgif($filename);

            default:
                throw new InvalidArgumentException(
                    'File "' . $filename . '" specifies not a valid JPG, PNG or GIF image.
                ');
        }
    }
}
