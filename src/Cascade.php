<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico;

use InvalidArgumentException;
use SplFixedArray;

/**
 * Cascade class.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/magicsunday/pico/
 */
class Cascade
{
    /**
     * The cascade file content. The array index starts with 1!
     *
     * @var array
     * @see http://php.net/manual/de/function.unpack.php
     */
    private $bytes;

    private $tdepth;
    private $ntrees;
    private $tcodes = [];
    private $tpreds = [];
    private $thresh = [];

    /**
     * Cascade constructor.
     *
     * @param string $filename The cascade file to load.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $filename)
    {
        $this->load($filename);
        $this->unpack();
    }

    /**
     * Reads the cascade file and returns its binary content as array.
     *
     * @param string $filename The cascade file to load.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function load(string $filename)
    {
        $handle = fopen($filename, 'rb');

        if (!$handle) {
            throw new InvalidArgumentException('Failed to load cascade file.');
        }

        $size   = filesize($filename);
        $binary = fread($handle, $size);

        if (!$binary) {
            throw new InvalidArgumentException('Failed to load cascade file.');
        }

        fclose($handle);

        // ! Array starts with index 1
        // @see http://php.net/manual/de/function.unpack.php
        $this->bytes = unpack('c*', $binary);
    }

    /**
     * Unpacks the cascade data.
     *
     * @return void
     */
    private function unpack()
    {
        $dataView = new DataView(new SplFixedArray(4));

        // Skip the first 8 bytes of the cascade file (version number and bounding box)
        $p = 8;

        // Depth (size) of each tree 832-bit signed integer9
        $dataView->setUint8(0, $this->bytes[$p + 1]);
        $dataView->setUint8(1, $this->bytes[$p + 2]);
        $dataView->setUint8(2, $this->bytes[$p + 3]);
        $dataView->setUint8(3, $this->bytes[$p + 4]);

        $this->tdepth = $dataView->getInt32(0);

        $p += 4;

        // Number of trees in the cascade (32-bit signed integer)
        $dataView->setUint8(0, $this->bytes[$p + 1]);
        $dataView->setUint8(1, $this->bytes[$p + 2]);
        $dataView->setUint8(2, $this->bytes[$p + 3]);
        $dataView->setUint8(3, $this->bytes[$p + 4]);

        $this->ntrees = $dataView->getInt32(0);

        $p += 4;

        $this->tcodes = [[]];
        $this->tpreds = [];
        $this->thresh = [];

        for ($t = 0; $t < $this->ntrees; ++$t) {
            $length = ((1 << $this->tdepth) - 1) * 4;

            // Binary tests placed in internal tree nodes
            $this->tcodes[] = [0, 0, 0, 0];
            $this->tcodes[] = \array_slice($this->bytes, $p, $length);

            $p += $length;

            // Prediction in the leaf nodes of the tree
            for ($i = 0; $i < (1 << $this->tdepth); ++$i) {
                $dataView->setUint8(0, $this->bytes[$p + 1]);
                $dataView->setUint8(1, $this->bytes[$p + 2]);
                $dataView->setUint8(2, $this->bytes[$p + 3]);
                $dataView->setUint8(3, $this->bytes[$p + 4]);

                $this->tpreds[] = $dataView->getFloat32(0);

                $p += 4;
            }

            // Threshold
            $dataView->setUint8(0, $this->bytes[$p + 1]);
            $dataView->setUint8(1, $this->bytes[$p + 2]);
            $dataView->setUint8(2, $this->bytes[$p + 3]);
            $dataView->setUint8(3, $this->bytes[$p + 4]);

            $this->thresh[] = $dataView->getFloat32(0);

            $p += 4;
        }

        // Merge all sub arrays into a single one
        $this->tcodes = array_merge(...$this->tcodes);
    }

    /**
     * @param int   $x
     * @param int   $y
     * @param int   $s
     * @param array $pixels
     * @param int   $width
     *
     * @return float
     */
    private function runCascade(int $x, int $y, int $s, array $pixels, int $width): float
    {
        $x *= 256;
        $y *= 256;

//        if (($y + 128 * $s) / 256 >= $height
//            || ($y - 128 * $s) / 256 < 0
//            || ($x + 128 * $s) / 256 >= $width
//            || ($x - 128 * $s) / 256 < 0
//        ) {
//            return -1.0;
//        }

        $root       = 0;
        $o          = 0.0;
        $pow2tdepth = 1 << $this->tdepth;

        for ($i = 0; $i < $this->ntrees; ++$i) {
            $idx = 1;

            for ($j = 0; $j < $this->tdepth; ++$j) {
                $pos = $root + 4 * $idx;

                $idx = 2 * $idx + ($pixels[(($y + $this->tcodes[$pos + 0] * $s) >> 8) * $width + (($x + $this->tcodes[$pos + 1] * $s) >> 8)]
                                <= $pixels[(($y + $this->tcodes[$pos + 2] * $s) >> 8) * $width + (($x + $this->tcodes[$pos + 3] * $s) >> 8)]);
            }

            $o += $this->tpreds[($pow2tdepth * $i) + $idx - $pow2tdepth];

            if ($o <= $this->thresh[$i]) {
                return -1.0;
            }

            $root += 4 * $pow2tdepth;
        }

        return $o - $this->thresh[$this->ntrees - 1];
    }

