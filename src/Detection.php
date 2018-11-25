<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico;

/**
 * Detection class.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/magicsunday/pico/
 */
class Detection
{
    /**
     * The x coordinate of the center of the detection.
     *
     * @var int
     */
    private $x;

    /**
     * The y coordinate of the center of the detection.
     *
     * @var int
     */
    private $y;

    /**
     * The scale of the detection.
     *
     * @var int
     */
    private $scale;

    /**
     * The score of the detection.
     *
     * @var float
     */
    private $score;

    /**
     * Detection constructor.
     *
     * @param float $x
     * @param float $y
     * @param float $scale
     * @param float $score
     */
    public function __construct(float $x, float $y, float $scale, float $score)
    {
        $this->x     = (int) $x;
        $this->y     = (int) $y;
        $this->scale = (int) $scale;
        $this->score = $score;
    }

    /**
     * @return int
     */
    public function getX(): int
    {
        return $this->x;
    }

    /**
     * @return int
     */
    public function getY(): int
    {
        return $this->y;
    }

    /**
     * @return int
     */
    public function getScale(): int
    {
        return $this->scale;
    }

    /**
     * @return float
     */
    public function getScore(): float
    {
        return $this->score;
    }
}
