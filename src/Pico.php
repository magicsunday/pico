<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico;

use MagicSunday\Pico\Image\Converter;
use RuntimeException;
use SplFixedArray;

/**
 * Pico class.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/magicsunday/pico/
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
     * The image converter utility.
     *
     * @var Converter
     */
    private $converter;

    /**
     * Pico constructor.
     */
    public function __construct()
    {
        $this->converter = new Converter();
    }

    public function open()
    {
        $cascade = new Cascade(self::CASCADE_FILE);

        $image = $this->loadImageAsGrayScale('data/img.jpg');
        $gray  = $this->converter->toArray($image);

        $params = [
            'shiftFactor' => self::STRIDE_FACTOR,
            'minSize'     => self::MIN_SIZE,
            'maxSize'     => self::MAX_SIZE,
            'scaleFactor' => self::SCALE_FACTOR,
        ];

$s = microtime(true);

        // run the cascade over the image
        // dets is an array that contains (r, c, s, q) quadruplets
        // (representing row, column, scale and detection score)
        $dets = $cascade->runCascade($gray, $image->getWidth(), $image->getHeight(), $params);

var_dump(microtime(true) - $s);

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

var_dump(microtime(true) - $s);

var_dump($dets);
exit;
    }

    /**
     * Loads the image and converts it to gray scale.
     *
     * @param string $filename The image file to load.
     *
     * @return Image
     */
    private function loadImageAsGrayScale(string $filename): Image
    {
        $image = new Image($filename);
        $this->converter->toGrayScale($image);

        return $image;
    }
}
