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

    /**
     * The depth (size) of each tree.
     *
     * @var int
     */
    private $treeDepth;

    /**
     * The number of trees in the cascade
     *
     * @var int
     */
    private $numberOfTrees;

    /**
     * The binary tests placed in internal tree nodes.
     *
     * @var int[]
     */
    private $treeCodes = [];

    /**
     * The predictions in the leaf nodes of the tree.
     *
     * @var float[]
     */
    private $predictions = [];

    /**
     * The thresholds.
     *
     * @var float[]
     */
    private $thresholds = [];

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

        // Depth (size) of each tree (32-bit signed integer)
        $dataView->setUint8(0, $this->bytes[$p + 1]);
        $dataView->setUint8(1, $this->bytes[$p + 2]);
        $dataView->setUint8(2, $this->bytes[$p + 3]);
        $dataView->setUint8(3, $this->bytes[$p + 4]);

        $this->treeDepth = $dataView->getInt32(0);

        $p += 4;

        // Number of trees in the cascade (32-bit signed integer)
        $dataView->setUint8(0, $this->bytes[$p + 1]);
        $dataView->setUint8(1, $this->bytes[$p + 2]);
        $dataView->setUint8(2, $this->bytes[$p + 3]);
        $dataView->setUint8(3, $this->bytes[$p + 4]);

        $this->numberOfTrees = $dataView->getInt32(0);

        $p += 4;

        $this->treeCodes   = [[]];
        $this->predictions = [];
        $this->thresholds  = [];

        for ($t = 0; $t < $this->numberOfTrees; ++$t) {
            $length = ((1 << $this->treeDepth) - 1) * 4;

            // Binary tests placed in internal tree nodes
            $this->treeCodes[] = [0, 0, 0, 0];
            $this->treeCodes[] = \array_slice($this->bytes, $p, $length);

            $p += $length;

            // Prediction in the leaf nodes of the tree
            for ($i = 0; $i < (1 << $this->treeDepth); ++$i) {
                $dataView->setUint8(0, $this->bytes[$p + 1]);
                $dataView->setUint8(1, $this->bytes[$p + 2]);
                $dataView->setUint8(2, $this->bytes[$p + 3]);
                $dataView->setUint8(3, $this->bytes[$p + 4]);

                $this->predictions[] = $dataView->getFloat32(0);

                $p += 4;
            }

            // Threshold
            $dataView->setUint8(0, $this->bytes[$p + 1]);
            $dataView->setUint8(1, $this->bytes[$p + 2]);
            $dataView->setUint8(2, $this->bytes[$p + 3]);
            $dataView->setUint8(3, $this->bytes[$p + 4]);

            $this->thresholds[] = $dataView->getFloat32(0);

            $p += 4;
        }

        // Merge all sub arrays into a single one
        $this->treeCodes = array_merge(...$this->treeCodes);
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
    private function runCascade(int $x, int $y, int $s, array $pixels, int $width, array $lut): float
    {
        $x <<= 8; // * 256
        $y <<= 8; // * 256

//        if (($y + 128 * $s) / 256 >= $height
//            || ($y - 128 * $s) / 256 < 0
//            || ($x + 128 * $s) / 256 >= $width
//            || ($x - 128 * $s) / 256 < 0
//        ) {
//            return -1.0;
//        }

//          (($y* $width  + $this->treeCodes[$pos + 0] * $s * $width ) / (256 * $width))
//        + (($x + $this->treeCodes[$pos + 1] * $s) >> 8)

        $root          = 0;
        $o             = 0.0;
        $pow2treeDepth = 1 << $this->treeDepth;

        for ($i = 0; $i < $this->numberOfTrees; ++$i) {
            $idx     = 1;
            $predIdx = ($pow2treeDepth * $i) - $pow2treeDepth;

            for ($j = 0; $j < $this->treeDepth; ++$j) {
                $pos = $root + ($idx << 2); // idx * 4

                $idx = 2 * $idx + ($pixels[(($y + $lut[$pos][$s][0]) >> 8) * $width + (($x + $lut[$pos][$s][1]) >> 8)]
                                <= $pixels[(($y + $lut[$pos][$s][2]) >> 8) * $width + (($x + $lut[$pos][$s][3]) >> 8)]);

//                $idx = 2 * $idx + ($pixels[(($y + $this->treeCodes[$pos + 0] * $s) >> 8) * $width + (($x + $this->treeCodes[$pos + 1] * $s) >> 8)]
//                                <= $pixels[(($y + $this->treeCodes[$pos + 2] * $s) >> 8) * $width + (($x + $this->treeCodes[$pos + 3] * $s) >> 8)]);
            }

            $o += $this->predictions[$predIdx + $idx];

            if ($o <= $this->thresholds[$i]) {
                return -1.0;
            }

            $root += $pow2treeDepth << 2; // $pow2treeDepth * 4
        }

        return $o - $this->thresholds[$this->numberOfTrees - 1];
    }

    private function createLut(int $s)
    {
        $lut  = [];
        $root = 0;
        $pow2treeDepth = 1 << $this->treeDepth;

        for ($i = 0; $i < $this->numberOfTrees; ++$i) {
            $idx = 1;

            for ($j = 0; $j < $this->treeDepth; ++$j) {
                $pos = $root + ($idx << 2);

                $lut[$pos][$s][0] = $this->treeCodes[$pos + 0] * $s;
                $lut[$pos][$s][1] = $this->treeCodes[$pos + 1] * $s;
                $lut[$pos][$s][2] = $this->treeCodes[$pos + 2] * $s;
                $lut[$pos][$s][3] = $this->treeCodes[$pos + 3] * $s;
            }

            $root += $pow2treeDepth << 2;
        }

        return $lut;
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
     * @return Detection[] List of quadruplets containing info about detected objects.
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
            $step    = max($strideFactor * $scale, 1.0);
            $offset  = ($scale / 2.0) + 1;
            $xOffset = $width - $offset;
            $yOffset = $height - $offset;

            $lut = $this->createLut((int) $scale);

            for ($y = $offset; $y <= $yOffset; $y += $step) {
                for ($x = $offset; $x <= $xOffset; $x += $step) {
                    $q = $this->runCascade((int) $x, (int) $y, (int) $scale, $image, $width, $lut);

                    if ($q > 0.0) {
                        $detections[] = new Detection($x, $y, $scale, $q);
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
     * @param Detection $one The first detection.
     * @param Detection $two The second detection.
     *
     * @return float
     */
    private function calculateIntersection(Detection $one, Detection $two): float
    {
        // Calculate detection overlap in each dimension
        $overX = max(
            0,
            min(
                $one->getX() + $one->getScale() >> 1,
                $two->getX() + $two->getScale() >> 1
            ) - max(
                $one->getX() - $one->getScale() >> 1,
                $two->getX() - $two->getScale() >> 1
            )
        );

        $overY = max(
            0,
            min(
                $one->getY() + $one->getScale() >> 1,
                $two->getY() + $two->getScale() >> 1
            ) - max(
                $one->getY() - $one->getScale() >> 1,
                $two->getY() - $two->getScale() >> 1
            )
        );

        // Calculate and return intersection over union
        return ($overX * $overY) / (($one->getScale() * $one->getScale()) + ($two->getScale() * $two->getScale()) - ($overX * $overY));
    }

    /**
     * Cluster the obtained objects.
     *
     * @param Detection[] $detections The list of detected objects (faces).
     * @param float       $threshold  The threshold used to merge overlapping regions together.
     *
     * @return Detection[] List of clustered detections
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

                        $x += $detections[$j]->getX();
                        $y += $detections[$j]->getY();
                        $s += $detections[$j]->getScale();
                        $q += $detections[$j]->getScore();

                        ++$n;
                    }
                }

                $clusters[] = new Detection($x / $n, $y / $n, $s / $n, $q);
            }
        }

        return $clusters;
    }
}
