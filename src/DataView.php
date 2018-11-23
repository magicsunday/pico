<?php
/**
 * See LICENSE.md file for further details.
 */
declare(strict_types=1);

namespace MagicSunday\Pico;

use RuntimeException;
use SplFixedArray;

/**
 * A PHP implementation of the javascript DataView class.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/magicsunday/ancestral-fan-chart/
 */
class DataView
{
    /**
     * Internal array buffer.
     *
     * @var SplFixedArray
     */
    private $arrayBuffer;

    /**
     * DataView constructor.
     *
     * @param SplFixedArray $arrayBuffer The fixed size array buffer
     */
    public function __construct(SplFixedArray $arrayBuffer)
    {
        $this->arrayBuffer = $arrayBuffer;
    }

    /**
     * Sets an unsigned 8-bit integer (byte) value at the specified byte offset from the start of the DataView.
     *
     * @param int $byteOffset The offset, in byte, from the start of the view where to store the data.
     * @param int $value      The value to .
     *
     * @return self
     * @throws RuntimeException
     */
    public function setUint8(int $byteOffset, int $value): self
    {
        $this->arrayBuffer[$byteOffset] = $value;
        return $this;
    }

    /**
     * Gets a signed 32-bit integer (long) at the specified byte offset from the start of the DataView.
     *
     * @param int  $byteOffset The offset, in byte, from the start of the view where to read the data.
     * @param bool $bigEndian  Indicates whether the 32-bit int is stored in little- or big-endian format.
     *                         If TRUE a big-endian value is read.
     *
     * @return int A signed 32-bit integer number.
     */
    public function getInt32(int $byteOffset, bool $bigEndian = false): int
    {
        if ($bigEndian) {
            return ($this->arrayBuffer[$byteOffset + 3] & 0xff)
                | (($this->arrayBuffer[$byteOffset + 2] & 0xff) << 8)
                | (($this->arrayBuffer[$byteOffset + 1] & 0xff) << 16)
                | (($this->arrayBuffer[$byteOffset] & 0xff) << 24);
        }

        return (($this->arrayBuffer[$byteOffset + 3] & 0xff) << 24)
            | (($this->arrayBuffer[$byteOffset + 2] & 0xff) << 16)
            | (($this->arrayBuffer[$byteOffset + 1] & 0xff) << 8)
            | ($this->arrayBuffer[$byteOffset] & 0xff);
    }

    /**
     * Gets a signed 32-bit float (float) at the specified byte offset from the start of the DataView.
     *
     * @param int  $byteOffset The offset, in byte, from the start of the view where to read the data.
     * @param bool $bigEndian  Indicates whether the 32-bit float is stored in little- or big-endian format.
     *                         If TRUE a big-endian value is read.
     *
     * @return float A signed 32-bit float number.
     */
    public function getFloat32(int $byteOffset, bool $bigEndian = false): float
    {
        $int32 = $this->getInt32($byteOffset, $bigEndian);

        return unpack('f', pack('L', $int32))[1];
    }
}
