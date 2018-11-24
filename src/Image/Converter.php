<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico\Image;

use MagicSunday\Pico\Image;

/**
 * Converter class.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/magicsunday/pico/
 */
class Converter
{
    /**
     * Converts the image into gray scale by changing the red, green and blue components to their weighted sum
     * using the same coefficients as the REC.601 luma (Y') calculation. The alpha components are retained.
     *
     * @param Image $image The image to convert to gray scale.
     *
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public function toGrayScale(Image $image): bool
    {
        return imagefilter($image->getResource(), IMG_FILTER_GRAYSCALE);
    }

    /**
     * Converts the image resource to a array containing the plain gray scale image data.
     *
     * @param Image $image The gray scale image to convert to an array
     *
     * @return array
     */
    public function toArray(Image $image): array
    {
        $data     = [];
        $yPos     = 0;
        $width    = $image->getWidth();
        $height   = $image->getHeight();
        $resource = $image->getResource();

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $data[$yPos + $x] = imagecolorat($resource, $x, $y);
            }

            $yPos += $width;
        }

        return $data;
    }
}
