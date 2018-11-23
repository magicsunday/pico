<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico;

use SplFixedArray;

/**
 * Cascade class.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/magicsunday/ancestral-fan-chart/
 */
class Cascade
{
    private $tdepth;
    private $ntrees;
    private $tcodes = [];
    private $tpreds = [];
    private $thresh = [];

    /**
     * Pico constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param array $bytes
     */
    public function unpackCascade(array $bytes)
    {
        $dataView = new DataView(new SplFixedArray(4));

        // we skip the first 8 bytes of the cascade file
        // (version number and bounding box)
        $p = 8;

        // read the depth (size) of each tree first: a 32-bit signed integer
        $dataView->setUint8(0, $bytes[$p + 1]);
        $dataView->setUint8(1, $bytes[$p + 2]);
        $dataView->setUint8(2, $bytes[$p + 3]);
        $dataView->setUint8(3, $bytes[$p + 4]);

        $this->tdepth = $dataView->getInt32(0);

        $p += 4;

        // next, read the number of trees in the cascade: another 32-bit signed integer
        $dataView->setUint8(0, $bytes[$p + 1]);
        $dataView->setUint8(1, $bytes[$p + 2]);
        $dataView->setUint8(2, $bytes[$p + 3]);
        $dataView->setUint8(3, $bytes[$p + 4]);

        $this->ntrees = $dataView->getInt32(0);

        $p += 4;

        //  read the actual trees and cascade thresholds
        $this->tcodes = [];
        $this->tpreds = [];
        $this->thresh = [];

        for ($t = 0; $t < $this->ntrees; ++$t) {
            $length = ((1 << $this->tdepth) - 1) * 4;

            // read the binary tests placed in internal tree nodes
            $this->tcodes = array_merge($this->tcodes, [0, 0, 0, 0]);
            $this->tcodes = array_merge($this->tcodes, array_slice($bytes, $p, $length));

            $p += $length;

            // read the prediction in the leaf nodes of the tree
            for ($i = 0; $i < (1 << $this->tdepth); ++$i) {
                $dataView->setUint8(0, $bytes[$p + 1]);
                $dataView->setUint8(1, $bytes[$p + 2]);
                $dataView->setUint8(2, $bytes[$p + 3]);
                $dataView->setUint8(3, $bytes[$p + 4]);

                $this->tpreds[] = $dataView->getFloat32(0);

                $p += 4;
            }

            // read the threshold
            $dataView->setUint8(0, $bytes[$p + 1]);
            $dataView->setUint8(1, $bytes[$p + 2]);
            $dataView->setUint8(2, $bytes[$p + 3]);
            $dataView->setUint8(3, $bytes[$p + 4]);

            $this->thresh[] = $dataView->getFloat32(0);

            $p += 4;
        }

        $x = 1;
//        for ($i = 0; $i < count($this->tcodes); ++$i) {
//            $this->tcodes[$i] = (int) $this->tcodes[$i];
//        }

//        $this->tcodes = new Int8Array($this->tcodes);
//        $this->tpreds = new Float32Array($this->tpreds);
//        $this->thresh = new Float32Array($this->thresh);
    }

    // construct the classification function from the read data
    public function classifyRegion(int $r, int $c, float $s, array $pixels, int $ldim): float
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
        $r1 = $det1[0];
        $c1 = $det1[1];
        $s1 = $det1[2];

        $r2 = $det2[0];
        $c2 = $det2[1];
        $s2 = $det2[2];

        // calculate detection overlap in each dimension
        $overr = max(0, min($r1 + $s1/2, $r2 + $s2/2) - max($r1 - $s1/2, $r2 - $s2/2));
        $overc = max(0, min($c1 + $s1/2, $c2 + $s2/2) - max($c1 - $s1/2, $c2 - $s2/2));

        // calculate and return IoU
        return $overr * $overc / ($s1 * $s1 + $s2 * $s2 - $overr * $overc);
    }

    public function clusterDetections(array $dets, $iouthreshold)
    {
        // sort detections by their score
        usort($dets, function (array $a, array $b) {
            return $b[3] - $a[3];
        });

        $detsLength = \count($dets);

        // do clustering through non-maximum suppression
        $assignments = new SplFixedArray($detsLength);

        for ($i = 0; $i < $detsLength; ++$i) {
            $assignments[$i] = 0;
        }

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
                        $n += 1;
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
