<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico;

/**
 * FixedFloat class.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/magicsunday/pico/
 */
class FixedFloat
{
    /**
     * Number of bits used for the integer part.
     */
    const INT_BITS = 24;

    /**
     * Number of bits used for the fractional part.
     */
    const FRACT_BITS = 8;

    /**
     * 1.0 as fixed point representation.
     */
    const FIXED_ONE  = 1 << self::FRACT_BITS;

    /**
     * Internal data represenation.
     *
     * @var int
     */
    private $value;

    /**
     * FixedFloat constructor.
     *
     * @param float $value The float value to convert to a fixed point number.
     */
    public function __construct(float $value)
    {
        $this->value = (int) ($value * self::FIXED_ONE);
    }

    /**
     * Adds the given fixed float value to the current one.
     *
     * @param FixedFloat $x
     *
     * @return void
     */
    public function add(FixedFloat $x)
    {
        $this->value += $x->value();
    }

    /**
     * Substracts the given fixed float value from the current one.
     *
     * @param FixedFloat $x
     *
     * @return void
     */
    public function substract(FixedFloat $x)
    {
        $this->value -= $x->value();
    }

    /**
     * Multiplies the given fixed float value with the current one.
     *
     * @param FixedFloat $x
     *
     * @return void
     */
    public function multiply(FixedFloat $x)
    {
        $this->value = ($this->value * $x->value()) >> self::FRACT_BITS;
    }

    /**
     * Divides the current fixed float value by the given one.
     *
     * @param FixedFloat $x
     *
     * @return void
     */
    public function divide(FixedFloat $x)
    {
        $this->value = ($this->value << self::FRACT_BITS) / $x->value();
    }

    /**
     * Returns the fixed number representation.
     *
     * @return int
     */
    public function value(): int
    {
        return $this->value;
    }

    /**
     * Converts the fixed number back to float.
     *
     * @return float
     */
    public function toFloat(): float
    {
        return ((float) $this->value) / self::FIXED_ONE;
    }
}
