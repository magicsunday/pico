<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico\Test;

use MagicSunday\Pico\Pico;
use PHPUnit\Framework\TestCase;

/**
 * Class PicoTest
 */
class PicoTest extends TestCase
{
    /**
     * @test
     */
    public function open()
    {
        $pico = new Pico();
        $pico->open();
    }
}