    /**
     * Finds objects inside a gray scale image.
     *
     * @param array $image        The gray scale image used to detect the objects.
     * @param int   $width        The width of the image.
     * @param int   $height       The height of the image.
     * @param int   $minSize      The minimum size of a face in pixel.
     * @param int   $maxSize      The maximum size of a face in pixel.
     * @param float $scaleFactor  How much to rescale the window during the multi scale detection process.
     * @param float $strideFactor How much to move the window between neighboring detections (default is 0.1, i.e., 10%).
     *
     * @return array List of quadruplets containing info about detected objects.
     */
    public function findObjects(
        array $image,
        int $width,
        int $height,
        int $minSize = 100,
        int $maxSize = 1000,
        float $scaleFactor = 1.1,
        float $strideFactor = 0.1
    ): array {
        $scale      = (float) $minSize;
        $detections = [];

        while ($scale <= $maxSize) {
            $step   = max($strideFactor * $scale, 1.0);
            $offset = ($scale / 2.0) + 1;

            for ($y = $offset; $y <= $height - $offset; $y += $step) {
                for ($x = $offset; $x <= $width - $offset; $x += $step) {
                    $q = $this->runCascade((int) $x, (int) $y, (int) $scale, $image, $width);

                    if ($q > 0.0) {
                        $detections[] = [
                            'c' => $x,
                            'r' => $y,
                            's' => $scale,
                            'q' => $q,
                        ];
                    }
                }
            }

            $scale *= $scaleFactor;
        }

        return $detections;
    }

    /**
     * Calculates the intersection over union for two detections.
     *
     * @param array $detectionOne The first detection.
     * @param array $detectionTwo The second detection.
     *
     * @return float
     */
    private function calculateIntersection(array $detectionOne, array $detectionTwo): float
    {
        $r1 = $detectionOne['r'];
        $c1 = $detectionOne['c'];
        $s1 = $detectionOne['s'];

        $r2 = $detectionTwo['r'];
        $c2 = $detectionTwo['c'];
        $s2 = $detectionTwo['s'];

        $s1Half = $s1 / 2;
        $s2Half = $s2 / 2;

        // Calculate detection overlap in each dimension
        $overX = max(0, min($c1 + $s1Half, $c2 + $s2Half) - max($c1 - $s1Half, $c2 - $s2Half));
        $overY = max(0, min($r1 + $s1Half, $r2 + $s2Half) - max($r1 - $s1Half, $r2 - $s2Half));
        $over  = $overY * $overX;

        // Calculate and return intersection over union
        return $over / (($s1 * $s1) + ($s2 * $s2) - $over);
    }

    /**
     * Cluster the obtained objects.
     *
     * @param array $detections The list of detected objects (faces).
     * @param float $threshold  The threshold used to merge overlapping regions together.
     *
     * @return array List of clustered detections
     */
    public function clusterDetections(array $detections, float $threshold): array
    {
        $detectionsCount = \count($detections);
        $assignments     = array_fill(0, $detectionsCount, 0);
        $clusters        = [];

        for ($i = 0; $i < $detectionsCount; ++$i) {
            if ($assignments[$i] === 0) {
                $y = 0.0;
                $x = 0.0;
                $s = 0.0;

                // Accumulated confidence measure
                $q = 0.0;
                $n = 0;

                for ($j = $i; $j < $detectionsCount; ++$j) {
                    if ($this->calculateIntersection($detections[$i], $detections[$j]) > $threshold) {
                        $assignments[$j] = 1;

                        $x += $detections[$j]['c'];
                        $y += $detections[$j]['r'];
                        $s += $detections[$j]['s'];
                        $q += $detections[$j]['q'];

                        ++$n;
                    }
                }

                $clusters[] = [
                    'x' => (int) ($x / $n),
                    'y' => (int) ($y / $n),
                    'r' => (int) ($s / $n),
                    'q' => $q,
                ];
            }
        }

        return $clusters;
    }
}
