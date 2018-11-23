<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico;

use RuntimeException;
use SplFixedArray;

/**
 * Pico class.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/magicsunday/ancestral-fan-chart/
 */
class Pico
{
    /**
     * The detection quality threshold. All detections with estimated quality
     * below this threshold will be discarded.
     */
    const DETECTION_QUALITY_THRESHOLD = 5.0;

    /**
     * How much to rescale the window during the multi scale detection process.
     */
    const SCALE_FACTOR = 1.1;

    /**
     * The minimum size of a face in pixel.
     */
    const MIN_SIZE = 20;

    /**
     * The maximum size of a face in pixel.
     */
    const MAX_SIZE = 1000;

    /**
     * How much to move the window between neighboring detections (default is 0.1, i.e., 10%).
     */
    const STRIDE_FACTOR = 0.1;

    /**
     * The face detection cascade data file.
     */
    const CASCADE_FILE = 'data/face.cascade';

    /**
     * Pico constructor.
     */
    public function __construct()
    {
    }

    /**
     * Reads the cascade file and returns its binary content as array.
     *
     * @return array The array index starts with 1!
     *
     * @throws RuntimeException
     */
    private function readCascadeFile(): array
    {
        $handle = fopen(self::CASCADE_FILE, 'rb');

        if (!$handle) {
            throw new RuntimeException('Failed to load cascade file');
        }

        $size   = filesize(self::CASCADE_FILE);
        $binary = fread($handle, $size);

        if (!$binary) {
            throw new RuntimeException('Failed to load cascade file');
        }

        fclose($handle);

        // ! Array starts with index 1
        // @see http://php.net/manual/de/function.unpack.php
        return unpack('c*', $binary);
    }

    public function open()
    {
        $bytes = $this->readCascadeFile();

        $cascade = new Cascade();
        $cascade->unpackCascade($bytes);

//$s = microtime(true);

        $image  = imagecreatefromjpeg('data/img.jpg');
        $width  = imagesx($image);
        $height = imagesy($image);

        // Convert image to a grayscale one
        imagefilter($image, IMG_FILTER_GRAYSCALE);

        $gray = $this->readImage($image, $width, $height);

        imagedestroy($image);

//var_dump(microtime(true) - $s);

        $params = [
            'shiftFactor' => self::STRIDE_FACTOR,
            'minSize'     => self::MIN_SIZE,
            'maxSize'     => self::MAX_SIZE,
            'scaleFactor' => self::SCALE_FACTOR,
        ];

        // run the cascade over the image
        // dets is an array that contains (r, c, s, q) quadruplets
        // (representing row, column, scale and detection score)
        $dets = $cascade->runCascade($gray, $width, $height, $params);

        // cluster the obtained detections
        $dets = $cascade->clusterDetections($dets, 0.2); // set IoU threshold to 0.2

//        for ($i = 0; $i < count($dets); ++$i) {
//            // check the detection score
//            // if it's above the threshold, draw it
//            if ($dets[$i][3] > self::DETECTION_QUALITY_THRESHOLD) {
////                ctx . beginPath();
////                ctx . arc(dets[i][1], dets[i][0], dets[i][2] / 2, 0, 2 * Math . PI, false);
////                ctx . lineWidth = 3;
////                ctx . strokeStyle = 'red';
////                ctx . stroke();
//            }
//        }

var_dump($dets);
exit;
    }

    /**
     * @param resource $image
     * @param int      $width
     * @param int      $height
     *
     * @return array
     */
    public function readImage($image, int $width, int $height): array
    {
        $data = [];

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $rgb = imagecolorat($image, $x, $y);
                $pos = ($y * $width) + $x;

                $data[($y * $width) + $x] = $rgb;

//                $data[$pos]['r'] = ($rgb >> 16) & 0xff;
//                $data[$pos]['g'] = ($rgb >> 8) & 0xff;
//                $data[$pos]['b'] = $rgb & 0xff;
            }
        }

        return $data;
    }

    /**
     * @param array $rgb
     * @param int   $width
     * @param int   $height
     *
     * @return array
     */
    public function rgb2gray(array $rgb, int $width, int $height): array
    {
        $data = [];

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $color = $rgb[($y * $width) + $x];

                // Multiplied the REC601 luma components by 256 (avoiding floating point operations)
                // gray = 0.299 * r + 0.587 * g + 0.114 * b
//                $gray = ($color['r'] * 77) + ($color['g'] * 150) + ($color['b'] * 29);

                $gray = ($color['r'] * 2) + ($color['g'] * 7) + ($color['b'] * 1);

                // Unshift the result
                $data[($y * $width) + $x] = (int) ($gray / 10);
//                $data[($y * $width) + $x] = $gray >> 8;
            }
        }

        return $data;
    }
}
