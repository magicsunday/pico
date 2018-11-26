<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico\Test;

use MagicSunday\Pico\FixedFloat;
use PHPUnit\Framework\TestCase;

/**
 * Class FixedFloatTest
 */
class FixedFloatTest extends TestCase
{
    /**
     * @test
     */
    public function floatToFixed()
    {
        $fixed = new FixedFloat(450.1);

        self::assertSame(450.1, $fixed->toFloat());
    }

    /**
     * @test
     */
    public function add()
    {
        $fixed = new FixedFloat(450.12);
        $fixed->add(new FixedFloat(368.78));

        self::assertSame(818.9, $fixed->toFloat());
    }

    /**
     * @test
     */
    public function substract()
    {
        $fixed = new FixedFloat(450.12);
        $fixed->substract(new FixedFloat(122.44));

        self::assertSame(327.68, $fixed->toFloat());
    }

    /**
     * @test
     */
    public function mulitply()
    {
        $fixed = new FixedFloat(450.12);
        $fixed->multiply(new FixedFloat(2.0));

        self::assertSame(900.24, $fixed->toFloat());
    }

    /**
     * @test
     */
    public function divide()
    {
        $fixed = new FixedFloat(900.25);
        $fixed->divide(new FixedFloat(2.0));

        self::assertSame(450.125, $fixed->toFloat());
    }
}
