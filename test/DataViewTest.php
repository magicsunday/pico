<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico\Test;

use MagicSunday\Pico\DataView;
use PHPUnit\Framework\TestCase;
use SplFixedArray;

/**
 * Class DataViewTest
 */
class DataViewTest extends TestCase
{
    /**
     * @test
     */
    public function uint8ToInt32()
    {
        $dataView = new DataView(new SplFixedArray(4));

        $dataView->setUint8(0, 1);
        $dataView->setUint8(1, 2);
        $dataView->setUint8(2, 3);
        $dataView->setUint8(3, 4);

        self::assertSame(67305985, $dataView->getInt32(0));
        self::assertSame(16909060, $dataView->getInt32(0, true));

        $dataView->setUint8(0, 4);
        $dataView->setUint8(1, 3);
        $dataView->setUint8(2, 2);
        $dataView->setUint8(3, 1);

        self::assertSame(16909060, $dataView->getInt32(0));
        self::assertSame(67305985, $dataView->getInt32(0, true));
    }
    /**
     * @test
     */
    public function uint8ToFloat32()
    {
        $dataView = new DataView(new SplFixedArray(4));

        $dataView->setUint8(0, 66);
        $dataView->setUint8(1, 233);
        $dataView->setUint8(2, 204);
        $dataView->setUint8(3, 205);

        // Fix rounding error
        $result1 = (float) sprintf('%.8g', $dataView->getFloat32(0));
        $result2 = (float) sprintf('%.8g', $dataView->getFloat32(0, true));

        self::assertSame(-429729860.0, $result1);
        self::assertSame(116.9, $result2);
    }
}
