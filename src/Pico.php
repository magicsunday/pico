<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico;

use MagicSunday\Pico\Image\Converter;

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
    const DETECTION_QUALITY_THRESHOLD = 10.0;

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

$s = microtime(true);

        $image = $this->loadImageAsGrayScale('data/img.jpg');
        $gray  = $this->converter->toArray($image);

        // Find objects in image
        $detections = $cascade->findObjects(
            $gray,
            $image->getWidth(),
            $image->getHeight(),
            20
        );

        // Cluster the obtained detections
        $detections = $cascade->clusterDetections($detections, 0.2);

        foreach ($detections as $detection) {
            // Check the detection score
            if ($detection->getScore() > self::DETECTION_QUALITY_THRESHOLD) {
                $red = imagecolorallocate($image->getResource(), 255, 0, 0);

                imagearc(
                    $image->getResource(),
                    $detection->getX(),
                    $detection->getY(),
                    $detection->getScale(),
                    $detection->getScale(),
                    0,
                    360,
                    $red
                );
            }
        }

        imagepng($image->getResource(), 'test.png');

var_dump(microtime(true) - $s);

var_dump($detections);
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
