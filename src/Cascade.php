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

        // read the depth (size) of each tree first: a 32-bit signed integer
        $dataView->setUint8(0, $this->bytes[$p + 1]);
        $dataView->setUint8(1, $this->bytes[$p + 2]);
        $dataView->setUint8(2, $this->bytes[$p + 3]);
        $dataView->setUint8(3, $this->bytes[$p + 4]);

        $this->tdepth = $dataView->getInt32(0);

        $p += 4;

        // next, read the number of trees in the cascade: another 32-bit signed integer
        $dataView->setUint8(0, $this->bytes[$p + 1]);
        $dataView->setUint8(1, $this->bytes[$p + 2]);
        $dataView->setUint8(2, $this->bytes[$p + 3]);
        $dataView->setUint8(3, $this->bytes[$p + 4]);

        $this->ntrees = $dataView->getInt32(0);

        $p += 4;

        //  read the actual trees and cascade thresholds
        $this->tcodes = [[]];
        $this->tpreds = [];
        $this->thresh = [];

        for ($t = 0; $t < $this->ntrees; ++$t) {
            $length = ((1 << $this->tdepth) - 1) * 4;

            // read the binary tests placed in internal tree nodes
            $this->tcodes[] = [0, 0, 0, 0];
            $this->tcodes[] = \array_slice($this->bytes, $p, $length);

            $p += $length;

            // read the prediction in the leaf nodes of the tree
            for ($i = 0; $i < (1 << $this->tdepth); ++$i) {
                $dataView->setUint8(0, $this->bytes[$p + 1]);
                $dataView->setUint8(1, $this->bytes[$p + 2]);
                $dataView->setUint8(2, $this->bytes[$p + 3]);
                $dataView->setUint8(3, $this->bytes[$p + 4]);

                $this->tpreds[] = $dataView->getFloat32(0);

                $p += 4;
            }

            // read the threshold
            $dataView->setUint8(0, $this->bytes[$p + 1]);
            $dataView->setUint8(1, $this->bytes[$p + 2]);
            $dataView->setUint8(2, $this->bytes[$p + 3]);
            $dataView->setUint8(3, $this->bytes[$p + 4]);

            $this->thresh[] = $dataView->getFloat32(0);

            $p += 4;
        }

        $this->tcodes = array_merge(...$this->tcodes);
    }

    // construct the classification function from the read data
    private function classifyRegion(int $r, int $c, float $s, array $pixels, int $ldim): float
    {
        $r *= 256;
        $c *= 256;

        $root       = 0;
        $o          = 0.0;
        $pow2tdepth = 1 << $this->tdepth;

        for ($i = 0; $i < $this->ntrees; ++$i) {
             $idx = 1;

             for ($j = 0; $j < $this->tdepth; ++$j) {
                 // we use '>> 8' here to perform an integer division: this seems important for performance
                 $idx = 2 * $idx + ($pixels[(($r + $this->tcodes[$root + 4 * $idx + 0] * $s) >> 8) * $ldim + (($c + $this->tcodes[$root + 4 * $idx + 1] * $s) >> 8)]
                                 <= $pixels[(($r + $this->tcodes[$root + 4 * $idx + 2] * $s) >> 8) * $ldim + (($c + $this->tcodes[$root + 4 * $idx + 3] * $s) >> 8)]);
             }

             $o += $this->tpreds[($pow2tdepth * $i) + $idx - $pow2tdepth];

             if ($o <= $this->thresh[$i]) {
                 return -1;
             }

             $root += 4 * $pow2tdepth;
        }

        return $o - $this->thresh[$this->ntrees - 1];
    }

    public function runCascade(array $image, int $width, int $height, array $params): array
    {
        $shiftFactor = $params['shiftFactor'];
        $minSize     = $params['minSize'];
        $maxSize     = $params['maxSize'];
        $scaleFactor = $params['scaleFactor'];
        $scale       = $minSize;
        $detections  = [];

        while ($scale <= $maxSize) {
            // '>>0' transforms this number to int
            $step   = max($shiftFactor * $scale, 1) >> 0;
            $offset = ($scale / 2 + 1) >> 0;

            for ($r = $offset; $r <= $height - $offset; $r += $step) {
                for ($c = $offset; $c <= $width - $offset; $c += $step) {
                    $q = $this->classifyRegion($r, $c, $scale, $image, $width);

                    if ($q > 0.0) {
                        $detections[] = [$r, $c, $scale, $q];
                    }
                }
            }

            $scale *= $scaleFactor;
        }

        return $detections;
    }

    // this helper function calculates the intersection over union for two detections
    public function calculateIntersection(array $det1, array $det2): float
    {
        // unpack the position and size of each detection
        list($r1, $c1, $s1) = $det1;
        list($r2, $c2, $s2) = $det2;

        // calculate detection overlap in each dimension
        $overr = max(0, min($r1 + $s1/2, $r2 + $s2/2) - max($r1 - $s1/2, $r2 - $s2/2));
        $overc = max(0, min($c1 + $s1/2, $c2 + $s2/2) - max($c1 - $s1/2, $c2 - $s2/2));

        // calculate and return IoU
        return $overr * $overc / ($s1 * $s1 + $s2 * $s2 - $overr * $overc);
    }

    public function clusterDetections(array $dets, float $iouthreshold): array
    {
        // sort detections by their score
        usort($dets, function (array $a, array $b) {
            return $b[3] - $a[3];
        });

        $detsLength = \count($dets);

        // do clustering through non-maximum suppression
        $assignments = array_fill(0, $detsLength, 0);

        $clusters   = [];

        for ($i = 0; $i < $detsLength; ++$i) {
            // is this detection assigned to a cluster?
            if ($assignments[$i] === 0) {
                // it is not:
                // now we make a cluster out of it and see whether some other detections belong to it
                $r = 0.0;
                $c = 0.0;
                $s = 0.0;

                // accumulated confidence measure
                $q = 0.0;
                $n = 0;

                for ($j = $i; $j < $detsLength; ++$j) {
                    if ($this->calculateIntersection($dets[$i], $dets[$j]) > $iouthreshold) {
                        $assignments[$j] = 1;

                        $r += $dets[$j][0];
                        $c += $dets[$j][1];
                        $s += $dets[$j][2];
                        $q += $dets[$j][3];

                        ++$n;
                    }
                }

                // make a cluster representative
                $clusters[] = [
                    'x' => $r / $n,
                    'y' => $c / $n,
                    'r' => $s / $n,
                    'q' => $q,
                ];
            }
        }

        return $clusters;
    }
}
